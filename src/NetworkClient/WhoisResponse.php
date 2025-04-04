<?php

declare(strict_types=1);

namespace WhoRdap\NetworkClient;

final readonly class WhoisResponse
{
    public function __construct(
        public string $data,
    ) {
    }
}
