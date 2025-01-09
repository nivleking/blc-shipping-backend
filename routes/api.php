<?php

use App\Http\Controllers\RoomController;
use App\Http\Controllers\SalesCallCardController;
use App\Http\Controllers\SalesCallCardDeckController;
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

// User and Admin Routes
Route::prefix('user')->group(
    function () {
        Route::post('/register', [UserController::class, 'register'])->middleware('auth:sanctum');
        Route::post('/login', [UserController::class, 'login']);
        Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('/{user}', [UserController::class, 'show'])->middleware('auth:sanctum', 'admin');
        Route::put('/{user}', [UserController::class, 'update'])->middleware(['auth:sanctum', 'admin']);
        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware(['auth:sanctum', 'admin']);
    }
);

// Admin - Card Routes
Route::apiResource('sales-call-cards', SalesCallCardController::class);
Route::get('generate-sales-call-cards', [SalesCallCardController::class, 'generate']);

// Admin - Deck Routes
Route::apiResource('sales-call-card-decks', SalesCallCardDeckController::class);
Route::post('sales-call-card-decks/{deck}/add-card', [SalesCallCardDeckController::class, 'addSalesCallCard']);
Route::delete('sales-call-card-decks/{deck}/remove-card/{salesCallCard}', [SalesCallCardDeckController::class, 'removeSalesCallCard']);

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
