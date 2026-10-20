<?php

namespace App\Domains\News\Jobs;

use Throwable;
use Carbon\CarbonImmutable;
use Illuminate\Cache\NullStore;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Log;
use Illuminate\Cache\MemcachedStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/** Coordinates publisher and global request intervals through atomic cache locks. */
final class PublisherRequestGate
{
    private const RESERVATION_LOCK_KEY = 'feedarium:publisher-gate:reservation';

    private const GLOBAL_RESERVATION_KEY = 'feedarium:publisher:global';

    public function __construct(
        private readonly CacheFactory $cache,
        private readonly ConfigRepository $config,
        private readonly Application $application,
    ) {}

    /** @return array{allowed: bool, delay: int, reason: string} */
    public function reserve(?string $url): array
    {
        $store = $this->atomicStore();
        if ($store === null) {
            return ['allowed' => false, 'delay' => 30, 'reason' => 'cache_store_has_no_atomic_locks'];
        }

        $lock = $this->acquireLock($store, self::RESERVATION_LOCK_KEY, $this->lockSeconds());
        if ($lock === false) {
            return [
                'allowed' => false,
                'delay' => max(1, (int) $this->config->get('feedarium.publisher_throttle.lock_contention_release_seconds', 2)),
                'reason' => 'reservation_lock_busy',
            ];
        }
        if ($lock === null) {
            return ['allowed' => false, 'delay' => 30, 'reason' => 'cache_store_unavailable'];
        }

        $publisher = $this->publisherKey($url);
        $publisherLock = $this->acquireLock($store, $this->publisherCooldownLockKey($publisher), $this->lockSeconds());
        if ($publisherLock === null || $publisherLock === false) {
            $lock->release();

            if ($publisherLock === null) {
                return ['allowed' => false, 'delay' => 30, 'reason' => 'cache_store_unavailable'];
            }

            return [
                'allowed' => false,
                'delay' => max(1, (int) $this->config->get('feedarium.publisher_throttle.lock_contention_release_seconds', 2)),
                'reason' => 'reservation_lock_busy',
            ];
        }

        try {
            $now = microtime(true);
            $publisherKey = $this->publisherReservationKey($publisher);
            $cooldownKey = $this->publisherCooldownKey($publisher);
            $fallbackCooldownUntil = (float) $store->get($this->publisherCooldownBarrierKey($publisher), 0);
            $fallbackCooldownKeys = $this->publisherCooldownBarrierBucketKeys($publisher, $now);
            foreach ($store->many($fallbackCooldownKeys) as $barrierUntil) {
                $fallbackCooldownUntil = max($fallbackCooldownUntil, (float) $barrierUntil);
            }
            $readyAt = max(
                (float) $store->get($publisherKey, 0),
                (float) $store->get(self::GLOBAL_RESERVATION_KEY, 0),
                (float) $store->get($cooldownKey, 0),
                $fallbackCooldownUntil,
            );

            if ($readyAt > $now) {
                return ['allowed' => false, 'delay' => max(1, (int) ceil($readyAt - $now)), 'reason' => 'publisher_or_global_budget'];
            }

            [$publisherInterval, $globalInterval] = $this->reservationIntervals();
            $publisherUntil = $now + $publisherInterval;
            $globalUntil = $now + $globalInterval;
            if (! $store->put($publisherKey, $publisherUntil, $this->reservationKeyTtl($publisherUntil, $now))
                || ! $store->put(self::GLOBAL_RESERVATION_KEY, $globalUntil, $this->reservationKeyTtl($globalUntil, $now))) {
                Log::warning('Publisher request reservation was withheld because the cache write failed');

                return ['allowed' => false, 'delay' => 30, 'reason' => 'cache_store_unavailable'];
            }

            return ['allowed' => true, 'delay' => 0, 'reason' => 'reserved'];
        } catch (Throwable $exception) {
            $this->logCacheFailure('Publisher request reservation failed closed', $exception);

            return ['allowed' => false, 'delay' => 30, 'reason' => 'cache_store_unavailable'];
        } finally {
            $publisherLock->release();
            $lock->release();
        }
    }

    public function cooldown(?string $url, int $seconds): bool
    {
        $publisher = $this->publisherKey($url);
        if ($publisher === 'unknown') {
            return true;
        }

        $store = $this->atomicStore();
        if ($store === null) {
            return false;
        }

        $waitSeconds = $this->lockContentionWaitSeconds();
        $key = $this->publisherCooldownKey($publisher);
        $lock = $this->acquireLock($store, $this->publisherCooldownLockKey($publisher), $this->lockSeconds(), $waitSeconds);
        if ($lock === null || $lock === false) {
            return $this->installCooldownBarrier($store, $publisher, $seconds);
        }

        try {
            $now = microtime(true);
            $maximum = max(1, (int) $this->config->get('feedarium.publisher_throttle.cooldown_max_seconds', 3600));
            $requestedUntil = $now + min(max(1, $seconds), $maximum);
            $effectiveUntil = max((float) $store->get($key, 0), $requestedUntil);
            $ttl = max(60, (int) ceil($effectiveUntil - $now) + 60);

            if (! $store->put($key, $effectiveUntil, $ttl)) {
                Log::error('Publisher cooldown cache write failed; installing fail-closed barrier', ['publisher' => $publisher]);

                return $this->installCooldownBarrier($store, $publisher, $seconds);
            }

            return true;
        } catch (Throwable $exception) {
            $this->logCacheFailure('Publisher cooldown cache write failed; installing fail-closed barrier', $exception, ['publisher' => $publisher]);

            return $this->installCooldownBarrier($store, $publisher, $seconds);
        } finally {
            $lock->release();
        }
    }

