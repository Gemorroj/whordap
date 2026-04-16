<?php

namespace WhoRdap\Tests\Handler;

use PHPUnit\Framework\Attributes\DataProvider;
use WhoRdap\Exception\NetworkException;
use WhoRdap\Exception\RegistrarServerException;
use WhoRdap\Handler\DomainHandler;
use WhoRdap\Resource\RdapTldServerList;
use WhoRdap\Resource\WhoisTldServerList;
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
        $data = $handler->processRdap('localhost');
        // \file_put_contents('/test.txt', $data->response);
        // var_dump($data->response);
        self::assertNull($data->registrarResponse);
        self::assertStringContainsString('"Domain not found :","localhost"', $data->response);
        self::assertEquals(self::createRdapTldServerList()->serverDefault, $data->server);
    }

    public function testLocalhostWhois(): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createWhoisTldServerList());
        $data = $handler->processWhois('localhost');
        // \file_put_contents('/test.txt', $data->response);
        // var_dump($data->response);
        self::assertNull($data->registrarResponse);
        self::assertStringContainsString('You queried for localhost but this server does not have', $data->response);
        self::assertEquals(self::createWhoisTldServerList()->serverDefault, $data->server);
    }

    public function testForceWhoisServer(): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createWhoisTldServerList());
        $data = $handler->processWhois('sirus.su', 'whois.tcinet.ru');
        // \file_put_contents('/test.txt', $data->response);
        // var_dump($data->response);

        self::assertNull($data->registrarResponse);
        self::assertStringContainsString('e-mail:        sir.nyll@gmail.com', $data->response);
        self::assertEquals('whois.tcinet.ru', $data->server);
    }

    public function testForceRdapServer(): void
    {
        $handler = new DomainHandler($this->createLoggedClient(), self::createRdapTldServerList());
        $data = $handler->processRdap('wikipedia.org', 'https://rdap.publicinterestregistry.org/rdap/');
        // \file_put_contents('/test.txt', $data->response);
        // var_dump($data->response);

        self::assertNull($data->registrarResponse);
        self::assertStringContainsString('"ldhName": "wikipedia.org"', $data->response);
        self::assertEquals('https://rdap.publicinterestregistry.org/rdap/', $data->server);
    }

    #[DataProvider('provideWhoisDomainResponse')]
    public function testProcessWhois(
        string $query,
        string $expectedServer,
        string $expectedResponse,
        ?string $expectedRegistrarServer,
        ?string $expectedRegistrarResponse,
    ): void {
        $handler = new DomainHandler($this->createLoggedClient(), self::createWhoisTldServerList());
        $data = $handler->processWhois($query);
        // \file_put_contents('/test.txt', $data->response);
        // var_dump($data->response);

        self::assertEquals($expectedServer, $data->server);
        self::assertStringContainsString($expectedResponse, $data->response);
        self::assertEquals($expectedRegistrarServer, $data->registrarResponse?->server);
        if (null === $expectedRegistrarResponse) {
            self::assertNull($data->registrarResponse?->response);
        } else {
            self::assertStringContainsString($expectedRegistrarResponse, $data->registrarResponse?->response);
        }
    }

    public static function provideWhoisDomainResponse(): \Generator
    {
        yield ['ru', self::createWhoisTldServerList()->serverDefault, 'organisation: Coordination Center for TLD RU', null, null];
        yield ['vk.com', 'whois.verisign-grs.com', 'Registrar URL: http://nic.ru', 'whois.nic.ru', 'Registrant Country: RU'];
        yield ['registro.br', 'whois.registro.br', 'Núcleo de Inf. e Coord. do Ponto BR - NIC.BR', null, null]; // non UTF-8
        yield ['президент.рф', 'whois.tcinet.ru', 'org:           Special Communications and Information Service of the Federal Guard Service of the Russian Federation (Spetssvyaz FSO RF)', null, null]; // punycode
    }

    #[DataProvider('provideRdapDomainResponse')]
    public function testProcessRdap(
        string $query,
        string $expectedServer,
        string $expectedResponse,
        ?string $expectedRegistrarServer,
        ?string $expectedRegistrarResponse,
    ): void {
        $handler = new DomainHandler($this->createLoggedClient(), self::createRdapTldServerList());
        $data = $handler->processRdap($query);
        // \file_put_contents('/test.txt', $data->response);
        // var_dump($data->response);

        self::assertEquals($expectedServer, $data->server);
        self::assertStringContainsString($expectedResponse, $data->response);
        self::assertEquals($expectedRegistrarServer, $data->registrarResponse?->server);
        if (null === $expectedRegistrarResponse) {
            self::assertNull($data->registrarResponse?->response);
        } else {
            self::assertStringContainsString($expectedRegistrarResponse, $data->registrarResponse?->response);
        }
    }

    public static function provideRdapDomainResponse(): \Generator
    {
        yield ['ru', self::createRdapTldServerList()->serverDefault, '"Coordination Center for TLD RU"', null, null];
        yield ['google.com', 'https://rdap.verisign.com/com/v1/', '"handle":"2138514_DOMAIN_COM-VRSN","ldhName":"GOOGLE.COM"', 'https://rdap.markmonitor.com/rdap/', '"handle":"2138514_DOMAIN_COM-VRSN"']; // 403 (russophobic server)
        yield ['registro.br', 'https://rdap.registro.br/', 'Núcleo de Inf. e Coord. do Ponto BR - NIC.BR', null, null];
        yield ['я.рус', 'https://api.rdap.nic.xn--p1acf/', '"handle":"8144-CoCCA","ldhName":"xn--41a.xn--p1acf"', null, null]; // punycode
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
        $handler = new DomainHandler($this->createLoggedClient(timeout: 1), self::createWhoisTldServerList());
        $this->expectException(NetworkException::class);
        $data = $handler->processWhois('haiku-inc.org', 'whois.namecheap.com');
        // \file_put_contents('/test.txt', $data->response);
        // var_dump($data->response);
    }
}
