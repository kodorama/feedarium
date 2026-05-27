<?php

namespace App\Domains\Settings\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

final class LocaleDetector
{
    /**
     * Return locales available on both the backend (lang/) and frontend (i18n/locales/).
     *
     * @return Collection<int, string>
     */
    public static function available(): Collection
    {
        $backendLocales = collect(File::directories(lang_path()))
            ->map(fn (string $path): string => basename($path));

        $frontendLocales = collect(File::files(resource_path('js/i18n/locales')))
            ->filter(fn (\SplFileInfo $file): bool => $file->getExtension() === 'json')
            ->map(fn (\SplFileInfo $file): string => $file->getFilenameWithoutExtension());

        return $backendLocales
            ->intersect($frontendLocales)
            ->values()
            ->sort()
            ->values();
    }

    /**
     * Return available locales with metadata from config/locales.php.
     *
     * @return Collection<int, array{code: string, name: string, native: string, rtl: bool}>
     */
    public static function availableWithMetadata(): Collection
    {
        $metadata = config('locales.metadata', []);

        return self::available()->map(function (string $code) use ($metadata): array {
            $meta = $metadata[$code] ?? [];

            return [
                'code' => $code,
                'name' => $meta['name'] ?? $code,
                'native' => $meta['native'] ?? $code,
                'rtl' => $meta['rtl'] ?? false,
            ];
        });
    }

    /**
     * Resolve the active locale with the priority:
     * 1. stored instance locale (Setting model)
     * 2. config('app.locale')
     * 3. config('locales.fallback') → 'en'
     *
     * If the resolved locale is not in the available list, fallback to 'en'.
     */
    public static function resolve(?string $stored = null): string
    {
        $candidate = $stored
            ?? config('app.locale', 'en');

        $available = self::available();

        if ($available->contains($candidate)) {
            return $candidate;
        }

        $fallback = (string) config('locales.fallback', 'en');

        return $available->contains($fallback) ? $fallback : 'en';
    }
}
