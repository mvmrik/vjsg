<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarketController;

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

// Market endpoints
Route::post('/market/orders', [MarketController::class, 'order']);
Route::get('/market/{toolType}/orderbook', [MarketController::class, 'orderbook']);
Route::get('/market/{toolType}/trades', [MarketController::class, 'trades']);
Route::get('/market/orders', [MarketController::class, 'userOrders']);

use App\Http\Controllers\GameController;

// Simple API endpoint for demonstration
Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello from Laravel API!',
        'timestamp' => now(),
        'status' => 'success'
    ]);
});

// Translations API
Route::get('/translations/{locale}', function ($locale) {
    $validLocales = ['en', 'bg'];
    if (!in_array($locale, $validLocales)) {
        return response()->json(['error' => 'Invalid locale'], 400);
    }
    
    $translations = [
        'global' => __('global', [], $locale),
        'settings' => __('settings', [], $locale),
        'menu' => __('menu', [], $locale),
        'market' => __('market', [], $locale),
        'events' => __('events', [], $locale),
        'city' => __('city', [], $locale),
        'home' => __('home', [], $locale),
        'map' => __('map', [], $locale),
        'notifications' => __('notifications', [], $locale),
        'help' => __('help', [], $locale),
    ];
    
    return response()->json($translations);
});

// User API routes (require authentication)
Route::middleware(['game.auth'])->group(function () {
    Route::get('/user-data', [GameController::class, 'getUserData'])->name('api.user-data');
    Route::post('/user-data', [GameController::class, 'updateUserData'])->name('api.user-data.update');
    Route::get('/game-settings', [GameController::class, 'getGameSettings'])->name('api.game-settings.get');
    Route::post('/game-settings', [GameController::class, 'setGameSetting'])->name('api.game-settings.set');
    Route::get('/game-settings', [GameController::class, 'getGameSettings'])->name('api.game-settings.get');
    Route::post('/game-settings', [GameController::class, 'setGameSetting'])->name('api.game-settings.set');
    // Stats used by profile/settings page
    Route::get('/stats', [\App\Http\Controllers\StatsController::class, 'index'])->name('api.stats');
    // Events - namespaced controllers under Events folder
    Route::get('/events/current', [\App\Http\Controllers\Events\EventController::class, 'current'])->name('api.events.current');
    Route::post('/events/lottery/enter', [\App\Http\Controllers\Events\LotteryController::class, 'enter'])->name('api.events.lottery.enter');
    Route::get('/events/lottery/jackpot', [\App\Http\Controllers\Events\LotteryController::class, 'jackpot'])->name('api.events.lottery.jackpot');
    Route::get('/events/lottery/history', [\App\Http\Controllers\Events\LotteryController::class, 'history'])->name('api.events.lottery.history');
    // Remember tokens management
    Route::get('/remember-tokens', [\App\Http\Controllers\RememberTokenController::class, 'index']);
    Route::delete('/remember-tokens/{id}', [\App\Http\Controllers\RememberTokenController::class, 'destroy']);
    Route::delete('/remember-tokens', [\App\Http\Controllers\RememberTokenController::class, 'destroyAll']);
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
    Route::post('/city-objects/produce', [CityController::class, 'produce'])->name('api.city-objects.produce');
    Route::get('/object-types', [CityController::class, 'types'])->name('api.object-types.index');
    Route::get('/people', [\App\Http\Controllers\PeopleController::class, 'index'])->name('api.people.index');
});

use App\Http\Controllers\NotificationsController;

Route::middleware(['game.auth'])->group(function () {
    Route::get('/notifications', [NotificationsController::class, 'index'])->name('api.notifications.index');
    Route::get('/notifications/unread-count', [NotificationsController::class, 'unreadCount'])->name('api.notifications.unread-count');
    Route::get('/notifications/latest-unread', [NotificationsController::class, 'latestUnread'])->name('api.notifications.latest-unread');
    Route::get('/notifications/{id}', [NotificationsController::class, 'show'])->name('api.notifications.show');
    Route::patch('/notifications/{id}/read', [NotificationsController::class, 'markAsRead'])->name('api.notifications.mark-read');
    Route::patch('/notifications/mark-all-read', [NotificationsController::class, 'markAllAsRead'])->name('api.notifications.mark-all-read');
    Route::patch('/notifications/{id}/confirm', [NotificationsController::class, 'confirm'])->name('api.notifications.confirm');
});

use App\Http\Controllers\ToolController;

Route::middleware(['game.auth'])->group(function () {
    Route::get('/objects/{objectId}/available-tools', [ToolController::class, 'getAvailableTools']);
    Route::get('/tool-types', [ToolController::class, 'listToolTypes']);
    Route::post('/objects/add-tool', [ToolController::class, 'addTool']);
    Route::get('/objects/{objectId}/tools', [ToolController::class, 'getTools']);
    Route::post('/objects/update-tool-position', [ToolController::class, 'updateToolPosition']);
    
    // Inventory
    Route::get('/inventories', [\App\Http\Controllers\InventoryController::class, 'index']);
});
