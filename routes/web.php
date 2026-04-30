<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\RegistrationInterestController;
use Illuminate\Support\Facades\Route;

// Interest notification endpoint (locale-agnostic JSON API)
Route::post('/registracija-pranesimai', [RegistrationInterestController::class, 'store'])->name('interest.store');

// Admin CSV export — protected by Filament auth
Route::get('/admin/interests/export', [RegistrationInterestController::class, 'export'])
    ->middleware(['auth'])
    ->name('admin.interests.export');

// Routes without locale prefix (default lt)
Route::middleware('setlocale')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/turnyrai', [TournamentController::class, 'index'])->name('tournaments');
    Route::get('/turnyrai/{slug}', [TournamentController::class, 'show'])->name('tournament.show');
    Route::get('/naujienos', [NewsController::class, 'index'])->name('news.index');
    Route::get('/naujienos/{slug}', [NewsController::class, 'show'])->name('news.show');
    Route::get('/kontaktai', [ContactController::class, 'index'])->name('contact');
    Route::post('/kontaktai', [ContactController::class, 'store'])->middleware('throttle:3,10')->name('contact.store');
    Route::get('/tapk-remeju', [ProposalController::class, 'index'])->name('proposal');
});

// Routes with locale prefix (lt or en)
Route::prefix('{locale}')->where(['locale' => 'lt|en'])->middleware('setlocale')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home.locale');
    Route::get('/turnyrai', [TournamentController::class, 'index'])->name('tournaments.locale');
    Route::get('/turnyrai/{slug}', [TournamentController::class, 'show'])->name('tournament.show.locale');
    Route::get('/naujienos', [NewsController::class, 'index'])->name('news.index.locale');
    Route::get('/naujienos/{slug}', [NewsController::class, 'show'])->name('news.show.locale');
    Route::get('/kontaktai', [ContactController::class, 'index'])->name('contact.locale');
    Route::post('/kontaktai', [ContactController::class, 'store'])->middleware('throttle:3,10')->name('contact.store.locale');
    Route::get('/tapk-remeju', [ProposalController::class, 'index'])->name('proposal.locale');
});
