<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Domains\News\Jobs\EnrichArticleJob;
use App\Domains\News\Jobs\PublisherRequestGate;
use Illuminate\Http\Client\ConnectionException;
use App\Domains\News\Jobs\RateLimitPublisherRequests;

describe('EnrichArticleJob', function () {
    beforeEach(function () {
        User::factory()->create(['is_admin' => true]);
        $this->actingAs(User::first());
    });

    it('extracts body and metadata image from one HTML response', function () {
        Setting::set('scrape_full_body', 'true');
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://example.com/story']);
        Http::fake(['example.com/story' => Http::response('<meta content="https://example.com/card.jpg" property="og:image"><article><p>Full story</p></article>', 200)]);

        $this->app->call([new EnrichArticleJob($news->id), 'handle']);

        Http::assertSentCount(1);
        expect($news->fresh()->full_body)->toContain('Full story')
            ->and($news->fresh()->thumbnail_url)->toBe('https://example.com/card.jpg');
    });

    it('extracts only a thumbnail when full-body scraping is disabled', function () {
        Setting::set('scrape_full_body', 'false');
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://example.com/story']);
        Http::fake(['*' => Http::response('<meta property="og:image" content="https://example.com/card.jpg"><article>Body</article>', 200)]);

        $this->app->call([new EnrichArticleJob($news->id), 'handle']);

        expect($news->fresh()->thumbnail_url)->toBe('https://example.com/card.jpg')
            ->and($news->fresh()->full_body)->toBeNull();
    });

    it('preserves existing content and image when extraction results are empty', function () {
        Setting::set('scrape_full_body', 'true');
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://example.com/story', 'thumbnail_url' => 'https://example.com/good.jpg', 'full_body' => '<article>Good</article>']);
        Http::fake(['*' => Http::response('<html><body>Unstructured</body></html>', 200)]);

        $this->app->call([new EnrichArticleJob($news->id), 'handle']);

        expect($news->fresh()->thumbnail_url)->toBe('https://example.com/good.jpg')
            ->and($news->fresh()->full_body)->toBe('<article>Good</article>');
        Http::assertNothingSent();
    });

    it('rethrows connection failures for Laravel queue retry handling', function () {
        Setting::set('scrape_full_body', 'true');
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://example.com/story']);
        Http::fake(['*' => static fn () => throw new ConnectionException('temporary')]);

        $job = new EnrichArticleJob($news->id);
        expect(fn () => $this->app->call([$job, 'handle']))
            ->toThrow(ConnectionException::class);
    });

    it('sets publisher cooldown from Retry-After seconds and dates', function () {
        $gate = app(PublisherRequestGate::class);
        expect($gate->cooldownFor('42', 1))->toBe(42)
            ->and($gate->cooldownFor(now()->addMinutes(2)->toRfc2822String(), 1))->toBeGreaterThanOrEqual(119)
            ->and($gate->cooldownFor(null, 1))->toBeGreaterThanOrEqual(30);
    });

    it('shares a 429 Retry-After cooldown and uses it as the failed job delay', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array']);
        Cache::store('array')->flush();
        Setting::set('scrape_full_body', 'true');
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://example.com/story']);
        Http::fake(['*' => Http::response('', 429, ['Retry-After' => '42'])]);
        $job = new EnrichArticleJob($news->id);

        expect(fn () => $this->app->call([$job, 'handle']))
            ->toThrow(RuntimeException::class);

        expect($job->backoff())->toBe([42, 42, 42])
            ->and(app(PublisherRequestGate::class)->reserve('https://example.com/another')['allowed'])->toBeFalse();
    });

    it('releases the actual queue wrapper using a 503 Retry-After delay', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array']);
        Cache::store('array')->flush();
        Setting::set('scrape_full_body', 'true');
        $feed = Feed::factory()->create();
        $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://example.com/story']);
        Http::fake(['*' => Http::response('', 503, ['Retry-After' => '37'])]);
        $queueJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(37);
        $job = new EnrichArticleJob($news->id);
        $job->setJob($queueJob);

        $this->app->call([$job, 'handle']);
    });

    it('enforces publisher and global request intervals across sequential reservations', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array', 'feedarium.publisher_throttle.requests_per_minute' => 60, 'feedarium.publisher_throttle.global_requests_per_minute' => 600]);
        Cache::store('array')->flush();
        $gate = app(PublisherRequestGate::class);
        expect($gate->reserve('https://example.com/feed')['allowed'])->toBeTrue()
            ->and($gate->reserve('https://www.example.com/story')['allowed'])->toBeFalse();

        $gate->cooldown('https://example.com/feed', 30);
        expect($gate->reserve('https://example.com/another')['allowed'])->toBeFalse();
    });

    it('enforces the global interval across different publishers', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array', 'feedarium.publisher_throttle.requests_per_minute' => 60, 'feedarium.publisher_throttle.global_requests_per_minute' => 60]);
        Cache::store('array')->flush();
        $gate = app(PublisherRequestGate::class);

        expect($gate->reserve('https://first.example/feed')['allowed'])->toBeTrue()
            ->and($gate->reserve('https://second.example/feed'))->toMatchArray([
                'allowed' => false,
                'reason' => 'publisher_or_global_budget',
            ]);
    });

    it('waits through reservation lock contention before persisting a cooldown', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array', 'feedarium.publisher_throttle.lock_seconds' => 3]);
        $store = Cache::store('array')->getStore();
        $heldLock = $store->lock('feedarium:publisher-gate:reservation', 1);
        expect($heldLock->get())->toBeTrue();

        $gate = app(PublisherRequestGate::class);
        expect($gate->cooldown('https://example.com/story', 90))->toBeTrue()
            ->and($gate->reserve('https://example.com/another')['allowed'])->toBeFalse();
    });

    it('installs a fail-closed publisher barrier when the cooldown lock itself stays contended', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array', 'feedarium.publisher_throttle.lock_seconds' => 1]);
        Cache::store('array')->flush();
        $store = Cache::store('array')->getStore();
        $heldLock = $store->lock('feedarium:publisher:'.sha1('example.com').':cooldown-lock', 10);
        expect($heldLock->get())->toBeTrue();

        $gate = app(PublisherRequestGate::class);
        expect($gate->cooldown('https://example.com/story', 90))->toBeTrue()
            ->and($gate->reserve('https://example.com/another')['allowed'])->toBeFalse();
    });

    it('falls back to a barrier when a normal cooldown cache put returns false', function () {
        $this->app['cache']->extend('failing-cooldown-write', function () {
            return new \Illuminate\Cache\Repository(new class extends \Illuminate\Cache\ArrayStore
            {
                public function put($key, $value, $seconds): bool
                {
                    if (str_ends_with((string) $key, ':cooldown')) {
                        return false;
                    }

                    return parent::put($key, $value, $seconds);
                }
            });
        });
        config([
            'cache.stores.failing-cooldown-write' => ['driver' => 'failing-cooldown-write'],
            'feedarium.publisher_throttle.cache_store' => 'failing-cooldown-write',
        ]);
        Cache::store('failing-cooldown-write')->flush();
        $gate = app(PublisherRequestGate::class);

        expect($gate->cooldown('https://example.com/story', 90))->toBeTrue()
            ->and($gate->reserve('https://example.com/another'))->toMatchArray([
                'allowed' => false,
                'reason' => 'publisher_or_global_budget',
            ]);
    });

    it('uses an independent barrier bucket when a shorter legacy barrier cannot be extended', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array', 'feedarium.publisher_throttle.lock_seconds' => 1]);
        Cache::store('array')->flush();
        $store = Cache::store('array')->getStore();
        $reservationKey = 'feedarium:publisher:'.sha1('example.com');
        $legacyBarrierKey = $reservationKey.':cooldown-barrier';
        $store->put($legacyBarrierKey, microtime(true) + 1, 60);
        $publisherLock = $store->lock($reservationKey.':cooldown-lock', 10);
        $extensionLock = $store->lock($legacyBarrierKey.':lock', 10);
        expect($publisherLock->get())->toBeTrue()->and($extensionLock->get())->toBeTrue();

        $gate = app(PublisherRequestGate::class);
        expect($gate->cooldown('https://example.com/story', 120))->toBeTrue()
            ->and($gate->cooldown('https://example.com/story', 20))->toBeTrue();
        $publisherLock->release();
        $extensionLock->release();

        $reservation = $gate->reserve('https://example.com/another');
        expect($reservation)->toMatchArray([
            'allowed' => false,
            'reason' => 'publisher_or_global_budget',
        ])->and($reservation['delay'])->toBeGreaterThanOrEqual(100)
            ->and($gate->reserve('https://different.example/feed')['allowed'])->toBeTrue();
    });

    it('keeps the longest active cooldown and sets cache expiry after it', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array']);
        Cache::store('array')->flush();
        $gate = app(PublisherRequestGate::class);
        $gate->cooldown('https://example.com/story', 120);
        $gate->cooldown('https://example.com/story', 20);

        $cooldownRecord = collect(Cache::store('array')->getStore()->all())
            ->first(fn (array $record, string $key): bool => str_ends_with($key, ':cooldown'));

        expect($cooldownRecord['value'])->toBeGreaterThan(microtime(true) + 100)
            ->and($cooldownRecord['expiresAt'])->toBeGreaterThan($cooldownRecord['value']);
    });

    it('charges a redirect destination for subsequent publisher requests', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array', 'feedarium.publisher_throttle.requests_per_minute' => 60, 'feedarium.publisher_throttle.global_requests_per_minute' => 600]);
        Cache::store('array')->flush();
        $gate = app(PublisherRequestGate::class);
        expect($gate->reserve('https://source.example/feed')['allowed'])->toBeTrue();

        $gate->recordRedirect('https://source.example/feed', 'https://destination.example/story');

        expect($gate->reserve('https://destination.example/another')['allowed'])->toBeFalse();
    });

    it('releases a queued job when its publisher is in cooldown', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array']);
        Cache::store('array')->flush();
        app(PublisherRequestGate::class)->cooldown('https://example.com/story', 90);
        $job = new class
        {
            public int $releasedFor = 0;

            public function publisherUrl(): string
            {
                return 'https://www.example.com/feed';
            }

            public function release(int $delay): void
            {
                $this->releasedFor = $delay;
            }
        };
        $ran = false;

        app(RateLimitPublisherRequests::class)->handle($job, function () use (&$ran): void {
            $ran = true;
        });

        expect($job->releasedFor)->toBeGreaterThanOrEqual(89)
            ->and($ran)->toBeFalse();
    });

    it('fails closed when a non-shared array cache is selected outside tests', function () {
        $this->app['env'] = 'production';
        config(['feedarium.publisher_throttle.cache_store' => 'array']);

        expect(app(PublisherRequestGate::class)->reserve('https://example.com/feed'))
            ->toMatchArray(['allowed' => false, 'reason' => 'cache_store_has_no_atomic_locks']);
    });

    it('falls back to the configured default cache store when no publisher store override is set', function () {
        config(['feedarium.publisher_throttle.cache_store' => null, 'cache.default' => 'array']);
        Cache::store('array')->flush();

        expect(app(PublisherRequestGate::class)->reserve('https://example.com/feed')['allowed'])->toBeTrue();
    });

    it('fails closed and logs actionable guidance for an unsupported store name', function () {
        \Illuminate\Support\Facades\Log::spy();
        config(['feedarium.publisher_throttle.cache_store' => 'missing-publisher-cache']);

        expect(app(PublisherRequestGate::class)->reserve('https://example.com/feed'))
            ->toMatchArray(['allowed' => false, 'reason' => 'cache_store_has_no_atomic_locks']);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'could not be initialized')
                && $context['recommended_setting'] === 'Check FEEDARIUM_PUBLISHER_CACHE_STORE and configure a shared atomic cache store.')
            ->once();
    });

    it('fails closed when the selected cache store cannot provide atomic locks', function () {
        config([
            'cache.stores.publisher-null' => ['driver' => 'null'],
            'feedarium.publisher_throttle.cache_store' => 'publisher-null',
        ]);

        expect(app(PublisherRequestGate::class)->reserve('https://example.com/feed'))
            ->toMatchArray(['allowed' => false, 'reason' => 'cache_store_has_no_atomic_locks']);
    });

    it('rejects Memcached coordination when Laravel hides a bulk-read error as cache misses', function () {
        if (! class_exists('Memcached')) {
            eval('class Memcached {
                public const GET_PRESERVE_ORDER = 1;
                public function getMulti($keys, &$cas = null, $flags = 0) { return false; }
                public function getResultCode() { return 35; }
            }');
        }

        $memcachedStore = new \Illuminate\Cache\MemcachedStore(new \Memcached, '');
        expect($memcachedStore->many(['fallback-barrier']))
            ->toBe(['fallback-barrier' => null]);

        $repository = new \Illuminate\Cache\Repository($memcachedStore);
        $cache = \Mockery::mock(\Illuminate\Contracts\Cache\Factory::class);
        $cache->shouldReceive('store')->with('memcached')->andReturn($repository);
        config(['feedarium.publisher_throttle.cache_store' => 'memcached']);
        \Illuminate\Support\Facades\Log::spy();
        $gate = new PublisherRequestGate(
            $cache,
            app(\Illuminate\Contracts\Config\Repository::class),
            app(\Illuminate\Contracts\Foundation\Application::class),
        );

        expect($gate->cooldown('https://example.com/story', 120))->toBeFalse()
            ->and($gate->reserve('https://example.com/story'))
            ->toMatchArray(['allowed' => false, 'reason' => 'cache_store_has_no_atomic_locks']);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'atomic shared cache store')
                && str_contains($context['reason'], 'all-null results'))
            ->twice();
    });

    it('fails an overlap-middleware job for unsupported stores instead of releasing it repeatedly', function () {
        config([
            'cache.stores.publisher-null' => ['driver' => 'null'],
            'feedarium.publisher_throttle.cache_store' => 'publisher-null',
        ]);
        $job = new class
        {
            public bool $failed = false;

            public function publisherWorkKey(): string
            {
                return 'test';
            }

            public function release(int $delay): void
            {
                throw new RuntimeException('Unsupported store must not be released');
            }

            public function fail(Throwable $exception): void
            {
                $this->failed = true;
            }
        };
        $ran = false;

        app(\App\Domains\News\Jobs\WithoutOverlappingPublisherWork::class)->handle($job, function () use (&$ran): void {
            $ran = true;
        });

        expect($job->failed)->toBeTrue()->and($ran)->toBeFalse();
    });

    it('fails rate-limit middleware for unsupported stores instead of retrying indefinitely', function () {
        config([
            'cache.stores.publisher-null' => ['driver' => 'null'],
            'feedarium.publisher_throttle.cache_store' => 'publisher-null',
        ]);
        $job = new class
        {
            public bool $failed = false;

            public function publisherUrl(): string
            {
                return 'https://example.com/feed';
            }

            public function release(int $delay): void
            {
                throw new RuntimeException('Unsupported store must not be released');
            }

            public function fail(Throwable $exception): void
            {
                $this->failed = true;
            }
        };
        $ran = false;

        app(RateLimitPublisherRequests::class)->handle($job, function () use (&$ran): void {
            $ran = true;
        });

        expect($job->failed)->toBeTrue()->and($ran)->toBeFalse();
    });

    it('releases overlap work for ordinary contention on a valid cache store', function () {
        config(['feedarium.publisher_throttle.cache_store' => 'array']);
        Cache::store('array')->flush();
        $key = 'feedarium:work:test';
        $lock = Cache::store('array')->getStore()->lock($key, 120);
        expect($lock->get())->toBeTrue();
        $job = new class
        {
            public int $releasedFor = 0;

            public function publisherWorkKey(): string
            {
                return 'test';
            }

            public function release(int $delay): void
            {
                $this->releasedFor = $delay;
            }
        };
        $ran = false;

        app(\App\Domains\News\Jobs\WithoutOverlappingPublisherWork::class)->handle($job, function () use (&$ran): void {
            $ran = true;
        });

        expect($job->releasedFor)->toBe(5)->and($ran)->toBeFalse();
    });

    it('keeps the serialized enrichment job payload independent of the injected gate', function () {
        $job = new EnrichArticleJob(456);
        $payload = serialize($job);
        $restored = unserialize($payload);

        expect($restored)->toBeInstanceOf(EnrichArticleJob::class)
            ->and($restored->newsId)->toBe(456)
            ->and($payload)->not->toContain('PublisherRequestGate');
    });
});
