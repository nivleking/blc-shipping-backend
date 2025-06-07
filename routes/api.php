<?php

use App\Http\Controllers\CapacityUptakeController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardTemporaryController;
use App\Http\Controllers\DeckController;
use App\Http\Controllers\MarketIntelligenceController;
use App\Http\Controllers\ShipBayController;
use App\Http\Controllers\ShipDockController;
use App\Http\Controllers\ShipLayoutController;
use App\Http\Controllers\SimulationLogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeeklyPerformanceController;
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

Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    return "Cache is cleared";
});

Route::get('/users', [UserController::class, 'getUsers']);
Route::get('/users/all-users', [UserController::class, 'getAllUsers'])->middleware(['auth:sanctum', 'room.access']);
Route::get('/users/all-admins', [UserController::class, 'getAllAdmins'])->middleware(['auth:sanctum', 'admin']);

// Token
Route::post('/users/login', [UserController::class, 'login']);
// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('users/refresh-token', [UserController::class, 'refreshToken']);
// });
Route::middleware(['auth:sanctum', 'single.session'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

Route::post('/users/force-logout', [UserController::class, 'forceLogout']);

// User Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/users/register', [UserController::class, 'register'])->middleware('admin');
    Route::post('/users/logout', [UserController::class, 'logout']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::get('/users/{userId}/rooms', [UserController::class, 'getUserRooms']);
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('admin');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('admin');
    Route::post('/users/{user}/password', [UserController::class, 'showPassword']);
});

// Admin - Deck Routes
Route::get('/decks/{deck}/origins', [DeckController::class, 'getOrigins'])->middleware(['auth:sanctum', 'admin']);

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/decks', [DeckController::class, 'index']);
    Route::post('/decks', [DeckController::class, 'store']);
    Route::get('/decks/{deck}', [DeckController::class, 'show']);
    Route::put('/decks/{deck}', [DeckController::class, 'update']);
    Route::delete('/decks/{deck}', [DeckController::class, 'destroy']);

    Route::delete('/decks/{deck}/cards', [DeckController::class, 'removeAllCards']);;
    Route::post('/decks/{deck}/import-cards', [CardController::class, 'importFromExcel']);
    Route::post('/decks/{deck}/generate-cards', [CardController::class, 'generate']);
});

// Admin - Card Routes
Route::post('/cards', [CardController::class, 'store'])->middleware(['auth:sanctum', 'admin']);
Route::put('/cards/{card}', [CardController::class, 'update'])->middleware(['auth:sanctum', 'admin']);
Route::delete('/cards/{card}', [CardController::class, 'destroy'])->middleware(['auth:sanctum', 'admin']);

// Basic Room Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms', [RoomController::class, 'store'])->middleware('admin');
    Route::get('/rooms/{room}', [RoomController::class, 'show']);
    Route::put('rooms/{room}', [RoomController::class, 'update'])->middleware('admin');
    Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->middleware('admin');
});

// ShipLayout Routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/ship-layouts', [ShipLayoutController::class, 'index']);
    Route::post('/ship-layouts', [ShipLayoutController::class, 'store']);
    Route::put('/ship-layouts/{shipLayout}', [ShipLayoutController::class, 'update']);
    Route::delete('/ship-layouts/{shipLayout}', [ShipLayoutController::class, 'destroy']);
});

// Simulation Log Routes
Route::get('/simulation-logs/rooms/{roomId}/users/{userId}/', [SimulationLogController::class, 'getUserLogs'])->middleware('auth:sanctum', 'user.ownership');

// Market Intelligence Routes
Route::get('/market-intelligence/deck/{deck}', [MarketIntelligenceController::class, 'forDeck'])->middleware('auth:sanctum');
Route::post('/market-intelligence/deck/{deck}', [MarketIntelligenceController::class, 'storeOrUpdate'])->middleware('auth:sanctum', 'admin');
Route::post('/market-intelligence/deck/{deck}/generate-default', [MarketIntelligenceController::class, 'generateDefault'])->middleware('auth:sanctum', 'admin');

