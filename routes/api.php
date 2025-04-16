<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ComicController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\PostController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/comics/update-slugs', [ComicController::class, 'updateMissingSlugs'])->name(name: 'comics.updateSlugs');

Route::get('/comics', [ComicController::class, 'getAllComics']);
Route::get('/comics/{id}', [ComicController::class, 'getComic']);
Route::get('/comics/{id}/pages', [PageController::class, 'getPagesByComicId']);

Route::get('/collections', [CollectionController::class, 'getAllCollections']);
Route::get('/collections/{id}', [CollectionController::class, 'showById']);

Route::get('/posts', [PostController::class, 'index']);

Route::post('/analytics', [AnalyticsController::class, 'store'])->name(name: 'analytics');
