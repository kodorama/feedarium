<?php

namespace App\Support\OutboundHttp;

use Illuminate\Support\Str;

trait HasPublisherRedirectContinuation
{
    public ?string $continuationUrl = null;

    public ?string $continuationMethod = null;

    public ?string $continuationSecret = null;

    public int $continuationRedirects = 0;

    /** @var list<string> */
    public array $continuationVisited = [];

    private ?string $continuationIdentity = null;

    private ?int $continuationExpiresAt = null;

    public function restorePublisherRedirectContinuation(PublisherRedirectContinuationStore $store, ?string $queueId = null): void
    {
        $this->continuationIdentity = $queueId ?? $this->currentQueueId() ?? $this->continuationIdentity ?? (string) Str::uuid();
        $this->continuationExpiresAt ??= $this->queueExpiry() ?? now()->addHours((int) config('feedarium.publisher_throttle.retry_window_hours', 6))->timestamp;
        $this->continuationUrl = null;
        $this->continuationMethod = null;
        $this->continuationSecret = null;
        $this->continuationRedirects = 0;
        $this->continuationVisited = [];
        $state = $store->get($this->continuationIdentity, $this->continuationExpiresAt);
        if ($state === null) {
            return;
        }

        $this->continuationUrl = $state['url'];
        $this->continuationMethod = $state['method'];
        $this->continuationSecret = $state['secret'];
        $this->continuationRedirects = $state['redirects'];
        $this->continuationVisited = $state['visited'];
    }

    private function savePublisherRedirectContinuation(
        PublisherRedirectContinuationStore $store,
        string $url,
        string $method,
        int $redirects,
        array $visited,
        ?string $secret = null,
    ): void {
        $this->continuationUrl = $url;
        $this->continuationMethod = $method;
        $this->continuationSecret = $secret;
        $this->continuationRedirects = $redirects;
        $this->continuationVisited = $visited;
        $this->continuationIdentity = $this->currentQueueId() ?? $this->continuationIdentity ?? (string) Str::uuid();
        $this->continuationExpiresAt ??= $this->queueExpiry() ?? now()->addHours((int) config('feedarium.publisher_throttle.retry_window_hours', 6))->timestamp;

        $store->put($this->continuationIdentity, $this->continuationExpiresAt, [
            'url' => $url,
            'method' => $method,
            'secret' => $secret,
            'redirects' => $redirects,
            'visited' => array_values($visited),
        ]);
    }

    private function clearPublisherRedirectContinuation(PublisherRedirectContinuationStore $store): void
    {
        if ($this->continuationIdentity !== null) {
            $store->forget($this->continuationIdentity);
        }

        $this->continuationUrl = null;
        $this->continuationMethod = null;
        $this->continuationSecret = null;
        $this->continuationRedirects = 0;
        $this->continuationVisited = [];
    }

    private function publisherRedirectIdentity(): string
    {
        return $this->continuationIdentity = $this->currentQueueId() ?? $this->continuationIdentity ?? (string) Str::uuid();
    }

    private function publisherRedirectExpiry(): int
    {
        return $this->continuationExpiresAt ??= $this->queueExpiry() ?? now()->addHours((int) config('feedarium.publisher_throttle.retry_window_hours', 6))->timestamp;
    }

    private function queueExpiry(): ?int
    {
        if (! isset($this->job) || ! method_exists($this->job, 'payload')) {
            return null;
        }

        try {
            $retryUntil = $this->job->payload()['retryUntil'] ?? null;
        } catch (\Throwable) {
            return null;
        }

        return is_numeric($retryUntil) ? (int) $retryUntil : null;
    }

    private function currentQueueId(): ?string
    {
        if (! isset($this->job) || ! method_exists($this->job, 'getJobId')) {
            return null;
        }

        try {
            $id = $this->job->getJobId();

            return is_string($id) || is_int($id) ? (string) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
