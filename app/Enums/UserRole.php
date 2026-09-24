<?php

namespace App\Enums;

enum UserRole: string
{
    case Organizer = 'organizer';
    case Attendee = 'attendee';
}
