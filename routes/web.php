<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminImportController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\ComicController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', [ComicController::class, 'index'])->name('comics.index');

Route::prefix('admin')->middleware(['auth', AdminMiddleware::class])->controller(AdminController::class)->group(function () {
    Route::get('/', 'users')->name('admin.users.index');
    Route::get('/dashboard', 'dashboard')->name('admin.dashboard');
    Route::get('/analytics', 'analytics')->name('admin.analytics');
    Route::get('/users', 'users')->name('admin.users');
    Route::delete('/users/{id}', 'destroyUser')->name('admin.users.destroy');
    Route::get('/users/{id}/edit', 'editUser')->name('admin.users.edit');
    Route::put('/users/{id}', 'updateUser')->name('admin.users.update');
    Route::get('/comics', 'comics')->name('admin.comics');
    Route::delete('/comics/{comic}', 'destroyComic')->name('admin.comics.destroy');

    Route::get('/tag-categories', 'tagCategories')->name('admin.tag-categories');
    Route::get('/tag-categories/create', 'tagCategoryCreate')->name('admin.tag-categories.create');
    Route::post('/tag-categories', 'tagCategoryStore')->name('admin.tag-categories.store');
    Route::get('/tag-categories/{id}/edit', 'tagCategoryEdit')->name('admin.tag-categories.edit');
    Route::put('/tag-categories/{id}', 'tagCategoryUpdate')->name('admin.tag-categories.update');
    Route::delete('/tag-categories/{id}', 'tagCategoryDestroy')->name('admin.tag-categories.destroy');
    Route::post('/tag-categories/recategorize', 'tagCategoryRecategorize')->name('admin.tag-categories.recategorize');
    Route::post('/tag-categories/{id}/toggle', 'tagCategoryToggle')->name('admin.tag-categories.toggle');
    Route::post('/tag-categories/{id}/clear-learned', 'tagCategoryClearLearned')->name('admin.tag-categories.clear-learned');

    Route::get('/widgets', 'widgets')->name('admin.widgets');
    Route::post('/widgets', 'storeWidget')->name('admin.widgets.store');
    Route::get('/widgets/{id}/edit', 'editWidget')->name('admin.widgets.edit');
    Route::put('/widgets/{id}', 'updateWidget')->name('admin.widgets.update');
    Route::delete('/widgets/{id}', 'destroyWidget')->name('admin.widgets.destroy');

    Route::get('/migrations', 'migrations')->name('admin.migrations');
    Route::post('/migrations/run', 'runMigrations')->name('admin.migrations.run');
    Route::get('/import', [AdminImportController::class, 'show'])->name('admin.import');
    Route::post('/import/preview', [AdminImportController::class, 'preview'])->name('admin.import.preview');
    Route::post('/import', [AdminImportController::class, 'store'])->name('admin.import.store');
    Route::post('/import/settings', [AdminImportController::class, 'updateSettings'])->name('admin.import.settings');
    Route::get('/import/{comic}/progress', [AdminImportController::class, 'progress'])->name('admin.import.progress');
    Route::get('/import/{comic}/pages/{number}/file', [AdminImportController::class, 'downloadPageFile'])->whereNumber('number')->name('admin.import.page-file');
    Route::post('/import/{comic}/pages', [AdminImportController::class, 'fetchPages'])->name('admin.import.pages');
    Route::post('/import/{comic}/retry', [AdminImportController::class, 'retry'])->name('admin.import.retry');
    Route::post('/import/{comic}/retry-page', [AdminImportController::class, 'retryPage'])->name('admin.import.retry-page');
    Route::post('/import/{comic}/redownload', [AdminImportController::class, 'redownload'])->name('admin.import.redownload');
    Route::delete('/import/{comic}', [AdminImportController::class, 'destroy'])->name('admin.import.destroy');
    Route::get('/deploy', 'deploy')->name('admin.deploy');
    Route::post('/deploy/verify', 'verifyDeploy')->name('admin.deploy.verify');
    Route::post('/deploy/run', 'runDeploy')->name('admin.deploy.run');
    Route::get('/deploy/status/{id}', 'deployStatus')->name('admin.deploy.status');
    Route::post('/deploy/cancel/{id}', 'cancelDeploy')->name('admin.deploy.cancel');
    Route::get('/changelogs', 'changelogs')->name('admin.changelogs');
    Route::post('/changelogs/import', 'changelogImport')->name('admin.changelogs.import');
    Route::get('/changelogs/create', 'changelogCreate')->name('admin.changelogs.create');
    Route::post('/changelogs', 'changelogStore')->name('admin.changelogs.store');
    Route::get('/changelogs/{id}/edit', 'changelogEdit')->name('admin.changelogs.edit')->where('id', '[A-Za-z0-9\-]+');
    Route::put('/changelogs/{id}', 'changelogUpdate')->name('admin.changelogs.update')->where('id', '[A-Za-z0-9\-]+');
    Route::delete('/changelogs/{id}', 'changelogDestroy')->name('admin.changelogs.destroy')->where('id', '[A-Za-z0-9\-]+');
});

