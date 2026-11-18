<?php

namespace App\Services;

use App\Models\AiConfiguration;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class QwenService
{
    public function __construct(private FridayDataService $data)
    {
    }

    /**
     * Ask Friday for a reply.
     *
     * Factual questions are answered directly from the backend after a
     * structured database lookup, so counts, names, schedules and statuses can
     * never be invented by the model. Qwen is only used for advisory/planning
     * questions, and always receives the backend-authorised context.
     */
    public function reply(User $user, string $message): string
    {
        $resolution = $this->data->resolve($user, $message);

        if ($resolution['mode'] !== 'guided') {
            return (string) $resolution['answer'];
        }

        $fallback = (string) $resolution['answer'];
        $config = AiConfiguration::current();

        if (! $config || ! $config->isReady()) {
            return $fallback;
        }

        try {
            $response = Http::withToken($config->api_key)
                ->acceptJson()
                ->timeout(30)
                ->post($config->chatEndpoint(), [
                    'model' => $config->model,
                    'messages' => $this->messages($user, $message, $resolution['context']),
                    'temperature' => 0.4,
                    'max_tokens' => 700,
                ]);

            if ($response->successful()) {
                $text = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

                if ($text !== '' && $this->looksIndonesian($text)) {
                    return $text;
                }

                if ($text !== '') {
                    Log::warning('Qwen returned a non-Indonesian Friday reply; using the deterministic Indonesian fallback.');
                }
            }

            Log::warning('Qwen API returned an unusable response for Friday.', [
                'status' => $response->status(),
                'endpoint_host' => $this->endpointHost($config->endpoint),
                'model' => $config->model,
            ]);
        } catch (Throwable $e) {
            Log::warning('Qwen API request failed for Friday.', [
                'exception' => $e::class,
                'endpoint_host' => $this->endpointHost($config->endpoint),
                'model' => $config->model,
            ]);
        }

        return $fallback;
    }

    /**
     * Perform a real API call using the supplied configuration. This is used
     * by the admin Test Connection button and never fabricates success.
     *
     * @return array{success: bool, status_code: int|null, message: string}
     */
    public function testConnection(AiConfiguration $config): array
    {
        if (blank($config->api_key)) {
            return $this->testResult(false, null, 'Enter a Qwen API key before testing the connection.');
        }

        if (blank($config->endpoint) || blank($config->model)) {
            return $this->testResult(false, null, 'The API endpoint and model are required.');
        }

        try {
            $response = Http::withToken($config->api_key)
                ->acceptJson()
                ->timeout(20)
                ->post($config->chatEndpoint(), [
                    'model' => $config->model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a connection test. Reply with exactly OK.'],
                        ['role' => 'user', 'content' => 'Reply with OK.'],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 8,
                ]);

            return $this->normalizeTestResponse($response, $config);
        } catch (Throwable $e) {
            Log::warning('Qwen connection test failed.', [
                'exception' => $e::class,
                'endpoint_host' => $this->endpointHost($config->endpoint),
            ]);

            return $this->testResult(false, null, 'Connection failed: the Qwen API could not be reached.');
        }
    }

    private function messages(User $user, string $message, string $context): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemInstruction($user)],
        ];

        $recent = $user->chatHistories()
            ->latest('id')
            ->limit(6)
            ->get()
            ->reverse();

        foreach ($recent as $chat) {
            $messages[] = ['role' => 'user', 'content' => $chat->message];
            $messages[] = ['role' => 'assistant', 'content' => $chat->response];
        }

        $messages[] = [
            'role' => 'user',
            'content' => "Pertanyaan pengguna:\n{$message}\n\n"
                ."Data resmi dari backend DailyMate:\n{$context}\n\n"
                .'Jawab dalam Bahasa Indonesia yang singkat dan langsung. '
                .'Gunakan hanya data resmi di atas. Jangan menambah angka, nama, tugas, jadwal, atau klaim yang tidak ada. '
                .'Jika pengguna memakai kata "sebutkan", tampilkan semua item yang relevan dari data resmi, bukan sebagian. '
                .'Jika pengguna memakai kata "berapa", jawab dengan angka yang tepat dari data resmi. '
                .'Jika data tidak ada, katakan dengan jujur.',
        ];

        return $messages;
    }

    private function systemInstruction(User $user): string
    {
        $role = $user->isAdmin() ? 'Admin' : 'User';

        return "Kamu adalah Friday, asisten AI resmi DailyMate.\n\n"
            ."Nama pengguna yang sedang login: {$user->name}. Role: {$role}.\n\n"
            ."Tugasmu adalah membantu pengguna mengatur waktu dan memahami data DailyMate seperti asisten profesional.\n\n"
            ."WAJIB:\n"
            ."1. Selalu menjawab menggunakan Bahasa Indonesia yang natural.\n"
            ."2. Berikan jawaban singkat, tepat, dan langsung ke inti. Jangan mengulang pertanyaan.\n"
            ."3. Gunakan data resmi dari backend sebagai satu-satunya sumber informasi, termasuk data grafik dan statistik.\n"
            ."4. Jangan mengarang nama, angka, statistik, tugas, jadwal, atau informasi akun.\n"
            ."5. Jangan menganggap data yang tidak tersedia sebagai fakta. Jika data tidak ditemukan, sampaikan dengan jujur.\n"
            ."6. Jika pengguna memakai kata \"sebutkan\", tampilkan semua item yang relevan sesuai data resmi, bukan sebagian atau hanya ringkasan.\n"
            ."7. Jika pengguna memakai kata \"berapa\", jawab dengan angka yang tepat sesuai data resmi. Jika pengguna meminta daftar sekaligus, tampilkan angka dan daftarnya.\n"
            ."8. Saat membantu mengatur waktu, urutkan tugas berdasarkan tugas terlambat, deadline terdekat, lalu prioritas; jelaskan alasan singkatnya.\n"
            ."9. Jangan menampilkan informasi yang tidak diminta.\n"
            ."10. Patuhi batasan akses berdasarkan role. Jangan pernah membocorkan API key, token, password, atau informasi rahasia.\n"
            ."11. Jika pengguna bertanya di luar fungsi DailyMate, tolak secara singkat dan arahkan kembali ke DailyMate.\n\n"
            .($user->isAdmin()
                ? "Sebagai Admin, kamu hanya menerima data agregat, status sistem, feedback, dan activity log yang diizinkan. "
                    ."Kamu tidak boleh membaca atau menampilkan judul/detail tugas pribadi User, jadwal pribadi User, "
                    ."nomor pribadi User, atau isi percakapan AI User."
                : "Sebagai User, kamu hanya boleh membahas data milik akun yang sedang login. "
                    ."Jangan pernah membahas tugas, jadwal, statistik, atau percakapan akun lain.")
            ."\n\nJawablah seperti asisten produktivitas profesional yang dapat mengatur waktu, membaca grafik/statistik, "
            ."dan selalu menjawab berdasarkan database. Jangan pernah mengarang data.";
    }

    /**
     * Reject English/model chatter so Friday's visible answer stays Indonesian.
     */
    private function looksIndonesian(string $text): bool
    {
        $text = trim($text);

        if ($text === '' || preg_match('/\p{L}/u', $text) !== 1) {
            return false;
        }

        if (preg_match(
            '/\b(dan|yang|anda|saya|kami|kita|tugas|jadwal|adalah|dengan|untuk|tidak|sudah|belum|hari|ini|besok|pengguna|akun|data|berdasarkan|memiliki|ada|silakan|bisa|dapat|akan|dari|pada|atau|juga|hanya|mulai|kerjakan|prioritas|selesai|ringkasan|sistem|status|aktif|membantu|berikut|tersebut|dalam|secara|lebih|paling|satu|dua|tiga|pukul|menit|jam|minggu|bulan|tahun|nomor|langkah|poin|saran|rencana|fokus|produktif)\b/iu',
            $text
        ) === 1) {
            return true;
        }

        $looksEnglish = preg_match(
            '/\b(the|your|you|yours|tasks?|schedules?|deadlines?|today|tomorrow|account|user|total|active|inactive|please|here|is|are|of|to|and|with|this|that|will|can|should|work|first|help|use|own|reply|response|aggregate|following|recommend|would|could)\b/i',
            $text
        ) === 1;

        return ! $looksEnglish;
    }

    private function normalizeTestResponse(Response $response, AiConfiguration $config): array
    {
        $json = $response->json();
        $content = trim((string) data_get($json, 'choices.0.message.content', ''));

        if ($response->successful() && $content !== '') {
            return $this->testResult(
                true,
                $response->status(),
                'Connection successful. Qwen replied using model '.$config->model.'.'
            );
        }

        $providerMessage = data_get($json, 'error.message')
            ?? data_get($json, 'message')
            ?? ($response->successful() ? 'The API responded without a usable completion.' : 'HTTP '.$response->status().'.');

        $providerMessage = $this->safeSnippet((string) $providerMessage, $config);

        return $this->testResult(
            false,
            $response->status(),
            'Connection failed: '.$providerMessage
        );
    }

    private function safeSnippet(string $text, AiConfiguration $config): string
    {
        if (filled($config->api_key)) {
            $text = str_replace($config->api_key, '[redacted]', $text);
        }

        $text = preg_replace('/\s+/', ' ', trim($text)) ?? $text;

        return mb_strlen($text) > 300 ? mb_substr($text, 0, 297).'...' : $text;
    }

    private function endpointHost(?string $url): string
    {
        $host = parse_url((string) $url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'unknown';
    }

    private function testResult(bool $success, ?int $statusCode, string $message): array
    {
        return [
            'success' => $success,
            'status_code' => $statusCode,
            'message' => $message,
        ];
    }
}
