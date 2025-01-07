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

Route::get('/admin', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('user')->group(
    function () {
        Route::post('/register', [UserController::class, 'register']);
        Route::post('/login', [UserController::class, 'login']);
        Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:sanctum');
        Route::post('rooms/{room}/join', [RoomController::class, 'joinRoom'])->middleware('auth:sanctum');
    }
);

Route::apiResource('rooms', RoomController::class)->middleware('auth:sanctum');
Route::get('rooms/{room}/users', [RoomController::class, 'getRoomUsers'])->middleware('auth:sanctum');

Route::prefix('admin')->group(
    function () {
        Route::post('/register', [AdminController::class, 'register']);
        Route::post('/login', [AdminController::class, 'login']);
        Route::post('/logout', [AdminController::class, 'logout'])->middleware('auth:sanctum');
        Route::delete('rooms/{room}/kick/{user}', [RoomController::class, 'kickUser'])->middleware('auth:sanctum');
    }
);