Route::prefix('admin')->middleware(['auth', AdminMiddleware::class])->group(function () {
    Route::get('/analytics/referral', [AnalyticsController::class, 'referralAnalytics'])->name('analytics.referral');
});

Route::get('/comics', [ComicController::class, 'index'])->name('comics.index');
Route::get('/comics/create', [ComicController::class, 'create'])->middleware('auth')->name('comics.create');
Route::post('/comics', [ComicController::class, 'store'])->middleware('auth')->name('comics.store');
Route::get('/comics/search', [ComicController::class, 'search'])->name('comics.search');

// Route to access a comic by ID
Route::get('/comics/id/{id}', [ComicController::class, 'showById'])->name('comics.showById');

// Route to access a comic by slug
Route::get('/comics/{slug}', [ComicController::class, 'showBySlug'])->name('comics.showBySlug');

// ////////////////////////////////////////////////////////////
Route::get('/comics/{comic}/edit', [ComicController::class, 'edit'])->middleware('auth')->name('comics.edit');
Route::put('/comics/{comic}/update', [ComicController::class, 'update'])->middleware('auth')->name('comics.update');

Route::post('/comics/{comic}/reorder-pages', [ComicController::class, 'reorderPages'])->middleware('auth')->name('comics.reorderPages');
Route::delete('/page/{page}', [ComicController::class, 'deletePage'])->middleware('auth')->name('pages.deletePage');
Route::post('/comics/{comic}/add-pages', [PageController::class, 'addPage'])->middleware('auth')->name('pages.addPage');

Route::post('/comics/{comic}/pages', [PageController::class, 'store'])->middleware('auth')->name('pages.store');
Route::post('/comics/{comic}/set-cover', [ComicController::class, 'setCover'])
    ->middleware('auth')
    ->name('comics.setCover');

// Comic engagement: likes + comments (modal-driven, JSON for AJAX).
Route::post('/comics/{comic}/like', [LikeController::class, 'toggleComic'])->name('comics.like')->middleware('auth');
Route::get('/comics/{comic}/comments', [CommentController::class, 'index'])->name('comments.index');
Route::post('/comics/{comic}/comments', [CommentController::class, 'store'])->middleware('auth')->name('comments.store');
Route::put('/comments/{comment}', [CommentController::class, 'update'])->middleware('auth')->name('comments.update');
Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->middleware('auth')->name('comments.destroy');
// ////////////////////////////////////////////////////////////
// Route to display all collections
Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');

// Route to display the form for creating a new collection
// (must be registered before /collections/{collection}, otherwise
// "create" is captured as the {collection} parameter and 404s)
Route::get('/collections/create', [CollectionController::class, 'create'])->middleware('auth')->name('collections.create');

// Viewer's own collections for the quick-add dropdown (must precede {collection}).
Route::get('/collections/mine', [CollectionController::class, 'mine'])->middleware('auth')->name('collections.mine');

// Route to display a specific collection by ID
Route::get('/collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');

// Route to store the new collection
Route::post('/collections', [CollectionController::class, 'store'])->middleware('auth')->name('collections.store');
// Route to display the edit form for a collection
Route::get('/collections/{collection}/edit', [CollectionController::class, 'edit'])->middleware('auth')->name('collections.edit');
// Route to update the collection
Route::put('/collections/{collection}', [CollectionController::class, 'update'])->middleware('auth')->name('collections.update');
Route::delete('/collections/{collection}', [CollectionController::class, 'destroy'])->middleware('auth')->name('collections.destroy');
Route::post('/collections/{collection}/sort/update', [CollectionController::class, 'updateSortOrder'])
    ->middleware('auth')
    ->name('collections.sort.update');
// Quick-add + favorites (auth; ownership verified in the controller).
Route::post('/collections/favorite/{comic}', [CollectionController::class, 'toggleFavorite'])->middleware('auth')->name('collections.favorite');
Route::post('/collections/{collection}/comics/{comic}', [CollectionController::class, 'addComic'])->middleware('auth')->name('collections.addComic');
Route::delete('/collections/{collection}/comics/{comic}', [CollectionController::class, 'removeComic'])->middleware('auth')->name('collections.removeComic');

// ////////////////////////////////////////////////////////////
// Only index + store exist on PostController, so register just those:
// index is public, store requires login (guests would otherwise 500 on
// the non-nullable author_id column).
Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::post('/posts', [PostController::class, 'store'])->name('posts.store')->middleware('auth');
Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->middleware('auth')->name('posts.edit');
Route::put('/posts/{post}', [PostController::class, 'update'])->middleware('auth')->name('posts.update');
Route::post('/posts/{post}/like', [LikeController::class, 'toggle'])->name('posts.like')->middleware('auth');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
// ////////////////////////////////////////////////////////////

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
});
Route::get('/profile/id/{id}', [ProfileController::class, 'publicShowById'])->name('profile.public.show.id');
Route::get('/profile/{username}', [ProfileController::class, 'publicShowByUsername'])->name('profile.public.show.username');

// ////////////////////////////////////////////////////////////
// What's new: file-per-PR changelog (changelogs/*.json).
Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog.index');
Route::get('/changelog/{entry}', [ChangelogController::class, 'show'])->name('changelog.show');
