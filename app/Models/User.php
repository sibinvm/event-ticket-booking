<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ORGANIZER = 'organizer';

    public const ROLE_ATTENDEE = 'attendee';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Interact with the user's role.
     */
    protected function role(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value instanceof \BackedEnum ? $value->value : $value,
        );
    }

    /**
     * Check if the user is an organizer.
     */
    public function isOrganizer(): bool
    {
        return $this->role === self::ROLE_ORGANIZER;
    }

    /**
     * Check if the user is an attendee.
     */
    public function isAttendee(): bool
    {
        return $this->role === self::ROLE_ATTENDEE;
    }

    /**
     * Get the events organized by the user.
     *
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    /**
     * Get the bookings made by the user.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'user_id');
    }
}
