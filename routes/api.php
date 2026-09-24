<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TicketTypeController;
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

        Route::scopeBindings()->group(function (): void {
            Route::post('/events/{event}/ticket-types', [TicketTypeController::class, 'store']);
            Route::get('/events/{event}/ticket-types', [TicketTypeController::class, 'index']);
            Route::get('/events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'show']);
            Route::put('/events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'update']);
            Route::delete('/events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy']);
        });
    });
});
