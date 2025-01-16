<?php

use App\Http\Controllers\ContainerController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SalesCallCardController;
use App\Http\Controllers\SalesCallCardDeckController;
use App\Http\Controllers\ShipBayController;
use App\Http\Controllers\ShipDockController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

Route::get('/', function () {
    return response()->json(['message' => 'Hello World!']);
});

Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    return "Cache is cleared";
});

Route::get('all-users', [UserController::class, 'getAllUsers']);
Route::get('all-admins', [UserController::class, 'getAllAdmins']);

// Token
Route::post('user/login', [UserController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user/refresh-token', [UserController::class, 'refreshToken']);
});
Route::middleware('auth:sanctum', 'ability:access-api')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// User Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('user/register', [UserController::class, 'register'])->middleware('admin');
    Route::post('user/logout', [UserController::class, 'logout']);
    Route::get('user/{user}', [UserController::class, 'show']);
    Route::put('user/{user}', [UserController::class, 'update'])->middleware('admin');
    Route::delete('user/{user}', [UserController::class, 'destroy'])->middleware('admin');
});

// Admin - Deck Routes
Route::apiResource('decks', SalesCallCardDeckController::class);
Route::post('decks/{deck}/add-card', [SalesCallCardDeckController::class, 'addSalesCallCard']);
Route::delete('decks/{deck}/remove-card/{salesCallCard}', [SalesCallCardDeckController::class, 'removeSalesCallCard']);
Route::get('decks/{deck}/cards', [SalesCallCardDeckController::class, 'showByDeck']);
Route::get('decks/{deck}/origins', [SalesCallCardDeckController::class, 'getOrigins']);

// Admin - Card Routes
Route::apiResource('cards', SalesCallCardController::class);
Route::post('generate-cards/{deck}', [SalesCallCardController::class, 'generate']);
Route::post('/cards/{cardId}/accept', [SalesCallCardController::class, 'accept']);
Route::post('/cards/{cardId}/reject', [SalesCallCardController::class, 'reject']);

// Admin - Container Routes
Route::apiResource('containers', ContainerController::class);

// Basic Room Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('room', [RoomController::class, 'index'])->middleware('admin');
    Route::post('room', [RoomController::class, 'store'])->middleware('admin');
    Route::get('room/{room}', [RoomController::class, 'show']);
    Route::put('room/{room}', [RoomController::class, 'update'])->middleware('admin');
    Route::delete('room/{room}', [RoomController::class, 'destroy'])->middleware('admin');
});

// Other Room Routes
Route::get('room/{room}/users', [RoomController::class, 'getRoomUsers'])->middleware('auth:sanctum');
Route::post('room/{room}/join', [RoomController::class, 'joinRoom'])->middleware('auth:sanctum', 'user');
Route::post('room/{room}/leave', [RoomController::class, 'leaveRoom'])->middleware('auth:sanctum', 'user');
Route::delete('room/{room}/kick/{user}', [RoomController::class, 'kickUser'])->middleware('auth:sanctum', 'admin');
Route::put('room/{room}/swap-bays', [RoomController::class, 'swapBays'])->middleware('auth:sanctum', 'admin');
Route::get('room/{room}/deck-origins', [RoomController::class, 'getDeckOrigins'])->middleware('auth:sanctum');
Route::put('room/{room}/set-ports', [RoomController::class, 'setPorts'])->middleware('auth:sanctum');
Route::put('room/{room}/select-deck', [RoomController::class, 'selectDeck'])->middleware('auth:sanctum', 'admin');
Route::post('room/{room}/save-config', [RoomController::class, 'saveConfig'])->middleware('auth:sanctum', 'admin');
Route::get('room/{room}/config', [RoomController::class, 'getConfig'])->middleware('auth:sanctum');
Route::get('room/{room}/user-port', [RoomController::class, 'getUserPort'])->middleware('auth:sanctum');

// ShipBay Routes
Route::apiResource('ship-bays', ShipBayController::class);
Route::get('ship-bays/{room}/{user}', [ShipBayController::class, 'showBayByUserAndRoom']);
Route::get('moveDragMe', function () {
    return response()->json(['message' => 'Drag me to the right place!']);
});

// ShipDock Routes
Route::apiResource('ship-docks', ShipDockController::class);
Route::get('ship-docks/{room}/{user}', [ShipDockController::class, 'showDockByUserAndRoom']);
