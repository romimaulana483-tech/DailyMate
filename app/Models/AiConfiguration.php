<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiConfiguration extends Model
{
    use HasFactory;

    public const DEFAULT_PROVIDER = 'qwen';

    public const DEFAULT_ENDPOINT = 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions';

    public const DEFAULT_MODEL = 'qwen-plus';

    protected $fillable = [
        'provider',
        'endpoint',
        'model',
        'api_key',
        'is_active',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    /**
     * The API key must never be serialised to the browser or an API response.
     */
    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->oldest('id')->first();
    }

    public static function defaults(): self
    {
        return new self([
            'provider' => self::DEFAULT_PROVIDER,
            'endpoint' => self::DEFAULT_ENDPOINT,
            'model' => self::DEFAULT_MODEL,
            'is_active' => true,
        ]);
    }

    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }

    public function isReady(): bool
    {
        return $this->is_active
            && $this->hasApiKey()
            && filled($this->endpoint)
            && filled($this->model);
    }

    /**
     * Accept either a base URL or a full chat-completions URL and normalise it
     * to the endpoint required by the OpenAI-compatible Qwen API.
     */
    public function chatEndpoint(): string
    {
        $endpoint = rtrim(trim((string) $this->endpoint), '/');

        if (str_ends_with(strtolower($endpoint), '/chat/completions')) {
            return $endpoint;
        }

        return $endpoint.'/chat/completions';
    }
}
