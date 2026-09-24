<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendBookingConfirmation implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>|int
     */
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(public Booking $booking)
    {
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $booking = $this->booking->loadMissing(['user', 'event', 'ticketType']);

        Log::info("Booking confirmation email sent for Booking #{$booking->id}", [
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'user_email' => $booking->user?->email,
            'event_id' => $booking->event_id,
            'event_title' => $booking->event?->title,
            'ticket_type' => $booking->ticketType?->name,
            'quantity' => $booking->quantity,
            'total_amount' => $booking->total_amount,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("Failed to send booking confirmation for Booking #{$this->booking->id}", [
            'booking_id' => $this->booking->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
