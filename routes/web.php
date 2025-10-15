<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;

// Debug route
Route::get('/debug', function () {
    return view('debug');
});

// Main SPA route - catch all routes and let Vue Router handle them
Route::get('/{any}', function () {
    return view('game');
})->where('any', '.*');

// Authentication API routes
Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
// Authentication API routes
Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Vue.js demo route (keep existing for testing)
Route::get('/app-demo', function () {
    return view('game');
});
