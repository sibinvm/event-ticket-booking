<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TicketTypeController;
use App\Http\Middleware\EnsureUserIsOrganizer;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/events', [EventController::class, 'index']);

// Protected routes
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // Attendee Booking & Cancellation
    Route::post('/events/{event}/book', [BookingController::class, 'store']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

    // Organizer Event, Ticket Type Management & Reports
    Route::middleware(EnsureUserIsOrganizer::class)->group(function (): void {
        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/events/{event}/reports', [ReportController::class, 'eventReport']);

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
