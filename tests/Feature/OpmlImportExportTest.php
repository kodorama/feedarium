<?php

use App\Models\Feed;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use App\Domains\Feed\Jobs\FetchFeedJob;
use App\Domains\Feed\Jobs\ImportOpmlJob;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('previews OPML without creating categories, feeds, jobs or making network requests', function () {
    Feed::factory()->create(['url' => 'https://existing.example/rss']);
    Queue::fake();
    Http::fake();

    $xml = <<<'XML'
    <opml version="2.0"><body>
      <outline text="Technology">
        <outline text="New Feed" xmlUrl="https://new.example/rss"/>
        <outline text="Existing Feed" xmlUrl="https://existing.example/rss"/>
        <outline text="Unsafe Feed" xmlUrl="http://127.0.0.1/private"/>
        <outline text="Repeated Feed" xmlUrl="https://new.example/rss"/>
      </outline>
    </body></opml>
    XML;

    $response = $this->post('/api/feeds/opml/preview', [
        'file' => UploadedFile::fake()->createWithContent('subscriptions.opml', $xml),
    ]);

    $response->assertOk()->assertJsonPath('summary.feed_count', 2)
        ->assertJsonPath('summary.new_feeds', 1)
        ->assertJsonPath('summary.already_subscribed', 1)
        ->assertJsonPath('summary.duplicates', 1)
        ->assertJsonPath('summary.invalid', 1)
        ->assertJsonPath('summary.unsupported', 0)
        ->assertJsonPath('summary.categories', 1);
    expect(Category::query()->count())->toBe(0)
        ->and(Feed::query()->count())->toBe(1);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('requires an admin for preview, import and export', function () {
    auth()->logout();
    $this->actingAs(User::factory()->create(['is_admin' => false]));
    $upload = UploadedFile::fake()->createWithContent('subscriptions.xml', '<opml><body/></opml>');

    $this->post('/api/feeds/opml/preview', ['file' => $upload])->assertForbidden();
    $this->post('/api/feeds/opml/import', ['file' => $upload])->assertForbidden();
    $this->get('/api/feeds/opml/export')->assertForbidden();
    $this->get('/settings/import-export')->assertForbidden();
});

it('returns import results and creates imported feeds before queued fetching', function () {
    Queue::fake();
    Feed::factory()->create(['url' => 'https://existing.example/rss']);
    $xml = <<<'XML'
    <opml version="2.0"><body>
      <outline text="News"><outline text="First" xmlUrl="https://one.example/rss"/></outline>
      <outline text="News"><outline text="Second" xmlUrl="https://two.example/rss"/></outline>
      <outline text="News"><outline text="Existing" xmlUrl="https://existing.example/rss"/></outline>
      <outline text="News"><outline text="Repeated" xmlUrl="https://one.example/rss"/></outline>
      <outline text="Invalid" xmlUrl="http://127.0.0.1/feed"/>
      <outline text="Unsupported" xmlUrl="ftp://example.com/feed"/>
    </body></opml>
    XML;

    $response = $this->post('/api/feeds/opml/import', [
        'file' => UploadedFile::fake()->createWithContent('subscriptions.opml', $xml),
    ]);

    $response->assertOk()
        ->assertJsonPath('summary.created', 2)
        ->assertJsonPath('summary.existing', 1)
        ->assertJsonPath('summary.categories_created', 1)
        ->assertJsonPath('summary.duplicates', 1)
        ->assertJsonPath('summary.invalid', 1)
        ->assertJsonPath('summary.unsupported', 1)
        ->assertJsonPath('summary.failures', 0);

    $one = Feed::query()->where('url', 'https://one.example/rss')->firstOrFail();
    $two = Feed::query()->where('url', 'https://two.example/rss')->firstOrFail();
    expect(Feed::query()->count())->toBe(3)
        ->and(Category::query()->where('name', 'News')->count())->toBe(1);
});

it('reports a per-feed creation failure while importing other valid entries', function () {
    Bus::fake();
    Event::listen('eloquent.creating: '.Feed::class, function (Feed $feed): void {
        if ($feed->url === 'https://failure.example/rss') {
            throw new RuntimeException('Synthetic feed insert failure.');
        }
    });

    try {
        $result = (new ImportOpmlJob([
            ['name' => 'Failed', 'url' => 'https://failure.example/rss', 'description' => null, 'category' => 'Tech'],
            ['name' => 'Created', 'url' => 'https://created.example/rss', 'description' => null, 'category' => 'Tech'],
        ]))->handle();
    } finally {
        Event::forget('eloquent.creating: '.Feed::class);
    }

    expect($result['created'])->toBe(1)
        ->and($result['categories_created'])->toBe(1)
        ->and($result['failures'])->toBe(1)
        ->and(Feed::query()->where('url', 'https://created.example/rss')->exists())->toBeTrue()
        ->and(Feed::query()->where('url', 'https://failure.example/rss')->exists())->toBeFalse();
});

it('accepts only OPML and XML filenames for preview and import', function () {
    foreach (['subscriptions.opml', 'subscriptions.xml'] as $filename) {
        $this->post('/api/feeds/opml/preview', [
            'file' => UploadedFile::fake()->createWithContent($filename, '<opml><body/></opml>'),
        ])->assertOk();
    }

    $this->withHeaders(['Accept' => 'application/json'])->post('/api/feeds/opml/preview', [
        'file' => UploadedFile::fake()->createWithContent('subscriptions.html', '<opml><body/></opml>'),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');

    $this->withHeaders(['Accept' => 'application/json'])->post('/api/feeds/opml/import', [
        'file' => UploadedFile::fake()->createWithContent('subscriptions.txt', '<opml><body/></opml>'),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('creates categories and feeds idempotently, then chains only one initial fetch per new feed', function () {
    Bus::fake();
    $entries = [
        ['name' => 'One', 'url' => 'https://one.example/rss', 'description' => null, 'category' => 'News / Tech'],
        ['name' => 'Two', 'url' => 'https://two.example/rss', 'description' => 'Summary', 'category' => 'News / Tech'],
    ];

    (new ImportOpmlJob($entries))->handle();

    $one = Feed::query()->where('url', 'https://one.example/rss')->firstOrFail();
    $two = Feed::query()->where('url', 'https://two.example/rss')->firstOrFail();
    expect(Category::query()->where('name', 'News / Tech')->count())->toBe(1)
        ->and($one->category_id)->toBe($two->category_id)
        ->and($one->active)->toBeTrue()
        ->and($one->favicon_url)->toContain('one.example');

    Bus::assertChained([
        new FetchFeedJob($one->id),
        new FetchFeedJob($two->id),
    ]);

    (new ImportOpmlJob($entries))->handle();
    expect(Feed::query()->count())->toBe(2)
        ->and(Category::query()->count())->toBe(1);
    Bus::assertDispatchedTimes(FetchFeedJob::class, 1);
});

it('exports valid OPML 2.0 that round-trips feed names, urls, descriptions and flat categories', function () {
    $category = Category::query()->create(['name' => 'News / Technology']);
    Feed::factory()->create([
        'name' => 'Example & News',
        'url' => 'https://example.com/rss?a=1&b=2',
        'description' => 'A <useful> feed',
        'category_id' => $category->id,
    ]);
    Feed::factory()->create([
        'name' => 'Uncategorized',
        'url' => 'https://plain.example/rss',
        'description' => null,
        'category_id' => null,
    ]);

    $response = $this->get('/api/feeds/opml/export')->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename="feedarium-subscriptions.opml"');

    $parsed = app(\App\Domains\Feed\Support\OpmlParser::class)->parse($response->getContent());
    expect($parsed['feeds'])->toContain([
        'name' => 'Example & News',
        'url' => 'https://example.com/rss?a=1&b=2',
        'description' => 'A <useful> feed',
        'category' => 'News / Technology',
    ])->toContain([
        'name' => 'Uncategorized',
        'url' => 'https://plain.example/rss',
        'description' => null,
        'category' => null,
    ]);
});

it('rejects malformed OPML uploads without dispatching an import', function () {
    Queue::fake();

    $this->withHeaders(['Accept' => 'application/json'])->post('/api/feeds/opml/preview', [
        'file' => UploadedFile::fake()->createWithContent('subscriptions.xml', '<opml><body>'),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');

    Queue::assertNothingPushed();
});
