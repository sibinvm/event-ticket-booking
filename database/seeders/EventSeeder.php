<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizer = User::where('email', 'organizer@example.com')->first();

        if (! $organizer) {
            $organizer = User::firstOrCreate(
                ['email' => 'organizer@example.com'],
                [
                    'name' => 'Demo Organizer',
                    'password' => bcrypt('password123'),
                    'role' => User::ROLE_ORGANIZER,
                ]
            );
        }

        $eventsData = [
            [
                'title' => 'Tech Innovation Summit 2026',
                'description' => 'An immersive summit exploring cloud native architectures, artificial intelligence, and developer productivity.',
                'venue' => 'Silicon Convention Center, Hall A',
                'starts_at' => now()->addDays(14)->setTime(9, 0),
                'status' => Event::STATUS_PUBLISHED,
                'tickets' => [
                    [
                        'name' => 'General Admission',
                        'price' => 49.99,
                        'quantity' => 200,
                    ],
                    [
                        'name' => 'VIP Access',
                        'price' => 149.99,
                        'quantity' => 50,
                    ],
                ],
            ],
            [
                'title' => 'Global Design & UX Expo 2026',
                'description' => 'Connecting world-class product designers, design system creators, and user experience researchers.',
                'venue' => 'Design Arts Pavilion, Grand Hall',
                'starts_at' => now()->addDays(21)->setTime(10, 0),
                'status' => Event::STATUS_PUBLISHED,
                'tickets' => [
                    [
                        'name' => 'Standard Pass',
                        'price' => 79.00,
                        'quantity' => 150,
                    ],
                    [
                        'name' => 'Workshop & VIP Pass',
                        'price' => 199.00,
                        'quantity' => 40,
                    ],
                ],
            ],
            [
                'title' => 'Full-Stack Developers Conference 2026',
                'description' => 'Deep dive into modern web APIs, distributed databases, high-performance computing, and containerization.',
                'venue' => 'City Center Amphitheater',
                'starts_at' => now()->addDays(30)->setTime(9, 30),
                'status' => Event::STATUS_PUBLISHED,
                'tickets' => [
                    [
                        'name' => 'Early Bird Pass',
                        'price' => 59.00,
                        'quantity' => 100,
                    ],
                    [
                        'name' => 'Full Conference Pass',
                        'price' => 99.00,
                        'quantity' => 150,
                    ],
                ],
            ],
        ];

        foreach ($eventsData as $eventData) {
            $tickets = $eventData['tickets'];
            unset($eventData['tickets']);

            $event = Event::updateOrCreate(
                [
                    'organizer_id' => $organizer->id,
                    'title' => $eventData['title'],
                ],
                $eventData
            );

            foreach ($tickets as $ticket) {
                TicketType::updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'name' => $ticket['name'],
                    ],
                    $ticket
                );
            }
        }
    }
}
