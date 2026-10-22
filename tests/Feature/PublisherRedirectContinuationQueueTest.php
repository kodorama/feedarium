<?php

use App\Models\Feed;
use App\Models\News;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Domains\Feed\Jobs\FetchFeedJob;
use Tests\Support\QueuePayloadRehydrator;
use App\Domains\News\Jobs\EnrichArticleJob;
use App\Domains\Feed\Jobs\SubscribeToHubJob;
use App\Domains\Feed\Jobs\ImportFeedItemsJob;
use App\Domains\News\Jobs\RateLimitPublisherRequests;
use App\Support\OutboundHttp\PublisherRedirectContinuationStore;

function runPublisherRedirectAttempt(object $job): void
{
    app(RateLimitPublisherRequests::class)->handle($job, static function (object $job): void {
        app()->call([$job, 'handle']);
    });
}

function expirePublisherGlobalBudget(?string $publisherHost = null): void
{
    Cache::store('array')->put('feedarium:publisher:global', microtime(true) - 1, 120);
    if ($publisherHost !== null) {
        Cache::store('array')->put('feedarium:publisher:'.sha1($publisherHost), microtime(true) - 1, 120);
    }
}

function continuationCacheEntries(): array
{
    return array_filter(
        Cache::store('array')->getStore()->all(),
        static fn (array $entry, string $key): bool => str_starts_with($key, 'feedarium:outbound-redirect:'),
        ARRAY_FILTER_USE_BOTH,
    );
}

beforeEach(function () {
    config([
        'feedarium.publisher_throttle.cache_store' => 'array',
        'feedarium.publisher_throttle.requests_per_minute' => 60,
        'feedarium.publisher_throttle.global_requests_per_minute' => 600,
    ]);
    Cache::store('array')->flush();
    Queue::fake();
});

it('rehydrates FetchFeedJob from its original encrypted payload across repeated gate deferrals', function () {
    $feed = Feed::factory()->create(['active' => true, 'url' => 'https://source.example/feed.xml']);
    $firstTarget = 'https://redirect-one.test/feed.xml';
    $secondTarget = 'https://redirect-two.test/feed.xml';
    Http::fakeSequence()
        ->push('', 302, ['Location' => $firstTarget])
        ->push('', 302, ['Location' => $secondTarget])
        ->push('<rss></rss>', 200);

    $payload = QueuePayloadRehydrator::capture(new FetchFeedJob($feed->id));
    expect($payload['data']['command'])->not->toContain('source.example');

    $firstAttempt = QueuePayloadRehydrator::fresh($payload, true);
    runPublisherRedirectAttempt($firstAttempt);
    expect($firstAttempt->continuationUrl)->toBe($firstTarget)
        ->and(continuationCacheEntries())->toHaveCount(1);

    expirePublisherGlobalBudget();
    $secondAttempt = QueuePayloadRehydrator::fresh($payload, true);
    runPublisherRedirectAttempt($secondAttempt);
    expect($secondAttempt->continuationUrl)->toBe($secondTarget)
        ->and($secondAttempt->continuationRedirects)->toBe(2)
        ->and($secondAttempt->continuationVisited)->toBe([
            'https://source.example/feed.xml',
            $firstTarget,
            $secondTarget,
        ]);

    expirePublisherGlobalBudget();
    $thirdAttempt = QueuePayloadRehydrator::fresh($payload);
    runPublisherRedirectAttempt($thirdAttempt);

    Http::assertSentInOrder([
        fn ($request) => $request->url() === $feed->url,
        fn ($request) => $request->url() === $firstTarget,
        fn ($request) => $request->url() === $secondTarget,
    ]);
    Queue::assertPushedTimes(ImportFeedItemsJob::class, 1);
    Queue::assertPushed(ImportFeedItemsJob::class, fn ($job) => $job->feedId === $feed->id);
    expect(continuationCacheEntries())->toBeEmpty();
});

it('rehydrates EnrichArticleJob from its original payload and clears continuation on terminal failure', function () {
    Setting::set('scrape_full_body', 'true');
    $feed = Feed::factory()->create();
    $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://article.example/story']);
    Http::fakeSequence()->push('', 302, ['Location' => 'https://article-redirect.test/story']);
    $payload = QueuePayloadRehydrator::capture(new EnrichArticleJob($news->id));

    $firstAttempt = QueuePayloadRehydrator::fresh($payload, true);
    runPublisherRedirectAttempt($firstAttempt);
    expect(continuationCacheEntries())->toHaveCount(1);

    $terminalAttempt = QueuePayloadRehydrator::fresh($payload);
    $terminalAttempt->failed(new RuntimeException('exhausted'));
    $rehydratedAfterFailure = QueuePayloadRehydrator::fresh($payload);
    $rehydratedAfterFailure->restorePublisherRedirectContinuation(
        app(PublisherRedirectContinuationStore::class),
        $payload['uuid'],
    );

    expect($rehydratedAfterFailure->continuationUrl)->toBeNull()
        ->and(continuationCacheEntries())->toBeEmpty();
});

