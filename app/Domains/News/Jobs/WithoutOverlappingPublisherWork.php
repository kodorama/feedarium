<?php

namespace App\Domains\News\Jobs;

use RuntimeException;
use Illuminate\Support\Facades\Log;

/** Holds work locks in the same shared cache store used by publisher throttling. */
final class WithoutOverlappingPublisherWork
{
    public function __construct(
        private readonly PublisherRequestGate $gate,
        private readonly int $releaseAfter = 5,
        private readonly int $expiresAfter = 120,
    ) {}

    public function handle(object $job, \Closure $next): void
    {
        if (! method_exists($job, 'publisherWorkKey')) {
            Log::warning('Queued publisher work withheld because its overlap key is unavailable', [
                'job' => $job::class,
            ]);
            $job->release($this->releaseAfter);

            return;
        }

        $expiresAfter = method_exists($job, 'publisherWorkLockExpiresAfter')
            ? max(1, $job->publisherWorkLockExpiresAfter())
            : $this->expiresAfter;
        $lockKey = 'feedarium:work:'.$job->publisherWorkKey();
        $locked = $this->gate->withLock($lockKey, $expiresAfter, fn () => $next($job));
        if ($locked === null) {
            $this->failUnavailableStore($job);

            return;
        }

        if ($locked === false) {
            $job->release($this->releaseAfter);

            return;
        }
    }

    private function failUnavailableStore(object $job): void
    {
        Log::error('Queued publisher work failed because its configured cache store cannot provide atomic locks', [
            'job' => $job::class,
        ]);
        $exception = new RuntimeException('Publisher work requires a supported atomic cache store; configure FEEDARIUM_PUBLISHER_CACHE_STORE.');
        if (method_exists($job, 'fail')) {
            $job->fail($exception);

            return;
        }

        throw $exception;
    }
}
