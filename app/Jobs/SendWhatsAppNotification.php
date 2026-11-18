<?php

namespace App\Jobs;

use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppNotification;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $notificationId,
        public string $message
    ) {
    }

    public function handle(WhatsAppService $whatsApp): void
    {
        $notification = WhatsAppNotification::with('user')->find($this->notificationId);

        if (! $notification || $notification->status !== 'pending') {
            return;
        }

        $user = $notification->user;

        if (! $user || ! $user->hasWhatsAppNotifications()) {
            $notification->update([
                'status' => 'skipped',
                'error_message' => 'User disabled WhatsApp notifications or has no number.',
            ]);

            return;
        }

        $config = WhatsAppConfiguration::current();

        if (! $config || ! $config->isReadyToSend()) {
            $notification->update([
                'status' => 'failed',
                'error_message' => 'WhatsApp API is not configured or is inactive.',
            ]);

            return;
        }

        $notification->increment('attempts');

        try {
            $result = $whatsApp->sendMessage($config, $user->whatsapp_number, $this->message);
        } catch (Throwable $e) {
            Log::warning('WhatsApp notification job failed.', [
                'notification_id' => $notification->id,
                'provider' => $config->provider,
                'exception' => $e::class,
            ]);

            $notification->update([
                'status' => 'failed',
                'error_message' => 'Unexpected delivery error. Check the application log.',
            ]);

            return;
        }

        if ($result['success']) {
            $notification->update([
                'status' => 'sent',
                'provider_message_id' => $result['message_id'],
                'error_message' => null,
                'sent_at' => now(),
            ]);

            return;
        }

        $notification->update([
            'status' => 'failed',
            'error_message' => mb_substr($result['message'], 0, 1000),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $notification = WhatsAppNotification::find($this->notificationId);

        if (! $notification || $notification->status !== 'pending') {
            return;
        }

        $notification->update([
            'status' => 'failed',
            'error_message' => 'The queued delivery could not be completed. Check the queue worker.',
        ]);
    }
}
