<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('permissions:notify-upcoming --days=2')->dailyAt('07:00');
        $schedule->command('attendance:rebuild-alerts')->dailyAt('06:30')->withoutOverlapping();
        $schedule->command('attendance:run-scheduled-reports')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('backup:database')
            ->cron(config('backup.schedule'))
            ->environments(['production'])
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
