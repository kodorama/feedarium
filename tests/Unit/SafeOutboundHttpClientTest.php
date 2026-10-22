<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;
use App\Support\OutboundHttp\OutboundHostResolver;
use App\Support\OutboundHttp\LimitedResponseStream;
use App\Support\OutboundHttp\SafeOutboundHttpClient;
use App\Support\OutboundHttp\OutboundRequestRejected;
use App\Support\OutboundHttp\CurlOutboundHttpHandlerFactory;
use App\Support\OutboundHttp\SystemOutboundPeerAddressVerifier;

uses(Tests\TestCase::class);

beforeEach(function () {
    $resolver = new \Tests\Support\FakeOutboundHostResolver;
    app()->instance(OutboundHostResolver::class, $resolver);
});

it('accepts public HTTP destinations and returns the bounded body', function () {
    Http::fake(['*' => Http::response('feed body', 200)]);

    $response = app(SafeOutboundHttpClient::class)->get('https://feeds.example/rss', ['Accept' => 'application/rss+xml']);

    expect($response->successful())->toBeTrue()
        ->and($response->body())->toBe('feed body')
        ->and($response->effectiveUrl)->toBe('https://feeds.example/rss');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://feeds.example/rss');
});

it('accepts a public IPv6 literal while validating it directly', function () {
    Http::fake(['*' => Http::response('feed body', 200)]);

    $response = app(SafeOutboundHttpClient::class)->get('https://[2606:4700:4700::1111]/rss');

    expect($response->successful())->toBeTrue()
        ->and($response->effectiveUrl)->toBe('https://[2606:4700:4700::1111]/rss');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://[2606:4700:4700::1111]/rss');
});

it('fails closed when even one DNS answer is non-public', function () {
    $resolver = app(OutboundHostResolver::class);
    $resolver->answers['mixed.example'] = ['93.184.216.34', '127.0.0.1'];
    Http::fake();

    expect(fn () => app(SafeOutboundHttpClient::class)->get('https://mixed.example/feed'))
        ->toThrow(OutboundRequestRejected::class);
    Http::assertNothingSent();
});

it('rejects private mapped and ambiguous numeric IP addresses before sending', function () {
    Http::fake();
    $client = app(SafeOutboundHttpClient::class);

    foreach ([
        'http://127.0.0.1/feed',
        'http://169.254.169.254/latest/meta-data',
        'http://192.0.2.4/feed',
        'http://[::ffff:127.0.0.1]/feed',
        'http://[2001:db8::1]/feed',
        'http://[3fff::1]/feed',
        'http://2130706433/feed',
        'https://user:pass@example.com/feed',
        'https://@example.com/feed',
        'https://example.com:65536/feed',
    ] as $url) {
        expect(fn () => $client->get($url))->toThrow(OutboundRequestRejected::class);
    }
    Http::assertNothingSent();
});

it('configures cURL to pin the destination and bypass proxies', function () {
    $client = app(SafeOutboundHttpClient::class);
    $method = new ReflectionMethod($client, 'pinnedCurlOptions');
    $options = $method->invoke($client, 'feed.example', 443, '93.184.216.34');

    expect($options[CURLOPT_RESOLVE])->toBe(['feed.example:443:93.184.216.34'])
        ->and($options[CURLOPT_PROXY])->toBe('')
        ->and($options[CURLOPT_NOPROXY])->toBe('*')
        ->and($options[CURLOPT_SSL_VERIFYPEER])->toBeTrue()
        ->and($options[CURLOPT_SSL_VERIFYHOST])->toBe(2);
});

it('forces the cURL handler and disables inherited stream mode', function () {
    $client = new SafeOutboundHttpClient(
        app(OutboundHostResolver::class),
        new SystemOutboundPeerAddressVerifier,
        new CurlOutboundHttpHandlerFactory,
    );
    $method = new ReflectionMethod($client, 'requestOptions');
    $peer = null;
    $options = $method->invokeArgs($client, ['feed.example', 443, '93.184.216.34', new LimitedResponseStream(512), &$peer]);

    expect($options['handler'])->toBeInstanceOf(GuzzleHttp\Handler\CurlHandler::class)
        ->and($options['stream'])->toBeFalse()
        ->and($options['proxy'])->toBe('')
        ->and($options['curl'][CURLOPT_RESOLVE])->toBe(['feed.example:443:93.184.216.34']);
});

