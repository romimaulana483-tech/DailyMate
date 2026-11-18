<?php

namespace App\Services;

use App\Models\WhatsAppConfiguration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppService
{
    /**
     * Send a text message using the selected provider's real HTTP contract.
     *
     * @return array{success: bool, status_code: int|null, message_id: string|null, message: string}
     */
    public function sendMessage(WhatsAppConfiguration $config, string $to, string $message): array
    {
        $to = preg_replace('/\D+/', '', $to) ?? '';

        if (strlen($to) < 8) {
            return $this->result(false, null, null, 'The destination WhatsApp number is invalid.');
        }

        if (blank($config->endpoint)) {
            return $this->result(false, null, null, 'The WhatsApp API endpoint is not configured.');
        }

        if ($config->provider !== 'custom' && blank($config->token)) {
            return $this->result(false, null, null, 'The WhatsApp API token is not configured.');
        }

        try {
            return match ($config->provider) {
                'meta_cloud' => $this->sendMeta($config, $to, $message),
                'fonnte' => $this->sendFonnte($config, $to, $message),
                'wablas' => $this->sendWablas($config, $to, $message),
                'custom' => $this->sendCustom($config, $to, $message),
                default => $this->result(false, null, null, 'Unsupported WhatsApp API provider.'),
            };
        } catch (Throwable $e) {
            // Never log the token, request body, or private notification text.
            Log::warning('WhatsApp API request failed.', [
                'provider' => $config->provider,
                'host' => $this->endpointHost($config->endpoint),
                'exception' => $e::class,
            ]);

            return $this->result(false, null, null, 'Could not reach the WhatsApp API. Check the endpoint and network connection.');
        }
    }

    /**
     * Check a provider's API with an authenticated request. The result is
     * based on the real HTTP response; no status is synthesised.
     *
     * @return array{success: bool, status_code: int|null, message_id: string|null, message: string}
     */
    public function testConnection(WhatsAppConfiguration $config): array
    {
        if (blank($config->endpoint)) {
            return $this->result(false, null, null, 'Enter an API endpoint before testing the connection.');
        }

        if ($config->provider !== 'custom' && blank($config->token)) {
            return $this->result(false, null, null, 'Enter an API token before testing the connection.');
        }

        $url = $this->testUrl($config);
        $method = strtoupper($config->test_method ?: 'GET');

        // Fonnte's documented device-profile endpoint is POST /device.
        if ($config->provider === 'fonnte') {
            $method = 'POST';
        } elseif (! in_array($method, ['GET', 'HEAD', 'POST'], true)) {
            $method = 'GET';
        }

        try {
            $response = $this->client($config)->send($method, $url);

            return $this->normalizeTestResponse($response, $config);
        } catch (Throwable $e) {
            Log::warning('WhatsApp connection test failed.', [
                'provider' => $config->provider,
                'host' => $this->endpointHost($url),
                'exception' => $e::class,
            ]);

            return $this->result(false, null, null, 'Connection test failed: the API could not be reached.');
        }
    }

    private function sendMeta(WhatsAppConfiguration $config, string $to, string $message): array
    {
        $response = $this->client($config)->post($this->metaSendUrl($config), [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ]);

        return $this->normalizeSendResponse($response, $config);
    }

    private function sendFonnte(WhatsAppConfiguration $config, string $to, string $message): array
    {
        // Fonnte expects form fields. countryCode=0 disables its automatic
        // country-code rewriting because DailyMate stores full international
        // numbers (for example 6281234567890). Always use Fonnte's API host;
        // dashboard/device URLs saved in the endpoint field return a login page.
        $response = $this->client($config)
            ->asForm()
            ->post(WhatsAppConfiguration::FONNTE_SEND_ENDPOINT, [
                'target' => $to,
                'message' => $message,
                'countryCode' => '0',
            ]);

        return $this->normalizeSendResponse($response, $config);
    }

    private function sendWablas(WhatsAppConfiguration $config, string $to, string $message): array
    {
        $response = $this->client($config)
            ->asForm()
            ->post($config->endpoint, [
                'phone' => $to,
                'message' => $message,
            ]);

        return $this->normalizeSendResponse($response, $config);
    }

    private function sendCustom(WhatsAppConfiguration $config, string $to, string $message): array
    {
        $method = strtoupper($config->custom_method ?: 'POST');

        if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return $this->result(false, null, null, 'The custom provider method must be POST, PUT or PATCH.');
        }

        $payload = $this->renderCustomPayload($config, $to, $message);

        $response = $this->client($config)
            ->withBody(json_encode($payload, JSON_THROW_ON_ERROR), 'application/json')
            ->send($method, $config->endpoint);

        return $this->normalizeSendResponse($response, $config);
    }

    private function client(WhatsAppConfiguration $config): PendingRequest
    {
        return Http::timeout((int) config('whatsapp.http_timeout', 20))
            ->acceptJson()
            ->withHeaders($this->authHeaders($config));
    }

    private function authHeaders(WhatsAppConfiguration $config): array
    {
        if (blank($config->token)) {
            return [];
        }

        if ($config->provider === 'meta_cloud') {
            return ['Authorization' => 'Bearer '.$config->token];
        }

        if ($config->provider === 'custom') {
            $header = trim($config->custom_auth_header ?: 'Authorization');
            $scheme = trim((string) $config->custom_auth_scheme);

            return [$header => $scheme !== '' ? $scheme.' '.$config->token : $config->token];
        }

        return ['Authorization' => $config->token];
    }

    private function metaSendUrl(WhatsAppConfiguration $config): string
    {
        $endpoint = rtrim($config->endpoint, '/');

        if (str_ends_with(strtolower($endpoint), '/messages')) {
            return $endpoint;
        }

        if (filled($config->phone_number_id)) {
            return $endpoint.'/'.rawurlencode((string) $config->phone_number_id).'/messages';
        }

        return $endpoint;
    }

    private function testUrl(WhatsAppConfiguration $config): string
    {
        if ($config->provider === 'fonnte') {
            return WhatsAppConfiguration::FONNTE_DEVICE_ENDPOINT;
        }

        if (filled($config->test_endpoint)) {
            return $config->test_endpoint;
        }

        $endpoint = rtrim($config->endpoint, '/');

        if ($config->provider === 'meta_cloud') {
            if (str_ends_with(strtolower($endpoint), '/messages')) {
                $endpoint = substr($endpoint, 0, -strlen('/messages'));
            }

            if (filled($config->phone_number_id)) {
                $endpoint .= '/'.rawurlencode((string) $config->phone_number_id);
            }
        }

        return $endpoint;
    }

    private function renderCustomPayload(WhatsAppConfiguration $config, string $to, string $message): array
    {
        $template = trim((string) $config->custom_payload_template);

        if ($template === '') {
            $template = '{"to":"{{to}}","message":"{{message}}"}';
        }

        $json = str_replace(
            ['{{to}}', '{{message}}'],
            [$this->jsonFragment($to), $this->jsonFragment($message)],
            $template
        );

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new \InvalidArgumentException('The custom payload template must be a JSON object.');
        }

        return $payload;
    }

    private function jsonFragment(string $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return substr($encoded, 1, -1);
    }

    private function normalizeSendResponse(Response $response, WhatsAppConfiguration $config): array
    {
        if ($this->looksLikeWebPage($response)) {
            return $this->webPageFailure($response, $config);
        }

        $json = $response->json();
        $json = is_array($json) ? $json : [];
        $snippet = $this->safeSnippet($this->responseText($response, $response->json()), $config);
        $providerStatus = $json['status'] ?? null;
        $providerReason = $json['reason'] ?? null;

        if ($response->successful()) {
            // Fonnte explicitly returns status=true on accepted messages. Do
            // not treat an HTTP 200 with status=false as a success.
            if ($config->provider === 'fonnte' && $providerStatus !== true) {
                $providerMessage = $providerReason
                    ?? ($json['detail'] ?? null)
                    ?? ($json['message'] ?? null)
                    ?? ($snippet !== '' ? $snippet : 'The provider did not return status=true.');

                return $this->result(false, $response->status(), null, 'Provider rejected the message: '.$providerMessage);
            }

            if ($providerStatus === false || $providerStatus === 0 || $providerStatus === 'false') {
                $providerMessage = $providerReason
                    ?? ($json['detail'] ?? null)
                    ?? ($json['message'] ?? null)
                    ?? $snippet;

                return $this->result(false, $response->status(), null, 'Provider rejected the message: '.$providerMessage);
            }

            $ids = data_get($json, 'id');
            $messageId = data_get($json, 'messages.0.id')
                ?? data_get($json, 'message_id')
                ?? data_get($json, 'data.id')
                ?? (is_array($ids) ? ($ids[0] ?? null) : $ids);

            $detail = $json['detail'] ?? null;

            return $this->result(
                true,
                $response->status(),
                $messageId !== null && $messageId !== '' ? (string) $messageId : null,
                filled($detail)
                    ? 'The provider accepted the message: '.$detail
                    : 'The provider accepted the message.'
            );
        }

        $providerMessage = $providerReason
            ?? ($json['detail'] ?? null)
            ?? ($json['error']['message'] ?? null)
            ?? ($json['message'] ?? null)
            ?? $snippet;

        return $this->result(
            false,
            $response->status(),
            null,
            'Provider returned HTTP '.$response->status().(filled($providerMessage) ? ': '.$providerMessage : '')
        );
    }

    private function normalizeTestResponse(Response $response, WhatsAppConfiguration $config): array
    {
        if ($this->looksLikeWebPage($response)) {
            return $this->webPageFailure($response, $config);
        }

        $json = $response->json();
        $json = is_array($json) ? $json : [];
        $snippet = $this->safeSnippet($this->responseText($response, $response->json()), $config);
        $providerStatus = $json['status'] ?? null;
        $providerReason = $json['reason'] ?? null;

        if (! $response->successful()) {
            $providerMessage = $providerReason
                ?? ($json['detail'] ?? null)
                ?? ($json['error']['message'] ?? null)
                ?? ($json['message'] ?? null)
                ?? $snippet;

            return $this->result(
                false,
                $response->status(),
                null,
                'API returned HTTP '.$response->status().(filled($providerMessage) ? ': '.$providerMessage : '')
            );
        }

        if ($config->provider === 'fonnte') {
            if ($providerStatus !== true) {
                $providerMessage = $providerReason
                    ?? ($json['detail'] ?? null)
                    ?? ($json['message'] ?? null)
                    ?? ($snippet !== '' ? $snippet : 'The device endpoint did not return status=true.');

                return $this->result(false, $response->status(), null, 'Fonnte rejected the connection test: '.$providerMessage);
            }

            $deviceStatus = strtolower((string) ($json['device_status'] ?? ''));

            if ($deviceStatus !== 'connect') {
                return $this->result(
                    false,
                    $response->status(),
                    null,
                    'Fonnte token is valid, but the WhatsApp device is not connected. Open WhatsApp on the device or reconnect it in the Fonnte dashboard.'
                );
            }

            $details = array_filter([
                isset($json['device']) ? 'Device '.$json['device'] : null,
                isset($json['quota']) ? 'quota '.$json['quota'] : null,
                isset($json['expired']) ? 'expires '.$json['expired'] : null,
            ]);

            return $this->result(
                true,
                $response->status(),
                null,
                'Connection successful. Fonnte device is connected'.($details !== [] ? ' ('.implode(', ', $details).')' : '').'.'
            );
        }

        if ($providerStatus === false || $providerStatus === 0 || $providerStatus === 'false') {
            $providerMessage = $providerReason
                ?? ($json['detail'] ?? null)
                ?? ($json['message'] ?? null)
                ?? $snippet;

            return $this->result(false, $response->status(), null, 'API rejected the connection test: '.$providerMessage);
        }

        return $this->result(
            true,
            $response->status(),
            null,
            'Connection successful. The API responded with HTTP '.$response->status().'.'
        );
    }

    private function looksLikeWebPage(Response $response): bool
    {
        $contentType = strtolower((string) $response->header('Content-Type'));

        if (str_contains($contentType, 'text/html')) {
            return true;
        }

        $body = ltrim($response->body());

        if ($body === '') {
            return false;
        }

        return (bool) preg_match('/^<(!doctype|html|head|body|\?xml)/i', $body)
            || (str_contains(strtolower($body), '<form') && str_contains(strtolower($body), 'login'));
    }

    private function webPageFailure(Response $response, WhatsAppConfiguration $config): array
    {
        $message = $config->provider === 'fonnte'
            ? 'The WhatsApp API returned a login/web page instead of a Fonnte API response. DailyMate sends Fonnte messages to '.WhatsAppConfiguration::FONNTE_SEND_ENDPOINT.'. Check that the token is a Fonnte API token and not a dashboard login session.'
            : 'The WhatsApp API endpoint returned a web page instead of an API response. Check the API endpoint and authentication settings.';

        return $this->result(false, $response->status(), null, $message);
    }

    private function responseText(Response $response, mixed $json): string
    {
        if (is_array($json)) {
            $text = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $text = $response->body();
        }

        return trim((string) $text);
    }

    private function safeSnippet(string $text, WhatsAppConfiguration $config): string
    {
        if ($text === '') {
            return '';
        }

        if (filled($config->token)) {
            $text = str_replace($config->token, '[redacted]', $text);
        }

        // Remove markup and embedded CSS/JS from non-JSON error pages.
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', strip_tags($text)) ?? $text;

        return mb_strlen($text) > 280 ? mb_substr($text, 0, 277).'...' : $text;
    }

    private function endpointHost(?string $url): string
    {
        $host = parse_url((string) $url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'unknown';
    }

    private function result(bool $success, ?int $statusCode, ?string $messageId, string $message): array
    {
        return [
            'success' => $success,
            'status_code' => $statusCode,
            'message_id' => $messageId,
            'message' => $message,
        ];
    }
}
