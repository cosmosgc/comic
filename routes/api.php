<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ComicController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AnalyticsController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/comics/update-slugs', [ComicController::class, 'updateMissingSlugs'])->name('comics.updateSlugs');

Route::get('/comics', [ComicController::class, 'getAllComics']);
Route::get('/comics/{id}', [ComicController::class, 'getComic']);
Route::get('/comics/{id}/pages', [PageController::class, 'getPagesByComicId']);

Route::post('/analytics', [AnalyticsController::class, 'store']);

use App\Http\Controllers\TestsController;

Route::get('/restaurants', [TestsController::class, 'getRestaurants']);

