<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reminder timing
    |--------------------------------------------------------------------------
    |
    | The scheduler runs every five minutes. These windows decide which
    | reminders are due and are intentionally configurable.
    |
    */

    'deadline_lead_minutes' => (int) env('WHATSAPP_DEADLINE_LEAD_MINUTES', 60),

    'schedule_lead_minutes' => (int) env('WHATSAPP_SCHEDULE_LEAD_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    |
    | Queue delivery is recommended in production. Set WHATSAPP_QUEUE=false
    | for shared hosting where a queue worker cannot be kept running.
    |
    */

    'queue' => (bool) env('WHATSAPP_QUEUE', true),

    'http_timeout' => (int) env('WHATSAPP_HTTP_TIMEOUT', 20),

];
