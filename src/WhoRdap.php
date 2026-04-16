<?php

declare(strict_types=1);

namespace WhoRdap;

use WhoRdap\Exception\EmptyQueryException;
use WhoRdap\Handler\AsnHandler;
use WhoRdap\Handler\DomainHandler;
use WhoRdap\Handler\IpHandler;
use WhoRdap\NetworkClient\NetworkClient;
use WhoRdap\Resource\RdapAsnServerList;
use WhoRdap\Resource\RdapIpServerList;
use WhoRdap\Resource\RdapTldServerList;
use WhoRdap\Resource\WhoisAsnServerList;
use WhoRdap\Resource\WhoisIpServerList;
use WhoRdap\Resource\WhoisTldServerList;
use WhoRdap\Response\RdapAsnResponse;
use WhoRdap\Response\RdapDomainResponse;
use WhoRdap\Response\RdapIpResponse;
use WhoRdap\Response\WhoisAsnResponse;
use WhoRdap\Response\WhoisDomainResponse;
use WhoRdap\Response\WhoisIpResponse;

final readonly class WhoRdap
{
    public function __construct(
        private ?NetworkClientInterface $networkClient = null,
        private ?TldServerListInterface $whoisTldServerList = null,
        private ?AsnServerListInterface $whoisAsnServerList = null,
        private ?IpServerListInterface $whoisIpServerList = null,
        private ?TldServerListInterface $rdapTldServerList = null,
        private ?AsnServerListInterface $rdapAsnServerList = null,
        private ?IpServerListInterface $rdapIpServerList = null,
    ) {
    }

    /**
     * @throws EmptyQueryException
     */
    public function processWhois(string $query, ?string $forceServer = null): WhoisIpResponse|WhoisAsnResponse|WhoisDomainResponse
    {
        if ('' === $query) {
            return throw new EmptyQueryException('The query is empty');
        }

        return $this->createWhoisQueryHandler($query)->processWhois($query, $forceServer);
    }

    /**
     * @throws EmptyQueryException
     */
    public function processRdap(string $query, ?string $forceServer = null): RdapIpResponse|RdapAsnResponse|RdapDomainResponse
    {
        if ('' === $query) {
            return throw new EmptyQueryException('The query is empty');
        }

        return $this->createRdapQueryHandler($query)->processRdap($query, $forceServer);
    }

    private function createWhoisQueryHandler(string $query): HandlerInterface
    {
        $networkClient = $this->networkClient ?? new NetworkClient();

        if ($this->isIp($query)) {
            $ipServerList = $this->whoisIpServerList ?? new WhoisIpServerList();

            return new IpHandler($networkClient, $ipServerList);
        }
        if ($this->isAsn($query)) {
            $asnServerList = $this->whoisAsnServerList ?? new WhoisAsnServerList();

            return new AsnHandler($networkClient, $asnServerList);
        }

        $tldServerList = $this->whoisTldServerList ?? new WhoisTldServerList();

        return new DomainHandler($networkClient, $tldServerList);
    }

    private function createRdapQueryHandler(string $query): HandlerInterface
    {
        $networkClient = $this->networkClient ?? new NetworkClient();

        if ($this->isIp($query)) {
            $ipServerList = $this->rdapIpServerList ?? new RdapIpServerList();

            return new IpHandler($networkClient, $ipServerList);
        }
        if ($this->isAsn($query)) {
            $asnServerList = $this->rdapAsnServerList ?? new RdapAsnServerList();

            return new AsnHandler($networkClient, $asnServerList);
        }

        $tldServerList = $this->rdapTldServerList ?? new RdapTldServerList();

        return new DomainHandler($networkClient, $tldServerList);
    }

    private function isAsn(string $query): bool
    {
        $hasAsPrefix = false !== \stripos($query, 'AS');
        if ($hasAsPrefix) {
            $query = \substr($query, 2);
        }

        if (\preg_match('/^\d+$/', $query)) {
            return true;
        }

        return false;
    }

    private function isIp(string $query): bool
    {
        if (\str_contains($query, '/')) { // check CIDR
            $parts = \explode('/', $query);
            if (2 !== \count($parts)) {
                return false;
            }

            $ip = $parts[0];
            $netmask = (int) $parts[1];
            if ($netmask < 0) {
                return false;
            }

            if (\filter_var($ip, \FILTER_VALIDATE_IP, ['flags' => \FILTER_FLAG_IPV4])) {
                return $netmask <= 32;
            }

            if (\filter_var($ip, \FILTER_VALIDATE_IP, ['flags' => \FILTER_FLAG_IPV6])) {
                return $netmask <= 128;
            }

            return false;
        }

        return false !== \filter_var($query, \FILTER_VALIDATE_IP, ['flags' => \FILTER_FLAG_IPV4 | \FILTER_FLAG_IPV6]);
    }
}
