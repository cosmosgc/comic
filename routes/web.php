<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\ComicController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\CollectionController;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;

use App\Http\Controllers\PostController;

Route::get('/', [ComicController::class, 'index'])->name('comics.index');


Route::prefix('admin')->middleware('auth')->group(function () {
    Route::get('/', function () {
        if (Auth::user()->admin_level < 1) {
            return redirect('/'); // Redirect non-admin users
        }
        return app(AdminController::class)->users();
    })->name('admin.users.index');

    Route::get('/dashboard', function (Request $request) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }

        return app(AdminController::class)->dashboard($request);
    })->name('admin.dashboard');


    Route::get('/analytics', function () {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->analytics();
    })->name('admin.analytics');

    Route::get('/users', function () {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->users();
    })->name('admin.users');

    Route::delete('/users/{id}', function ($id) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->destroyUser($id);
    })->name('admin.users.destroy');

    Route::get('/users/{id}/edit', function ($id) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->editUser($id);
    })->name('admin.users.edit');

    Route::put('/users/{id}', function (Request $request, $id) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->updateUser($request, $id);
    })->name('admin.users.update');

    Route::get('/comics', function () {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->comics();
    })->name('admin.comics');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/widgets', [AdminController::class, 'widgets'])->name('widgets');
        Route::post('/widgets', [AdminController::class, 'storeWidget'])->name('widgets.store');
        Route::get('/widgets/{id}/edit', [AdminController::class, 'editWidget'])->name('widgets.edit');
        Route::put('/widgets/{id}', [AdminController::class, 'updateWidget'])->name('widgets.update');
        Route::delete('/widgets/{id}', [AdminController::class, 'destroyWidget'])->name('widgets.destroy');
    });
    

    Route::get('/analytics/referral', function () {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AnalyticsController::class)->referralAnalytics();
    })->name('analytics.referral');
    Route::get('/phpinfo', [AdminController::class, 'phpinfo'])->name('phpinfo');

});


Route::get('/comics', [ComicController::class, 'index'])->name('comics.index');
Route::get('/comics/create', [ComicController::class, 'create'])->name('comics.create');
Route::post('/comics', [ComicController::class, 'store'])->name('comics.store');

// Route to access a comic by ID
Route::get('/comics/id/{id}', [ComicController::class, 'showById'])->name('comics.showById');

// Route to access a comic by slug
Route::get('/comics/{slug}', [ComicController::class, 'showBySlug'])->name('comics.showBySlug');

//////////////////////////////////////////////////////////////
Route::get('comics/upload', [ComicController::class, 'create'])->name('comics.upload');
Route::post('comics/store', [ComicController::class, 'store'])->name('comics.store');

Route::get('/comics/{comic}/edit', [ComicController::class, 'edit'])->name('comics.edit');
Route::put('/comics/{comic}/update', [ComicController::class, 'update'])->name('comics.update');

Route::post('/comics/{comic}/reorder-pages', [ComicController::class, 'reorderPages'])->name('comics.reorderPages');
Route::delete('/page/{page}', [ComicController::class, 'deletePage'])->name('pages.deletePage');
Route::post('/comics/{comic}/add-pages', [PageController::class, 'addPage'])->name('pages.addPage');

Route::post('/comics/{comic}/pages', [PageController::class, 'store'])->name('pages.store');
//////////////////////////////////////////////////////////////
// Route to display all collections
Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');

// Route to display a specific collection by ID
Route::get('/collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');

// Route to display the form for creating a new collection
Route::get('/collections/create', [CollectionController::class, 'create'])->name('collections.create');
// Route to store the new collection
Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
// Route to display the edit form for a collection
Route::get('/collections/{collection}/edit', [CollectionController::class, 'edit'])->name('collections.edit');
// Route to update the collection
Route::put('/collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
Route::post('/collections/{collection}/sort/update', [CollectionController::class, 'updateSortOrder'])
    ->name('collections.sort.update');

//////////////////////////////////////////////////////////////
// Route::get('/posts', [PostController::class, 'index']);
Route::resource('posts', PostController::class);
//////////////////////////////////////////////////////////////

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

