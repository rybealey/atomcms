<?php

namespace App\Console;

use App\Models\User\WebsiteLoginLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Discord linking: drain the emulator's event queue every minute,
        // and run a full reconciliation sweep as a drift backstop.
        // The overlap lock expires after 10 minutes, not the default 24
        // hours: a run that gets killed never releases it, and the lock
        // outlives restarts, so it would block the drain for a day.
        $schedule->command('discord:process')
            ->everyMinute()
            ->withoutOverlapping(10);

        $schedule->command('discord:sweep')
            ->everyTenMinutes()
            ->withoutOverlapping();

        // pixelrp: the log in history (WebsiteLoginLog) is kept for 90 days.
        $schedule->command('model:prune', ['--model' => [WebsiteLoginLog::class]])->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
