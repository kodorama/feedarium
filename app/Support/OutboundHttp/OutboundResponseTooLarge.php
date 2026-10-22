<?php

namespace App\Support\OutboundHttp;

final class OutboundResponseTooLarge extends OutboundRequestRejected
{
    public function __construct()
    {
        parent::__construct('response_too_large');
    }
}
