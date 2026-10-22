<?php

namespace App\Support\OutboundHttp;

use RuntimeException;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;

final class LimitedResponseStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private StreamInterface $stream;

    private int $written = 0;

    private bool $overLimit = false;

    public function __construct(private readonly int $limit)
    {
        $resource = fopen('php://temp/maxmemory:1048576', 'w+b');
        if ($resource === false) {
            throw new RuntimeException('Unable to allocate bounded response stream.');
        }

        $this->stream = Utils::streamFor($resource);
    }

    public function write(string $string): int
    {
        $length = strlen($string);
        if ($length > $this->limit - $this->written) {
            $this->overLimit = true;

            return 0;
        }

        $written = $this->stream->write($string);
        $this->written += $written;

        return $written;
    }

    public function exceededLimit(): bool
    {
        return $this->overLimit;
    }

    public function contents(): string
    {
        $this->stream->rewind();

        return $this->stream->getContents();
    }
}
