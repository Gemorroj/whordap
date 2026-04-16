<?php

declare(strict_types=1);

namespace WhoRdap\Handler;

use Psr\Cache\InvalidArgumentException;
use WhoRdap\AsnServerListInterface;
use WhoRdap\Exception\HttpException;
use WhoRdap\Exception\InvalidResponseException;
use WhoRdap\Exception\NetworkException;
use WhoRdap\Exception\QueryRateLimitExceededException;
use WhoRdap\Exception\TimeoutException;
use WhoRdap\HandlerInterface;
use WhoRdap\NetworkClientInterface;
use WhoRdap\Response\RdapAsnResponse;
use WhoRdap\Response\WhoisAsnResponse;

final readonly class AsnHandler implements HandlerInterface
{
    public function __construct(private NetworkClientInterface $networkClient, private AsnServerListInterface $serverList)
    {
    }

    /**
     * @throws QueryRateLimitExceededException
     * @throws InvalidArgumentException
     * @throws NetworkException
     * @throws TimeoutException
     */
    public function processWhois(string $query, ?string $forceServer = null): WhoisAsnResponse
    {
        $server = $forceServer ?? $this->findAsnServer($query);

        $q = $this->prepareWhoisServerQuery($server, $query);
        $response = $this->networkClient->getWhoisResponse($server, $q);

        return new WhoisAsnResponse(
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
    public function processRdap(string $query, ?string $forceServer = null): RdapAsnResponse
    {
        $server = $forceServer ?? $this->findAsnServer($query);

        $q = $this->prepareRdapServerQuery($server, $query);
        $response = $this->networkClient->getRdapResponse($server, $q);

        return new RdapAsnResponse(
            $response,
            $server,
        );
    }

    private function findAsnServer(string $query): string
    {
        $hasAsPrefix = false !== \stripos($query, 'AS');
        $number = $hasAsPrefix ? \substr($query, 2) : $query;
        $number = (int) $number;

        foreach ($this->serverList->servers as $range => $server) {
            if (!\is_int($range) && \str_contains($range, '-')) {
                [$fromRange, $toRange] = \explode('-', $range, 2);
            } else {
                $fromRange = $toRange = $range;
            }

            if ($number >= $fromRange && $number <= $toRange) {
                return $server;
            }
        }

        return $this->serverList->serverDefault;
    }

    private function prepareWhoisServerQuery(string $server, string $query): string
    {
        if (\in_array($server, ['whois.arin.net', 'whois.arin.net:43'], true)) {
            $hasAsPrefix = false !== \stripos($query, 'AS');

            return $hasAsPrefix ? 'z '.\substr($query, 2) : 'z '.$query;
        }
        if (\in_array($server, ['whois.afrinic.net', 'whois.afrinic.net:43', 'whois.apnic.net', 'whois.apnic.net:43', 'whois.ripe.net', 'whois.ripe.net:43'], true)) {
            $hasAsPrefix = false !== \stripos($query, 'AS');

            return $hasAsPrefix ? $query : 'AS'.$query;
        }

        return $query;
    }

    private function prepareRdapServerQuery(string $server, string $query): string
    {
        $hasAsPrefix = false !== \stripos($query, 'AS');

        return '/autnum/'.($hasAsPrefix ? \substr($query, 2) : $query);
    }
}