// Other Room Routes
Route::middleware(['auth:sanctum', 'room.access'])->group(function () {
    // Container Routes
    Route::get('/rooms/{roomId}/containers', [ContainerController::class, 'getContainersByRoom']);
    Route::get('/rooms/{roomId}/containers/{container}', [ContainerController::class, 'show']);

    // Room-specific Routes
    Route::post('/rooms/{room}/join', [RoomController::class, 'joinRoom'])->middleware('auth:sanctum', 'user');
    Route::post('/rooms/{room}/leave', [RoomController::class, 'leaveRoom'])->middleware('auth:sanctum', 'user');
    Route::delete('/rooms/{room}/kick/{user}', [RoomController::class, 'kickUser'])->middleware('auth:sanctum', 'admin');
    Route::put('/rooms/{room}/swap-bays', [RoomController::class, 'swapBays'])->middleware('auth:sanctum', 'admin');
    Route::get('/rooms/{room}/users', [RoomController::class, 'getRoomUsers'])->middleware('auth:sanctum');
    Route::put('/rooms/{room}/set-ports', [RoomController::class, 'setPorts'])->middleware('auth:sanctum', 'admin');
    Route::get('/rooms/{room}/user-port', [RoomController::class, 'getUserPortsV1'])->middleware('auth:sanctum');
    Route::get('/rooms/{room}/rankings', [RoomController::class, 'getUsersRanking'])->middleware('auth:sanctum', 'admin');
    Route::put('/rooms/{room}/swap-config', [RoomController::class, 'updateSwapConfig'])->middleware('auth:sanctum', 'admin');
    Route::get('/rooms/{roomId}/port-sequence/{port}', [RoomController::class, 'getProperStackingOrder']);
    Route::get('/rooms/{roomId}/restowage-status', [RoomController::class, 'getRestowageStatus'])->middleware('auth:sanctum');
    Route::get('/rooms/{roomId}/details', [RoomController::class, 'getRoomDetails'])->middleware('auth:sanctum');

    Route::get('/card-temporary/{roomId}/{userId}', [CardTemporaryController::class, 'getCardTemporaries'])->middleware('user.ownership');
    Route::get('/card-temporary/all-cards/{roomId}/{deckId}', [CardTemporaryController::class, 'getAllCardTemporaries']);

    // ShipBay Routes
    Route::post('/rooms/{room}/ship-bays', [ShipBayController::class, 'store'])->middleware(['auth:sanctum', 'user.ownership']);


    // Other ShipBay Routes
    Route::get('/rooms/{room}/ship-bays/arena-data/{userId}', [ShipBayController::class, 'getConsolidatedArenaData'])->middleware('auth:sanctum', 'user.ownership');
    Route::put('/rooms/{room}/ship-bays/{user}/section', [ShipBayController::class, 'updateSection'])->middleware(['auth:sanctum', 'user.ownership']);
    Route::post('/rooms/{room}/ship-bays/{user}/moves', [ShipBayController::class, 'incrementMoves'])->middleware('auth:sanctum, user.ownership');
    Route::post('/rooms/{room}/ship-bays/{user}/cards', [ShipBayController::class, 'incrementCards'])->middleware('auth:sanctum, user.ownership');

    // ShipDock Routes
    Route::post('/rooms/{room}/ship-docks', [ShipDockController::class, 'store'])->middleware(['auth:sanctum', 'user.ownership']);

    // Weekly Performance Routes
    Route::get('/rooms/{roomId}/weekly-performance-all/{userId}', [WeeklyPerformanceController::class, 'getAllWeeklyPerformance'])->middleware('auth:sanctum', 'user.ownership');
    Route::get('/rooms/{roomId}/ship-bays/financial-summary/{userId}', [WeeklyPerformanceController::class, 'getFinancialSummary'])->middleware('auth:sanctum', 'user.ownership');

    // Capacity Uptake Routes
    Route::get('/rooms/{roomId}/capacity-uptakes/{userId}/{week?}', [CapacityUptakeController::class, 'getCapacityUptake'])->middleware('auth:sanctum', 'user.ownership');
});
