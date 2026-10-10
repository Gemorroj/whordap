<?php

declare(strict_types=1);

namespace WhoRdap\NetworkClient;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use WhoRdap\Exception\HttpException;
use WhoRdap\Exception\InvalidResponseException;
use WhoRdap\Exception\NetworkException;
use WhoRdap\Exception\QueryRateLimitExceededException;
use WhoRdap\Exception\TimeoutException;
use WhoRdap\NetworkClientInterface;
use WhoRdap\Transport\NativeTransport;
use WhoRdap\TransportInterface;

readonly class NetworkClient implements NetworkClientInterface
{
    private TransportInterface $transport;

    public function __construct(
        private ?CacheItemPoolInterface $cache = null,
        private ?LoggerInterface $logger = null,
        ?TransportInterface $transport = null,
    ) {
        $this->transport = $transport ?? new NativeTransport(logger: $this->logger);
    }

    protected function makeCacheKey(string ...$args): string
    {
        return \md5(\serialize($args));
    }

    /**
     * @throws QueryRateLimitExceededException
     * @throws InvalidArgumentException
     * @throws NetworkException
     * @throws TimeoutException
     */
    public function getWhoisResponse(string $server, string $query): string
    {
        $cacheItem = null;
        if ($this->cache) {
            $cacheKey = $this->makeCacheKey($server, $query);
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                $this->logger?->debug("Load cached data for $server -> $query");

                return $cacheItem->get();
            }
        }

        $response = $this->transport->requestWhois($server, $query);

        if ($this->isWhoisQueryRateLimitExceeded($response)) {
            throw QueryRateLimitExceededException::create($server);
        }

        if ($cacheItem) {
            $this->logger?->debug('Save cache');
            $cacheItem->set($response);
            $this->cache->save($cacheItem);
        }

        return $response;
    }

    /**
     * @throws InvalidResponseException
     * @throws InvalidArgumentException
     * @throws HttpException
     * @throws NetworkException
     */
    public function getRdapResponse(string $server, string $query): string
    {
        $cacheItem = null;
        if ($this->cache) {
            $cacheKey = $this->makeCacheKey($server, $query);
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                $this->logger?->debug("Load cached data for $server -> $query");

                return $cacheItem->get();
            }
        }

        $response = $this->transport->requestRdap($server, $query);

        if ($cacheItem) {
            $this->logger?->debug('Save cache');
            $cacheItem->set($response);
            $this->cache->save($cacheItem);
        }

        return $response;
    }

    protected function isWhoisQueryRateLimitExceeded(string $response): bool
    {
        $stings = [
            'Query rate limit exceeded',
            'You have exceeded this limit',
            'Lookup quota exceeded',
            'WHOIS LIMIT EXCEEDED',
            'Excessive querying, grace period of',
            'Query limitation is',
            'exceeded allowed connection rate',
            'too many requests',
            'and will be replenished',
            'contained within a list of IP addresses that may have failed',
            'You exceeded the maximum',
            'Maximum Daily connection limit reached',
            'exceeded maximum connection limit',
            'Still in grace period, wait',
            'Query rate of ',
        ];

        return \array_any($stings, static function (string $value) use ($response): bool {
            return \str_contains($response, $value);
        });
    }
}
