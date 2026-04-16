<?php

declare(strict_types=1);

namespace WhoRdap\Handler;

use Psr\Cache\InvalidArgumentException;
use WhoRdap\Exception\HttpException;
use WhoRdap\Exception\InvalidResponseException;
use WhoRdap\Exception\NetworkException;
use WhoRdap\Exception\QueryRateLimitExceededException;
use WhoRdap\Exception\TimeoutException;
use WhoRdap\HandlerInterface;
use WhoRdap\IpServerListInterface;
use WhoRdap\NetworkClientInterface;
use WhoRdap\Response\RdapIpResponse;
use WhoRdap\Response\WhoisIpResponse;

final readonly class IpHandler implements HandlerInterface
{
    public function __construct(private NetworkClientInterface $networkClient, private IpServerListInterface $serverList)
    {
    }

    /**
     * @throws QueryRateLimitExceededException
     * @throws InvalidArgumentException
     * @throws NetworkException
     * @throws TimeoutException
     */
    public function processWhois(string $query, ?string $forceServer = null): WhoisIpResponse
    {
        $server = $forceServer ?? $this->findIpServer($query);

        $q = $this->prepareWhoisServerQuery($server, $query);
        $response = $this->networkClient->getWhoisResponse($server, $q);

        return new WhoisIpResponse(
            $response,
            $server,
        );
    }

    /**
     * @throws InvalidResponseException
     * @throws InvalidArgumentException
     * @throws HttpException
     * @throws NetworkException
     */
    public function processRdap(string $query, ?string $forceServer = null): RdapIpResponse
    {
        $server = $forceServer ?? $this->findIpServer($query);

        $q = $this->prepareRdapServerQuery($server, $query);
        $response = $this->networkClient->getRdapResponse($server, $q);

        return new RdapIpResponse(
            $response,
            $server,
        );
    }

    private function findIpServer(string $query): string
    {
        $slashPos = \strpos($query, '/'); // skip query CIDR
        if (false === $slashPos) {
            $ip = $query;
        } else {
            $ip = \substr($query, 0, $slashPos);
        }

        $isIpv6 = \substr_count($query, ':');
        if ($isIpv6) {
            return $this->findIpv6Server($ip) ?? $this->serverList->serverDefault;
        }

        return $this->findIpv4Server($ip) ?? $this->serverList->serverDefault;
    }

    private function findIpv4Server(string $ip): ?string
    {
        foreach ($this->serverList->serversIpv4 as $ipv4Cidr => $server) {
            $parts = \explode('/', $ipv4Cidr, 2);
            $subnet = $parts[0];
            $mask = (int) ($parts[1] ?? 32);

            $address = \ip2long($ip);
            $subnetAddress = \ip2long($subnet);
            $mask = -1 << (32 - $mask);
            $subnetAddress &= $mask; // nb: in case the supplied subnet wasn't correctly aligned
            $match = ($address & $mask) === $subnetAddress;
            if ($match) {
                return $server;
            }
        }

        return null;
    }

    private function findIpv6Server(string $ip): ?string
    {
        foreach ($this->serverList->serversIpv6 as $ipv6Cidr => $server) {
            $parts = \explode('/', $ipv6Cidr, 2);
            $subnet = $parts[0];
            $mask = (int) ($parts[1] ?? 128);

            $subnet = \inet_pton($subnet);
            $addr = \inet_pton($ip);

            $binMask = \str_repeat('f', \intdiv($mask, 4));
            switch ($mask % 4) {
                case 1:
                    $binMask .= '8';
                    break;
                case 2:
                    $binMask .= 'c';
                    break;
                case 3:
                    $binMask .= 'e';
                    break;
            }
            $binMask = \str_pad($binMask, 32, '0');
            $binMask = \pack('H*', $binMask);

            $match = ($addr & $binMask) === $subnet;
            if ($match) {
                return $server;
            }
        }

        return null;
    }

    private function prepareRdapServerQuery(string $server, string $query): string
    {
        return '/ip/'.$query;
    }

    private function prepareWhoisServerQuery(string $server, string $query): string
    {
        if (\in_array($server, ['whois.arin.net', 'whois.arin.net:43'], true)) {
            $isCidr = \str_contains($query, '/');

            return $isCidr ? 'r = '.$query : 'z '.$query;
        }

        return $query;
    }
}
