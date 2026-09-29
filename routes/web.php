<?php

use Illuminate\Support\Facades\Route;

// The only HTML page: a shell for the React app. Everything else is the JSON API.
Route::view('/', 'app');
