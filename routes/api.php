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

Route::get('users', [UserController::class, 'getUsers']);
Route::get('users/all-users', [UserController::class, 'getAllUsers']);
Route::get('users/all-admins', [UserController::class, 'getAllAdmins']);

// Token
Route::post('users/login', [UserController::class, 'login']);
// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('users/refresh-token', [UserController::class, 'refreshToken']);
// });
Route::middleware('auth:sanctum', 'ability:access-api')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// User Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('users/register', [UserController::class, 'register'])->middleware('admin');
    Route::post('users/logout', [UserController::class, 'logout']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('admin');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('admin');
    Route::post('/users/{user}/password', [UserController::class, 'showPassword']);
});

// Admin - Deck Routes)
Route::get('decks', [DeckController::class, 'index']);
Route::post('decks', [DeckController::class, 'store']);
Route::get('decks/{deck}', [DeckController::class, 'show']);
Route::put('decks/{deck}', [DeckController::class, 'update']);
Route::delete('decks/{deck}', [DeckController::class, 'destroy']);
Route::get('decks/{deck}/origins', [DeckController::class, 'getOrigins']);
Route::delete('decks/{deck}/cards', [DeckController::class, 'removeAllCards']);
Route::post('decks/{deck}/import-cards', [CardController::class, 'importFromExcel']);
Route::post('decks/{deck}/generate-cards', [CardController::class, 'generate']);
// Route::get('decks/{deck}/cards', [DeckController::class, 'showByDeck']);
// Route::post('decks/{deck}/add-card', [DeckController::class, 'addCard']);
// Route::delete('decks/{deck}/remove-card/{cardId}', [DeckController::class, 'removeCard']);

// Market Intelligence Routes
Route::prefix('market-intelligence')->group(function () {
    Route::get('/deck/{deck}', [MarketIntelligenceController::class, 'forDeck']);
    Route::post('/deck/{deck}', [MarketIntelligenceController::class, 'storeOrUpdate']);
    Route::post('/deck/{deck}/generate-default', [MarketIntelligenceController::class, 'generateDefault']);
    Route::delete('/{marketIntelligence}', [MarketIntelligenceController::class, 'destroy']);
});

// Admin - Card Routes
Route::get('cards', [CardController::class, 'index']);
Route::post('cards', [CardController::class, 'store']);
Route::get('cards/{card}', [CardController::class, 'show']);
Route::put('cards/{card}', [CardController::class, 'update']);
Route::delete('cards/{card}', [CardController::class, 'destroy']);

// Admin - Container Routes
// Route::apiResource('containers', ContainerController::class);
Route::get('containers', [ContainerController::class, 'index']);
Route::post('/containers/destinations', [ContainerController::class, 'getContainerDestinations']);
// Route::post('containers', [ContainerController::class, 'store']);
Route::get('containers/{container}', [ContainerController::class, 'show']);
// Route::put('containers/{container}', [ContainerController::class, 'update']);
// Route::delete('containers/{container}', [ContainerController::class, 'destroy']);

// Basic Room Routes
Route::middleware('auth:sanctum')->group(function () {
    // Route::get('rooms/available-users', [RoomController::class, 'getAvailableUsers']);
    Route::get('rooms', [RoomController::class, 'index'])->middleware('admin');
    Route::post('rooms', [RoomController::class, 'store'])->middleware('admin');
    Route::get('rooms/{room}', [RoomController::class, 'show']);
    Route::put('rooms/{room}', [RoomController::class, 'update']);
    Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->middleware('admin');
});

// Other Room Routes
Route::post('rooms/{room}/join', [RoomController::class, 'joinRoom'])->middleware('auth:sanctum', 'user');
Route::post('rooms/{room}/leave', [RoomController::class, 'leaveRoom'])->middleware('auth:sanctum', 'user');
Route::delete('rooms/{room}/kick/{user}', [RoomController::class, 'kickUser'])->middleware('auth:sanctum', 'admin');
Route::put('rooms/{room}/swap-bays', [RoomController::class, 'swapBays'])->middleware('auth:sanctum', 'admin');
// Route::put('rooms/{room}/swap-bays-custom', [RoomController::class, 'swapBaysCustom'])->middleware('auth:sanctum', 'admin');
Route::get('rooms/{room}/users', [RoomController::class, 'getRoomUsers'])->middleware('auth:sanctum');
Route::get('rooms/{room}/deck-origins', [RoomController::class, 'getDeckOrigins'])->middleware('auth:sanctum');
Route::put('rooms/{room}/set-ports', [RoomController::class, 'setPorts'])->middleware('auth:sanctum');
Route::get('rooms/{room}/config', [RoomController::class, 'getBayConfig'])->middleware('auth:sanctum');
Route::get('rooms/{room}/user-port', [RoomController::class, 'getUserPortsV1'])->middleware('auth:sanctum');
Route::get('rooms/{room}/user-port2', [RoomController::class, 'getUserPortsV2'])->middleware('auth:sanctum');
Route::get('rooms/{room}/rankings', [RoomController::class, 'getUsersRanking'])->middleware('auth:sanctum');
Route::put('rooms/{room}/swap-config', [RoomController::class, 'updateSwapConfig'])->middleware('auth:sanctum');

