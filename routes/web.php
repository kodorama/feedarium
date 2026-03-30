<?php

use App\Models\Feed;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (User::query()->count() === 0) {
        return Inertia::render('auth/RegisterAdmin');
    }

    if (auth()->check()) {
        return redirect()->route('feeds.index');
    }

    return redirect()->route('login');
})->name('home');

Route::get('register-admin', function () {
    return Inertia::render('auth/RegisterAdmin');
})->name('register-admin');
Route::post('register-admin', \App\Domains\User\Controllers\RegisterAdminController::class);

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Saved articles
    Route::get('/saved', function () {
        return Inertia::render('feeds/Saved');
    })->name('saved.index');

    // Feeds main page
    Route::get('/feeds', function (Request $request) {
        return Inertia::render('feeds/Index', [
            'selectedFeedId' => $request->filled('feed_id') ? $request->integer('feed_id') : null,
            'selectedCategoryId' => $request->filled('category_id') ? $request->integer('category_id') : null,
        ]);
    })->name('feeds.index');

    // Category CRUD pages
    Route::get('/categories', function () {
        return Inertia::render('Category/Index');
    })->name('categories.index');
    Route::get('/categories/create', function () {
        return Inertia::render('Category/Create');
    })->name('categories.create');
    Route::get('/categories/{id}/edit', function ($id) {
        $category = Category::query()->findOrFail($id);

        return Inertia::render('Category/Edit', ['category' => $category]);
    })->name('categories.edit');

    // Feed CRUD pages
    Route::get('/feeds/create', function () {
        return Inertia::render('Feed/Create');
    })->name('feeds.create');
    Route::get('/feeds/{id}/edit', function ($id) {
        $feed = Feed::query()->findOrFail($id);

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
        $user = User::query()->findOrFail($id);

        return Inertia::render('User/Edit', ['user' => $user]);
    })->name('users.edit');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
