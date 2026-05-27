<?php

namespace App\Domains\Settings\Jobs;

use App\Models\Setting;
use App\Domains\Settings\Support\LocaleDetector;

final class GetSettingsJob
{
    /**
     * @return array{
     *     news_retention_enabled: bool,
     *     news_retention_days: int,
     *     scrape_full_body: bool,
     *     locale: string,
     *     available_locales: list<array{code: string, name: string, native: string, rtl: bool}>,
     * }
     */
    public function handle(): array
    {
        $storedLocale = Setting::get('locale');

        return [
            'news_retention_enabled' => Setting::get('news_retention_enabled', 'true') === 'true',
            'news_retention_days' => (int) Setting::get('news_retention_days', '90'),
            'scrape_full_body' => Setting::get('scrape_full_body', 'false') === 'true',
            'locale' => LocaleDetector::resolve($storedLocale),
            'available_locales' => LocaleDetector::availableWithMetadata()->values()->all(),
        ];
    }
}
