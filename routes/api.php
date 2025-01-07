<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\RoomController;
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

// User Routes
Route::prefix('user')->group(
    function () {
        Route::post('/register', [UserController::class, 'register']);
        Route::post('/login', [UserController::class, 'login']);
        Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:sanctum');
    }
);

// Basic Room Routes
Route::apiResource('room', RoomController::class)->middleware('auth:sanctum');

// Other Room Routes
Route::get('room/{room}/users', [RoomController::class, 'getRoomUsers'])->middleware('auth:sanctum');
Route::post('room/{room}/join', [RoomController::class, 'joinRoom'])->middleware('auth:sanctum');
Route::post('room/{room}/leave', [RoomController::class, 'leaveRoom'])->middleware('auth:sanctum');
Route::delete('room/{room}/kick/{user}', [RoomController::class, 'kickUser'])->middleware('auth:sanctum');
