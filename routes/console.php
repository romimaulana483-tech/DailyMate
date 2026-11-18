<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| WhatsApp reminders
|--------------------------------------------------------------------------
|
| Run Laravel's scheduler every minute (php artisan schedule:run). Due
| deadline/schedule reminders are checked every five minutes, while the
| high-priority summary is sent once each morning.
|
*/

Schedule::command('whatsapp:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('whatsapp:send-reminders --high-priority')
    ->dailyAt('08:00')
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Scheduled admin reports
|--------------------------------------------------------------------------
|
| The command checks the saved send time and whether a daily, weekly, or
| monthly report is due. It is safe to run every minute.
|
*/

Schedule::command('reports:send-due')
    ->everyMinute()
    ->withoutOverlapping();
