<?php

namespace App\Support\OutboundHttp;

use GuzzleHttp\Handler\CurlHandler;

final class CurlOutboundHttpHandlerFactory implements OutboundHttpHandlerFactory
{
    public function create(): ?callable
    {
        if (! extension_loaded('curl')) {
            return null;
        }

        return new CurlHandler;
    }
}
