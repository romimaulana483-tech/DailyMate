<?php

namespace App\Console\Commands;

use App\Models\WhatsAppConfiguration;
use App\Services\WhatsAppReminderService;
use Illuminate\Console\Command;

class SendWhatsAppReminders extends Command
{
    protected $signature = 'whatsapp:send-reminders
                            {--high-priority : Send the daily high-priority summary instead of due reminders}';

    protected $description = 'Send due task and schedule reminders through WhatsApp';

    public function handle(WhatsAppReminderService $reminders): int
    {
        $config = WhatsAppConfiguration::current();
        $whatsappReady = $config && $config->isReadyToSend();

        if (! $whatsappReady) {
            $this->warn('WhatsApp API is not ready. In-app notifications will still be recorded; WhatsApp delivery is skipped.');
        }

        if ($this->option('high-priority')) {
            $count = $reminders->sendHighPrioritySummaries();
            $this->info("Created {$count} high-priority reminder(s).");

            return self::SUCCESS;
        }

        $deadlineCount = $reminders->sendDueTaskReminders();
        $scheduleCount = $reminders->sendUpcomingScheduleReminders();
        $total = $deadlineCount + $scheduleCount;

        $this->info("Created {$total} reminder(s): {$deadlineCount} task deadline(s), {$scheduleCount} schedule(s).");

        return self::SUCCESS;
    }
}
