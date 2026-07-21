<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class ProductionSafetyServiceProvider extends ServiceProvider
{
    private const BLOCKED_DATABASE_COMMANDS = [
        'db:seed',
        'db:wipe',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
    ];

    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($this->app->environment(['local', 'development', 'testing'])) {
                return;
            }

            if (in_array($event->command, self::BLOCKED_DATABASE_COMMANDS, true)) {
                throw new RuntimeException(sprintf(
                    'El comando "%s" está bloqueado fuera de entornos de desarrollo para proteger los datos de todos los módulos.',
                    $event->command
                ));
            }
        });
    }
}
