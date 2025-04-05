<?php

declare(strict_types=1);

namespace WhoRdap\Response;

final readonly class RdapAsnResponse
{
    public function __construct(
        public string $response,
        public string $server,
    ) {
    }
}
