<?php

namespace Database\Seeders;

use App\Models\ChatHistory;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed realistic demo content so every dashboard and chart has data.
     */
    public function run(): void
    {
        $password = Hash::make('User12345');

        $demo = User::updateOrCreate(
            ['email' => 'user@dailymate.test'],
            [
                'name' => 'Alya Putri',
                'password' => $password,
                'role' => 'user',
                'status' => 'active',
                'email_verified_at' => now()->subMonths(2),
                'created_at' => now()->subMonths(2),
            ]
        );

        // A second account makes data isolation easy to verify manually.
        $second = User::updateOrCreate(
            ['email' => 'budi@dailymate.test'],
            [
                'name' => 'Budi Santoso',
                'password' => $password,
                'role' => 'user',
                'status' => 'active',
                'email_verified_at' => now()->subMonth(),
                'created_at' => now()->subMonth(),
            ]
        );

        $this->seedTasksFor($demo);
        $this->seedSchedulesFor($demo);
        $this->seedChatsFor($demo);

        Task::factory()->count(4)->create(['user_id' => $second->id]);
        Schedule::factory()->count(3)->create(['user_id' => $second->id]);

        // Additional users give the admin statistics something meaningful to show.
        User::factory()->count(7)->create(['password' => $password]);
        User::factory()->count(3)->inactive()->create(['password' => $password]);
    }

    private function seedTasksFor(User $user): void
    {
        $tasks = [
            ['Finalize project proposal', 'Review the proposal, add the budget section, and send it to the client.', 'Work', 'high', 'in_progress', now()->addDay()],
            ['Prepare weekly team meeting', 'Create the agenda and share the meeting link with the team.', 'Work', 'medium', 'todo', now()->addDays(2)],
            ['Complete Laravel assignment', 'Finish the authentication and authorization module for the campus project.', 'Study', 'high', 'todo', now()->addDays(3)],
            ['Morning workout', '30 minutes of cardio followed by stretching.', 'Health', 'low', 'completed', now()->subDay()],
            ['Pay electricity bill', 'Pay the monthly electricity bill before the due date.', 'Finance', 'high', 'todo', now()->addDays(4)],
            ['Read 20 pages of a book', 'Continue reading the productivity book on the bedside table.', 'Personal', 'low', 'completed', now()->subDays(2)],
            ['Update portfolio website', 'Add the newest projects and refresh the about section.', 'Personal', 'medium', 'in_progress', now()->addDays(5)],
            ['Review monthly budget', 'Compare spending with the budget and adjust categories.', 'Finance', 'medium', 'completed', now()->subDays(3)],
            ['Design dashboard mockup', 'Create a clean SaaS dashboard mockup for the client presentation.', 'Work', 'high', 'completed', now()->subDays(4)],
            ['Study for statistics quiz', 'Review probability distributions and practice questions.', 'Study', 'medium', 'completed', now()->subDays(5)],
            ['Plan weekend hiking trip', 'Check the route, weather, and prepare the packing list.', 'Personal', 'low', 'todo', now()->addDays(6)],
            ['Drink 2L of water', 'Track water intake throughout the day.', 'Health', 'low', 'completed', now()->subHours(20)],
        ];

        foreach ($tasks as $index => [$title, $description, $category, $priority, $status, $deadline]) {
            $createdAt = now()->subDays(10)->addDays($index);

            $task = Task::create([
                'user_id' => $user->id,
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'priority' => $priority,
                'status' => $status,
                'deadline' => $deadline,
            ]);

            // Spread completion timestamps across recent days for meaningful charts.
            $task->created_at = $createdAt;
            if ($status === 'completed') {
                $task->updated_at = now()->subDays(5 - min($index, 5))->setTime(15, 30);
            }
            $task->saveQuietly();
        }
    }

    private function seedSchedulesFor(User $user): void
    {
        $schedules = [
            ['Team stand-up meeting', 'Daily sync with the product team.', 0, '09:00', '09:30'],
            ['Campus class - Web Programming', 'Practical session in the computer lab.', 1, '10:00', '12:00'],
            ['Lunch with project partner', 'Discuss the next milestone at the cafe.', 1, '12:30', '13:30'],
            ['Gym session', 'Upper body workout with a personal trainer.', 2, '18:00', '19:30'],
            ['Client presentation', 'Present the dashboard design to the client.', 3, '14:00', '15:00'],
            ['Study group', 'Prepare for the final statistics exam together.', 5, '19:00', '21:00'],
            ['Weekly review', 'Review progress and plan next week priorities.', 7, '16:00', '17:00'],
            ['Family dinner', 'Dinner at Grandma\'s house.', 9, '18:30', '20:00'],
        ];

        foreach ($schedules as [$title, $description, $offset, $start, $end]) {
            Schedule::create([
                'user_id' => $user->id,
                'title' => $title,
                'description' => $description,
                'schedule_date' => now()->addDays($offset)->toDateString(),
                'start_time' => $start,
                'end_time' => $end,
            ]);
        }
    }

    private function seedChatsFor(User $user): void
    {
        ChatHistory::create([
            'user_id' => $user->id,
            'message' => 'I have too many tasks today. Where should I start?',
            'response' => "Start with the task that has the highest priority and the nearest deadline. From your list, **Finalize project proposal** is a good first choice.\n\nTry the 25/5 method: focus for 25 minutes, then take a 5-minute break. Move low-priority items like plan the weekend trip to tomorrow if today feels packed.",
        ]);

        ChatHistory::create([
            'user_id' => $user->id,
            'message' => 'Help me plan tomorrow.',
            'response' => "Here is a simple plan for tomorrow:\n\n1. 08:00 - Review your top 3 priorities\n2. 09:00 - Deep work: finalize the project proposal\n3. 12:00 - Lunch and a short walk\n4. 13:00 - Complete the Laravel assignment\n5. 16:00 - Weekly review and inbox clean-up\n\nKeep one buffer hour for unexpected work and protect your energy with a proper break.",
        ]);
    }
}
