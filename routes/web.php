<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function() {
    return response()->json(['message' => 'Please use POST /api/login for authentication'], 401);
})->name('login');

Route::get('/api-docs', function() {
    return redirect('/api/documentation');
});