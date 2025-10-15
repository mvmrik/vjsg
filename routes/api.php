<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

use App\Http\Controllers\GameController;

// Simple API endpoint for demonstration
Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello from Laravel API!',
        'timestamp' => now(),
        'status' => 'success'
    ]);
});

// User API routes (require authentication)
Route::middleware(['game.auth'])->group(function () {
    Route::get('/user-data', [GameController::class, 'getUserData'])->name('api.user-data');
});

use App\Http\Controllers\ParcelsController;

Route::middleware(['game.auth'])->group(function () {
    Route::get('/parcels', [ParcelsController::class, 'index'])->name('api.parcels.index');
    Route::post('/parcels/claim', [ParcelsController::class, 'claim'])->name('api.parcels.claim');
});

use App\Http\Controllers\CityController;

Route::middleware(['game.auth'])->group(function () {
    Route::get('/city-objects', [CityController::class, 'index'])->name('api.city-objects.index');
    Route::post('/city-objects/save', [CityController::class, 'save'])->name('api.city-objects.save');
    Route::post('/city-objects/upgrade', [CityController::class, 'upgrade'])->name('api.city-objects.upgrade');
    Route::get('/object-types', [CityController::class, 'types'])->name('api.object-types.index');
    Route::get('/people', [\App\Http\Controllers\PeopleController::class, 'index'])->name('api.people.index');
});
