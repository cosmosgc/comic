<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\ComicController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/comics/update-slugs', [ComicController::class, 'updateMissingSlugs'])->name(name: 'comics.updateSlugs');

Route::get('/comics', [ComicController::class, 'getAllComics'])->name('api.comics');
Route::get('/comics/{id}', [ComicController::class, 'getComic']);
Route::get('/comics/{id}/pages', [PageController::class, 'getPagesByComicId']);

Route::get('/collections', [CollectionController::class, 'getAllCollections']);
Route::get('/collections/{id}', [CollectionController::class, 'showById']);

Route::get('/posts', [PostController::class, 'index']);

Route::get('/tags', [TagController::class, 'index'])->middleware('throttle:60,1')->name('api.tags');

Route::get('/users/{username}/card', [ProfileController::class, 'card'])
    ->middleware('throttle:120,1')
    ->where('username', '.*')
    ->name('api.profile.card');

Route::post('/analytics', [AnalyticsController::class, 'store'])->middleware('throttle:60,1')->name(name: 'analytics');
