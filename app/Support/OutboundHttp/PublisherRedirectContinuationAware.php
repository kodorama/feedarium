<?php

namespace App\Support\OutboundHttp;

interface PublisherRedirectContinuationAware
{
    public function restorePublisherRedirectContinuation(PublisherRedirectContinuationStore $store, ?string $queueId = null): void;
}
