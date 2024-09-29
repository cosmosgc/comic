<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ComicController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PageController;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;

Route::get('/', [ComicController::class, 'index'])->name('comics.index');

Route::prefix('admin')->middleware('auth')->group(function () {
    Route::get('/', [AdminController::class, 'users'])->name('admin.users.index');
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/analytics', [AdminController::class, 'analytics'])->name('admin.analytics');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');

    Route::delete('/users/{id}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
    Route::get('/users/{id}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
    Route::put('/admin/users/{id}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::get('/analytics/referral', [AnalyticsController::class, 'referralAnalytics'])->name('analytics.referral');

});



Route::get('/comics', [ComicController::class, 'index'])->name('comics.index');
Route::get('/comics/create', [ComicController::class, 'create'])->name('comics.create');
Route::post('/comics', [ComicController::class, 'store'])->name('comics.store');

// Route to access a comic by ID
Route::get('/comics/id/{id}', [ComicController::class, 'showById'])->name('comics.showById');

// Route to access a comic by slug
Route::get('/comics/{slug}', [ComicController::class, 'showBySlug'])->name('comics.showBySlug');


Route::get('comics/upload', [ComicController::class, 'create'])->name('comics.upload');
Route::post('comics/store', [ComicController::class, 'store'])->name('comics.store');

Route::get('/comics/{comic}/edit', [ComicController::class, 'edit'])->name('comics.edit');
Route::put('/comics/{comic}', [ComicController::class, 'update'])->name('comics.update');

Route::post('/comics/{comic}/reorder-pages', [ComicController::class, 'reorderPages'])->name('comics.reorderPages');
Route::delete('/comics/{page}/deletePage', [ComicController::class, 'deletePage'])->name('comics.deletePage');

Route::post('/comics/{comic}/pages', [PageController::class, 'store'])->name('pages.store');


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

