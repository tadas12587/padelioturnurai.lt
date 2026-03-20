<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

// Routes without locale prefix (default lt)
Route::middleware('setlocale')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/turnyrai', [TournamentController::class, 'index'])->name('tournaments');
    Route::get('/turnyrai/{slug}', [TournamentController::class, 'show'])->name('tournament.show');
    Route::get('/kontaktai', [ContactController::class, 'index'])->name('contact');
    Route::post('/kontaktai', [ContactController::class, 'store'])->name('contact.store');
});

// Routes with locale prefix (lt or en)
Route::prefix('{locale}')->where(['locale' => 'lt|en'])->middleware('setlocale')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home.locale');
    Route::get('/turnyrai', [TournamentController::class, 'index'])->name('tournaments.locale');
    Route::get('/turnyrai/{slug}', [TournamentController::class, 'show'])->name('tournament.show.locale');
    Route::get('/kontaktai', [ContactController::class, 'index'])->name('contact.locale');
    Route::post('/kontaktai', [ContactController::class, 'store'])->name('contact.store.locale');
});
