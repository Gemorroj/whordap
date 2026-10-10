<?php

declare(strict_types=1);

namespace WhoRdap;

use WhoRdap\Exception\HttpException;
use WhoRdap\Exception\InvalidResponseException;
use WhoRdap\Exception\NetworkException;
use WhoRdap\Exception\TimeoutException;

interface TransportInterface
{
    /**
     * @throws NetworkException
     * @throws TimeoutException
     */
    public function requestWhois(string $server, string $query): string;

    /**
     * @throws NetworkException
     * @throws InvalidResponseException
     * @throws HttpException
     */
    public function requestRdap(string $server, string $query): string;
}
