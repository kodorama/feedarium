<?php

use Illuminate\Support\Facades\Route;
use App\Domains\User\Controllers\LoginController;
use App\Domains\User\Controllers\LogoutController;
use App\Domains\Feed\Controllers\ListFeedController;
use App\Domains\News\Controllers\ListNewsController;
use App\Domains\User\Controllers\ListUserController;
use App\Domains\Feed\Controllers\CreateFeedController;
use App\Domains\Feed\Controllers\DeleteFeedController;
use App\Domains\Feed\Controllers\ToggleFeedController;
use App\Domains\Feed\Controllers\UpdateFeedController;
use App\Domains\News\Controllers\CreateNewsController;
use App\Domains\News\Controllers\DeleteNewsController;
use App\Domains\News\Controllers\SearchNewsController;
use App\Domains\News\Controllers\UpdateNewsController;
use App\Domains\User\Controllers\CreateUserController;
use App\Domains\User\Controllers\DeleteUserController;
use App\Domains\User\Controllers\UpdateUserController;
use App\Domains\Feed\Controllers\SearchFeedsController;
use App\Domains\News\Controllers\SaveArticleController;
use App\Domains\News\Controllers\UnsaveArticleController;
use App\Domains\User\Controllers\RegisterAdminController;
use App\Domains\Category\Controllers\ListCategoryController;
use App\Domains\News\Controllers\ListSavedArticlesController;
use App\Domains\Category\Controllers\CreateCategoryController;
use App\Domains\Category\Controllers\DeleteCategoryController;
use App\Domains\Category\Controllers\UpdateCategoryController;

// WebSub (PubSubHubbub) callbacks — no auth, hub must reach these
Route::match(['GET', 'POST'], '/websub/callback/{feedId}', \App\Domains\Feed\Controllers\WebSubCallbackController::class);

Route::post('/register-admin', RegisterAdminController::class);
Route::post('/login', LoginController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class);

    // Feed Sources
    Route::get('/feeds', ListFeedController::class);
    Route::post('/feeds', CreateFeedController::class);
    Route::put('/feeds/{id}', UpdateFeedController::class);
    Route::delete('/feeds/{id}', DeleteFeedController::class);
    Route::patch('/feeds/{id}/toggle', ToggleFeedController::class);

    // Categories
    Route::get('/categories', ListCategoryController::class);
    Route::post('/categories', CreateCategoryController::class);
    Route::put('/categories/{id}', UpdateCategoryController::class);
    Route::delete('/categories/{id}', DeleteCategoryController::class);

    // News / Articles
    Route::get('/news', ListNewsController::class);
    Route::get('/news/search', SearchNewsController::class);
    Route::get('/news/saved', ListSavedArticlesController::class);
    Route::post('/news/{id}/save', SaveArticleController::class);
    Route::delete('/news/{id}/save', UnsaveArticleController::class);
    Route::post('/news', CreateNewsController::class);
    Route::put('/news/{id}', UpdateNewsController::class);
    Route::delete('/news/{id}', DeleteNewsController::class);

    // Feed Search
    Route::get('/feeds/search', SearchFeedsController::class);

    // Users
    Route::get('/users', ListUserController::class);
    Route::post('/users', CreateUserController::class);
    Route::put('/users/{id}', UpdateUserController::class);
    Route::delete('/users/{id}', DeleteUserController::class);
});
