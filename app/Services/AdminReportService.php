<?php

namespace App\Services;

use App\Models\AiConfiguration;
use App\Models\ChatHistory;
use App\Models\ReportLog;
use App\Models\ReportSetting;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppNotification;

class AdminReportService
{
    public function __construct(private readonly WhatsAppService $whatsApp)
    {
    }

    /**
     * Build an aggregate-only administrative report. Private task titles,
     * schedule details, user phone numbers and AI conversations are excluded.
     */
    public function buildMessage(string $frequency = 'manual'): string
    {
        $users = User::query()->where('role', 'user');
        $totalUsers = (clone $users)->count();
        $activeUsers = (clone $users)->where('status', 'active')->count();
        $inactiveUsers = (clone $users)->where('status', 'inactive')->count();
        $newUsers = (clone $users)->where('created_at', '>=', now()->startOfMonth())->count();

        $totalTasks = Task::count();
        $completedTasks = Task::where('status', 'completed')->count();
        $openTasks = Task::where('status', '!=', 'completed')->count();
        $overdueTasks = Task::where('status', '!=', 'completed')
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->count();

        $totalSchedules = Schedule::count();
        $todaySchedules = Schedule::whereDate('schedule_date', today())->count();
        $totalChats = ChatHistory::count();
        $todayChats = ChatHistory::whereDate('created_at', today())->count();

        $sentToday = WhatsAppNotification::where('status', 'sent')
            ->whereDate('sent_at', today())
            ->count();
        $failedToday = WhatsAppNotification::where('status', 'failed')
            ->whereDate('updated_at', today())
            ->count();
        $pending = WhatsAppNotification::where('status', 'pending')->count();

        $ai = AiConfiguration::current();
        $whatsapp = WhatsAppConfiguration::current();
        $label = ReportLog::FREQUENCIES[$frequency] ?? ucfirst($frequency);

        return "📊 *DailyMate Admin Report — {$label}*\n"
            .'Generated: '.now()->format('Y-m-d H:i')."\n\n"
            ."*Accounts*\n"
            ."• Total users: {$totalUsers}\n"
            ."• Active / inactive: {$activeUsers} / {$inactiveUsers}\n"
            ."• New this month: {$newUsers}\n\n"
            ."*Workload (aggregate)*\n"
            ."• Tasks: {$totalTasks} total, {$completedTasks} completed, {$openTasks} open\n"
            ."• Overdue tasks: {$overdueTasks}\n"
            ."• Schedules: {$totalSchedules} total, {$todaySchedules} today\n"
            ."• AI conversations: {$totalChats} total, {$todayChats} today\n\n"
            ."*System health*\n"
            .'• Qwen AI: '.($ai?->isReady() ? 'ready' : 'not configured')."\n"
            .'• WhatsApp: '.($whatsapp?->isReadyToSend() ? 'ready' : 'not configured')."\n"
            ."• WhatsApp delivered today: {$sentToday}\n"
            ."• WhatsApp failed today: {$failedToday}\n"
            ."• WhatsApp pending: {$pending}\n\n"
            ."This report contains aggregate data only. No private user content is included.";
    }

    /**
     * @return array{success: bool, message: string, log: ReportLog}
     */
    public function send(string $frequency = 'manual', ?string $recipient = null): array
    {
        $setting = ReportSetting::current();
        $number = $this->normalizeNumber($recipient ?: $setting?->recipient_number);

        if (mb_strlen($number) < 8) {
            return $this->recordFailure(
                $frequency,
                $number,
                'A valid report recipient WhatsApp number is required.'
            );
        }

        $config = WhatsAppConfiguration::current();

        if (! $config || ! $config->isReadyToSend()) {
            return $this->recordFailure(
                $frequency,
                $number,
                'WhatsApp API is not configured or is inactive. The report was not sent.'
            );
        }

        $message = $this->buildMessage($frequency);
        $result = $this->whatsApp->sendMessage($config, $number, $message);

        $log = ReportLog::create([
            'frequency' => $frequency,
            'recipient_number' => $number,
            'status' => $result['success'] ? 'sent' : 'failed',
            'provider_message_id' => $result['message_id'] ?? null,
            'error_message' => $result['success'] ? null : $result['message'],
            'sent_at' => $result['success'] ? now() : null,
        ]);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'log' => $log,
        ];
    }

    /**
     * Send any scheduled report that is due. Runs every minute, so daily,
     * weekly and monthly reports are delivered once per period.
     */
    public function sendDue(): int
    {
        $setting = ReportSetting::current();

        if (! $setting || ! $setting->is_active || ! $setting->hasRecipient()) {
            return 0;
        }

        $now = now();
        $sendTime = $setting->send_time ?: '08:00';

        if ($now->format('H:i') < $sendTime) {
            return 0;
        }

        $due = [];

        if ($setting->daily_enabled
            && (! $setting->last_daily_sent_on || $setting->last_daily_sent_on->toDateString() < $now->toDateString())) {
            $due['daily'] = 'last_daily_sent_on';
        }

        if ($setting->weekly_enabled
            && $now->isMonday
            && (! $setting->last_weekly_sent_on || $setting->last_weekly_sent_on->toDateString() < $now->toDateString())) {
            $due['weekly'] = 'last_weekly_sent_on';
        }

        if ($setting->monthly_enabled
            && $now->day === 1
            && (! $setting->last_monthly_sent_on || $setting->last_monthly_sent_on->toDateString() < $now->toDateString())) {
            $due['monthly'] = 'last_monthly_sent_on';
        }

        $count = 0;

        foreach ($due as $frequency => $lastSentColumn) {
            $result = $this->send($frequency, $setting->recipient_number);

            // Mark the period as attempted even on failure. The log records the
            // technical reason, and the next period can try again without
            // spamming the provider every minute.
            $setting->forceFill([$lastSentColumn => today()])->save();
            $count++;
        }

        return $count;
    }

    private function normalizeNumber(?string $number): string
    {
        return preg_replace('/\D+/', '', (string) $number) ?? '';
    }

    /**
     * @return array{success: bool, message: string, log: ReportLog}
     */
    private function recordFailure(string $frequency, string $number, string $message): array
    {
        $log = ReportLog::create([
            'frequency' => $frequency,
            'recipient_number' => $number !== '' ? $number : null,
            'status' => 'failed',
            'error_message' => $message,
        ]);

        return [
            'success' => false,
            'message' => $message,
            'log' => $log,
        ];
    }
}
