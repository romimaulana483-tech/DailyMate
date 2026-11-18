<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppReminderService
{
    public function sendDueTaskReminders(): int
    {
        $now = now();
        // The largest supported preference is one day. Slightly widen the
        // query so a scheduler run can see a task at the exact 24-hour mark.
        $cutoff = $now->copy()->addMinutes(1500);
        $dispatched = 0;

        Task::query()
            ->with('user')
            ->where('status', '!=', 'completed')
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [$now, $cutoff])
            ->orderBy('deadline')
            ->chunkById(100, function ($tasks) use ($now, &$dispatched): void {
                foreach ($tasks as $task) {
                    $user = $task->user;

                    if (! $user?->hasWhatsAppNotifications() || $now->gt($task->deadline)) {
                        continue;
                    }

                    // Send at most one offset per task per scheduler run. The
                    // smallest due offset is the nearest reminder (1h, then 3h,
                    // 12h, 1 day), so a late-created task cannot cause a burst.
                    foreach ($user->reminderOffsets() as $offset) {
                        $remindAt = $task->deadline->copy()->subMinutes($offset);

                        if ($now->lt($remindAt)) {
                            continue;
                        }

                        $dedupeKey = 'task.deadline:'.$task->id.':'
                            .$task->deadline->format('YmdHi').':'.$offset;

                        $dispatched += $this->dispatchReminder(
                            $user,
                            $task,
                            'task_deadline',
                            $dedupeKey,
                            $this->taskDeadlineMessage($task, $offset)
                        );

                        break;
                    }
                }
            });

        return $dispatched;
    }

    public function sendUpcomingScheduleReminders(): int
    {
        $now = now();
        $cutoff = $now->copy()->addMinutes((int) config('whatsapp.schedule_lead_minutes', 15));
        $dispatched = 0;

        Schedule::query()
            ->with('user')
            ->whereDate('schedule_date', $now->toDateString())
            ->orderBy('start_time')
            ->chunkById(100, function ($schedules) use ($now, $cutoff, &$dispatched): void {
                foreach ($schedules as $schedule) {
                    if (! $schedule->user?->hasWhatsAppNotifications()) {
                        continue;
                    }

                    $start = $schedule->schedule_date->copy()->setTimeFromTimeString($schedule->start_time);

                    if ($start->lt($now) || $start->gt($cutoff)) {
                        continue;
                    }

                    $dedupeKey = 'schedule.start:'.$schedule->id.':'
                        .$schedule->schedule_date->format('Ymd').':'.$schedule->start_time;

                    $dispatched += $this->dispatchReminder(
                        $schedule->user,
                        $schedule,
                        'schedule_start',
                        $dedupeKey,
                        $this->scheduleReminderMessage($schedule)
                    );
                }
            });

        return $dispatched;
    }

    public function sendHighPrioritySummaries(): int
    {
        $dispatched = 0;
        $today = now()->toDateString();

        User::query()
            ->where('role', 'user')
            ->where('status', 'active')
            ->where('whatsapp_notifications_enabled', true)
            ->whereNotNull('whatsapp_number')
            ->where('whatsapp_number', '!=', '')
            ->whereHas('tasks', function ($query): void {
                $query->where('status', '!=', 'completed')->where('priority', 'high');
            })
            ->with(['tasks' => function ($query): void {
                $query->where('status', '!=', 'completed')
                    ->where('priority', 'high')
                    ->orderByRaw('deadline IS NULL, deadline ASC')
                    ->limit(8);
            }])
            ->chunkById(100, function ($users) use ($today, &$dispatched): void {
                foreach ($users as $user) {
                    if ($user->tasks->isEmpty()) {
                        continue;
                    }

                    $dispatched += $this->dispatchReminder(
                        $user,
                        null,
                        'task_high_priority',
                        'task.high-priority:'.$user->id.':'.$today,
                        $this->highPriorityMessage($user)
                    );
                }
            });

        return $dispatched;
    }

    private function dispatchReminder(
        User $user,
        ?Model $notifiable,
        string $eventType,
        string $dedupeKey,
        string $message
    ): int {
        // In-app history is independent from delivery. It lets the user review
        // reminders even if the WhatsApp provider temporarily fails.
        UserNotification::firstOrCreate(
            [
                'user_id' => $user->id,
                'dedupe_key' => $dedupeKey,
            ],
            [
                'type' => $eventType,
                'title' => $this->inAppTitle($notifiable, $eventType),
                'body' => $message,
                'data' => [
                    'notifiable_type' => $notifiable?->getMorphClass(),
                    'notifiable_id' => $notifiable?->getKey(),
                ],
            ]
        );

        $config = WhatsAppConfiguration::current();

        if (! $config || ! $config->isReadyToSend()) {
            return 0;
        }

        $notification = WhatsAppNotification::firstOrCreate(
            [
                'user_id' => $user->id,
                'dedupe_key' => $dedupeKey,
            ],
            [
                'notifiable_type' => $notifiable?->getMorphClass(),
                'notifiable_id' => $notifiable?->getKey(),
                'event_type' => $eventType,
                'status' => 'pending',
            ]
        );

        if (! $notification->wasRecentlyCreated) {
            return 0;
        }

        try {
            if (config('whatsapp.queue', true)) {
                SendWhatsAppNotification::dispatch($notification->id, $message);
            } else {
                SendWhatsAppNotification::dispatchSync($notification->id, $message);
            }
        } catch (Throwable $e) {
            $notification->update([
                'status' => 'failed',
                'error_message' => 'Could not queue the WhatsApp notification. Check the queue configuration.',
            ]);

            Log::warning('Could not queue a WhatsApp reminder.', [
                'notification_id' => $notification->id,
                'exception' => $e::class,
            ]);

            return 0;
        }

        return 1;
    }

    private function inAppTitle(?Model $notifiable, string $eventType): string
    {
        if ($notifiable instanceof Task) {
            return $eventType === 'task_high_priority'
                ? 'Tugas prioritas tinggi: '.$notifiable->title
                : 'Deadline tugas: '.$notifiable->title;
        }

        if ($notifiable instanceof Schedule) {
            return 'Jadwal: '.$notifiable->title;
        }

        return $eventType === 'task_high_priority'
            ? 'Ringkasan tugas prioritas tinggi'
            : 'Pengingat DailyMate';
    }

    private function taskDeadlineMessage(Task $task, int $offsetMinutes): string
    {
        $priority = ucfirst($task->priority);
        $offset = match ($offsetMinutes) {
            1440 => '1 hari',
            720 => '12 jam',
            180 => '3 jam',
            60 => '1 jam',
            default => $offsetMinutes.' menit',
        };

        return "Halo! Pengingat {$offset} sebelum deadline dari DailyMate.\n\n"
            ."Tugas kamu:\n{$task->title}\n\n"
            ."Prioritas: {$priority}\n"
            ."Deadline: {$this->formatDeadline($task->deadline)}\n\n"
            .'Jangan lupa selesaikan tugasmu tepat waktu!';
    }

    private function scheduleReminderMessage(Schedule $schedule): string
    {
        return "Halo! Ini pengingat aktivitas dari DailyMate.\n\n"
            ."Aktivitas: {$schedule->title}\n"
            ."Waktu: ".substr($schedule->start_time, 0, 5).' - '.substr($schedule->end_time, 0, 5)."\n\n"
            .'Persiapkan aktivitasmu dan jangan sampai terlewat!';
    }

    private function highPriorityMessage(User $user): string
    {
        $lines = $user->tasks->values()->map(function (Task $task, int $index): string {
            $line = ($index + 1).'. '.$task->title;

            if ($task->deadline) {
                $line .= ' (deadline '.$this->formatDeadline($task->deadline).')';
            }

            return $line;
        })->implode("\n");

        return "Halo {$user->name}! DailyMate mengingatkan tugas prioritas High yang belum selesai:\n\n"
            .$lines."\n\n"
            .'Kerjakan satu per satu dan beri jeda istirahat singkat di antara tugas.';
    }

    private function formatDeadline(CarbonInterface $deadline): string
    {
        $deadline = $deadline->copy()->setTimezone(config('app.timezone'));

        if ($deadline->isToday()) {
            return 'Hari ini pukul '.$deadline->format('H.i');
        }

        if ($deadline->isTomorrow()) {
            return 'Besok pukul '.$deadline->format('H.i');
        }

        return $deadline->format('d/m/Y').' pukul '.$deadline->format('H.i');
    }
}
