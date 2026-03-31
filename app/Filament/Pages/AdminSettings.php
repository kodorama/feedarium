<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rules\Password;
use App\Domains\News\Jobs\FlushScoutIndexJob;
use App\Domains\News\Jobs\ImportScoutIndexJob;
use App\Domains\User\Jobs\UpdateAdminPasswordJob;

final class AdminSettings extends Page
{
    protected string $view = 'filament.pages.admin-settings';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationLabel(): string
    {
        return 'Settings';
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Admin Settings';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public string $theme = 'system';

    public string $language = 'en';

    public string $timezone = 'UTC';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->theme = request()->cookie('appearance', 'system');
        $this->language = config('app.locale', 'en');
        $this->timezone = config('app.timezone', 'UTC');
    }

    public function saveTheme(): void
    {
        $this->validate(['theme' => ['required', 'in:light,dark,system']]);
        cookie()->queue(cookie()->forever('appearance', $this->theme));
        Notification::make()->title('Theme saved')->success()->send();
    }

    public function saveLanguage(): void
    {
        $this->validate(['language' => ['required', 'string', 'max:10']]);
        Notification::make()
            ->title('Language preference noted (update APP_LOCALE in .env to persist)')
            ->success()->send();
    }

    public function saveTimezone(): void
    {
        $this->validate(['timezone' => ['required', 'timezone']]);
        Notification::make()
            ->title('Timezone preference noted (update APP_TIMEZONE in .env to persist)')
            ->success()->send();
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        UpdateAdminPasswordJob::dispatchSync(Auth::user(), $this->password);

        $this->current_password = '';
        $this->password = '';
        $this->password_confirmation = '';

        Notification::make()->title('Password updated successfully')->success()->send();
    }

    public function scoutFlush(): void
    {
        dispatch(new FlushScoutIndexJob);
        Notification::make()->title('Search index flush queued')->success()->send();
    }

    public function scoutImport(): void
    {
        dispatch(new ImportScoutIndexJob);
        Notification::make()->title('Search index import queued')->success()->send();
    }

    public function scoutSyncSettings(): void
    {
        try {
            Artisan::queue('scout:sync-index-settings', ['--no-interaction' => true]);
            Notification::make()->title('Search index settings sync queued')->success()->send();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Scout sync-index-settings queue failed', ['exception' => $e->getMessage()]);
            Notification::make()->title('Failed to queue search index settings sync')->danger()->send();
        }
    }
}
