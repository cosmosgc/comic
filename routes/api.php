<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ComicController;
use App\Http\Controllers\PageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::get('/comics/update-slugs', [ComicController::class, 'updateMissingSlugs'])->name('comics.updateSlugs');

Route::get('/comics/{id}', [ComicController::class, 'getComic']);
Route::get('/comics/{id}/pages', [PageController::class, 'getPagesByComicId']);
