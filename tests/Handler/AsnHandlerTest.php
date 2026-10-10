<?php

namespace WhoRdap\Tests\Handler;

use PHPUnit\Framework\Attributes\DataProvider;
use WhoRdap\Handler\AsnHandler;
use WhoRdap\NetworkClientInterface;
use WhoRdap\Resource\RdapAsnServerList;
use WhoRdap\Resource\WhoisAsnServerList;
use WhoRdap\Tests\BaseTestCase;

final class AsnHandlerTest extends BaseTestCase
{
    #[DataProvider('provideRdapAsns')]
    public function testFindAsnServerRdap(string $query, string $server): void
    {
        $handler = new AsnHandler($this->createLoggedClient(), self::createRdapAsnServerList());
        $reflectionObject = new \ReflectionObject($handler);
        $reflectionMethod = $reflectionObject->getMethod('findAsnServer');
        $result = $reflectionMethod->invoke($handler, $query);

        self::assertEquals($server, $result);
    }

    public static function provideRdapAsns(): \Generator
    {
        yield ['1', 'https://rdap.arin.net/registry/'];
        yield ['AS1', 'https://rdap.arin.net/registry/'];
        yield ['2043', 'https://rdap.db.ripe.net/'];
        yield ['123123123123', self::createRdapAsnServerList()->serverDefault];
    }

    #[DataProvider('provideWhoisAsns')]
    public function testFindAsnServerWhois(string $query, string $server): void
    {
        $handler = new AsnHandler($this->createLoggedClient(), self::createWhoisAsnServerList());
        $reflectionObject = new \ReflectionObject($handler);
        $reflectionMethod = $reflectionObject->getMethod('findAsnServer');
        $result = $reflectionMethod->invoke($handler, $query);

        self::assertEquals($server, $result);
    }

    public static function provideWhoisAsns(): \Generator
    {
        yield ['1', 'whois.arin.net'];
        yield ['AS1', 'whois.arin.net'];
        yield ['2043', 'whois.ripe.net'];
        yield ['123123123123', self::createWhoisAsnServerList()->serverDefault];
    }

    #[DataProvider('provideWhoisAsnResponse')]
    public function testProcessWhois(string $query, string $expectedServer): void
    {
        $handler = new AsnHandler($this->createLoggedClient(), self::createWhoisAsnServerList());
        $data = $handler->processWhois($query);

        self::assertSame($expectedServer, $data->server);
        self::assertNotSame('', $data->response);
    }

    public static function provideWhoisAsnResponse(): \Generator
    {
        yield ['AS4837', 'whois.apnic.net'];
        yield ['4837', 'whois.apnic.net'];
        yield ['AS220', 'whois.arin.net'];
        yield ['220', 'whois.arin.net'];
        yield ['AS2043', 'whois.ripe.net'];
        yield ['2043', 'whois.ripe.net'];
        yield ['AS28001', 'whois.lacnic.net'];
        yield ['28001', 'whois.lacnic.net'];
        yield ['AS36864', 'whois.afrinic.net'];
        yield ['36864', 'whois.afrinic.net'];
    }

    #[DataProvider('provideRdapAsnResponse')]
    public function testProcessRdap(string $query, string $expectedServer): void
    {
        $handler = new AsnHandler($this->createLoggedClient(), self::createRdapAsnServerList());
        $data = $handler->processRdap($query);

        self::assertSame($expectedServer, $data->server);
        self::assertJson($data->response);
    }

    public static function provideRdapAsnResponse(): \Generator
    {
        yield ['AS4837', 'https://rdap.apnic.net/'];
        yield ['4837', 'https://rdap.apnic.net/'];
        yield ['AS220', 'https://rdap.arin.net/registry/'];
        yield ['220', 'https://rdap.arin.net/registry/'];
        yield ['AS2043', 'https://rdap.db.ripe.net/'];
        yield ['2043', 'https://rdap.db.ripe.net/'];
        yield ['AS28001', 'https://rdap.lacnic.net/rdap/'];
        yield ['28001', 'https://rdap.lacnic.net/rdap/'];
        yield ['AS36864', 'https://rdap.afrinic.net/rdap/'];
        yield ['36864', 'https://rdap.afrinic.net/rdap/'];
    }

    public function testProcessWhoisWithFixture(): void
    {
        $client = $this->createStub(NetworkClientInterface::class);
        $client->method('getWhoisResponse')->willReturnMap([
            ['whois.arin.net', 'z 220', self::fixture('whois/asn/220.txt')],
        ]);

        $data = new AsnHandler($client, self::createWhoisAsnServerList())->processWhois('AS220');

        self::assertSame('whois.arin.net', $data->server);
        self::assertStringContainsString('ASHandle:', $data->response);
        self::assertStringContainsString('AS220', $data->response);
        self::assertStringContainsString('OOALC-HOSTNET-AS', $data->response);
    }

    public function testProcessRdapWithFixture(): void
    {
        $client = $this->createStub(NetworkClientInterface::class);
        $client->method('getRdapResponse')->willReturnMap([
            ['https://rdap.arin.net/registry/', '/autnum/220', self::fixture('rdap/asn/220.json')],
        ]);

        $data = new AsnHandler($client, self::createRdapAsnServerList())->processRdap('AS220');

        self::assertSame('https://rdap.arin.net/registry/', $data->server);
        self::assertJson($data->response);
        self::assertStringContainsString('"AS220"', $data->response);
    }

    private static function createRdapAsnServerList(): RdapAsnServerList
    {
        $serverList = new RdapAsnServerList();
        $serverList->serverDefault = 'https://rdap.arin.net/registry';
        $serverList->servers = [
            '1-1876' => 'https://rdap.arin.net/registry/',
            '2043' => 'https://rdap.db.ripe.net/',
            '4608-4865' => 'https://rdap.apnic.net/',
            '27648-28671' => 'https://rdap.lacnic.net/rdap/',
            '36864-37887' => 'https://rdap.afrinic.net/rdap/',
        ];

        return $serverList;
    }

    private static function createWhoisAsnServerList(): WhoisAsnServerList
    {
        $serverList = new WhoisAsnServerList();
        $serverList->serverDefault = 'whois.arin.net';
        $serverList->servers = [
            '1-1876' => 'whois.arin.net',
            '2043' => 'whois.ripe.net',
            '4608-4865' => 'whois.apnic.net',
            '27648-28671' => 'whois.lacnic.net',
            '36864-37887' => 'whois.afrinic.net',
        ];

        return $serverList;
    }
}
