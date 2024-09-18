<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ComicController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::get('/comics/update-slugs', [ComicController::class, 'updateMissingSlugs'])->name('comics.updateSlugs');
