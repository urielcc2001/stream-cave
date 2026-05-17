<?php

use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => redirect()->route('catalog'));

Route::middleware('auth')->group(function () {
    Route::get('/catalog', [MediaController::class, 'index'])->name('catalog');
    Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show');
    Route::get('/media/{media}/play', [MediaController::class, 'play'])->name('media.play');
    Route::get('/episode/{episode}/play', [MediaController::class, 'playEpisode'])->name('episode.play');
    Route::get('/media/{media}/stream', [MediaController::class, 'stream'])->name('media.stream');
    Route::get('/episode/{episode}/stream', [MediaController::class, 'streamEpisode'])->name('episode.stream');
    Route::get('/media/{media}/subtitles/{index}', [MediaController::class, 'subtitles'])->name('media.subtitles');
    Route::get('/episode/{episode}/subtitles/{index}', [MediaController::class, 'subtitlesEpisode'])->name('episode.subtitles');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
