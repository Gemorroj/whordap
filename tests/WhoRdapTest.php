<?php

namespace WhoRdap\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use WhoRdap\Handler\AsnHandler;
use WhoRdap\Handler\DomainHandler;
use WhoRdap\Handler\IpHandler;
use WhoRdap\WhoRdap;

final class WhoRdapTest extends BaseTestCase
{
    #[DataProvider('provideQueries')]
    public function testCreateWhoisQueryHandler(string $query, string $className): void
    {
        $whois = new WhoRdap();
        $reflectionObject = new \ReflectionObject($whois);
        $reflectionMethod = $reflectionObject->getMethod('createWhoisQueryHandler');
        $handler = $reflectionMethod->invoke($whois, $query);
        self::assertEquals($handler::class, $className);

        $reflectionObjectHandler = new \ReflectionObject($handler);
        $reflectionProperty = $reflectionObjectHandler->getProperty('serverList');
        self::assertStringStartsWith('WhoRdap\\Resource\\Whois', $reflectionProperty->getValue($handler)::class);
    }

    #[DataProvider('provideQueries')]
    public function testCreateRdapQueryHandler(string $query, string $className): void
    {
        $whois = new WhoRdap();
        $reflectionObject = new \ReflectionObject($whois);
        $reflectionMethod = $reflectionObject->getMethod('createRdapQueryHandler');
        $handler = $reflectionMethod->invoke($whois, $query);
        self::assertEquals($handler::class, $className);

        $reflectionObjectHandler = new \ReflectionObject($handler);
        $reflectionProperty = $reflectionObjectHandler->getProperty('serverList');
        self::assertStringStartsWith('WhoRdap\\Resource\\Rdap', $reflectionProperty->getValue($handler)::class);
    }

    public static function provideQueries(): \Generator
    {
        yield ['127.0.0.1', IpHandler::class];
        yield ['::/128 ', IpHandler::class];
        yield ['::ffff:0:0/96  ', IpHandler::class];
        yield ['2001:0db8:85a3:0000:0000:8a2e:0370:7334', IpHandler::class];
        yield ['192.168.0.1', IpHandler::class];
        yield ['192.168.0.0/24', IpHandler::class];
        yield ['1.1.1.1', IpHandler::class];
        yield ['AS220', AsnHandler::class];
        yield ['12345', AsnHandler::class];
        yield ['ya.ru', DomainHandler::class];
        yield ['президент.рф', DomainHandler::class];
        yield ['.рф', DomainHandler::class];
        yield ['ru', DomainHandler::class];
    }
}
