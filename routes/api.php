<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Middleware\EnsureUserIsOrganizer;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    Route::middleware(EnsureUserIsOrganizer::class)->group(function (): void {
        Route::post('/events', [EventController::class, 'store']);
        Route::get('/events/{event}', [EventController::class, 'show']);
        Route::put('/events/{event}', [EventController::class, 'update']);
        Route::delete('/events/{event}', [EventController::class, 'destroy']);
        Route::post('/events/{event}/publish', [EventController::class, 'publish']);
        Route::post('/events/{event}/unpublish', [EventController::class, 'unpublish']);
    });
});
