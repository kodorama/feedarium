<?php

use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (User::count() === 0) {
        return Inertia::render('auth/RegisterAdmin');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::get('register-admin', function () {
    return Inertia::render('auth/RegisterAdmin');
})->name('register-admin');
Route::post('register-admin', \App\Domains\User\Controllers\RegisterAdminController::class);

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Category CRUD pages
    Route::get('/categories', function () {
        return Inertia::render('Category/Index');
    })->name('categories.index');
    Route::get('/categories/create', function () {
        return Inertia::render('Category/Create');
    })->name('categories.create');
    Route::get('/categories/{id}/edit', function ($id) {
        $category = \App\Models\Category::findOrFail($id);

        return Inertia::render('Category/Edit', ['category' => $category]);
    })->name('categories.edit');

    // Feed CRUD pages
    Route::get('/feeds', function () {
        return Inertia::render('Feed/Index');
    })->name('feeds.index');
    Route::get('/feeds/create', function () {
        return Inertia::render('Feed/Create');
    })->name('feeds.create');
    Route::get('/feeds/{id}/edit', function ($id) {
        $feed = \App\Models\Feed::findOrFail($id);

        return Inertia::render('Feed/Edit', ['feed' => $feed]);
    })->name('feeds.edit');

    // User CRUD pages
    Route::get('/users', function () {
        return Inertia::render('User/Index');
    })->name('users.index');
    Route::get('/users/create', function () {
        return Inertia::render('User/Create');
    })->name('users.create');
    Route::get('/users/{id}/edit', function ($id) {
        $user = \App\Models\User::findOrFail($id);

        return Inertia::render('User/Edit', ['user' => $user]);
    })->name('users.edit');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
