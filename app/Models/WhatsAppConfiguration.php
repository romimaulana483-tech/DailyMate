<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppConfiguration extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_configurations';

    public const PROVIDERS = [
        'meta_cloud' => 'Meta WhatsApp Cloud API',
        'fonnte' => 'Fonnte',
        'wablas' => 'Wablas',
        'custom' => 'Custom HTTP API',
    ];

    public const FONNTE_SEND_ENDPOINT = 'https://api.fonnte.com/send';

    public const FONNTE_DEVICE_ENDPOINT = 'https://api.fonnte.com/device';

    protected $fillable = [
        'provider',
        'endpoint',
        'token',
        'phone_number_id',
        'sender_number',
        'is_active',
        'custom_method',
        'custom_auth_header',
        'custom_auth_scheme',
        'custom_payload_template',
        'test_method',
        'test_endpoint',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    /**
     * Never serialise the secret token. It is encrypted at rest by the cast.
     */
    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->oldest('id')->first();
    }

    public static function providerLabels(): array
    {
        return self::PROVIDERS;
    }

    public function providerLabel(): string
    {
        return self::PROVIDERS[$this->provider] ?? ucfirst((string) $this->provider);
    }

    public function hasToken(): bool
    {
        return filled($this->token);
    }

    public function isReadyToSend(): bool
    {
        return $this->is_active && filled($this->endpoint);
    }
}
