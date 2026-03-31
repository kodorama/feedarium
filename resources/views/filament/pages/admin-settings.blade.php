<x-filament-panels::page>
    <div class="space-y-8">

        {{-- Theme --}}
        <x-filament::section heading="Theme">
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Appearance</label>
                    <div class="mt-2">
                        <select wire:model="theme" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                            <option value="system">System (auto)</option>
                        </select>
                    </div>
                </div>
                <x-filament::button wire:click="saveTheme">Save Theme</x-filament::button>
            </div>
        </x-filament::section>

        {{-- Language --}}
        <x-filament::section heading="Language">
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Interface Language</label>
                    <div class="mt-2">
                        <select wire:model="language" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="en">English</option>
                        </select>
                    </div>
                </div>
                <x-filament::button wire:click="saveLanguage">Save Language</x-filament::button>
            </div>
        </x-filament::section>

        {{-- Timezone --}}
        <x-filament::section heading="Timezone">
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Timezone</label>
                    <div class="mt-2">
                        <select wire:model="timezone" class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @foreach (\DateTimeZone::listIdentifiers() as $tz)
                                <option value="{{ $tz }}">{{ $tz }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <x-filament::button wire:click="saveTimezone">Save Timezone</x-filament::button>
            </div>
        </x-filament::section>

        {{-- Password --}}
        <x-filament::section heading="Change Password">
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Current Password</label>
                    <div class="mt-2">
                        <input
                            type="password"
                            wire:model="current_password"
                            autocomplete="current-password"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                        @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
                    <div class="mt-2">
                        <input
                            type="password"
                            wire:model="password"
                            autocomplete="new-password"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
                    <div class="mt-2">
                        <input
                            type="password"
                            wire:model="password_confirmation"
                            autocomplete="new-password"
                            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                </div>
                <x-filament::button wire:click="updatePassword" color="danger">Update Password</x-filament::button>
            </div>
        </x-filament::section>

        {{-- Search Index --}}
        <x-filament::section heading="Search Index">
            <div class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Manage the full-text search index for articles. All operations run in the background via the queue worker.
                </p>
                <div class="flex flex-wrap gap-3">
                    <x-filament::button wire:click="scoutImport" color="primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="scoutImport">Import All Articles</span>
                        <span wire:loading wire:target="scoutImport">Dispatching…</span>
                    </x-filament::button>
                    <x-filament::button wire:click="scoutFlush" color="warning" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="scoutFlush">Flush Index</span>
                        <span wire:loading wire:target="scoutFlush">Dispatching…</span>
                    </x-filament::button>
                    @if(config('scout.driver') === 'meilisearch')
                    <x-filament::button wire:click="scoutSyncSettings" color="gray" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="scoutSyncSettings">Sync Index Settings</span>
                        <span wire:loading wire:target="scoutSyncSettings">Dispatching…</span>
                    </x-filament::button>
                    @endif
                </div>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>

