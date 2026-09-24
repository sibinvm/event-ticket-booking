<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_with_role_and_helper_methods(): void
    {
        $organizer = User::factory()->organizer()->create([
            'email' => 'organizer@example.com',
        ]);

        $this->assertEquals(User::ROLE_ORGANIZER, $organizer->role);
        $this->assertTrue($organizer->isOrganizer());
        $this->assertFalse($organizer->isAttendee());

        $attendee = User::factory()->attendee()->create([
            'email' => 'attendee@example.com',
        ]);

        $this->assertEquals(User::ROLE_ATTENDEE, $attendee->role);
        $this->assertTrue($attendee->isAttendee());
        $this->assertFalse($attendee->isOrganizer());

        // Test assigning BackedEnum directly
        $attendee->role = UserRole::Organizer;
        $this->assertEquals('organizer', $attendee->role);
        $this->assertTrue($attendee->isOrganizer());
    }

    public function test_event_can_be_created_with_organizer_and_casts(): void
    {
        $organizer = User::factory()->organizer()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Tech Summit 2026',
            'description' => 'A premier technology conference.',
            'venue' => 'Grand Hall',
            'starts_at' => '2026-10-15 09:00:00',
            'status' => Event::STATUS_DRAFT,
        ]);

        $this->assertEquals('Tech Summit 2026', $event->title);
        $this->assertEquals('Grand Hall', $event->venue);
        $this->assertEquals(Event::STATUS_DRAFT, $event->status);
        $this->assertTrue($event->isDraft());
        $this->assertFalse($event->isPublished());
        $this->assertInstanceOf(Carbon::class, $event->starts_at);
        $this->assertEquals('2026-10-15 09:00:00', $event->starts_at->format('Y-m-d H:i:s'));

        // Relationship test
        $this->assertTrue($event->organizer->is($organizer));
        $this->assertTrue($organizer->events->contains($event));

        // Test assigning BackedEnum
        $event->status = EventStatus::Published;
        $this->assertEquals('published', $event->status);
        $this->assertTrue($event->isPublished());
    }

    public function test_ticket_types_can_be_created_and_cast_price_and_quantity(): void
    {
        $event = Event::factory()->published()->create();

        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'name' => 'VIP Access',
            'price' => 199.99,
            'quantity' => 100,
        ]);

        $this->assertEquals('VIP Access', $ticketType->name);
        $this->assertEquals('199.99', $ticketType->price);
        $this->assertSame(100, $ticketType->quantity);

        // Relationships
        $this->assertTrue($ticketType->event->is($event));
        $this->assertTrue($event->ticketTypes->contains($ticketType));
    }

    public function test_booking_can_be_created_with_relationships_and_casts(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);
        $ticketType = TicketType::factory()->create([
            'event_id' => $event->id,
            'price' => 50.00,
            'quantity' => 100,
        ]);
        $attendee = User::factory()->attendee()->create();

        $booking = Booking::factory()->confirmed()->create([
            'user_id' => $attendee->id,
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'total_amount' => 100.00,
        ]);

        $this->assertSame(2, $booking->quantity);
        $this->assertEquals('100.00', $booking->total_amount);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertTrue($booking->isConfirmed());
        $this->assertFalse($booking->isCancelled());

        // Relationships
        $this->assertTrue($booking->user->is($attendee));
        $this->assertTrue($booking->event->is($event));
        $this->assertTrue($booking->ticketType->is($ticketType));

        $this->assertTrue($attendee->bookings->contains($booking));
        $this->assertTrue($event->bookings->contains($booking));
        $this->assertTrue($ticketType->bookings->contains($booking));

        // Test BackedEnum status assignment
        $booking->status = BookingStatus::Cancelled;
        $this->assertEquals('cancelled', $booking->status);
        $this->assertTrue($booking->isCancelled());
    }

    public function test_cascading_delete_when_event_is_deleted(): void
    {
        $event = Event::factory()->create();
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);
        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
        ]);

        $this->assertDatabaseHas('events', ['id' => $event->id]);
        $this->assertDatabaseHas('ticket_types', ['id' => $ticketType->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);

        $event->delete();

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('ticket_types', ['id' => $ticketType->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_cascading_delete_when_organizer_user_is_deleted(): void
    {
        $organizer = User::factory()->organizer()->create();
        $event = Event::factory()->create(['organizer_id' => $organizer->id]);
        $ticketType = TicketType::factory()->create(['event_id' => $event->id]);
        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
        ]);

        $organizer->delete();

        $this->assertDatabaseMissing('users', ['id' => $organizer->id]);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('ticket_types', ['id' => $ticketType->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_cascading_delete_when_attendee_user_is_deleted(): void
    {
        $attendee = User::factory()->attendee()->create();
        $booking = Booking::factory()->create(['user_id' => $attendee->id]);

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);

        $attendee->delete();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_cascading_delete_when_ticket_type_is_deleted(): void
    {
        $ticketType = TicketType::factory()->create();
        $booking = Booking::factory()->create([
            'event_id' => $ticketType->event_id,
            'ticket_type_id' => $ticketType->id,
        ]);

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);

        $ticketType->delete();

        $this->assertDatabaseMissing('ticket_types', ['id' => $ticketType->id]);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }
}
