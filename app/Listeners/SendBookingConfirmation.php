<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Jobs\SendBookingConfirmation as SendBookingConfirmationJob;

class SendBookingConfirmation
{
    /**
     * Handle the event.
     */
    public function handle(BookingConfirmed $event): void
    {
        SendBookingConfirmationJob::dispatch($event->booking)->onQueue('emails');
    }
}
