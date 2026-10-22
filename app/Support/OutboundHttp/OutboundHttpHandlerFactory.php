<?php

namespace App\Support\OutboundHttp;

interface OutboundHttpHandlerFactory
{
    public function create(): ?callable;
}
