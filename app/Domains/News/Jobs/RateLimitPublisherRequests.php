<?php

namespace App\Domains\News\Jobs;

use RuntimeException;
use Illuminate\Support\Facades\Log;
use App\Support\OutboundHttp\PublisherRedirectContinuationAware;
use App\Support\OutboundHttp\PublisherRedirectContinuationStore;

/** Releases queue work until a shared publisher/global request slot is available. */
final class RateLimitPublisherRequests
{
    public function __construct(
        private readonly PublisherRequestGate $gate,
        private readonly PublisherRedirectContinuationStore $continuations,
    ) {}

    public function handle(object $job, \Closure $next): void
    {
        if (! method_exists($job, 'publisherUrl')) {
            $job->release(30);

            return;
        }

        if ($job instanceof PublisherRedirectContinuationAware) {
            $job->restorePublisherRedirectContinuation($this->continuations);
        }

        $reservation = $this->gate->reserve($job->publisherUrl());
        if (! $reservation['allowed']) {
            Log::info('Publisher HTTP request delayed', [
                'job' => $job::class,
                'delay_seconds' => $reservation['delay'],
                'reason' => $reservation['reason'],
            ]);
            if (in_array($reservation['reason'], ['cache_store_has_no_atomic_locks', 'cache_store_unavailable'], true)) {
                $exception = new RuntimeException('Publisher request reservation failed because the configured cache store is unavailable or does not support atomic locks.');
                if (method_exists($job, 'fail')) {
                    $job->fail($exception);

                    return;
                }

                throw $exception;
            }

            $job->release($reservation['delay']);

            return;
        }

        $next($job);
    }
}
