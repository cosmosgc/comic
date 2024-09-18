<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ComicController;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ProfileController;

Route::get('/', [ComicController::class, 'index'])->name('comics.index');


Route::get('/comics', [ComicController::class, 'index'])->name('comics.index');
Route::get('/comics/create', [ComicController::class, 'create'])->name('comics.create');
Route::post('/comics', [ComicController::class, 'store'])->name('comics.store');

// Route to access a comic by ID
Route::get('/comics/id/{id}', [ComicController::class, 'showById'])->name('comics.showById');

// Route to access a comic by slug
Route::get('/comics/{slug}', [ComicController::class, 'showBySlug'])->name('comics.showBySlug');


Route::get('comics/upload', [ComicController::class, 'create'])->name('comics.upload');
Route::post('comics/store', [ComicController::class, 'store'])->name('comics.store');





Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');


Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
});