    /** Account for a redirect hop for subsequent requests; the HTTP client already followed this hop. */
    public function recordRedirect(?string $originalUrl, ?string $effectiveUrl): void
    {
        $publisher = $this->publisherKey($effectiveUrl);
        if ($this->publisherKey($originalUrl) === $publisher || $publisher === 'unknown') {
            return;
        }

        $store = $this->atomicStore();
        if ($store === null) {
            return;
        }

        $lock = $this->acquireLock($store, self::RESERVATION_LOCK_KEY, $this->lockSeconds(), $this->lockContentionWaitSeconds());
        if ($lock === null || $lock === false) {
            Log::warning('Redirect destination request budget was not recorded because the reservation lock remained unavailable', [
                'publisher' => $publisher,
            ]);

            return;
        }

        try {
            $now = microtime(true);
            $publisherKey = $this->publisherReservationKey($publisher);
            [$publisherInterval, $globalInterval] = $this->reservationIntervals();
            $publisherUntil = max($now + $publisherInterval, (float) $store->get($publisherKey, 0));
            $globalUntil = max($now + $globalInterval, (float) $store->get(self::GLOBAL_RESERVATION_KEY, 0));

            if (! $store->put($publisherKey, $publisherUntil, $this->reservationKeyTtl($publisherUntil, $now))
                || ! $store->put(self::GLOBAL_RESERVATION_KEY, $globalUntil, $this->reservationKeyTtl($globalUntil, $now))) {
                Log::warning('Redirect destination request budget cache write failed', ['publisher' => $publisher]);
            }
        } catch (Throwable $exception) {
            $this->logCacheFailure('Redirect destination request budget could not be recorded', $exception, [
                'publisher' => $publisher,
            ]);
        } finally {
            $lock->release();
        }
    }

    /** Execute cache-backed work while holding a lock from the same validated store as request reservations. */
    public function withLock(string $key, int $expiresAfter, \Closure $callback): ?bool
    {
        $store = $this->atomicStore();
        if ($store === null) {
            return null;
        }

        $lock = $this->acquireLock($store, $key, max(1, $expiresAfter));
        if ($lock === null) {
            return null;
        }
        if ($lock === false) {
            return false;
        }

        try {
            $callback();

            return true;
        } finally {
            $lock->release();
        }
    }

    public function cooldownFor(?string $retryAfter, int $attempt): int
    {
        $maximum = max(1, (int) $this->config->get('feedarium.publisher_throttle.cooldown_max_seconds', 3600));
        if ($retryAfter !== null && trim($retryAfter) !== '') {
            $value = trim($retryAfter);
            if (ctype_digit($value)) {
                return min($maximum, max(1, (int) $value));
            }

            try {
                return min($maximum, max(1, CarbonImmutable::parse($value)->timestamp - now()->timestamp));
            } catch (Throwable) {
                // Invalid publisher header: use bounded exponential backoff below.
            }
        }

        $base = min($maximum, 30 * (2 ** min(10, max(0, $attempt - 1))));

        return min($maximum, $base + random_int(0, min(15, max(1, (int) ceil($base * 0.2)))));
    }

    private function atomicStore(): ?CacheRepository
    {
        $storeName = $this->config->get('feedarium.publisher_throttle.cache_store');

        try {
            $store = $this->cache->store(is_string($storeName) && $storeName !== '' ? $storeName : null);
            $driver = $store->getStore();

            if (! $driver instanceof LockProvider
                || $driver instanceof NullStore
                || $driver instanceof MemcachedStore
                || ($driver instanceof ArrayStore && ! $this->application->environment('testing'))) {
                $reason = $driver instanceof MemcachedStore
                    ? 'Laravel MemcachedStore can return all-null results for a failed getMulti call, indistinguishable from cache misses.'
                    : null;
                $this->logUnsupportedStore($storeName, $driver::class, $reason);

                return null;
            }

            return $store;
        } catch (Throwable $exception) {
            $this->logCacheFailure('Publisher request cache store could not be initialized', $exception, [
                'configured_store' => $storeName,
                'recommended_setting' => 'FEEDARIUM_PUBLISHER_CACHE_STORE',
            ]);

            return null;
        }
    }

