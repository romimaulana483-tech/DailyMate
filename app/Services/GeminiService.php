<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiService
{
    /**
     * Send a message to Gemini. When no API key is configured, or the API
     * fails, a helpful local fallback response is returned instead.
     */
    public function reply(User $user, string $message): string
    {
        $apiKey = config('services.gemini.key');

        if (blank($apiKey)) {
            return $this->fallbackReply($user, $message);
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/'
                        .config('services.gemini.model', 'gemini-2.5-flash')
                        .':generateContent?key='.urlencode($apiKey),
                    [
                        'system_instruction' => [
                            'parts' => [[
                                'text' => $this->systemInstruction($user),
                            ]],
                        ],
                        'contents' => $this->conversationContents($user, $message),
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 800,
                        ],
                    ]
                );

            if ($response->successful()) {
                $parts = $response->json('candidates.0.content.parts', []);
                $text = collect($parts)->pluck('text')->filter()->implode("\n");

                if (filled($text)) {
                    return trim($text);
                }
            }

            Log::warning('Gemini API returned an unusable response.', [
                'status' => $response->status(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Gemini API request failed: '.$e->getMessage());
        }

        return $this->fallbackReply($user, $message);
    }

    private function systemInstruction(User $user): string
    {
        return "You are DailyMate AI, a friendly productivity coach inside a daily planner app. "
            ."The user's name is {$user->name}. Give practical, concise advice about task prioritisation, "
            ."time management, daily planning, and motivation. Use short paragraphs and numbered lists when useful. "
            ."Never invent tasks or schedules that are not in the provided context. "
            ."Do not ask for passwords or sensitive personal data. Keep answers under 250 words.";
    }

    /**
     * Include recent private conversation and a snapshot of the user's own
     * tasks/schedules so the advice is relevant. This data never leaves the
     * current user's context.
     */
    private function conversationContents(User $user, string $message): array
    {
        $contents = [];

        $recent = $user->chatHistories()
            ->latest()
            ->limit(6)
            ->get()
            ->reverse();

        foreach ($recent as $chat) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $chat->message]],
            ];
            $contents[] = [
                'role' => 'model',
                'parts' => [['text' => $chat->response]],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message."\n\n".$this->userContext($user)]],
        ];

        return $contents;
    }

    private function userContext(User $user): string
    {
        $openTasks = $user->tasks()
            ->where('status', '!=', 'completed')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->limit(5)
            ->get();

        $upcoming = $user->schedules()
            ->where('schedule_date', '>=', today())
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->limit(3)
            ->get();

        $total = $user->tasks()->count();
        $completed = $user->tasks()->where('status', 'completed')->count();

        $lines = [
            '--- User context (private) ---',
            "Tasks: {$total} total, {$completed} completed.",
            'Open tasks:',
        ];

        if ($openTasks->isEmpty()) {
            $lines[] = '- No open tasks.';
        } else {
            foreach ($openTasks as $task) {
                $lines[] = "- {$task->title} | priority: {$task->priority} | status: {$task->status}"
                    .($task->deadline ? ' | deadline: '.$task->deadline->format('Y-m-d H:i') : '');
            }
        }

        $lines[] = 'Upcoming schedules:';
        if ($upcoming->isEmpty()) {
            $lines[] = '- No upcoming schedules.';
        } else {
            foreach ($upcoming as $schedule) {
                $lines[] = "- {$schedule->title} on {$schedule->schedule_date->format('Y-m-d')} ({$schedule->time_range})";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Local productivity assistant used when Gemini is unavailable.
     */
    private function fallbackReply(User $user, string $message): string
    {
        $text = strtolower($message);
        $firstName = explode(' ', trim($user->name))[0];

        $openTasks = $user->tasks()
            ->where('status', '!=', 'completed')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->limit(3)
            ->get();

        $taskList = $openTasks->isEmpty()
            ? "You have no open tasks right now. A good moment to plan ahead or take a well-earned break."
            : $openTasks->map(fn (Task $task, int $index) => ($index + 1).'. '.$task->title
                .' ('.ucfirst($task->priority).' priority'
                .($task->deadline ? ', due '.$task->deadline->format('M j, H:i') : '')
                .')')->implode("\n");

        if (str_contains($text, 'priorit') || str_contains($text, 'start') || str_contains($text, 'first')) {
            return "Here is how I would prioritise your work, {$firstName}:\n\n"
                .$taskList."\n\nStart with the task that is both high priority and closest to its deadline. "
                ."Work on it for 25 focused minutes, then take a 5-minute break. "
                ."Only move to the next task when the first one is finished or genuinely blocked.";
        }

        if (str_contains($text, 'plan') || str_contains($text, 'schedule') || str_contains($text, 'tomorrow') || str_contains($text, 'today')) {
            $schedules = $user->schedules()
                ->whereDate('schedule_date', today())
                ->orderBy('start_time')
                ->get();

            $scheduleText = $schedules->isEmpty()
                ? "You have no fixed schedule today, so you can design the day around your priorities."
                : "Your schedule today:\n".$schedules->map(fn ($s) => '- '.$s->time_range.' '.$s->title)->implode("\n");

            return "A simple plan for today, {$firstName}:\n\n"
                .$scheduleText."\n\nTop priorities:\n".$taskList
                ."\n\nSuggested rhythm:\n1. 09:00 - 10:30 Deep work on your most important task\n"
                ."2. 10:30 - 10:45 Short break and stretch\n"
                ."3. 11:00 - 12:00 Clear smaller tasks\n"
                ."4. 16:30 - 17:00 Review progress and plan tomorrow\n\n"
                ."Keep one buffer hour free for unexpected work.";
        }

        if (str_contains($text, 'time') || str_contains($text, 'manage') || str_contains($text, 'focus') || str_contains($text, 'procrast')) {
            return "Time management tips that are easy to apply:\n\n"
                ."1. Pick a single Most Important Task each day.\n"
                ."2. Use the 25/5 focus method: 25 minutes of work, 5 minutes of rest.\n"
                ."3. Batch small admin tasks into one 30-minute block.\n"
                ."4. Protect your calendar by blocking deep-work time.\n"
                ."5. End the day with a two-minute review of what moved forward.\n\n"
                ."Your current focus list:\n".$taskList;
        }

        if (str_contains($text, 'motivat') || str_contains($text, 'tired') || str_contains($text, 'stuck') || str_contains($text, 'stress')) {
            return "Hey {$firstName}, progress does not have to be dramatic to count. "
                ."Pick the smallest useful next step, set a timer for 10 minutes, and give yourself permission to stop after that. "
                ."Momentum usually takes care of the rest.\n\n"
                ."One quick win to start with:\n".$taskList
                ."\n\nRemember to drink water, move your body, and take a real break. Consistency beats intensity.";
        }

        if (str_contains($text, 'tip') || str_contains($text, 'productiv')) {
            return "Three productivity habits worth building, {$firstName}:\n\n"
                ."1. Plan tomorrow before you finish today.\n"
                ."2. Limit your active list to three priorities.\n"
                ."3. Track completed work so you can see real progress.\n\n"
                ."Based on your data, your next three jobs are:\n".$taskList
                ."\n\nWould you like help turning these into a daily schedule?";
        }

        return "Hi {$firstName}, I am your DailyMate assistant. I can help you prioritise tasks, plan your day, manage time, and stay motivated.\n\n"
            ."Your current focus list:\n".$taskList."\n\n"
            ."Try asking:\n- What should I work on first?\n- Help me plan tomorrow\n- Give me a productivity tip\n- I need some motivation\n\n"
            ."Note: Gemini API is not configured, so I am running in smart offline mode.";
    }
}
