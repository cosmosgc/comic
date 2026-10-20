<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminForumController;
use App\Http\Controllers\AdminGamificationController;
use App\Http\Controllers\AdminImportController;
use App\Http\Controllers\AdminMarketplaceController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\ComicController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\ForumPollController;
use App\Http\Controllers\ForumPostController;
use App\Http\Controllers\ForumThreadController;
use App\Http\Controllers\HashtagController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MarketplaceListingController;
use App\Http\Controllers\MarketplaceMessageController;
use App\Http\Controllers\MuteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', [ComicController::class, 'index'])->name('comics.index');

Route::prefix('admin')->middleware(['auth', AdminMiddleware::class])->controller(AdminController::class)->group(function () {
    Route::get('/', 'users')->name('admin.users.index');
    Route::get('/dashboard', 'dashboard')->name('admin.dashboard');
    Route::get('/analytics', 'analytics')->name('admin.analytics');
    Route::get('/activities', 'activities')->name('admin.activities');
    Route::get('/activities/report', 'activityReport')->name('admin.activities.report');
    Route::get('/users', 'users')->name('admin.users');
    Route::delete('/users/{id}', 'destroyUser')->name('admin.users.destroy');
    Route::get('/users/{id}/edit', 'editUser')->name('admin.users.edit');
    Route::put('/users/{id}', 'updateUser')->name('admin.users.update');
    Route::get('/comics', 'comics')->name('admin.comics');
    Route::post('/comics/{comic}/schedule', 'scheduleComic')->name('admin.comics.schedule');
    Route::post('/comics/{comic}/exclusive', 'exclusiveComic')->name('admin.comics.exclusive');
    Route::delete('/comics/{comic}', 'destroyComic')->name('admin.comics.destroy');
    Route::get('/comics/{comic}/package', 'exportComicPackage')->name('admin.comics.package');
    Route::post('/comics/package', 'importComicPackage')->name('admin.comics.package-import');
    Route::post('/comics/{comic}/transfer', 'transferComic')->name('admin.comics.transfer');
    Route::post('/transfers/{transfer}/push', 'pushTransfer')->name('admin.transfers.push');

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
    Route::post('/caches/clear', 'clearCaches')->name('admin.caches.clear');
    Route::get('/import', [AdminImportController::class, 'show'])->name('admin.import');
    Route::post('/import/preview', [AdminImportController::class, 'preview'])->name('admin.import.preview');
    Route::post('/import', [AdminImportController::class, 'store'])->name('admin.import.store');
    Route::post('/import/settings', [AdminImportController::class, 'updateSettings'])->name('admin.import.settings');
    Route::get('/import/{comic}/progress', [AdminImportController::class, 'progress'])->name('admin.import.progress');
    Route::get('/import/{comic}/pages/{number}/file', [AdminImportController::class, 'downloadPageFile'])->whereNumber('number')->name('admin.import.page-file');
    Route::post('/import/{comic}/pages/{number}/file', [AdminImportController::class, 'uploadPageFile'])->whereNumber('number')->name('admin.import.page-upload');
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

    Route::get('/reports', 'reports')->name('admin.reports');
    Route::post('/reports/{report}/resolve', 'resolveReport')->name('admin.reports.resolve');
});

Route::prefix('admin')->middleware(['auth', AdminMiddleware::class])->group(function () {
    Route::get('/analytics/referral', [AnalyticsController::class, 'referralAnalytics'])->name('analytics.referral');
});

