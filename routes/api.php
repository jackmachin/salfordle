<?php

use App\Http\Controllers\GuessController;
use App\Http\Controllers\PuzzleController;
use App\Http\Controllers\PuzzleImageController;
use App\Http\Controllers\StreetListController;
use Illuminate\Support\Facades\Route;

Route::get('/streets', StreetListController::class);
Route::get('/puzzle', PuzzleController::class);
Route::get('/puzzles/{number}/image', PuzzleImageController::class)->whereNumber('number')->middleware('throttle:puzzle-image');
Route::post('/guesses', GuessController::class)->middleware('throttle:60,1');
