<?php

declare(strict_types=1);

namespace WhoRdap\Handler;

use Algo26\IdnaConvert\Exception\AlreadyPunycodeException;
use Algo26\IdnaConvert\Exception\InvalidCharacterException as Algo26InvalidCharacterException;
use Algo26\IdnaConvert\ToIdn;
use Psr\Cache\InvalidArgumentException;
use WhoRdap\Exception\HttpException;
use WhoRdap\Exception\InvalidCharacterException;
use WhoRdap\Exception\InvalidRdapServerException;
use WhoRdap\Exception\InvalidResponseException;
use WhoRdap\Exception\InvalidWhoisServerException;
use WhoRdap\Exception\NetworkException;
use WhoRdap\Exception\QueryRateLimitExceededException;
use WhoRdap\Exception\RegistrarServerException;
use WhoRdap\Exception\TimeoutException;
use WhoRdap\HandlerInterface;
use WhoRdap\NetworkClientInterface;
use WhoRdap\Response\RdapDomainRegistrarResponse;
use WhoRdap\Response\RdapDomainResponse;
use WhoRdap\Response\WhoisDomainRegistrarResponse;
use WhoRdap\Response\WhoisDomainResponse;
use WhoRdap\TldServerListInterface;

final readonly class DomainHandler implements HandlerInterface
{
    public function __construct(private NetworkClientInterface $networkClient, private TldServerListInterface $serverList)
    {
    }

    /**
     * @throws QueryRateLimitExceededException
     * @throws InvalidArgumentException
     * @throws InvalidCharacterException
     * @throws NetworkException
     * @throws TimeoutException
     */
    public function processWhois(string $query, ?string $forceServer = null): WhoisDomainResponse
    {
        try {
            $query = new ToIdn()->convert($query);
        } catch (AlreadyPunycodeException) {
            // $query is already a Punycode
        } catch (Algo26InvalidCharacterException $e) {
            throw new InvalidCharacterException('Invalid query: '.$query, previous: $e);
        }

        $server = $forceServer ?? $this->findTldServer($query);

        $q = $this->prepareWhoisServerQuery($server, $query);
        $response = $this->networkClient->getWhoisResponse($server, $q);
        $domainRegistrarResponse = null;

        if (!$forceServer) {
            $registrarServer = $this->findWhoisRegistrarServer($response);
            if ($registrarServer && $registrarServer !== $server) {
                $q = $this->prepareWhoisServerQuery($registrarServer, $query);
                try {
                    $registrarResponse = $this->networkClient->getWhoisResponse($registrarServer, $q);
                    $domainRegistrarResponse = new WhoisDomainRegistrarResponse($registrarResponse, $registrarServer);
                } catch (\Exception $e) {
                    $domainRegistrarResponse = new RegistrarServerException('Can\'t load info from registrar server.', previous: $e);
                }
            }
        }

        return new WhoisDomainResponse(
            $response,
            $server,
            $domainRegistrarResponse,
        );
    }

    /**
     * @throws InvalidResponseException
     * @throws InvalidCharacterException
     * @throws HttpException
     * @throws InvalidArgumentException
     * @throws NetworkException
     */
    public function processRdap(string $query, ?string $forceServer = null): RdapDomainResponse
    {
        try {
            $query = new ToIdn()->convert($query);
        } catch (AlreadyPunycodeException) {
            // $query is already a Punycode
        } catch (Algo26InvalidCharacterException $e) {
            throw new InvalidCharacterException('Invalid query: '.$query, previous: $e);
        }

        $server = $forceServer ?? $this->findTldServer($query);

        $q = $this->prepareRdapServerQuery($server, $query);
        $response = $this->networkClient->getRdapResponse($server, $q);
        $domainRegistrarResponse = null;

        if (!$forceServer) {
            $registrarServer = $this->findRdapRegistrarServer($response);
            if ($registrarServer && $registrarServer !== $server) {
                $q = $this->prepareRdapServerQuery($registrarServer, $query);
                try {
                    $registrarResponse = $this->networkClient->getRdapResponse($registrarServer, $q);
                    $domainRegistrarResponse = new RdapDomainRegistrarResponse($registrarResponse, $registrarServer);
                } catch (\Exception $e) {
                    $domainRegistrarResponse = new RegistrarServerException('Can\'t load info from registrar server.', previous: $e);
                }
            }
        }

        return new RdapDomainResponse(
            $response,
            $server,
            $domainRegistrarResponse,
        );
    }

    private function findTldServer(string $query): string
    {
        if (0 === \substr_count($query, '.')) {
            return $this->serverList->serverDefault;
        }

        $dp = \explode('.', $query);
        $np = \count($dp) - 1;
        $tldTests = [];

        for ($i = 0; $i < $np; ++$i) {
            \array_shift($dp);
            $tldTests[] = '.'.\implode('.', $dp);
        }

        foreach ($tldTests as $tld) {
            if (isset($this->serverList->servers[$tld])) {
                return $this->serverList->servers[$tld];
            }
        }

        return $this->serverList->serverDefault;
    }

    private function prepareWhoisServerQuery(string $server, string $query): string
    {
        return $query;
    }

    private function prepareRdapServerQuery(string $server, string $query): string
    {
        return '/domain/'.$query;
    }

    private function findRdapRegistrarServer(string $response): ?string
    {
        try {
            $json = \json_decode($response, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        // https://rdap.verisign.com/com/v1/domain/vk.com
        // https://datatracker.ietf.org/doc/html/rfc9083#name-links
        $links = $json['links'] ?? [];
        foreach ($links as $link) {
            if ($link['href'] && 'related' === $link['rel'] && 'application/rdap+json' === $link['type']) {
                $href = \strtolower($link['href']);
                $posDomain = \strpos($href, '/domain/');
                if (false !== $posDomain) {
                    $href = \substr($href, 0, $posDomain + 1);
                }

                try {
                    return $this->prepareRdapServer($href);
                } catch (InvalidRdapServerException) {
                    return null;
                }
            }
        }

        return null;
    }

    private function findWhoisRegistrarServer(string $response): ?string
    {
        $matches = [];
        if (\preg_match('/Registrar WHOIS Server:(.+)/i', $response, $matches)) {
            $server = \trim($matches[1]);
            if ('' === $server) {
                return null;
            }

            try {
                return $this->prepareWhoisServer($server);
            } catch (InvalidWhoisServerException) {
                return null;
            }
        }

        return null;
    }

    /**
     * @throws InvalidWhoisServerException
     */
    private function prepareWhoisServer(string $whoisServer): string
    {
        $whoisServer = \strtolower($whoisServer);

        if (\str_starts_with($whoisServer, 'rwhois://')) {
            $whoisServer = \substr($whoisServer, 9);
        }
        if (\str_starts_with($whoisServer, 'whois://')) {
            $whoisServer = \substr($whoisServer, 8);
        }

        $parsedWhoisServer = \parse_url($whoisServer);
        if (isset($parsedWhoisServer['scheme'])) {
            throw new InvalidWhoisServerException('Invalid WHOIS server path.');
        }
        if (isset($parsedWhoisServer['path']) && $parsedWhoisServer['path'] === $whoisServer) {
            // https://stackoverflow.com/questions/1418423/the-hostname-regex
            if (!\preg_match('/^(?=.{1,255}$)[0-9A-Za-z](?:(?:[0-9A-Za-z]|-){0,61}[0-9A-Za-z])?(?:\.[0-9A-Za-z](?:(?:[0-9A-Za-z]|-){0,61}[0-9A-Za-z])?)*\.?$/', $parsedWhoisServer['path'])) {
                // something strange path. /passwd for example
                throw new InvalidWhoisServerException('Invalid WHOIS server path.');
            }
        }

        return $whoisServer;
    }

    /**
     * @throws InvalidRdapServerException
     */
    private function prepareRdapServer(string $rdapServer): string
    {
        $rdapServer = \strtolower($rdapServer);

        $parsedRdapServer = \parse_url($rdapServer);
        if (!isset($parsedRdapServer['scheme'])) {
            throw new InvalidRdapServerException('Invalid RDAP server path.');
        }

        if (!\in_array($parsedRdapServer['scheme'], ['http', 'https'], true)) {
            // something strange path. /passwd for example
            throw new InvalidRdapServerException('Invalid RDAP server scheme. Must be HTTP or HTTPS.');
        }

        return $rdapServer;
    }
}