// Route::post('rooms/{room}/create-card-temporary/{user}', [RoomController::class, 'createCardTemporary'])->middleware('auth:sanctum', 'admin');
Route::get('card-temporary/{roomId}/{userId}', [RoomController::class, 'getCardTemporaries']);
Route::post('card-temporary/accept', [RoomController::class, 'acceptCardTemporary'])->middleware('auth:sanctum');
Route::post('card-temporary/reject', [RoomController::class, 'rejectCardTemporary'])->middleware('auth:sanctum');
Route::post('/card-temporary/batch', [CardTemporaryController::class, 'batchCreate']);

// ShipBay Routes
Route::get('ship-bays', [ShipBayController::class, 'index']);
Route::post('ship-bays', [ShipBayController::class, 'store']);
Route::get('ship-bays/{shipBay}', [ShipBayController::class, 'show']);
// Route::put('ship-bays/{shipBay}', [ShipBayController::class, 'update']);
// Route::delete('ship-bays/{shipBay}', [ShipBayController::class, 'destroy']);

// Other ShipBay Routes
Route::get('ship-bays/{room}/{user}', [ShipBayController::class, 'showBayByUserAndRoom']);
Route::put('ship-bays/{room}/{user}/section', [ShipBayController::class, 'updateSection']);
Route::post('ship-bays/{room}/{user}/moves', [ShipBayController::class, 'incrementMoves'])->middleware('auth:sanctum');
Route::post('ship-bays/{room}/{user}/cards', [ShipBayController::class, 'incrementCards'])->middleware('auth:sanctum');
Route::get('ship-bays/{room}/{user}/statistics', [ShipBayController::class, 'getBayStatistics']);
Route::get('rooms/{roomId}/users/{userId}/bay-statistics-history/{week?}', [ShipBayController::class, 'getBayStatisticsHistory'])->middleware('auth:sanctum');

// ShipLayout Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('ship-layouts', [ShipLayoutController::class, 'index']);
    Route::post('ship-layouts', [ShipLayoutController::class, 'store']);
    Route::get('ship-layouts/{shipLayout}', [ShipLayoutController::class, 'show']);
    Route::put('ship-layouts/{shipLayout}', [ShipLayoutController::class, 'update']);
    Route::delete('ship-layouts/{shipLayout}', [ShipLayoutController::class, 'destroy']);
});

// ShipDock Routes
// Route::apiResource('ship-docks', ShipDockController::class);
// Route::get('ship-docks', [ShipDockController::class, 'index']);
Route::post('ship-docks', [ShipDockController::class, 'store']);
// Route::get('ship-docks/{shipDock}', [ShipDockController::class, 'show']);
// Route::put('ship-docks/{shipDock}', [ShipDockController::class, 'update']);
// Route::delete('ship-docks/{shipDock}', [ShipDockController::class, 'destroy']);

// Other ShipDock Routes
Route::get('ship-docks/{room}/{user}', [ShipDockController::class, 'showDockByUserAndRoom']);

// Simulation Log Routes
// Route::get('simulation-logs', [SimulationLogController::class, 'index'])->middleware('auth:sanctum', 'admin');
Route::post('simulation-logs', [SimulationLogController::class, 'store'])->middleware('auth:sanctum');
Route::get('simulation-logs/{simulationLog}', [SimulationLogController::class, 'show'])->middleware('auth:sanctum', 'admin');
// Route::put('simulation-logs/{simulationLog}', [SimulationLogController::class, 'update'])->middleware('auth:sanctum', 'admin');
Route::delete('simulation-logs/{simulationLog}', [SimulationLogController::class, 'destroy'])->middleware('auth:sanctum', 'admin');
Route::get('simulation-logs/{roomId}/{userId}', [SimulationLogController::class, 'getByRoomAndUser']);

// Weekly Performance Routes
Route::get('/rooms/{roomId}/users/{userId}/weekly-performance/{week?}', [WeeklyPerformanceController::class, 'getWeeklyPerformance']);
Route::post('/rooms/{roomId}/users/{userId}/weekly-performance/{week}', [WeeklyPerformanceController::class, 'updateWeeklyPerformance']);

// Capacity Uptake Routes
Route::get('/capacity-uptakes/{roomId}/{userId}/{week?}', [CapacityUptakeController::class, 'getCapacityUptake']);
Route::post('/capacity-uptakes/{roomId}/{userId}/{week}', [CapacityUptakeController::class, 'updateCapacityUptake']);
