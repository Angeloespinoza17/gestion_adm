<?php

namespace App\Services\Weather;

use App\Models\WeatherDailyRecord;
use App\Models\WeatherDailySync;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ValdiviaWeatherService
{
    private const PROVIDER = 'weatherapi';

    private ?bool $dailyRecordsTableExists = null;

    private ?bool $dailySyncsTableExists = null;

    /**
     * Ejecuta como máximo una consulta diaria al proveedor para esta ubicación.
     *
     * El índice único de weather_daily_syncs funciona como exclusión entre
     * solicitudes y servidores: solo quien inserta el registro diario consulta
     * WeatherAPI. Un valor null indica que otro proceso ya tomó el intento.
     */
    public function syncToday(?int $requestedDays = null): ?int
    {
        $this->ensureReadyForSync();

        $now = now(config('app.timezone'));
        $locationKey = (string) config('services.weatherapi.location_key');
        $syncDate = $now->toDateString();

        if (WeatherDailySync::query()
            ->where('provider', self::PROVIDER)
            ->where('location_key', $locationKey)
            ->where('sync_date', $syncDate)
            ->exists()) {
            return null;
        }

        $inserted = DB::table('weather_daily_syncs')->insertOrIgnore([
            'provider' => self::PROVIDER,
            'location_key' => $locationKey,
            'sync_date' => $syncDate,
            'status' => WeatherDailySync::STATUS_PROCESSING,
            'requested_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            return null;
        }

        try {
            $count = $this->syncForecast($requestedDays);

            $this->finishDailySync($syncDate, [
                'status' => WeatherDailySync::STATUS_COMPLETED,
                'completed_at' => now(config('app.timezone')),
                'records_count' => $count,
                'error_message' => null,
            ]);

            return $count;
        } catch (Throwable $exception) {
            $this->finishDailySync($syncDate, [
                'status' => WeatherDailySync::STATUS_FAILED,
                'completed_at' => now(config('app.timezone')),
                'records_count' => 0,
                'error_message' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw $exception;
        }
    }

    public function syncForecast(?int $requestedDays = null): int
    {
        $apiKey = trim((string) config('services.weatherapi.key'));

        if ($apiKey === '') {
            throw new RuntimeException('WeatherAPI no está configurado. Define WEATHERAPI_KEY.');
        }

        $days = max(1, min($requestedDays ?? (int) config('services.weatherapi.forecast_days', 7), 14));

        try {
            $response = Http::baseUrl(rtrim((string) config('services.weatherapi.base_url'), '/'))
                ->acceptJson()
                ->timeout(15)
                ->connectTimeout(5)
                ->get('/forecast.json', [
                    'key' => $apiKey,
                    'q' => config('services.weatherapi.query'),
                    'days' => $days,
                    'lang' => 'es',
                    'aqi' => 'no',
                    'alerts' => 'no',
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('No fue posible conectar con WeatherAPI.');
        }

        if (! $response->successful()) {
            $message = (string) $response->json('error.message', 'WeatherAPI rechazó la solicitud.');

            throw new RuntimeException($message);
        }

        $payload = $response->json();
        $location = is_array($payload['location'] ?? null) ? $payload['location'] : [];
        $forecastDays = data_get($payload, 'forecast.forecastday');

        $this->assertValdiviaResponse($location, $forecastDays);

        $now = now(config('app.timezone'));
        $rows = collect($forecastDays)
            ->map(fn (array $forecastDay): array => $this->mapForecastDay($forecastDay, $location, $now))
            ->all();

        DB::transaction(function () use ($rows): void {
            WeatherDailyRecord::query()->upsert(
                $rows,
                ['provider', 'location_key', 'forecast_date'],
                [
                    // No se actualiza created_at: conserva cuándo esa fecha fue
                    // pronosticada por primera vez. fetched_at y los valores
                    // meteorológicos sí se reemplazan con la medición diaria
                    // más cercana y, por tanto, más precisa.
                    'location_name', 'administrative_region', 'provider_region', 'country',
                    'latitude', 'longitude', 'timezone', 'record_type', 'min_temp_c',
                    'max_temp_c', 'avg_temp_c', 'max_wind_kph', 'total_precip_mm',
                    'avg_humidity', 'chance_of_rain', 'condition_text', 'condition_code',
                    'icon_url', 'sunrise', 'sunset', 'source_daily_payload',
                    'provider_localtime', 'fetched_at', 'updated_at',
                ],
            );
        });

        return count($rows);
    }

    public function dashboard(): array
    {
        $empty = [
            'provider' => 'WeatherAPI.com',
            'location' => [
                'name' => config('services.weatherapi.location_name', 'Valdivia'),
                'region' => config('services.weatherapi.administrative_region', 'Región de Los Ríos'),
                'country' => 'Chile',
            ],
            'last_synced_at' => null,
            'days' => [],
        ];

        if (! $this->dailyRecordsTableExists()) {
            return $empty;
        }

        if (filled(config('services.weatherapi.key')) && $this->dailySyncsTableExists()) {
            try {
                $this->syncToday();
            } catch (Throwable $exception) {
                Log::warning('No fue posible completar la consulta meteorológica diaria.', [
                    'provider' => self::PROVIDER,
                    'location_key' => config('services.weatherapi.location_key'),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $records = WeatherDailyRecord::query()
            ->where('provider', self::PROVIDER)
            ->where('location_key', config('services.weatherapi.location_key'))
            ->where('forecast_date', '>=', now(config('app.timezone'))->toDateString())
            ->orderBy('forecast_date')
            ->limit(max(1, min((int) config('services.weatherapi.display_days', 7), 14)))
            ->get();

        if ($records->isEmpty()) {
            return $empty;
        }

        $first = $records->first();

        return [
            'provider' => 'WeatherAPI.com',
            'location' => [
                'name' => $first->location_name,
                'region' => $first->administrative_region,
                'country' => $first->country,
            ],
            'last_synced_at' => $records->max('fetched_at')?->toIso8601String(),
            'days' => $records->map(fn (WeatherDailyRecord $record): array => [
                'date' => $record->forecast_date->toDateString(),
                'min_temp_c' => (float) $record->min_temp_c,
                'max_temp_c' => (float) $record->max_temp_c,
                'avg_temp_c' => (float) $record->avg_temp_c,
                'max_wind_kph' => (float) $record->max_wind_kph,
                'total_precip_mm' => (float) $record->total_precip_mm,
                'avg_humidity' => $record->avg_humidity,
                'chance_of_rain' => $record->chance_of_rain,
                'condition' => [
                    'text' => $record->condition_text,
                    'code' => $record->condition_code,
                    'icon_url' => $record->icon_url,
                ],
                'sunrise' => $record->sunrise,
                'sunset' => $record->sunset,
            ])->all(),
        ];
    }

    private function assertValdiviaResponse(array $location, mixed $forecastDays): void
    {
        if (mb_strtolower((string) ($location['name'] ?? '')) !== 'valdivia'
            || mb_strtolower((string) ($location['country'] ?? '')) !== 'chile') {
            throw new RuntimeException('WeatherAPI no devolvió la ubicación esperada: Valdivia, Chile.');
        }

        if (! is_array($forecastDays) || $forecastDays === []) {
            throw new RuntimeException('WeatherAPI no devolvió días de pronóstico.');
        }
    }

    private function ensureReadyForSync(): void
    {
        if (trim((string) config('services.weatherapi.key')) === '') {
            throw new RuntimeException('WeatherAPI no está configurado. Define WEATHERAPI_KEY.');
        }

        if (! $this->dailyRecordsTableExists() || ! $this->dailySyncsTableExists()) {
            throw new RuntimeException('Las tablas del clima no están disponibles. Ejecuta las migraciones pendientes.');
        }
    }

    private function finishDailySync(string $syncDate, array $values): void
    {
        WeatherDailySync::query()
            ->where('provider', self::PROVIDER)
            ->where('location_key', config('services.weatherapi.location_key'))
            ->where('sync_date', $syncDate)
            ->update($values + ['updated_at' => now(config('app.timezone'))]);
    }

    private function dailyRecordsTableExists(): bool
    {
        return $this->dailyRecordsTableExists ??= Schema::hasTable('weather_daily_records');
    }

    private function dailySyncsTableExists(): bool
    {
        return $this->dailySyncsTableExists ??= Schema::hasTable('weather_daily_syncs');
    }

    private function mapForecastDay(array $forecastDay, array $location, mixed $now): array
    {
        $date = $forecastDay['date'] ?? null;
        $day = is_array($forecastDay['day'] ?? null) ? $forecastDay['day'] : [];
        $condition = is_array($day['condition'] ?? null) ? $day['condition'] : [];
        $astro = is_array($forecastDay['astro'] ?? null) ? $forecastDay['astro'] : [];

        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new RuntimeException('WeatherAPI devolvió una fecha de pronóstico inválida.');
        }

        $icon = (string) ($condition['icon'] ?? '');
        if (str_starts_with($icon, '//')) {
            $icon = 'https:'.$icon;
        }

        return [
            'provider' => self::PROVIDER,
            'location_key' => config('services.weatherapi.location_key'),
            'location_name' => config('services.weatherapi.location_name', 'Valdivia'),
            'administrative_region' => config('services.weatherapi.administrative_region', 'Región de Los Ríos'),
            'provider_region' => $location['region'] ?? null,
            'country' => 'Chile',
            'latitude' => (float) ($location['lat'] ?? config('services.weatherapi.latitude')),
            'longitude' => (float) ($location['lon'] ?? config('services.weatherapi.longitude')),
            'timezone' => (string) ($location['tz_id'] ?? config('app.timezone')),
            'forecast_date' => $date,
            'record_type' => 'forecast',
            'min_temp_c' => $day['mintemp_c'] ?? null,
            'max_temp_c' => $day['maxtemp_c'] ?? null,
            'avg_temp_c' => $day['avgtemp_c'] ?? null,
            'max_wind_kph' => $day['maxwind_kph'] ?? null,
            'total_precip_mm' => $day['totalprecip_mm'] ?? null,
            'avg_humidity' => $day['avghumidity'] ?? null,
            'chance_of_rain' => $day['daily_chance_of_rain'] ?? null,
            'condition_text' => $condition['text'] ?? null,
            'condition_code' => $condition['code'] ?? null,
            'icon_url' => $icon !== '' ? $icon : null,
            'sunrise' => $astro['sunrise'] ?? null,
            'sunset' => $astro['sunset'] ?? null,
            'source_daily_payload' => json_encode([
                'day' => $day,
                'astro' => $astro,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'provider_localtime' => $location['localtime'] ?? null,
            'fetched_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
