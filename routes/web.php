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
use App\Http\Controllers\CommentController;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;

use App\Http\Controllers\PostController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\ChangelogController;

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

    Route::name('admin.')->group(function () {
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
    Route::get('/phpinfo', function () {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->phpinfo();
    })->name('phpinfo');

    Route::get('/migrations', function (App\Services\MigrationInspector $inspector) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->migrations($inspector);
    })->name('admin.migrations');

    Route::post('/migrations/run', function (Request $request) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->runMigrations($request);
    })->name('admin.migrations.run');

    Route::get('/deploy', function (App\Services\Deploy\FtpDeployer $deployer) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->deploy($deployer);
    })->name('admin.deploy');

    Route::post('/deploy/verify', function (App\Services\Deploy\FtpDeployer $deployer) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->verifyDeploy($deployer);
    })->name('admin.deploy.verify');

    Route::post('/deploy/run', function (Request $request, App\Services\Deploy\FtpDeployer $deployer) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->runDeploy($request, $deployer);
    })->name('admin.deploy.run');

    Route::get('/deploy/status/{id}', function (string $id) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->deployStatus($id);
    })->name('admin.deploy.status');

    Route::post('/deploy/cancel/{id}', function (string $id) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->cancelDeploy($id);
    })->name('admin.deploy.cancel');

    Route::get('/changelogs', function (App\Services\ChangelogReader $reader, App\Services\GithubPullRequests $github) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->changelogs($reader, $github);
    })->name('admin.changelogs');

    Route::post('/changelogs/import', function (Request $request, App\Services\ChangelogReader $reader, App\Services\ChangelogWriter $writer, App\Services\GithubPullRequests $github) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->changelogImport($request, $reader, $writer, $github);
    })->name('admin.changelogs.import');

    Route::get('/changelogs/create', function () {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->changelogCreate();
    })->name('admin.changelogs.create');

    Route::post('/changelogs', function (Request $request, App\Services\ChangelogWriter $writer) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->changelogStore($request, $writer);
    })->name('admin.changelogs.store');

    Route::get('/changelogs/{id}/edit', function (string $id, App\Services\ChangelogReader $reader) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->changelogEdit($id, $reader);
    })->name('admin.changelogs.edit')->where('id', '[A-Za-z0-9\-]+');

    Route::put('/changelogs/{id}', function (Request $request, string $id, App\Services\ChangelogReader $reader, App\Services\ChangelogWriter $writer) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->changelogUpdate($request, $id, $reader, $writer);
    })->name('admin.changelogs.update')->where('id', '[A-Za-z0-9\-]+');

    Route::delete('/changelogs/{id}', function (string $id, App\Services\ChangelogReader $reader, App\Services\ChangelogWriter $writer) {
        if (Auth::user()->admin_level < 1) {
            return redirect('/');
        }
        return app(AdminController::class)->changelogDestroy($id, $reader, $writer);
    })->name('admin.changelogs.destroy')->where('id', '[A-Za-z0-9\-]+');

});


Route::get('/comics', [ComicController::class, 'index'])->name('comics.index');
Route::get('/comics/create', [ComicController::class, 'create'])->name('comics.create');
Route::post('/comics', [ComicController::class, 'store'])->name('comics.store');
Route::get('/comics/search', [ComicController::class, 'search'])->name('comics.search');

// Route to access a comic by ID
Route::get('/comics/id/{id}', [ComicController::class, 'showById'])->name('comics.showById');

// Route to access a comic by slug
Route::get('/comics/{slug}', [ComicController::class, 'showBySlug'])->name('comics.showBySlug');

//////////////////////////////////////////////////////////////
Route::get('/comics/{comic}/edit', [ComicController::class, 'edit'])->name('comics.edit');
Route::put('/comics/{comic}/update', [ComicController::class, 'update'])->name('comics.update');

Route::post('/comics/{comic}/reorder-pages', [ComicController::class, 'reorderPages'])->name('comics.reorderPages');
Route::delete('/page/{page}', [ComicController::class, 'deletePage'])->name('pages.deletePage');
Route::post('/comics/{comic}/add-pages', [PageController::class, 'addPage'])->name('pages.addPage');

Route::post('/comics/{comic}/pages', [PageController::class, 'store'])->name('pages.store');
Route::post('/comics/{comic}/set-cover', [ComicController::class, 'setCover'])
    ->name('comics.setCover');

// Comic engagement: likes + comments (modal-driven, JSON for AJAX).
Route::post('/comics/{comic}/like', [LikeController::class, 'toggleComic'])->name('comics.like')->middleware('auth');
Route::get('/comics/{comic}/comments', [CommentController::class, 'index'])->name('comments.index');
Route::post('/comics/{comic}/comments', [CommentController::class, 'store'])->middleware('auth')->name('comments.store');
Route::put('/comments/{comment}', [CommentController::class, 'update'])->middleware('auth')->name('comments.update');
Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->middleware('auth')->name('comments.destroy');
//////////////////////////////////////////////////////////////
// Route to display all collections
Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');

// Route to display the form for creating a new collection
// (must be registered before /collections/{collection}, otherwise
// "create" is captured as the {collection} parameter and 404s)
Route::get('/collections/create', [CollectionController::class, 'create'])->name('collections.create');

// Viewer's own collections for the quick-add dropdown (must precede {collection}).
Route::get('/collections/mine', [CollectionController::class, 'mine'])->middleware('auth')->name('collections.mine');

// Route to display a specific collection by ID
Route::get('/collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');

// Route to store the new collection
Route::post('/collections', [CollectionController::class, 'store'])->name('collections.store');
// Route to display the edit form for a collection
Route::get('/collections/{collection}/edit', [CollectionController::class, 'edit'])->name('collections.edit');
// Route to update the collection
Route::put('/collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
Route::delete('/collections/{collection}', [CollectionController::class, 'destroy'])->middleware('auth')->name('collections.destroy');
Route::post('/collections/{collection}/sort/update', [CollectionController::class, 'updateSortOrder'])
    ->name('collections.sort.update');
// Quick-add + favorites (auth; ownership verified in the controller).
Route::post('/collections/favorite/{comic}', [CollectionController::class, 'toggleFavorite'])->middleware('auth')->name('collections.favorite');
Route::post('/collections/{collection}/comics/{comic}', [CollectionController::class, 'addComic'])->middleware('auth')->name('collections.addComic');
Route::delete('/collections/{collection}/comics/{comic}', [CollectionController::class, 'removeComic'])->middleware('auth')->name('collections.removeComic');

//////////////////////////////////////////////////////////////
// Only index + store exist on PostController, so register just those:
// index is public, store requires login (guests would otherwise 500 on
// the non-nullable author_id column).
Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
Route::post('/posts', [PostController::class, 'store'])->name('posts.store')->middleware('auth');
Route::post('/posts/{post}/like', [LikeController::class, 'toggle'])->name('posts.like')->middleware('auth');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
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

//////////////////////////////////////////////////////////////
// What's new: file-per-PR changelog (changelogs/*.json).
Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog.index');
Route::get('/changelog/{entry}', [ChangelogController::class, 'show'])->name('changelog.show');