// Gamification management (admin). Separate controller so AdminController
// stays focused on the site's own panels.
Route::prefix('admin')->middleware(['auth', AdminMiddleware::class])->group(function () {
    Route::get('/gamification', [AdminGamificationController::class, 'index'])->name('admin.gamification');
    Route::post('/gamification/settings', [AdminGamificationController::class, 'saveSettings'])->name('admin.gamification.settings');
    Route::post('/gamification/grant', [AdminGamificationController::class, 'grant'])->name('admin.gamification.grant');
    Route::post('/gamification/backfill/preview', [AdminGamificationController::class, 'backfillPreview'])->name('admin.gamification.backfill.preview');
    Route::post('/gamification/backfill', [AdminGamificationController::class, 'backfill'])->name('admin.gamification.backfill');
    Route::post('/gamification/resync-levels', [AdminGamificationController::class, 'resyncLevels'])->name('admin.gamification.resync');
    Route::get('/gamification/users', [AdminGamificationController::class, 'searchUsers'])->name('admin.gamification.users');
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
Route::delete('/posts/{post}', [PostController::class, 'destroy'])->middleware('auth')->name('posts.destroy');
Route::post('/posts/{post}/like', [LikeController::class, 'toggle'])->name('posts.like')->middleware('auth');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
// ////////////////////////////////////////////////////////////
// Social graph: follows, bookmarks and moderation (auth).
Route::middleware('auth')->group(function () {
    Route::post('/users/{user}/follow', [FollowController::class, 'toggle'])->name('users.follow');
    Route::post('/users/{user}/mute', [MuteController::class, 'toggle'])->name('users.mute');
    Route::post('/posts/{post}/bookmark', [BookmarkController::class, 'toggle'])->name('posts.bookmark');
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/posts/{post}/report', [ReportController::class, 'store'])->name('posts.report');
});

// Hashtag timeline (public).
Route::get('/hashtag/{tag}', [HashtagController::class, 'show'])
    ->where('tag', '[A-Za-z0-9_]+')
    ->name('hashtags.show');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

// Social login / account linking (OAuth 2.0).
Route::post('/auth/google/credential', [SocialAuthController::class, 'googleCredential'])
    ->middleware('throttle:10,1')->name('oauth.google.credential');
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('oauth.callback');
Route::post('/auth/{provider}/unlink', [SocialAuthController::class, 'unlink'])
    ->middleware('auth')->name('oauth.unlink');

Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');

    // In-app notifications (level-ups, badge unlocks).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});
Route::get('/profile/id/{id}', [ProfileController::class, 'publicShowById'])->name('profile.public.show.id');
Route::get('/profile/{username}', [ProfileController::class, 'publicShowByUsername'])->name('profile.public.show.username');

// Public leaderboard (gamification).
Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');

// ////////////////////////////////////////////////////////////
// What's new: file-per-PR changelog (changelogs/*.json).
Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog.index');
Route::get('/changelog/{entry}', [ChangelogController::class, 'show'])->name('changelog.show');

// ////////////////////////////////////////////////////////////
// Forum: public read, auth to post. Guests may read the boards.
Route::prefix('forum')->name('forum.')->group(function () {
    Route::get('/', [ForumController::class, 'index'])->name('index');
    Route::get('/search', [ForumController::class, 'search'])->name('search');
    Route::get('/unread', [ForumController::class, 'unread'])->middleware('auth')->name('unread');

    // Leave the detached forum area and return to the normal site.
    Route::get('/exit', fn () => redirect()->route('comics.index'))->name('exit');

    Route::get('/{categorySlug}', [ForumController::class, 'category'])
        ->where('categorySlug', '[A-Za-z0-9\-]+')->name('category');
    Route::get('/{categorySlug}/{threadSlug}', [ForumController::class, 'thread'])
        ->where('threadSlug', '[A-Za-z0-9\-]+')->name('thread');

    Route::middleware('auth')->group(function () {
        Route::post('/{categorySlug}', [ForumThreadController::class, 'store'])
            ->where('categorySlug', '[A-Za-z0-9\-]+')->middleware('throttle:10,1')->name('threads.store');

        Route::post('/threads/{thread}/reply', [ForumPostController::class, 'store'])
            ->middleware('throttle:30,1')->name('posts.store');
        Route::put('/posts/{post}', [ForumPostController::class, 'update'])->name('posts.update');
        Route::delete('/posts/{post}', [ForumPostController::class, 'destroy'])->name('posts.destroy');
        Route::post('/posts/{post}/react', [ForumPostController::class, 'react'])->name('posts.react');
        Route::post('/posts/{post}/report', [ForumPostController::class, 'report'])->name('posts.report');

        Route::post('/threads/{thread}/subscribe', [ForumThreadController::class, 'subscribe'])->name('threads.subscribe');
        // Owner-editable topic banner (premium feature in the future; public for now).
        Route::put('/threads/{thread}', [ForumThreadController::class, 'update'])->name('threads.update');
        Route::post('/threads/{thread}/poll/vote', [ForumPollController::class, 'vote'])
            ->middleware('throttle:20,1')->name('polls.vote');
        Route::post('/threads/{thread}/report', [ForumThreadController::class, 'report'])->name('threads.report');
        Route::post('/mark-solved/{post}', [ForumThreadController::class, 'solve'])->name('threads.solve');

        // Moderation (authorized by policy: moderator-or-admin).
        Route::post('/threads/{thread}/pin', [ForumThreadController::class, 'pin'])->name('threads.pin');
        Route::post('/threads/{thread}/lock', [ForumThreadController::class, 'lock'])->name('threads.lock');
        Route::post('/threads/{thread}/approve', [ForumThreadController::class, 'approve'])->name('threads.approve');
        Route::post('/threads/{thread}/move', [ForumThreadController::class, 'move'])->name('threads.move');
        Route::delete('/threads/{thread}', [ForumThreadController::class, 'destroy'])->name('threads.destroy');
        Route::post('/posts/{post}/approve', [ForumPostController::class, 'approve'])->name('posts.approve');
    });
});

// Marketplace: public read, auth to create/contact. A detached area,
// like the forum, with its own layout and navigation.
Route::prefix('marketplace')->name('marketplace.')->group(function () {
    Route::get('/', [MarketplaceController::class, 'index'])->name('index');
    Route::get('/browse', [MarketplaceController::class, 'browse'])->name('browse');
    Route::get('/search', [MarketplaceController::class, 'search'])->name('search');
    Route::get('/category/{categorySlug}', [MarketplaceController::class, 'category'])
        ->where('categorySlug', '[A-Za-z0-9\-]+')->name('category');

    // Leave the detached marketplace area and return to the normal site.
    Route::get('/exit', fn () => redirect()->route('comics.index'))->name('exit');

    Route::middleware('auth')->group(function () {
        Route::get('/create', [MarketplaceListingController::class, 'create'])->name('create');
        Route::post('/', [MarketplaceListingController::class, 'store'])->name('store');
        Route::get('/mine', [MarketplaceController::class, 'mine'])->name('mine');
        Route::get('/watching', [MarketplaceController::class, 'watching'])->name('watching');

        Route::get('/messages', [MarketplaceMessageController::class, 'index'])->name('messages');
        Route::get('/messages/{conversation}', [MarketplaceMessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{conversation}', [MarketplaceMessageController::class, 'store'])->name('messages.store');

        Route::get('/{listing:slug}/edit', [MarketplaceListingController::class, 'edit'])->name('edit');
        Route::put('/{listing:slug}', [MarketplaceListingController::class, 'update'])->name('update');
        Route::delete('/{listing:slug}', [MarketplaceListingController::class, 'destroy'])->name('destroy');
        Route::post('/{listing:slug}/status', [MarketplaceListingController::class, 'updateStatus'])->name('status');
        Route::post('/{listing:slug}/watch', [MarketplaceListingController::class, 'toggleWatch'])->name('watch');
        Route::post('/{listing:slug}/report', [MarketplaceListingController::class, 'report'])->name('report');
        Route::post('/{listing:slug}/contact', [MarketplaceListingController::class, 'contact'])
            ->middleware('throttle:20,1')->name('contact');
    });

    Route::get('/{listing:slug}', [MarketplaceController::class, 'show'])
        ->where('listing', '[A-Za-z0-9\-]+')->name('show');
});

// Admin → Mercado.
Route::prefix('admin/marketplace')->name('admin.marketplace.')->middleware(['auth', AdminMiddleware::class])->group(function () {
    Route::get('/', [AdminMarketplaceController::class, 'dashboard'])->name('dashboard');
    Route::get('/categories', [AdminMarketplaceController::class, 'categories'])->name('categories');
    Route::get('/categories/create', [AdminMarketplaceController::class, 'categoryCreate'])->name('categories.create');
    Route::post('/categories', [AdminMarketplaceController::class, 'categoryStore'])->name('categories.store');
    Route::get('/categories/{id}/edit', [AdminMarketplaceController::class, 'categoryEdit'])->name('categories.edit');
    Route::put('/categories/{id}', [AdminMarketplaceController::class, 'categoryUpdate'])->name('categories.update');
    Route::delete('/categories/{id}', [AdminMarketplaceController::class, 'categoryDestroy'])->name('categories.destroy');
    Route::post('/categories/{id}/toggle', [AdminMarketplaceController::class, 'categoryToggle'])->name('categories.toggle');
    Route::get('/listings', [AdminMarketplaceController::class, 'listings'])->name('listings');
    Route::post('/listings/{id}/action', [AdminMarketplaceController::class, 'listingAction'])->name('listings.action');
    Route::get('/moderation', [AdminMarketplaceController::class, 'moderation'])->name('moderation');
    Route::post('/moderation/{id}/resolve', [AdminMarketplaceController::class, 'resolveReport'])->name('moderation.resolve');
    Route::get('/settings', [AdminMarketplaceController::class, 'settings'])->name('settings');
    Route::post('/settings', [AdminMarketplaceController::class, 'updateSettings'])->name('settings.update');
    Route::post('/expire', [AdminMarketplaceController::class, 'expireNow'])->name('expire');
});

// Admin → Fórum.
Route::prefix('admin/forum')->name('admin.forum.')->middleware(['auth', AdminMiddleware::class])->group(function () {
    Route::get('/', [AdminForumController::class, 'dashboard'])->name('dashboard');
    Route::get('/categories', [AdminForumController::class, 'categories'])->name('categories');
    Route::get('/categories/create', [AdminForumController::class, 'categoryCreate'])->name('categories.create');
    Route::post('/categories', [AdminForumController::class, 'categoryStore'])->name('categories.store');
    Route::post('/categories/reorder', [AdminForumController::class, 'reorderCategories'])->name('categories.reorder');
    Route::get('/categories/{id}/edit', [AdminForumController::class, 'categoryEdit'])->name('categories.edit');
    Route::put('/categories/{id}', [AdminForumController::class, 'categoryUpdate'])->name('categories.update');
    Route::delete('/categories/{id}', [AdminForumController::class, 'categoryDestroy'])->name('categories.destroy');
    Route::post('/categories/{id}/toggle', [AdminForumController::class, 'categoryToggle'])->name('categories.toggle');
    Route::get('/threads', [AdminForumController::class, 'threads'])->name('threads');
    Route::get('/threads/{id}/edit', [AdminForumController::class, 'threadEdit'])->name('threads.edit');
    Route::put('/threads/{id}', [AdminForumController::class, 'threadUpdate'])->name('threads.update');
    Route::delete('/threads/{id}', [AdminForumController::class, 'threadDestroy'])->name('threads.destroy');
    Route::post('/threads/{id}/restore', [AdminForumController::class, 'threadRestore'])->name('threads.restore');
    Route::get('/moderation', [AdminForumController::class, 'moderation'])->name('moderation');
    Route::post('/moderation/{report}/resolve', [AdminForumController::class, 'resolveReport'])->name('reports.resolve');
    Route::get('/moderators', [AdminForumController::class, 'moderators'])->name('moderators');
    Route::post('/moderators', [AdminForumController::class, 'moderatorStore'])->name('moderators.store');
    Route::delete('/moderators/{id}', [AdminForumController::class, 'moderatorDestroy'])->name('moderators.destroy');
    Route::get('/bans', [AdminForumController::class, 'bans'])->name('bans');
    Route::post('/bans', [AdminForumController::class, 'banStore'])->name('bans.store');
    Route::delete('/bans/{id}', [AdminForumController::class, 'banDestroy'])->name('bans.destroy');
    Route::get('/settings', [AdminForumController::class, 'settings'])->name('settings');
    Route::post('/settings', [AdminForumController::class, 'saveSettings'])->name('settings.save');
});
