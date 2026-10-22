<?php

namespace App\Support\OutboundHttp;

use RuntimeException;

final class OutboundRequestDeferred extends RuntimeException
{
    public function __construct(public readonly int $delaySeconds)
    {
        parent::__construct('Publisher request slot is not available for redirect destination.');
    }
}
