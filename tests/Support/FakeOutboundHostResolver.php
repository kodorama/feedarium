<?php

namespace Tests\Support;

use App\Support\OutboundHttp\OutboundHostResolver;

final class FakeOutboundHostResolver implements OutboundHostResolver
{
    /** @var array<string, list<string>> */
    public array $answers = [];

    public function resolve(string $hostname): array
    {
        if (isset($this->answers[$hostname])) {
            return $this->answers[$hostname];
        }

        return ['93.184.216.34'];
    }
}
