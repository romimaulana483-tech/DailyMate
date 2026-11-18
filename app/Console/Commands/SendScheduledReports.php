<?php

namespace App\Console\Commands;

use App\Services\AdminReportService;
use Illuminate\Console\Command;

class SendScheduledReports extends Command
{
    protected $signature = 'reports:send-due';

    protected $description = 'Send due daily, weekly and monthly aggregate admin reports';

    public function handle(AdminReportService $reports): int
    {
        $count = $reports->sendDue();

        $this->info("Sent {$count} scheduled admin report(s).");

        return self::SUCCESS;
    }
}
