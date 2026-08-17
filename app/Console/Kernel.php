<?php

namespace App\Console;

use App\Jobs\MonitorLibroDigitalServices;
use App\Jobs\RunLibroDigitalIntegrityCheck;
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
        $schedule->job(new MonitorLibroDigitalServices)
            ->everyFiveMinutes()
            ->onOneServer()
            ->withoutOverlapping();
        $schedule->job(new RunLibroDigitalIntegrityCheck)
            ->dailyAt('02:40')
            ->onOneServer()
            ->withoutOverlapping();
        $schedule->command('lcd:detect-missing-sessions')
            ->weekdays()
            ->dailyAt('18:15')
            ->onOneServer()
            ->withoutOverlapping();
        $schedule->command('lcd:detect-missing-signatures')
            ->weekdays()
            ->dailyAt('19:00')
            ->onOneServer()
            ->withoutOverlapping();
        $schedule->command('backup:database')
            ->cron(config('backup.schedule'))
            ->environments(['production'])
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->command('messaging:send-acknowledgement-reminders')
            ->everyThirtyMinutes()
            ->withoutOverlapping();
        $schedule->command('messaging:cleanup-temporary-uploads')
            ->hourly()
            ->withoutOverlapping();
        $schedule->command('social-work:evaluate-risks')
            ->dailyAt('06:50')
            ->onOneServer()
            ->withoutOverlapping();
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
