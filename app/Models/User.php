<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'whatsapp_number',
        'whatsapp_notifications_enabled',
        'whatsapp_consent_at',
        'whatsapp_reminder_offsets',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'whatsapp_notifications_enabled' => 'boolean',
            'whatsapp_consent_at' => 'datetime',
            'whatsapp_reminder_offsets' => 'array',
        ];
    }

    /* ---------------------------------------------------------------------
     | Relationships
     | ------------------------------------------------------------------ */

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function chatHistories()
    {
        return $this->hasMany(ChatHistory::class);
    }

    public function whatsappNotifications()
    {
        return $this->hasMany(WhatsAppNotification::class);
    }

    public function userNotifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Log entries created by this admin.
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class, 'admin_id');
    }

    /* ---------------------------------------------------------------------
     | Helpers
     | ------------------------------------------------------------------ */

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasWhatsAppNotifications(): bool
    {
        return $this->isActive()
            && $this->whatsapp_notifications_enabled
            && filled($this->whatsapp_number);
    }

    /**
     * Reminder offsets are stored in minutes. The four supported choices are
     * one day, 12 hours, 3 hours and 1 hour before a deadline.
     *
     * @return array<int, int>
     */
    public function reminderOffsets(): array
    {
        $allowed = [1440, 720, 180, 60];
        $stored = is_array($this->whatsapp_reminder_offsets)
            ? $this->whatsapp_reminder_offsets
            : [];

        $offsets = collect($stored)
            ->map(fn ($offset) => (int) $offset)
            ->filter(fn (int $offset) => in_array($offset, $allowed, true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $offsets !== [] ? $offsets : [60];
    }
}
