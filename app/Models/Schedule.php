<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'schedule_date',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'schedule_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function whatsappNotifications()
    {
        return $this->morphMany(WhatsAppNotification::class, 'notifiable');
    }

    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getTimeRangeAttribute(): string
    {
        return substr($this->start_time, 0, 5).' - '.substr($this->end_time, 0, 5);
    }
}
