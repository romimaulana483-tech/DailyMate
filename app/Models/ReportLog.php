<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportLog extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending' => 'Pending',
        'sent' => 'Sent',
        'failed' => 'Failed',
    ];

    public const FREQUENCIES = [
        'manual' => 'Manual',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
    ];

    protected $fillable = [
        'frequency',
        'recipient_number',
        'status',
        'provider_message_id',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function frequencyLabel(): string
    {
        return self::FREQUENCIES[$this->frequency] ?? ucfirst((string) $this->frequency);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function maskedRecipient(): string
    {
        $number = (string) $this->recipient_number;

        if (mb_strlen($number) <= 6) {
            return $number !== '' ? $number : '—';
        }

        return mb_substr($number, 0, 3).str_repeat('•', max(2, mb_strlen($number) - 7)).mb_substr($number, -4);
    }
}
