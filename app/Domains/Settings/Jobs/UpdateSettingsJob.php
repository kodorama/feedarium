<?php

namespace App\Domains\Settings\Jobs;

use App\Models\Setting;
use App\Domains\Settings\Requests\UpdateSettingsRequest;

final class UpdateSettingsJob
{
    public function __construct(
        private readonly UpdateSettingsRequest $request,
    ) {}

    public function handle(): void
    {
        if ($this->request->has('news_retention_enabled')) {
            Setting::set('news_retention_enabled', $this->request->boolean('news_retention_enabled') ? 'true' : 'false');
        }

        if ($this->request->has('news_retention_days')) {
            Setting::set('news_retention_days', (string) $this->request->integer('news_retention_days'));
        }

        if ($this->request->has('scrape_full_body')) {
            Setting::set('scrape_full_body', $this->request->boolean('scrape_full_body') ? 'true' : 'false');
        }
    }
}
