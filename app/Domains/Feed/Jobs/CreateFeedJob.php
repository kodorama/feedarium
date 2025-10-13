<?php

namespace App\Domains\Feed\Jobs;

use App\Models\Feed;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateFeedJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CreateFeedRequest $request): Feed
    {
        return Feed::create($request->validated());
    }
}
