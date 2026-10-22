<?php

namespace App\Support\OutboundHttp;

interface OutboundHostResolver
{
    /** @return list<string> */
    public function resolve(string $hostname): array;
}
