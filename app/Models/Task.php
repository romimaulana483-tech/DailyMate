<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function whatsappNotifications()
    {
        return $this->morphMany(WhatsAppNotification::class, 'notifiable');
    }

    /**
     * Named scopes keep filtering logic reusable and readable.
     */
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%");
        });
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->deadline
            && $this->status !== 'completed'
            && $this->deadline->isPast();
    }

    public static function statusLabels(): array
    {
        return [
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
        ];
    }

    public static function priorityLabels(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
        ];
    }
}