it('fails closed when cURL peer verification is missing or mismatched', function () {
    $verifier = new SystemOutboundPeerAddressVerifier;

    expect(fn () => $verifier->assertPinned(null, '93.184.216.34'))
        ->toThrow(OutboundRequestRejected::class)
        ->and(fn () => $verifier->assertPinned('127.0.0.1', '93.184.216.34'))
        ->toThrow(OutboundRequestRejected::class);
    $verifier->assertPinned('93.184.216.34', '93.184.216.34');
});

it('manually follows a public redirect after validating the next destination', function () {
    Http::fakeSequence()
        ->push('', 302, ['Location' => 'https://next.example/feed'])
        ->push('feed body', 200);

    $response = app(SafeOutboundHttpClient::class)->get('https://first.example/feed');

    expect($response->effectiveUrl)->toBe('https://next.example/feed')
        ->and($response->body())->toBe('feed body');
    Http::assertSentCount(2);
});

it('detects redirect loops across a resumed request', function () {
    Http::fakeSequence()
        ->push('', 302, ['Location' => 'https://second.example/feed'])
        ->push('', 302, ['Location' => 'https://first.example/feed']);

    try {
        app(SafeOutboundHttpClient::class)->get('https://first.example/feed');
        $this->fail('Expected a redirect loop to be rejected.');
    } catch (OutboundRequestRejected $exception) {
        expect($exception->reason)->toBe('redirect_loop');
    }
    Http::assertSentCount(2);
});

it('enforces the cumulative redirect cap across resumed requests', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://sixth.example/feed'])]);
    try {
        app(SafeOutboundHttpClient::class)->get(
            'https://fifth.example/feed',
            [],
            10_485_760,
            null,
            5,
            [
                'https://first.example/feed',
                'https://second.example/feed',
                'https://third.example/feed',
                'https://fourth.example/feed',
                'https://fifth.example/feed',
            ],
        );
        $this->fail('Expected the cumulative redirect cap to be enforced.');
    } catch (OutboundRequestRejected $exception) {
        expect($exception->reason)->toBe('redirect_limit_or_location_invalid');
    }
    Http::assertSentCount(1);
});

it('rejects unsafe redirect destinations and oversized responses', function () {
    Http::fakeSequence()->push('', 302, ['Location' => 'http://127.0.0.1/admin']);
    expect(fn () => app(SafeOutboundHttpClient::class)->get('https://first.example/feed'))
        ->toThrow(OutboundRequestRejected::class);
    Http::assertSentCount(1);
});

it('rejects oversized fake transport bodies', function () {
    Http::fake(['*' => Http::response(str_repeat('x', 32), 200)]);
    expect(fn () => app(SafeOutboundHttpClient::class)->get('https://first.example/feed', [], 16))
        ->toThrow(OutboundRequestRejected::class);
});

it('rejects oversized bodies even on failed HTTP statuses', function () {
    Http::fake(['*' => Http::response(str_repeat('x', 32), 500)]);

    expect(fn () => app(SafeOutboundHttpClient::class)->get('https://first.example/feed', [], 16))
        ->toThrow(OutboundRequestRejected::class);
});

it('redacts partial transport failures and drops exception chains', function () {
    Http::fake(['*' => static fn () => throw new ConnectionException('sensitive response fragment and query token')]);

    try {
        app(SafeOutboundHttpClient::class)->get('https://first.example/feed');
        $this->fail('Expected a sanitized connection exception.');
    } catch (ConnectionException $exception) {
        expect($exception->getMessage())->toBe('Outbound HTTP request failed.')
            ->and($exception->getPrevious())->toBeNull();
    }
});

it('normalizes response-bearing request exceptions without retaining their body', function () {
    $failedResponse = new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(500, [], 'private error body and token'));
    Http::fake(['*' => static fn () => throw new RequestException($failedResponse)]);

    try {
        app(SafeOutboundHttpClient::class)->get('https://first.example/feed');
        $this->fail('Expected a sanitized transport exception.');
    } catch (ConnectionException $exception) {
        expect($exception->getMessage())->toBe('Outbound HTTP request failed.')
            ->and($exception->getPrevious())->toBeNull();
    }
});

it('does not forward WebSub form secrets to another origin', function () {
    Http::fakeSequence()->push('', 307, ['Location' => 'https://other.example/subscribe']);

    expect(fn () => app(SafeOutboundHttpClient::class)->postForm('https://hub.example/subscribe', ['hub.secret' => 'never-log-this']))
        ->toThrow(OutboundRequestRejected::class);
    Http::assertSentCount(1);
});
