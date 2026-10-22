<?php

namespace App\Support\OutboundHttp;

use Throwable;
use RuntimeException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/** Stores bounded, encrypted redirect progress across immutable queue payload retries. */
final class PublisherRedirectContinuationStore
{
    public function __construct(
        private readonly CacheFactory $cache,
        private readonly ConfigRepository $config,
        private readonly Encrypter $encrypter,
    ) {}

    /** @return array{url: string, method: string, secret: ?string, redirects: int, visited: list<string>}|null */
    public function get(string $identity, int $expiresAt): ?array
    {
        $key = $this->key($identity);
        $store = $this->store();

        try {
            $encoded = $store->get($key);
        } catch (Throwable) {
            throw new RuntimeException('Unable to load outbound redirect continuation state.');
        }

        if (! is_string($encoded)) {
            return null;
        }

        if ($expiresAt <= now()->timestamp) {
            $this->forget($identity);

            return null;
        }

        try {
            $state = json_decode($this->encrypter->decryptString($encoded), true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->forget($identity);
            throw new RuntimeException('Outbound redirect continuation state is invalid.');
        }

        if (! is_array($state)
            || ! is_string($state['url'] ?? null)
            || strlen($state['url']) > 4096
            || ! in_array($state['method'] ?? null, ['GET', 'POST'], true)
            || ! is_int($state['redirects'] ?? null)
            || $state['redirects'] < 1
            || $state['redirects'] > 5
            || ! is_array($state['visited'] ?? null)
            || count($state['visited']) > 6
            || array_filter($state['visited'], static fn ($url): bool => ! is_string($url) || strlen($url) > 4096) !== []) {
            $this->forget($identity);
            throw new RuntimeException('Outbound redirect continuation state is invalid.');
        }

        return [
            'url' => $state['url'],
            'method' => $state['method'],
            'secret' => is_string($state['secret'] ?? null) ? $state['secret'] : null,
            'redirects' => $state['redirects'],
            'visited' => array_values($state['visited']),
        ];
    }

    /** @param array{url: string, method: string, secret: ?string, redirects: int, visited: list<string>} $state */
    public function put(string $identity, int $expiresAt, array $state): void
    {
        $ttl = $expiresAt - now()->timestamp;
        if ($ttl <= 0) {
            throw new RuntimeException('Outbound redirect continuation window expired.');
        }

        try {
            $value = $this->encrypter->encryptString(json_encode($state, JSON_THROW_ON_ERROR));
            if (! $this->store()->put($this->key($identity), $value, Carbon::now()->addSeconds($ttl))) {
                throw new RuntimeException('Cache store rejected redirect continuation state.');
            }
        } catch (Throwable) {
            throw new RuntimeException('Unable to persist outbound redirect continuation state.');
        }
    }

    public function forget(string $identity): void
    {
        try {
            $this->store()->forget($this->key($identity));
        } catch (Throwable) {
            // The absolute expiry is authoritative if the backing cache is temporarily unavailable.
            Log::warning('Unable to immediately remove outbound redirect continuation state.');
        }
    }

    private function store(): \Illuminate\Contracts\Cache\Repository
    {
        $name = $this->config->get('feedarium.publisher_throttle.cache_store');

        return $this->cache->store(is_string($name) && $name !== '' ? $name : null);
    }

    private function key(string $identity): string
    {
        return 'feedarium:outbound-redirect:'.hash('sha256', $identity);
    }
}
