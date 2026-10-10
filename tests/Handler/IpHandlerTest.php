<?php

namespace WhoRdap\Tests\Handler;

use PHPUnit\Framework\Attributes\DataProvider;
use WhoRdap\Handler\IpHandler;
use WhoRdap\NetworkClientInterface;
use WhoRdap\Resource\RdapIpServerList;
use WhoRdap\Resource\WhoisIpServerList;
use WhoRdap\Tests\BaseTestCase;

final class IpHandlerTest extends BaseTestCase
{
    #[DataProvider('provideRdapIps')]
    public function testFindIpServerRdap(string $query, string $server): void
    {
        $handler = new IpHandler($this->createLoggedClient(), self::createRdapIpServerList());
        $reflectionObject = new \ReflectionObject($handler);
        $reflectionMethod = $reflectionObject->getMethod('findIpServer');
        $result = $reflectionMethod->invoke($handler, $query);

        self::assertEquals($server, $result);
    }

    public static function provideRdapIps(): \Generator
    {
        yield ['1.1.1.1/8', 'https://rdap.apnic.net/'];
        yield ['1.1.1.1', 'https://rdap.apnic.net/'];
        yield ['2a00:1450:4011:808::1001', 'https://rdap.db.ripe.net/'];
        yield ['2.2.2.2', self::createRdapIpServerList()->serverDefault];
    }

    #[DataProvider('provideWhoisIps')]
    public function testFindIpServerWhois(string $query, string $server): void
    {
        $handler = new IpHandler($this->createLoggedClient(), self::createWhoisIpServerList());
        $reflectionObject = new \ReflectionObject($handler);
        $reflectionMethod = $reflectionObject->getMethod('findIpServer');
        $result = $reflectionMethod->invoke($handler, $query);

        self::assertEquals($server, $result);
    }

    public static function provideWhoisIps(): \Generator
    {
        yield ['1.1.1.1/8', 'whois.apnic.net'];
        yield ['1.1.1.1', 'whois.apnic.net'];
        yield ['2a00:1450:4011:808::1001', 'whois.ripe.net'];
        yield ['2.2.2.2', self::createWhoisIpServerList()->serverDefault];
    }

    #[DataProvider('provideWhoisIpResponse')]
    public function testProcessWhois(string $query, string $expectedServer): void
    {
        $handler = new IpHandler($this->createLoggedClient(), self::createWhoisIpServerList());
        $data = $handler->processWhois($query);

        self::assertSame($expectedServer, $data->server);
        self::assertNotSame('', $data->response);
    }

    public static function provideWhoisIpResponse(): \Generator
    {
        yield ['127.0.0.1', self::createWhoisIpServerList()->serverDefault];
        yield ['192.168.0.1', self::createWhoisIpServerList()->serverDefault];
        yield ['192.0.2.0/24', self::createWhoisIpServerList()->serverDefault];
        yield ['1.1.1.1', 'whois.apnic.net'];
        yield ['2001:4860:4860::8888', 'whois.arin.net'];
        yield ['193.0.11.51', 'whois.ripe.net'];
        yield ['200.3.13.10', 'whois.lacnic.net'];
        yield ['196.216.2.1', 'whois.afrinic.net'];
        yield ['199.212.0.46', 'whois.arin.net'];
    }

    #[DataProvider('provideRdapIpResponse')]
    public function testProcessRdap(string $query, string $expectedServer): void
    {
        $handler = new IpHandler($this->createLoggedClient(), self::createRdapIpServerList());
        $data = $handler->processRdap($query);

        self::assertSame($expectedServer, $data->server);
        self::assertJson($data->response);
    }

    public static function provideRdapIpResponse(): \Generator
    {
        yield ['127.0.0.1', self::createRdapIpServerList()->serverDefault];
        yield ['192.168.0.1', self::createRdapIpServerList()->serverDefault];
        yield ['192.0.2.0/24', self::createRdapIpServerList()->serverDefault];
        yield ['1.1.1.1', 'https://rdap.apnic.net/'];
        yield ['2001:4860:4860::8888', 'https://rdap.arin.net/registry/'];
        yield ['193.0.11.51', 'https://rdap.db.ripe.net/'];
        yield ['200.3.13.10', 'https://rdap.lacnic.net/rdap/'];
        yield ['196.216.2.1', 'https://rdap.afrinic.net/rdap/'];
        yield ['199.212.0.160', 'https://rdap.arin.net/registry'];
    }

    public function testProcessWhoisWithFixture(): void
    {
        $client = $this->createStub(NetworkClientInterface::class);
        $client->method('getWhoisResponse')->willReturnMap([
            ['whois.apnic.net', '1.1.1.1', self::fixture('whois/ip/1.1.1.1.txt')],
        ]);

        $data = new IpHandler($client, self::createWhoisIpServerList())->processWhois('1.1.1.1');

        self::assertSame('whois.apnic.net', $data->server);
        self::assertStringContainsString('1.1.1.0 - 1.1.1.255', $data->response);
        self::assertStringContainsString('APNIC-LABS', $data->response);
    }

    public function testProcessRdapWithFixture(): void
    {
        $client = $this->createStub(NetworkClientInterface::class);
        $client->method('getRdapResponse')->willReturnMap([
            ['https://rdap.apnic.net/', '/ip/1.1.1.1', self::fixture('rdap/ip/1.1.1.1.json')],
        ]);

        $data = new IpHandler($client, self::createRdapIpServerList())->processRdap('1.1.1.1');

        self::assertSame('https://rdap.apnic.net/', $data->server);
        self::assertJson($data->response);
        self::assertStringContainsString('"handle":"1.1.1.0 - 1.1.1.255"', $data->response);
    }

    private static function createRdapIpServerList(): RdapIpServerList
    {
        $serverList = new RdapIpServerList();
        $serverList->serverDefault = 'https://rdap.arin.net/registry';
        $serverList->serversIpv4 = [
            '1.0.0.0/8' => 'https://rdap.apnic.net/',
            '193.0.0.0/8' => 'https://rdap.db.ripe.net/',
            '200.0.0.0/8' => 'https://rdap.lacnic.net/rdap/',
            '196.0.0.0/8' => 'https://rdap.afrinic.net/rdap/',
            '192.72.254.0' => 'https://rdap.arin.net/registry/',
        ];
        $serverList->serversIpv6 = [
            '2a00::/12' => 'https://rdap.db.ripe.net/',
            '2001:4800::/23' => 'https://rdap.arin.net/registry/',
        ];

        return $serverList;
    }

    private static function createWhoisIpServerList(): WhoisIpServerList
    {
        $serverList = new WhoisIpServerList();
        $serverList->serverDefault = 'whois.arin.net';
        $serverList->serversIpv4 = [
            '1.0.0.0/8' => 'whois.apnic.net',
            '193.0.0.0/8' => 'whois.ripe.net',
            '200.0.0.0/7' => 'whois.lacnic.net',
            '196.0.0.0/7' => 'whois.afrinic.net',
            '192.72.254.0/24' => 'whois.arin.net',
        ];
        $serverList->serversIpv6 = [
            '2A00:0000::/12' => 'whois.ripe.net',
            '2001:4800::/23' => 'whois.arin.net',
        ];

        return $serverList;
    }
}
