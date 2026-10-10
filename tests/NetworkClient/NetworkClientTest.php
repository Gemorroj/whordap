<?php

declare(strict_types=1);

namespace WhoRdap\Tests\NetworkClient;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use WhoRdap\Exception\QueryRateLimitExceededException;
use WhoRdap\NetworkClient\NetworkClient;
use WhoRdap\TransportInterface;

final class NetworkClientTest extends TestCase
{
    public function testWhoisResponseIsDelegatedAndCached(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('requestWhois')
            ->with('whois.example', 'example.com')
            ->willReturn('Domain Name: EXAMPLE.COM');

        $client = new NetworkClient(cache: new ArrayAdapter(), transport: $transport);

        self::assertStringContainsString('Domain Name: EXAMPLE.COM', $client->getWhoisResponse('whois.example', 'example.com'));
        self::assertStringContainsString('Domain Name: EXAMPLE.COM', $client->getWhoisResponse('whois.example', 'example.com'));
    }

    public function testRdapResponseIsDelegatedAndCached(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('requestRdap')
            ->with('https://rdap.example/', '/domain/example.com')
            ->willReturn('{"objectClassName":"domain"}');

        $client = new NetworkClient(cache: new ArrayAdapter(), transport: $transport);

        self::assertStringContainsString('"objectClassName":"domain"', $client->getRdapResponse('https://rdap.example/', '/domain/example.com'));
        self::assertStringContainsString('"objectClassName":"domain"', $client->getRdapResponse('https://rdap.example/', '/domain/example.com'));
    }

    public function testWhoisRateLimitIsThrownAndNotCached(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->exactly(2))
            ->method('requestWhois')
            ->willReturn('Query rate limit exceeded');

        $client = new NetworkClient(cache: new ArrayAdapter(), transport: $transport);

        for ($i = 0; $i < 2; ++$i) {
            try {
                $client->getWhoisResponse('whois.example', 'example.com');
                self::fail('QueryRateLimitExceededException is expected.');
            } catch (QueryRateLimitExceededException) {
                // expected
            }
        }
    }
}