it('rehydrates EnrichArticleJob across repeated gate deferrals and completes once', function () {
    $feed = Feed::factory()->create();
    $news = News::factory()->create(['feed_id' => $feed->id, 'link' => 'https://article.example/story']);
    $firstTarget = 'https://article-one.test/story';
    $secondTarget = 'https://article-two.test/story';
    Http::fakeSequence()
        ->push('', 302, ['Location' => $firstTarget])
        ->push('', 302, ['Location' => $secondTarget])
        ->push('<meta property="og:image" content="https://images.example/card.jpg">', 200);
    $payload = QueuePayloadRehydrator::capture(new EnrichArticleJob($news->id));

    runPublisherRedirectAttempt(QueuePayloadRehydrator::fresh($payload, true));
    expirePublisherGlobalBudget();
    runPublisherRedirectAttempt(QueuePayloadRehydrator::fresh($payload, true));
    expirePublisherGlobalBudget();
    runPublisherRedirectAttempt(QueuePayloadRehydrator::fresh($payload));

    Http::assertSentInOrder([
        fn ($request) => $request->url() === $news->link,
        fn ($request) => $request->url() === $firstTarget,
        fn ($request) => $request->url() === $secondTarget,
    ]);
    expect($news->fresh()->thumbnail_url)->toBe('https://images.example/card.jpg')
        ->and(continuationCacheEntries())->toBeEmpty();
});

it('rehydrates encrypted WebSub secret and method state across repeated POST redirect deferrals', function () {
    $feed = Feed::factory()->create([
        'url' => 'https://topic.example/feed.xml',
        'hub_url' => 'https://hub.example/subscribe',
    ]);
    $firstTarget = 'https://hub.example/step-one';
    $secondTarget = 'https://hub.example/step-two';
    Http::fakeSequence()
        ->push('', 307, ['Location' => $firstTarget])
        ->push('', 307, ['Location' => $secondTarget])
        ->push('', 202);
    $payload = QueuePayloadRehydrator::capture(new SubscribeToHubJob($feed->id));

    runPublisherRedirectAttempt(QueuePayloadRehydrator::fresh($payload, true));
    $cached = continuationCacheEntries();
    expect($cached)->toHaveCount(1);
    $ciphertext = array_values($cached)[0]['value'];
    expect($ciphertext)->not->toContain('hub.secret');

    expirePublisherGlobalBudget('hub.example');
    $secondAttempt = QueuePayloadRehydrator::fresh($payload, true);
    runPublisherRedirectAttempt($secondAttempt);
    expect($secondAttempt->continuationMethod)->toBe('POST')
        ->and($secondAttempt->continuationRedirects)->toBe(2)
        ->and($secondAttempt->continuationSecret)->toHaveLength(40);
    $cached = continuationCacheEntries();
    expect(array_values($cached)[0]['value'])->not->toContain($secondAttempt->continuationSecret);

    expirePublisherGlobalBudget('hub.example');
    runPublisherRedirectAttempt(QueuePayloadRehydrator::fresh($payload));

    Http::assertSentInOrder([
        fn ($request) => $request->url() === $feed->hub_url && $request->method() === 'POST',
        fn ($request) => $request->url() === $firstTarget && $request->method() === 'POST',
        fn ($request) => $request->url() === $secondTarget && $request->method() === 'POST',
    ]);
    Http::assertSentCount(3);
    $secrets = Http::recorded()->map(fn ($pair) => $pair[0]->data()['hub.secret'])->unique();
    expect($secrets)->toHaveCount(1)
        ->and($feed->fresh()->websub_secret)->toBe($secrets->first())
        ->and($feed->fresh()->websub_subscribed_at)->not->toBeNull()
        ->and(continuationCacheEntries())->toBeEmpty();
});

it('clears deferred WebSub continuation state after terminal failure', function () {
    $feed = Feed::factory()->create(['hub_url' => 'https://hub.example/subscribe']);
    Http::fakeSequence()->push('', 307, ['Location' => 'https://hub.example/step-one']);
    $payload = QueuePayloadRehydrator::capture(new SubscribeToHubJob($feed->id));

    runPublisherRedirectAttempt(QueuePayloadRehydrator::fresh($payload, true));
    $terminalAttempt = QueuePayloadRehydrator::fresh($payload);
    $terminalAttempt->failed(new RuntimeException('exhausted'));

    expect(continuationCacheEntries())->toBeEmpty();
});

it('clears deferred feed continuation state after terminal failure', function () {
    $feed = Feed::factory()->create(['active' => true, 'url' => 'https://source.example/feed.xml']);
    Http::fakeSequence()->push('', 302, ['Location' => 'https://redirect.test/feed.xml']);
    $payload = QueuePayloadRehydrator::capture(new FetchFeedJob($feed->id));

    runPublisherRedirectAttempt(QueuePayloadRehydrator::fresh($payload, true));
    $terminalAttempt = QueuePayloadRehydrator::fresh($payload);
    $terminalAttempt->failed(new RuntimeException('exhausted'));

    expect(continuationCacheEntries())->toBeEmpty();
});
