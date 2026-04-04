<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\PasswordController;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');

    Route::get('settings/categories', function () {
        return Inertia::render('settings/Categories');
    })->name('settings.categories');

    Route::get('settings/feeds', function () {
        return Inertia::render('settings/FeedSources');
    })->name('settings.feeds');

    Route::get('settings/search', function () {
        return Inertia::render('settings/Search', [
            'scoutDriver' => config('scout.driver'),
        ]);
    })->name('settings.search');

    Route::get('settings/reading', function () {
        return Inertia::render('settings/Reading');
    })->name('settings.reading');
});