    private function acquireLock(CacheRepository $store, string $key, int $expiresAfter, ?int $waitSeconds = null): Lock|false|null
    {
        try {
            $lock = $store->lock($key, $expiresAfter);

            if ($waitSeconds !== null) {
                $lock->block(max(1, $waitSeconds));
            } elseif (! $lock->get()) {
                return false;
            }

            return $lock;
        } catch (LockTimeoutException) {
            return false;
        } catch (Throwable $exception) {
            $this->logCacheFailure('Publisher request cache lock could not be acquired', $exception, ['lock_key' => $key]);

            return null;
        }
    }

    /** @return array{0: float, 1: float} */
    private function reservationIntervals(): array
    {
        $publisherRequestsPerMinute = max(1, (int) $this->config->get('feedarium.publisher_throttle.requests_per_minute', 6));
        $globalRequestsPerMinute = max(1, (int) $this->config->get('feedarium.publisher_throttle.global_requests_per_minute', 30));

        return [60 / $publisherRequestsPerMinute, 60 / $globalRequestsPerMinute];
    }

    private function reservationKeyTtl(float $effectiveUntil, float $now): int
    {
        return max(120, (int) ceil($effectiveUntil - $now) + 60);
    }

    private function lockSeconds(): int
    {
        return max(1, (int) $this->config->get('feedarium.publisher_throttle.lock_seconds', 5));
    }

    private function lockContentionWaitSeconds(): int
    {
        return $this->lockSeconds() + 1;
    }

    private function publisherReservationKey(string $publisher): string
    {
        return 'feedarium:publisher:'.sha1($publisher);
    }

    private function publisherCooldownKey(string $publisher): string
    {
        return $this->publisherReservationKey($publisher).':cooldown';
    }

    private function publisherCooldownLockKey(string $publisher): string
    {
        return $this->publisherReservationKey($publisher).':cooldown-lock';
    }

    private function publisherCooldownBarrierKey(string $publisher): string
    {
        return $this->publisherReservationKey($publisher).':cooldown-barrier';
    }

    /** @return array<int, string> */
    private function publisherCooldownBarrierBucketKeys(string $publisher, float $now): array
    {
        $maximum = max(1, (int) $this->config->get('feedarium.publisher_throttle.cooldown_max_seconds', 3600));
        $firstBucket = (int) floor($now / 60);
        $lastBucket = (int) ceil(($now + $maximum) / 60);
        $prefix = $this->publisherReservationKey($publisher).':cooldown-barrier:';
        $keys = [];

        for ($bucket = $firstBucket; $bucket <= $lastBucket; $bucket++) {
            $keys[] = $prefix.$bucket;
        }

        return $keys;
    }

    private function installCooldownBarrier(CacheRepository $store, string $publisher, int $seconds): bool
    {
        try {
            $now = microtime(true);
            $maximum = max(1, (int) $this->config->get('feedarium.publisher_throttle.cooldown_max_seconds', 3600));
            $until = $now + min(max(1, $seconds), $maximum);
            $bucket = (int) ceil($until / 60);
            $bucketUntil = $bucket * 60.0;
            $key = $this->publisherReservationKey($publisher).':cooldown-barrier:'.$bucket;
            $ttl = max(60, (int) ceil($bucketUntil - $now) + 60);
            $stored = $store->add($key, $bucketUntil, $ttl);
            $activeUntil = (float) $store->get($key, 0);

            if ($stored || $activeUntil >= $until) {
                Log::warning('Publisher cooldown used a fail-closed barrier', [
                    'publisher' => $publisher,
                    'barrier_until' => $bucketUntil,
                ]);

                return true;
            }
        } catch (Throwable $exception) {
            $this->logCacheFailure('Publisher cooldown fail-closed barrier could not be installed', $exception, [
                'publisher' => $publisher,
            ]);
        }

        return false;
    }

    private function publisherKey(?string $url): string
    {
        $host = strtolower(rtrim((string) parse_url((string) $url, PHP_URL_HOST), '.'));
        if ($host === '') {
            return 'unknown';
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $aliases = $this->config->get('feedarium.publisher_throttle.publisher_aliases', []);
        foreach ($aliases as $alias => $publisher) {
            if ($host === strtolower($alias)) {
                return strtolower($publisher);
            }
        }

        return $host;
    }

    private function logUnsupportedStore(?string $storeName, string $driver, ?string $reason = null): void
    {
        Log::warning('Publisher request coordination requires an atomic shared cache store', [
            'configured_store' => $storeName ?: $this->config->get('cache.default'),
            'store_driver' => $driver,
            'reason' => $reason,
            'environment' => $this->application->environment(),
            'recommended_setting' => 'Set FEEDARIUM_PUBLISHER_CACHE_STORE to a shared Redis or database cache store; file cache is single-host.',
        ]);
    }

    /** @param array<string, mixed> $context */
    private function logCacheFailure(string $message, Throwable $exception, array $context = []): void
    {
        Log::warning($message, array_merge($context, [
            'error_class' => $exception::class,
            'recommended_setting' => 'Check FEEDARIUM_PUBLISHER_CACHE_STORE and configure a shared atomic cache store.',
        ]));
    }
}
