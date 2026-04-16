<?php

declare(strict_types=1);

namespace WhoRdap;

use Psr\Cache\InvalidArgumentException;
use WhoRdap\Exception\HttpException;
use WhoRdap\Exception\InvalidResponseException;
use WhoRdap\Exception\NetworkException;
use WhoRdap\Exception\QueryRateLimitExceededException;
use WhoRdap\Exception\TimeoutException;

interface NetworkClientInterface
{
    /**
     * @throws QueryRateLimitExceededException
     * @throws InvalidArgumentException
     * @throws NetworkException
     * @throws TimeoutException
     */
    public function getWhoisResponse(string $server, string $query): string;

    /**
     * @throws InvalidResponseException
     * @throws InvalidArgumentException
     * @throws HttpException
     * @throws NetworkException
     */
    public function getRdapResponse(string $server, string $query): string;
}
