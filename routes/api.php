<?php

use Illuminate\Support\Facades\Route;
use App\Domains\News\Controllers\ListNewsController;
use App\Domains\User\Controllers\ListUserController;
use App\Domains\Feed\Controllers\CreateFeedController;
use App\Domains\Feed\Controllers\UpdateFeedController;
use App\Domains\News\Controllers\CreateNewsController;
use App\Domains\News\Controllers\DeleteNewsController;
use App\Domains\News\Controllers\UpdateNewsController;
use App\Domains\User\Controllers\CreateUserController;
use App\Domains\User\Controllers\DeleteUserController;
use App\Domains\User\Controllers\UpdateUserController;
use App\Domains\User\Controllers\RegisterAdminController;
use App\Domains\Category\Controllers\ListCategoryController;
use App\Domains\Category\Controllers\CreateCategoryController;
use App\Domains\Category\Controllers\DeleteCategoryController;
use App\Domains\Category\Controllers\UpdateCategoryController;

Route::post('/register-admin', RegisterAdminController::class);
Route::post('/feeds', CreateFeedController::class)->middleware('auth:sanctum');
Route::put('/feeds/{id}', UpdateFeedController::class)->middleware('auth:sanctum');

Route::get('/categories', ListCategoryController::class)->middleware('auth:sanctum');
Route::post('/categories', CreateCategoryController::class)->middleware('auth:sanctum');
Route::put('/categories/{id}', UpdateCategoryController::class)->middleware('auth:sanctum');
Route::delete('/categories/{id}', DeleteCategoryController::class)->middleware('auth:sanctum');

Route::get('/news', ListNewsController::class)->middleware('auth:sanctum');
Route::post('/news', CreateNewsController::class)->middleware('auth:sanctum');
Route::put('/news/{id}', UpdateNewsController::class)->middleware('auth:sanctum');
Route::delete('/news/{id}', DeleteNewsController::class)->middleware('auth:sanctum');

Route::get('/users', ListUserController::class)->middleware('auth:sanctum');
Route::post('/users', CreateUserController::class)->middleware('auth:sanctum');
Route::put('/users/{id}', UpdateUserController::class)->middleware('auth:sanctum');
Route::delete('/users/{id}', DeleteUserController::class)->middleware('auth:sanctum');
