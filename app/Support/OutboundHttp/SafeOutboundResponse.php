<?php

namespace App\Support\OutboundHttp;

use Illuminate\Http\Client\Response;

final readonly class SafeOutboundResponse
{
    public function __construct(
        private Response $response,
        public string $effectiveUrl,
        private string $boundedBody,
    ) {}

    public function status(): int
    {
        return $this->response->status();
    }

    public function header(string $name): ?string
    {
        return $this->response->header($name);
    }

    public function body(): string
    {
        return $this->boundedBody;
    }

    public function successful(): bool
    {
        return $this->response->successful();
    }
}
