<?php

namespace App\Domains\Settings\Jobs;

use App\Models\Setting;

final class GetSettingsJob
{
    /**
     * @return array{news_retention_enabled: bool, news_retention_days: int, scrape_full_body: bool}
     */
    public function handle(): array
    {
        return [
            'news_retention_enabled' => Setting::get('news_retention_enabled', 'true') === 'true',
            'news_retention_days' => (int) Setting::get('news_retention_days', '90'),
            'scrape_full_body' => Setting::get('scrape_full_body', 'false') === 'true',
        ];
    }
}
