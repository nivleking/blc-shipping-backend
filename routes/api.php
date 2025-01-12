<?php

use App\Http\Controllers\ContainerController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SalesCallCardController;
use App\Http\Controllers\SalesCallCardDeckController;
use App\Http\Controllers\ShipBayController;
use App\Http\Controllers\UserController;
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

Route::get('/', function () {
    return response()->json(['message' => 'Hello World!']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('all-users', [UserController::class, 'getAllUsers']);
Route::get('all-admins', [UserController::class, 'getAllAdmins']);

// Token-Based Authentication Routes

// Session-Based Authentication Routes
// Route::middleware('web')->group(function () {
    //     Route::post('user/session-login', [UserController::class, 'sessionLogin']);
    //     Route::post('user/session-logout', [UserController::class, 'sessionLogout']);
    // });

Route::post('user/login', [UserController::class, 'login']);
Route::post('user/logout', [UserController::class, 'logout']);
Route::post('user/register', [UserController::class, 'register'])->middleware('auth:sanctum', 'admin');
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user/{user}', [UserController::class, 'show']);
    Route::put('user/{user}', [UserController::class, 'update'])->middleware('admin');
    Route::delete('user/{user}', [UserController::class, 'destroy'])->middleware('admin');
});

// Admin - Deck Routes
Route::apiResource('sales-call-card-decks', SalesCallCardDeckController::class);
Route::post('sales-call-card-decks/{deck}/add-card', [SalesCallCardDeckController::class, 'addSalesCallCard']);
Route::delete('sales-call-card-decks/{deck}/remove-card/{salesCallCard}', [SalesCallCardDeckController::class, 'removeSalesCallCard']);

Route::get('moveDragMe', function () {
    return response()->json(['message' => 'Drag me to the right place!']);
});

// Admin - Card Routes
Route::apiResource('sales-call-cards', SalesCallCardController::class);
Route::get('generate-sales-call-cards', [SalesCallCardController::class, 'generate']);

// Admin - Container Routes
Route::apiResource('containers', ContainerController::class);

// ShipBay Routes
Route::apiResource('ship-bays', ShipBayController::class);
Route::get('ship-bays/{room}/{user}', [ShipBayController::class, 'showByUserAndRoom']);

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
