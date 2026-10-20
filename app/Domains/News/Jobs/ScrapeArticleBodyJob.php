<?php

namespace App\Domains\News\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** @deprecated Kept to safely hand jobs already in the queue to the unified pipeline. */
final class ScrapeArticleBodyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $newsId, public readonly string $link = '') {}

    public function handle(): void
    {
        EnrichArticleJob::dispatch($this->newsId);
    }
}
