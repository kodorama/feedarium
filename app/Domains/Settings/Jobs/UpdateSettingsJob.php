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
        Setting::set('news_retention_enabled', $this->request->boolean('news_retention_enabled') ? 'true' : 'false');
        Setting::set('news_retention_days', (string) $this->request->integer('news_retention_days'));
    }
}
