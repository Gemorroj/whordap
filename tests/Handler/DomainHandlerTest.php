<?php

namespace WhoRdap\Tests\Handler;

use PHPUnit\Framework\Attributes\DataProvider;
use WhoRdap\Exception\NetworkException;
use WhoRdap\Exception\RegistrarServerException;
use WhoRdap\Exception\TimeoutException;
use WhoRdap\Handler\DomainHandler;
use WhoRdap\NetworkClientInterface;
use WhoRdap\Resource\RdapTldServerList;
use WhoRdap\Resource\WhoisTldServerList;
use WhoRdap\Response\RdapDomainRegistrarResponse;
use WhoRdap\Response\WhoisDomainRegistrarResponse;
use WhoRdap\Tests\BaseTestCase;

final class DomainHandlerTest extends BaseTestCase
{
    #[DataProvider('provideDomainsWhois')]
    public function testWhoisFindTldServer(string $query, ?string $server): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createWhoisTldServerList());
        $reflectionObject = new \ReflectionObject($handler);
        $reflectionMethod = $reflectionObject->getMethod('findTldServer');
        $result = $reflectionMethod->invoke($handler, $query);

        self::assertEquals($server, $result);
    }

    public static function provideDomainsWhois(): \Generator
    {
        yield ['vk.com', 'whois.verisign-grs.com'];
        yield ['ya.ru', 'whois.tcinet.ru'];
        yield ['test.org.ru', 'whois.nic.ru'];
        yield ['xn--d1abbgf6aiiy.xn--p1ai', 'whois.tcinet.ru']; // Algo26InvalidCharacterException
        yield ['ru', self::createWhoisTldServerList()->serverDefault];
        yield ['domain.unknowntld', self::createWhoisTldServerList()->serverDefault];
    }

    #[DataProvider('provideDomainsRdap')]
    public function testRdapFindTldServer(string $query, ?string $server): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createRdapTldServerList());
        $reflectionObject = new \ReflectionObject($handler);
        $reflectionMethod = $reflectionObject->getMethod('findTldServer');
        $result = $reflectionMethod->invoke($handler, $query);

        self::assertEquals($server, $result);
    }

    public static function provideDomainsRdap(): \Generator
    {
        yield ['vk.com', 'https://rdap.verisign.com/com/v1/'];
        yield ['test.org.ru', 'https://www.nic.ru/rdap/'];
        yield ['xn--41a.xn--p1acf', 'https://api.rdap.nic.xn--p1acf/']; // Algo26InvalidCharacterException
        yield ['ru', self::createRdapTldServerList()->serverDefault];
        yield ['domain.unknowntld', self::createRdapTldServerList()->serverDefault];
    }

    #[DataProvider('provideRegistrarDataWhois')]
    public function testFindWhoisRegistrarServer(string $response, ?string $expectedServer): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createWhoisTldServerList());
        $reflectionObject = new \ReflectionObject($handler);
        $reflectionMethod = $reflectionObject->getMethod('findWhoisRegistrarServer');
        $result = $reflectionMethod->invoke($handler, $response);

        self::assertEquals($expectedServer, $result);
    }

    public static function provideRegistrarDataWhois(): \Generator
    {
        yield ['   Registrar WHOIS Server: whois.nic.ru', 'whois.nic.ru'];
        yield ['   Registrar WHOIS Server: rwhois://whois.nic.ru', 'whois.nic.ru'];
        yield ['   test: string', null];
        yield ['   Registrar WHOIS Server: file://passwd.com', null];
        yield ['   Registrar WHOIS Server: ', null];
        yield ['whois:        whois.tcinet.ru ', null]; // the pattern from whois.iana.org
        yield ['whois:         ', null];
        yield [' whois:', null];
    }

    #[DataProvider('provideRegistrarDataRdap')]
    public function testFindRdapRegistrarServer(string $response, ?string $expectedServer): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createRdapTldServerList());
        $reflectionObject = new \ReflectionObject($handler);
        $reflectionMethod = $reflectionObject->getMethod('findRdapRegistrarServer');
        $result = $reflectionMethod->invoke($handler, $response);

        self::assertEquals($expectedServer, $result);
    }

    public static function provideRegistrarDataRdap(): \Generator
    {
        yield [\json_encode(['links' => [['href' => 'http://example.com', 'rel' => 'related', 'type' => 'application/rdap+json']]], \JSON_THROW_ON_ERROR), 'http://example.com'];
        yield [\json_encode(['links' => [['href' => 'https://example.com/domain/TEST.RU', 'rel' => 'related', 'type' => 'application/rdap+json']]], \JSON_THROW_ON_ERROR), 'https://example.com/'];
        yield [\json_encode(['links' => [['href' => 'https://example.com/v1/domain/TEST.RU', 'rel' => 'related', 'type' => 'application/rdap+json']]], \JSON_THROW_ON_ERROR), 'https://example.com/v1/'];
        yield [\json_encode(['links' => [['href' => 'http://example.com', 'rel' => 'self', 'type' => 'application/rdap+json']]], \JSON_THROW_ON_ERROR), null];
        yield [\json_encode(['links' => [['href' => 'http://example.com', 'rel' => 'related']]], \JSON_THROW_ON_ERROR), null]; // type is optional in RFC 9083
        yield [\json_encode(['links' => [['href' => 'http://example.com']]], \JSON_THROW_ON_ERROR), null]; // rel and type are optional
        yield [\json_encode(['links' => ['not-an-object']], \JSON_THROW_ON_ERROR), null];
        yield [\json_encode(['links' => 'not-an-array'], \JSON_THROW_ON_ERROR), null];
        yield ['"just a string"', null];
        yield ['   test: string', null];
    }

    #[DataProvider('provideWhoisServers')]
    public function testPrepareWhoisServer(string $server, ?string $preparedServer): void
    {
        try {
            $handler = new DomainHandler($this->createLoggedClient(), self::createWhoisTldServerList());
            $reflectionObject = new \ReflectionObject($handler);
            $reflectionMethod = $reflectionObject->getMethod('prepareWhoisServer');
            $result = $reflectionMethod->invoke($handler, $server);
        } catch (\Exception) {
            $result = null;
        }
        self::assertEquals($preparedServer, $result);
    }

    public static function provideWhoisServers(): \Generator
    {
        yield ['localhost', 'localhost'];
        yield ['whois.nic.ru', 'whois.nic.ru'];
        yield ['rwhois://whois.nic.ru', 'whois.nic.ru'];
        yield ['whois://whois.nic.ru', 'whois.nic.ru'];
        yield ['whois.nic.ru:44', 'whois.nic.ru:44'];
        yield ['http://test.com/?123&456', null];
        yield ['https://test.com/?123&456', null];
        yield ['file://passwords', null];
        yield ['/passwords', null];
        yield ['\\passwords', null];
    }

    #[DataProvider('provideRdapServers')]
    public function testPrepareRdapServer(string $server, ?string $preparedServer): void
    {
        try {
            $handler = new DomainHandler($this->createLoggedClient(), self::createRdapTldServerList());
            $reflectionObject = new \ReflectionObject($handler);
            $reflectionMethod = $reflectionObject->getMethod('prepareRdapServer');
            $result = $reflectionMethod->invoke($handler, $server);
        } catch (\Exception) {
            $result = null;
        }
        self::assertEquals($preparedServer, $result);
    }

    public static function provideRdapServers(): \Generator
    {
        yield ['localhost', null];
        yield ['whois.nic.ru', null];
        yield ['rwhois://whois.nic.ru', null];
        yield ['whois://whois.nic.ru', null];
        yield ['whois.nic.ru:44', null];
        yield ['http://test.com/?123&456', 'http://test.com/?123&456'];
        yield ['https://test.com/?123&456', 'https://test.com/?123&456'];
        yield ['file://passwords', null];
        yield ['/passwords', null];
        yield ['\\passwords', null];
    }

    /*public function testRegistrarServerExceptionRdap(): void
    {
        $serverList = new RdapTldServerList();
        $serverList->serverDefault = 'https://rdap.iana.org';
        $serverList->servers = [
            '.com' => 'https://rdap.verisign.com/com/v1/',
        ];

        $handler = new DomainHandler($this->createLoggedClient(), $serverList);
        $data = $handler->processRdap('vk.com'); // https://www.nic.ru/rdap/ is seems broken for robots
        // \file_put_contents('/test.txt', $data->response);
        // var_dump($data->response);

        self::assertInstanceOf(RegistrarServerException::class, $data->registrarResponse);
        self::assertEquals('Can\'t load info from registrar server.', $data->registrarResponse->getMessage());
        self::assertJson($data->response);
        $json = \json_decode($data->response, true, 512, \JSON_THROW_ON_ERROR);
        self::assertEquals('https://www.nic.ru/rdap/domain/VK.COM', $json['links'][1]['href']);
        self::assertEquals('https://rdap.verisign.com/com/v1/', $data->server);
    }*/

    public function testLocalhostRdap(): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createRdapTldServerList());
        $this->expectException(NetworkException::class);
        $handler->processRdap('localhost');
    }

    public function testLocalhostWhois(): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createWhoisTldServerList());
        $data = $handler->processWhois('localhost');

        self::assertSame(self::createWhoisTldServerList()->serverDefault, $data->server);
        self::assertNotSame('', $data->response);
        self::assertNull($data->registrarResponse);
    }

    public function testForceWhoisServer(): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createWhoisTldServerList());
        $data = $handler->processWhois('vk.com', 'whois.verisign-grs.com');

        self::assertSame('whois.verisign-grs.com', $data->server);
        self::assertNotSame('', $data->response);
        self::assertNull($data->registrarResponse);
    }

    public function testForceRdapServer(): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createRdapTldServerList());
        $data = $handler->processRdap('google.com', 'https://rdap.verisign.com/com/v1/');

        self::assertSame('https://rdap.verisign.com/com/v1/', $data->server);
        self::assertJson($data->response);
        self::assertNull($data->registrarResponse);
    }

    #[DataProvider('provideWhoisDomainResponse')]
    public function testProcessWhois(string $query, string $expectedServer): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createWhoisTldServerList());
        $data = $handler->processWhois($query);

        self::assertSame($expectedServer, $data->server);
        self::assertNotSame('', $data->response);
        if ($data->registrarResponse instanceof WhoisDomainRegistrarResponse) {
            self::assertNotSame('', $data->registrarResponse->server);
        }
    }

    public static function provideWhoisDomainResponse(): \Generator
    {
        yield ['ru', self::createWhoisTldServerList()->serverDefault];
        yield ['vk.com', 'whois.verisign-grs.com'];
        yield ['registro.br', 'whois.registro.br'];
        yield ['президент.рф', 'whois.tcinet.ru'];
    }

    #[DataProvider('provideRdapDomainResponse')]
    public function testProcessRdap(string $query, string $expectedServer): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createRdapTldServerList());
        $data = $handler->processRdap($query);

        self::assertSame($expectedServer, $data->server);
        self::assertJson($data->response);
        if ($data->registrarResponse instanceof RdapDomainRegistrarResponse) {
            self::assertNotSame('', $data->registrarResponse->server);
        }
    }

    public static function provideRdapDomainResponse(): \Generator
    {
        yield ['ru', self::createRdapTldServerList()->serverDefault];
        yield ['google.com', 'https://rdap.verisign.com/com/v1/'];
        yield ['registro.br', 'https://rdap.registro.br/'];
        yield ['я.рус', 'https://api.rdap.nic.xn--p1acf/'];
    }

    public function testProcessWhoisWithRegistrar(): void
    {
        $client = $this->createStub(NetworkClientInterface::class);
        $client->method('getWhoisResponse')->willReturnMap([
            ['whois.verisign-grs.com', 'vk.com', self::fixture('whois/domain/vk.com.txt')],
            ['whois.nic.ru', 'vk.com', self::fixture('whois/registrar/vk.com.txt')],
        ]);

        $data = new DomainHandler($client, self::createWhoisTldServerList())->processWhois('vk.com');

        self::assertSame('whois.verisign-grs.com', $data->server);
        self::assertStringContainsString('Domain Name: VK.COM', $data->response);
        self::assertStringContainsString('Registrar WHOIS Server: whois.nic.ru', $data->response);
        self::assertInstanceOf(WhoisDomainRegistrarResponse::class, $data->registrarResponse);
        self::assertSame('whois.nic.ru', $data->registrarResponse->server);
        self::assertStringContainsString('Registrant Country: RU', $data->registrarResponse->response);
    }

    public function testProcessWhoisWithoutRegistrar(): void
    {
        $client = $this->createStub(NetworkClientInterface::class);
        $client->method('getWhoisResponse')->willReturnMap([
            ['whois.iana.org', 'ru', self::fixture('whois/domain/ru.txt')],
        ]);

        $data = new DomainHandler($client, self::createWhoisTldServerList())->processWhois('ru');

        self::assertSame('whois.iana.org', $data->server);
        self::assertStringContainsString('organisation: Coordination Center for TLD RU', $data->response);
        self::assertNull($data->registrarResponse);
    }

    public function testProcessRdapWithRegistrar(): void
    {
        $client = $this->createStub(NetworkClientInterface::class);
        $client->method('getRdapResponse')->willReturnMap([
            ['https://rdap.verisign.com/com/v1/', '/domain/google.com', self::fixture('rdap/domain/google.com.json')],
            ['https://rdap.markmonitor.com/rdap/', '/domain/google.com', self::fixture('rdap/registrar/google.com.json')],
        ]);

        $data = new DomainHandler($client, self::createRdapTldServerList())->processRdap('google.com');

        self::assertSame('https://rdap.verisign.com/com/v1/', $data->server);
        self::assertJson($data->response);
        self::assertInstanceOf(RdapDomainRegistrarResponse::class, $data->registrarResponse);
        self::assertSame('https://rdap.markmonitor.com/rdap/', $data->registrarResponse->server);
        self::assertStringContainsString('REDACTED REGISTRANT', $data->registrarResponse->response);
    }

    private static function createRdapTldServerList(): RdapTldServerList
    {
        $serverList = new RdapTldServerList();
        $serverList->serverDefault = 'https://rdap.iana.org';
        $serverList->servers = [
            '.com' => 'https://rdap.verisign.com/com/v1/',
            '.org.ru' => 'https://www.nic.ru/rdap/',
            '.br' => 'https://rdap.registro.br/',
            '.xn--p1acf' => 'https://api.rdap.nic.xn--p1acf/', // .рус
        ];

        return $serverList;
    }

    private static function createWhoisTldServerList(): WhoisTldServerList
    {
        $serverList = new WhoisTldServerList();
        $serverList->serverDefault = 'whois.iana.org';
        $serverList->servers = [
            '.com' => 'whois.verisign-grs.com',
            '.ru' => 'whois.tcinet.ru',
            '.org.ru' => 'whois.nic.ru',
            '.br' => 'whois.registro.br', // non UTF-8
            '.xn--p1ai' => 'whois.tcinet.ru', // .рф
        ];

        return $serverList;
    }

    public function testProcessWhoisTimeout(): void
    {
        $client = $this->createStub(NetworkClientInterface::class);
        $client->method('getWhoisResponse')->willThrowException(new TimeoutException('Timeout reading from WHOIS server'));

        $handler = new DomainHandler($client, self::createWhoisTldServerList());
        $this->expectException(TimeoutException::class);
        $handler->processWhois('example.com');
    }
}
