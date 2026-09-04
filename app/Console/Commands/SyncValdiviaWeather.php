<?php

namespace App\Console\Commands;

use App\Services\Weather\ValdiviaWeatherService;
use Illuminate\Console\Command;
use RuntimeException;

class SyncValdiviaWeather extends Command
{
    protected $signature = 'weather:sync-valdivia
                            {--days= : Cantidad de días de pronóstico (1 a 14)}
                            {--force : Fuerza una consulta adicional aunque ya exista un intento hoy}';

    protected $description = 'Sincroniza el pronóstico diario de Valdivia desde WeatherAPI';

    public function handle(ValdiviaWeatherService $weather): int
    {
        $daysOption = $this->option('days');

        if ($daysOption !== null && (! ctype_digit((string) $daysOption) || (int) $daysOption < 1 || (int) $daysOption > 14)) {
            $this->error('La opción --days debe ser un número entre 1 y 14.');

            return self::INVALID;
        }

        try {
            $count = $this->option('force')
                ? $weather->syncForecast($daysOption !== null ? (int) $daysOption : null)
                : $weather->syncToday($daysOption !== null ? (int) $daysOption : null);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($count === null) {
            $this->info('WeatherAPI ya tuvo su única consulta diaria para Valdivia. Se conservan los datos de la base de datos.');

            return self::SUCCESS;
        }

        $this->info("Pronóstico de Valdivia sincronizado: {$count} días ordenados y almacenados.");

        return self::SUCCESS;
    }
}
