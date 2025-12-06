<?php

namespace WhoRdap\Tests\Handler;

use PHPUnit\Framework\Attributes\DataProvider;
use WhoRdap\Handler\IpHandler;
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
    public function testProcessWhois(string $query, string $expectedString, string $expectedServer): void
    {
        $handler = new IpHandler($this->createLoggedClient(), self::createWhoisIpServerList());
        $data = $handler->processWhois($query);
        // \file_put_contents('/test.txt', $data->response);
        // var_dump($data->response);
        self::assertStringContainsString($expectedString, $data->response);
        self::assertEquals($expectedServer, $data->server);
    }

    public static function provideWhoisIpResponse(): \Generator
    {
        yield ['127.0.0.1', 'NetName:        SPECIAL-IPV4-LOOPBACK-IANA-RESERVED', self::createWhoisIpServerList()->serverDefault];
        yield ['192.168.0.1', 'NetName:        PRIVATE-ADDRESS-CBLK-RFC1918-IANA-RESERVED', self::createWhoisIpServerList()->serverDefault];
        yield ['192.0.2.0/24', 'NetHandle:      NET-192-0-2-0-1', self::createWhoisIpServerList()->serverDefault];
        yield ['1.1.1.1', 'inetnum:        1.1.1.0 - 1.1.1.255', 'whois.apnic.net'];
        yield ['2001:4860:4860::8888', 'NetHandle:      NET6-2001-4860-1', 'whois.arin.net'];
        yield ['193.0.11.51', 'inetnum:        193.0.10.0 - 193.0.11.255', 'whois.ripe.net'];
        yield ['200.3.13.10', 'inetnum:     200.3.12.0/22', 'whois.lacnic.net'];
        yield ['196.216.2.1', 'inetnum:        196.216.2.0 - 196.216.3.255', 'whois.afrinic.net'];
        yield ['199.212.0.46', 'NetHandle:      NET-199-212-0-0-1', 'whois.arin.net'];
    }

    #[DataProvider('provideRdapIpResponse')]
    public function testProcessRdap(string $query, string $expectedString, string $expectedServer): void
    {
        $handler = new IpHandler($this->createLoggedClient(), self::createRdapIpServerList());
        $data = $handler->processRdap($query);
        // \file_put_contents('/test.txt', $data->response);
        // var_dump($data->response);
        self::assertStringContainsString($expectedString, $data->response);
        self::assertEquals($expectedServer, $data->server);
    }

    public static function provideRdapIpResponse(): \Generator
    {
        yield ['127.0.0.1', '"handle" : "NET-127-0-0-0-1",', self::createRdapIpServerList()->serverDefault];
        yield ['192.168.0.1', '"handle" : "NET-192-168-0-0-1"', self::createRdapIpServerList()->serverDefault];
        yield ['192.0.2.0/24', '"handle" : "NET-192-0-2-0-1"', self::createRdapIpServerList()->serverDefault];
        yield ['1.1.1.1', '"handle":"1.1.1.0 - 1.1.1.255"', 'https://rdap.apnic.net/'];
        yield ['2001:4860:4860::8888', '"handle" : "NET6-2001-4860-1"', 'https://rdap.arin.net/registry/'];
        yield ['193.0.11.51', '"handle" : "193.0.10.0 - 193.0.11.255"', 'https://rdap.db.ripe.net/'];
        yield ['200.3.13.10', '"handle":"200.3.12.0/22"', 'https://rdap.lacnic.net/rdap/'];
        yield ['196.216.2.1', '"handle":"196.216.2.0 - 196.216.3.255"', 'https://rdap.afrinic.net/rdap/'];
        yield ['199.212.0.160', '"handle" : "NET-199-212-0-0-1"', 'https://rdap.arin.net/registry'];
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
