<?php

namespace App\Console\Commands;

use App\Jobs\SendEventReminder;
use App\Models\Booking;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:send-reminders')]
#[Description('Send reminders to attendees for confirmed bookings of events starting within the next 24 hours')]
class SendEventReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Scanning for eligible bookings for events starting within the next 24 hours...');

        $upcomingCutoff = now()->addHours(24);
        $dispatchedCount = 0;

        Booking::query()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereNull('reminder_sent_at')
            ->whereHas('event', function ($query) use ($upcomingCutoff): void {
                $query->where('status', Event::STATUS_PUBLISHED)
                    ->where('starts_at', '>', now())
                    ->where('starts_at', '<=', $upcomingCutoff);
            })
            ->with(['user', 'event'])
            ->chunkById(200, function ($bookings) use (&$dispatchedCount): void {
                foreach ($bookings as $booking) {
                    SendEventReminder::dispatch($booking)->onQueue('emails');

                    $booking->update([
                        'reminder_sent_at' => now(),
                    ]);

                    $dispatchedCount++;
                }
            });

        $this->info("Successfully processed and dispatched {$dispatchedCount} event reminder(s).");

        return self::SUCCESS;
    }
}
