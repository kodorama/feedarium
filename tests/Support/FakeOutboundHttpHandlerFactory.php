<?php

namespace Tests\Support;

use App\Support\OutboundHttp\OutboundHttpHandlerFactory;

final class FakeOutboundHttpHandlerFactory implements OutboundHttpHandlerFactory
{
    public function create(): ?callable
    {
        return null;
    }
}
