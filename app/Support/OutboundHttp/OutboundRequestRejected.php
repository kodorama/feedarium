<?php

namespace App\Support\OutboundHttp;

use RuntimeException;

class OutboundRequestRejected extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Outbound HTTP request rejected by security policy.');
    }
}
