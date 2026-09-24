# Event Ticket Booking REST API

A robust, production-ready RESTful API for an Event Ticket Booking platform built with **Laravel 13**, **MySQL**, and **Laravel Sanctum**. This project features role-based access control, pessimistic concurrency locking to prevent overselling, automated event reminders, event-driven queue processing, and memory-safe reporting.

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Key Features](#key-features)
3. [Tech Stack](#tech-stack)
4. [System Requirements](#system-requirements)
5. [Installation Guide](#installation-guide)
6. [Environment Configuration](#environment-configuration)
7. [Database Architecture](#database-architecture)
8. [Migrations and Seeding](#migrations-and-seeding)
9. [Authentication with Sanctum](#authentication-with-sanctum)
10. [Roles and Permissions](#roles-and-permissions)
11. [API Endpoints](#api-endpoints)
12. [Request and Response Examples](#request-and-response-examples)
13. [Event and Ticket Type Management](#event-and-ticket-type-management)
14. [Ticket Booking Flow](#ticket-booking-flow)
15. [Concurrency and Oversold Prevention](#concurrency-and-oversold-prevention)
16. [Maximum 5 Tickets per User Rule](#maximum-5-tickets-per-user-rule)
17. [Booking Cancellation and 24-Hour Rule](#booking-cancellation-and-24-hour-rule)
18. [Events, Listeners, and Queued Jobs](#events-listeners-and-queued-jobs)
19. [Event Reminders Command and Scheduler](#event-reminders-command-and-scheduler)
20. [Reporting APIs](#reporting-apis)
21. [Queue Worker Setup](#queue-worker-setup)
22. [Postman Collection](#postman-collection)
23. [Default Test Credentials](#default-test-credentials)
24. [Development and Testing](#development-and-testing)

---

## Project Overview

The Event Ticket Booking REST API enables event organizers to manage events and ticket types, while allowing attendees to discover published upcoming events and book tickets securely. The system is engineered to handle high-concurrency booking rushes, preventing race conditions and inventory overselling through pessimistic row locking (`lockForUpdate()`) and atomic database transactions.

---

## Key Features

- **Token Authentication:** Secure API authentication via Laravel Sanctum Bearer tokens.
- **Role-Based Authorization:** Strict boundary separation between `organizer` and `attendee` roles.
- **Organizer Event Management:** Complete CRUD, publish, unpublish, and policy-driven ownership protection.
- **Ticket Type Management:** Dynamic ticket tiers (e.g., General, VIP, Early Bird) with real-time inventory tracking.
- **Public Event Discovery:** Search by title, filter by date ranges (`from` / `to`), with pagination and eager-loaded relationships.
- **Pessimistic Concurrency Locking:** `DB::transaction()` combined with `lockForUpdate()` prevents overselling during high-volume concurrent booking attempts.
- **Purchase Limits:** Attendees cannot purchase more than 5 tickets per event across all confirmed bookings.
- **Synchronous Seat Restoration:** Booking cancellations automatically and atomically return ticket inventory to the pool if cancelled at least 24 hours before event start.
- **Event-Driven Architecture:** `BookingConfirmed` event dispatches queued email jobs, updates sales statistics, and detects sold-out inventory.
- **Automated Reminders:** Hourly scheduled Artisan command scans upcoming events within 24 hours, memory-batched using `chunkById()`.
- **Memory-Safe Reporting:** Summary and event-specific sales and revenue analytics processed without loading unbounded records into memory.

---

## Tech Stack

- **Framework:** Laravel 13 (PHP ^8.3 / 8.5)
- **Database:** MySQL 8.0+
- **Authentication:** Laravel Sanctum 4.x
- **Queue System:** Laravel Database Queue (`jobs` table)
- **Cache System:** Database Cache
- **Code Formatter:** Laravel Pint
- **Test Runner:** PHPUnit 12.x

---

## System Requirements

- PHP >= 8.3 with extensions: `pdo_mysql`, `bcmath`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- Composer 2.x
- MySQL 8.0+
- Git

---

## Installation Guide

```bash
# 1. Clone the repository
git clone <repository-url>
cd event-ticket-booking

# 2. Install Composer dependencies
composer install

# 3. Create your local environment file
cp .env.example .env

# 4. Generate the application encryption key
php artisan key:generate
```

---

## Environment Configuration

Configure your MySQL and Queue settings in `.env`:

```dotenv
APP_NAME="Event Ticket Booking API"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=event_ticket_booking
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=database
```

---

## Database Architecture

### Entity Relationship Overview

```
users (organizers & attendees)
  │
  ├── 1:N ──> events (owned by organizer)
  │             │
  │             ├── 1:N ──> ticket_types
  │             │             │
  │             │             └── 1:N ──> bookings
  │             │                           │
  │             └── 1:N ────────────────────┘
  │
  └── 1:N ──> bookings (created by attendee)
```

### Core Tables

1. **`users`**: Contains user credentials and roles (`organizer`, `attendee`).
2. **`events`**: Events created by organizers with fields `title`, `description`, `venue`, `starts_at`, `status` (`draft` or `published`), and foreign key `organizer_id`.
3. **`ticket_types`**: Ticket tiers for events with fields `name`, `price`, `quantity` (available count), and foreign key `event_id`.
4. **`bookings`**: Confirmed or cancelled ticket reservations with `quantity`, `total_amount`, `status` (`confirmed`, `cancelled`), `reminder_sent_at`, and foreign keys `user_id`, `event_id`, `ticket_type_id`.
5. **`jobs`**: Background database queue table storing asynchronous jobs.

---

## Migrations and Seeding

Run the migrations to create all database tables:

```bash
# Run migrations
php artisan migrate

# Seed database with realistic organizer, attendee, event, and ticket type data
php artisan db:seed

# Optional: Reset database and run fresh migrations with seeds
php artisan migrate:fresh --seed
```

All seeders utilize `updateOrCreate()` to ensure idempotent execution. Running `php artisan db:seed` multiple times will not create duplicate records or violate database constraints.

---

## Authentication with Sanctum

Authentication uses **Laravel Sanctum** plain-text API tokens.

1. **Obtain Token:** Send credentials to `POST /api/register` or `POST /api/login`.
2. **Access Protected Endpoints:** Include the returned token in the `Authorization` header of subsequent HTTP requests:

```http
Authorization: Bearer <your_token_here>
Accept: application/json
```

3. **Revoke Token:** Call `POST /api/logout` with the Bearer token to delete the active token.

---

## Roles and Permissions

The application defines two distinct roles:

| Feature / Action | Attendee | Organizer | Public (Guest) |
| :--- | :---: | :---: | :---: |
| View Public Upcoming Events (`GET /api/events`) | Yes | Yes | Yes |
| Book Event Tickets (`POST /api/events/{event}/book`) | **Yes** | No (403) | No (401) |
| Cancel Own Booking (`POST /api/bookings/{booking}/cancel`) | **Yes** | No (403) | No (401) |
| Create & Manage Events (`/api/events/*`) | No (403) | **Yes (Own only)** | No (401) |
| Create & Manage Ticket Types (`/api/events/{event}/ticket-types/*`) | No (403) | **Yes (Own only)** | No (401) |
| View Sales & Summary Reports (`/api/reports/*`) | No (403) | **Yes** | No (401) |

---

## API Endpoints

### 1. Authentication (Public & Protected)

| Method | Endpoint | Auth Required | Description |
| :--- | :--- | :---: | :--- |
| `POST` | `/api/register` | No | Register a new user (`organizer` or `attendee`) |
| `POST` | `/api/login` | No | Authenticate credentials and receive Bearer token |
| `GET` | `/api/profile` | Yes | Retrieve the authenticated user's profile |
| `POST` | `/api/logout` | Yes | Revoke the active Bearer token |

### 2. Public Events

| Method | Endpoint | Auth Required | Description |
| :--- | :--- | :---: | :--- |
| `GET` | `/api/events` | No | List published upcoming events with pagination, search, and date filters |

### 3. Organizer Event Management (Protected: Organizer Only)

| Method | Endpoint | Auth Required | Description |
| :--- | :--- | :---: | :--- |
| `POST` | `/api/events` | Organizer | Create a new event (defaults to `draft`) |
| `GET` | `/api/events/{event}` | Organizer | View details of an owned event |
| `PUT` | `/api/events/{event}` | Organizer | Update an owned event |
| `DELETE` | `/api/events/{event}` | Organizer | Delete an owned event |
| `POST` | `/api/events/{event}/publish` | Organizer | Publish an event (`status = 'published'`) |
| `POST` | `/api/events/{event}/unpublish` | Organizer | Revert an event to draft (`status = 'draft'`) |

### 4. Organizer Ticket Type Management (Protected: Organizer Only)

| Method | Endpoint | Auth Required | Description |
| :--- | :--- | :---: | :--- |
| `POST` | `/api/events/{event}/ticket-types` | Organizer | Create a ticket tier for an owned event |
| `GET` | `/api/events/{event}/ticket-types` | Organizer | List ticket tiers for an owned event |
| `GET` | `/api/events/{event}/ticket-types/{ticketType}` | Organizer | View a ticket tier |
| `PUT` | `/api/events/{event}/ticket-types/{ticketType}` | Organizer | Update a ticket tier |
| `DELETE` | `/api/events/{event}/ticket-types/{ticketType}` | Organizer | Delete a ticket tier |

### 5. Attendee Bookings (Protected: Attendee Only)

| Method | Endpoint | Auth Required | Description |
| :--- | :--- | :---: | :--- |
| `POST` | `/api/events/{event}/book` | Attendee | Book tickets atomically with concurrency protection |
| `POST` | `/api/bookings/{booking}/cancel` | Attendee | Cancel an owned booking and restore ticket inventory |

### 6. Reports (Protected: Organizer Only)

| Method | Endpoint | Auth Required | Description |
| :--- | :--- | :---: | :--- |
| `GET` | `/api/reports/summary` | Organizer | Overall summary of confirmed bookings, tickets sold, and revenue |
| `GET` | `/api/events/{event}/reports` | Organizer | Event-specific report (event owner only) |

---

## Request and Response Examples

### 1. Register User (`POST /api/register`)

**Request:**
```http
POST /api/register
Content-Type: application/json
Accept: application/json

{
    "name": "Jane Attendee",
    "email": "jane@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "attendee"
}
```

**Response (`201 Created`):**
```json
{
    "user": {
        "id": 2,
        "name": "Jane Attendee",
        "email": "jane@example.com",
        "role": "attendee",
        "created_at": "2026-09-24T08:00:00.000000Z",
        "updated_at": "2026-09-24T08:00:00.000000Z"
    },
    "token": "1|sanctum_plain_text_token_here"
}
```

---

### 2. Book Tickets (`POST /api/events/{event}/book`)

**Request:**
```http
POST /api/events/1/book
Authorization: Bearer 1|sanctum_plain_text_token_here
Content-Type: application/json
Accept: application/json

{
    "ticket_type_id": 1,
    "quantity": 2
}
```

**Response (`201 Created`):**
```json
{
    "data": {
        "id": 1,
        "event": {
            "id": 1,
            "title": "Tech Innovation Summit 2026",
            "venue": "Silicon Convention Center, Hall A",
            "starts_at": "2026-10-15T09:00:00.000000Z"
        },
        "ticket_type": {
            "id": 1,
            "name": "General Admission",
            "price": "49.99"
        },
        "quantity": 2,
        "total_amount": "99.98",
        "status": "confirmed",
        "created_at": "2026-09-24T08:15:00.000000Z"
    }
}
```

---

### 3. Cancel Booking (`POST /api/bookings/{booking}/cancel`)

**Request:**
```http
POST /api/bookings/1/cancel
Authorization: Bearer 1|sanctum_plain_text_token_here
Accept: application/json
```

**Response (`200 OK`):**
```json
{
    "data": {
        "id": 1,
        "event": {
            "id": 1,
            "title": "Tech Innovation Summit 2026",
            "venue": "Silicon Convention Center, Hall A",
            "starts_at": "2026-10-15T09:00:00.000000Z"
        },
        "ticket_type": {
            "id": 1,
            "name": "General Admission",
            "price": "49.99"
        },
        "quantity": 2,
        "total_amount": "99.98",
        "status": "cancelled",
        "created_at": "2026-09-24T08:15:00.000000Z"
    }
}
```

---

### 4. Overall Sales Summary Report (`GET /api/reports/summary`)

**Response (`200 OK`):**
```json
{
    "total_bookings": 1420,
    "total_tickets_sold": 3850,
    "total_revenue": "192500.00"
}
```

---

## Event and Ticket Type Management

- **Event Ownership:** Governed by `EventPolicy`. Organizers can view, update, delete, publish, or unpublish only events where `organizer_id === auth()->id()`. Other organizers receive `403 Forbidden`.
- **Ticket Type Ownership:** Governed by `TicketTypePolicy`. Ticket types can only be modified if the parent event belongs to the authenticated organizer.
- **Publish Status:** Events are created as `draft`. Once published, they appear on the public `GET /api/events` listing provided `starts_at > now()`.

---

## Ticket Booking Flow

1. Validates that the user is authenticated and holds the `attendee` role.
2. Validates that the route event is `published` and `starts_at > now()`.
3. Validates that the submitted `ticket_type_id` actually belongs to the route event.
4. Executes the atomic reservation within a database transaction.
5. Calculates `total_amount = ticket_type.price * quantity` server-side (client input for price or total is never trusted).
6. Atomically creates the confirmed booking record and decrements available inventory.
7. Dispatches the `BookingConfirmed` event.

---

## Concurrency and Oversold Prevention

### The Race Condition Problem
When two attendees attempt to purchase the final available ticket simultaneously:
- Without locking, Request A and Request B both read `quantity = 1`.
- Both proceed, create 2 bookings, and decrease inventory, leading to an oversold state (`quantity = -1`).

### The Solution: `DB::transaction()` and `lockForUpdate()`
The application utilizes MySQL InnoDB exclusive row-level locking:

```php
$booking = DB::transaction(function () use ($user, $event, $ticketTypeId, $requestedQuantity) {
    // 1. Acquire exclusive lock on the ticket_types row
    $ticketType = TicketType::where('id', $ticketTypeId)
        ->where('event_id', $event->id)
        ->lockForUpdate()
        ->first();

    // 2. Availability check happens strictly AFTER acquiring the lock
    if ($ticketType->quantity < $requestedQuantity) {
        throw new HttpResponseException(response()->json([
            'message' => 'Not enough tickets available for this ticket type.',
        ], 409));
    }

    // 3. Create booking and decrement quantity atomically
    $booking = Booking::create([...]);
    $ticketType->decrement('quantity', $requestedQuantity);

    return $booking;
});
```

- When Request A locks the row, MySQL forces Request B to wait until Request A commits or rolls back.
- Request B then acquires the lock and reads the committed quantity (`0`), cleanly rejecting the request with HTTP `409 Conflict`.
- If an exception occurs, the transaction automatically rolls back, ensuring inventory is never altered unless the booking is permanently saved.

---

## Maximum 5 Tickets per User Rule

Attendees are limited to a maximum of **5 tickets per event** across all confirmed bookings.

1. The check runs inside the database transaction using `lockForUpdate()`:
   ```php
   $existingQuantity = (int) Booking::where('user_id', $user->id)
       ->where('event_id', $event->id)
       ->where('status', Booking::STATUS_CONFIRMED)
       ->lockForUpdate()
       ->sum('quantity');
   ```
2. If `($existingQuantity + $requestedQuantity) > 5`, the booking is rejected with HTTP `422 Unprocessable Content`:
   ```json
   {
       "message": "You cannot purchase more than 5 tickets per event. You currently have 3 confirmed ticket(s)."
   }
   ```

---

## Booking Cancellation and 24-Hour Rule

Attendees can cancel confirmed bookings, restoring seats immediately:

1. **Authorization:** Only the attendee who created the booking can cancel it. Others receive HTTP `403 Forbidden`.
2. **Status Check:** Only `confirmed` bookings can be cancelled. Already cancelled bookings return HTTP `422`.
3. **24-Hour Cutoff:**
   - If the event has already started (`starts_at <= now()`), returns HTTP `422`.
   - If the event starts in less than 24 hours (`starts_at <= now()->addHours(24)`), returns HTTP `422`.
4. **Atomic Restoration:**
   - Performed synchronously within `DB::transaction()` with `lockForUpdate()` on `ticket_types`.
   - The booking status transitions to `cancelled`, and `ticket_types.quantity` is immediately incremented.
   - No asynchronous jobs or delayed queues are used for seat restoration.

---

## Events, Listeners, and Queued Jobs

### 1. `BookingConfirmed` Event
Dispatched only after the booking transaction successfully commits to the database:
- **`SendBookingConfirmation` Listener & Job:** Dispatches the `SendBookingConfirmation` job to the `emails` queue with 3 retry attempts, exponential backoff (`[10, 30, 60]`), and a `failed()` logger.
- **`UpdateEventSalesStats` Listener:** Synchronously calculates confirmed bookings, tickets sold, and revenue for the event and caches them.
- **`NotifyOrganizerWhenSoldOut` Listener:** Queued listener that triggers only when the booked ticket type reaches `quantity === 0`.

### 2. Queued Jobs
- `SendBookingConfirmation`: Logs confirmation email dispatch on the `emails` queue.
- `SendEventReminder`: Logs event reminder email on the `emails` queue with confirmed status check.

---

## Event Reminders Command and Scheduler

### Hourly Artisan Command
```bash
php artisan events:send-reminders
```

- **Query:** Finds confirmed bookings where `reminder_sent_at IS NULL` and `events.starts_at` is between `now()` and `now()->addHours(24)`.
- **Memory Efficiency:** Uses `chunkById(200)` to handle 10,000+ bookings with minimal memory consumption.
- **Strict Idempotency:** Marks `reminder_sent_at = now()` immediately after dispatching `SendEventReminder`, guaranteeing each booking receives exactly one reminder.
- **Scheduler:** Configured in `routes/console.php` to run hourly (`Schedule::command('events:send-reminders')->hourly()`).

---

## Reporting APIs

1. **`GET /api/reports/summary`**: Returns total confirmed bookings, total tickets sold, and total revenue across the platform for organizers.
2. **`GET /api/events/{event}/reports`**: Returns event details and sales metrics for a specific event. Restricted to the event owner (other organizers receive `403 Forbidden`).
3. **Memory Optimization:** Both endpoints iterate using `chunkById(500)` and `bcadd()`, preventing out-of-memory errors on large datasets.

---

## Queue Worker Setup

Process background jobs (booking confirmations and event reminders) dispatched to the database queue:

```bash
# Process the emails queue
php artisan queue:work --queue=emails

# Process both emails and default queues (priority to emails)
php artisan queue:work --queue=emails,default

# Process a single job (testing)
php artisan queue:work --queue=emails --once
```

---

## Postman Collection

A complete, validated **Postman Collection v2.1** is included in the repository at:

```
postman/Event-Ticket-Booking.postman_collection.json
```

### Import Instructions:
1. Open Postman.
2. Click **Import** in the top left.
3. Select `postman/Event-Ticket-Booking.postman_collection.json`.
4. The collection contains pre-configured test scripts that automatically capture and persist:
   - `organizer_token` after login/registration
   - `attendee_token` after login/registration
   - `event_id` after event creation
   - `ticket_type_id` after ticket creation
   - `booking_id` after booking creation
5. Includes dedicated folders for:
   - `1. Authentication`
   - `2. Events`
   - `3. Ticket Types`
   - `4. Bookings`
   - `5. Reports`
   - `6. Negative Tests` (Unauthorized role, oversold, 5-ticket limit, 24h cutoff, invalid login)

---

## Default Test Credentials

Seeded via `php artisan db:seed`:

| Role | Name | Email | Password |
| :--- | :--- | :--- | :--- |
| **Organizer** | Demo Organizer | `organizer@example.com` | `password123` |
| **Attendee 1** | Alice Attendee | `attendee1@example.com` | `password123` |
| **Attendee 2** | Bob Attendee | `attendee2@example.com` | `password123` |

### Seeded Events (Owned by `organizer@example.com`):
1. **Tech Innovation Summit 2026** (14 days upcoming | General: $49.99 / VIP: $149.99)
2. **Global Design & UX Expo 2026** (21 days upcoming | Standard: $79.00 / Workshop: $199.00)
3. **Full-Stack Developers Conference 2026** (30 days upcoming | Early Bird: $59.00 / Full Pass: $99.00)

---

## Development and Testing

```bash
# Start the local development server
php artisan serve

# Run scheduled tasks locally
php artisan schedule:work

# Run automated tests
php artisan test

# Check code formatting with Laravel Pint
vendor/bin/pint --dirty --format agent
```
