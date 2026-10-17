<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\ComicController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public read-only endpoints. Throttled so a single client cannot hammer
// the site's own JSON API; the old `GET /comics/update-slugs` mass-write
// endpoint now lives in the `comics:backfill-slugs` console command.
Route::get('/comics', [ComicController::class, 'getAllComics'])
    ->middleware('throttle:240,1')
    ->name('api.comics');
Route::get('/comics/{id}', [ComicController::class, 'getComic'])->middleware('throttle:120,1');
Route::get('/comics/{id}/pages', [PageController::class, 'getPagesByComicId'])->middleware('throttle:120,1');

Route::get('/collections', [CollectionController::class, 'getAllCollections'])->middleware('throttle:120,1');
Route::get('/collections/{id}', [CollectionController::class, 'showById'])->middleware('throttle:120,1');

Route::get('/posts', [PostController::class, 'index'])->middleware('throttle:120,1');

Route::get('/tags', [TagController::class, 'index'])->middleware('throttle:60,1')->name('api.tags');
Route::get('/tags/lookup', [TagController::class, 'lookup'])->middleware('throttle:15,1')->name('api.tags.lookup');

Route::get('/users/{username}/card', [ProfileController::class, 'card'])
    ->middleware('throttle:120,1')
    ->where('username', '.*')
    ->name('api.profile.card');

Route::post('/analytics', [AnalyticsController::class, 'store'])->middleware('throttle:60,1')->name(name: 'analytics');

// Server-to-server transfer intake (shared-secret auth, see config/transfer.php).
Route::middleware('transfer.token')->prefix('transfer')->group(function () {
    Route::post('/comics', [TransferController::class, 'store'])->name('api.transfer.store');
    Route::get('/comics/{comic}/pages', [TransferController::class, 'pages'])->name('api.transfer.pages');
    Route::post('/comics/{comic}/pages', [TransferController::class, 'storePage'])->name('api.transfer.page');
    Route::post('/comics/{comic}/finalize', [TransferController::class, 'finalize'])->name('api.transfer.finalize');
});
