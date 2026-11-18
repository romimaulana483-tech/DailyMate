<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient_number',
        'is_active',
        'daily_enabled',
        'weekly_enabled',
        'monthly_enabled',
        'send_time',
        'last_daily_sent_on',
        'last_weekly_sent_on',
        'last_monthly_sent_on',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'daily_enabled' => 'boolean',
            'weekly_enabled' => 'boolean',
            'monthly_enabled' => 'boolean',
            'last_daily_sent_on' => 'date',
            'last_weekly_sent_on' => 'date',
            'last_monthly_sent_on' => 'date',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->oldest('id')->first();
    }

    public static function defaults(): self
    {
        return new self([
            'is_active' => false,
            'daily_enabled' => false,
            'weekly_enabled' => false,
            'monthly_enabled' => false,
            'send_time' => '08:00',
        ]);
    }

    public function hasRecipient(): bool
    {
        return filled($this->recipient_number);
    }
}
