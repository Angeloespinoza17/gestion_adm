<?php

namespace Tests\Feature\Weather;

use App\Models\User;
use App\Models\WeatherDailyRecord;
use App\Models\WeatherDailySync;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValdiviaWeatherIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_stores_ordered_daily_valdivia_data_without_duplicates(): void
    {
        Carbon::setTestNow('2026-08-30 10:00:00');
        config()->set('services.weatherapi.key', 'weather-test-key');
        config()->set('services.weatherapi.base_url', 'https://weather.test/v1');
        $updatedPayload = $this->forecastPayload();
        $updatedPayload['forecast']['forecastday'][0]['day']['maxtemp_c'] = 12.4;
        Http::preventStrayRequests();
        Http::fake([
            'weather.test/*' => Http::sequence()
                ->push($this->forecastPayload(), 200)
                ->push($updatedPayload, 200),
        ]);

        $this->artisan('weather:sync-valdivia', ['--days' => 2])
            ->expectsOutput('Pronóstico de Valdivia sincronizado: 2 días ordenados y almacenados.')
            ->assertSuccessful();

        $this->assertDatabaseCount('weather_daily_records', 2);
        $this->assertDatabaseHas('weather_daily_syncs', [
            'provider' => 'weatherapi',
            'location_key' => 'valdivia-los-rios-cl',
            'sync_date' => '2026-08-30',
            'status' => WeatherDailySync::STATUS_COMPLETED,
            'records_count' => 2,
        ]);
        $first = WeatherDailyRecord::query()->orderBy('forecast_date')->firstOrFail();
        $this->assertSame('2026-08-30', $first->forecast_date->toDateString());
        $this->assertSame('Valdivia', $first->location_name);
        $this->assertSame('Región de Los Ríos', $first->administrative_region);
        $this->assertSame('Los Lagos', $first->provider_region);
        $this->assertSame(85, $first->chance_of_rain);
        $this->assertSame('Chubasco ligero', $first->condition_text);
        $this->assertSame('https://cdn.weatherapi.com/weather/64x64/day/353.png', $first->icon_url);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://weather.test/v1/forecast.json?q=-39.8142%2C-73.2459&days=2&lang=es&aqi=no&alerts=no&key=weather-test-key'
                || ($request['q'] === '-39.8142,-73.2459'
                    && (int) $request['days'] === 2
                    && $request['lang'] === 'es'
                    && $request['key'] === 'weather-test-key');
        });

        $this->artisan('weather:sync-valdivia', ['--days' => 2])
            ->expectsOutput('WeatherAPI ya tuvo su única consulta diaria para Valdivia. Se conservan los datos de la base de datos.')
            ->assertSuccessful();

        Http::assertSentCount(1);

        $this->artisan('weather:sync-valdivia', ['--days' => 2, '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('weather_daily_records', 2);
        $this->assertDatabaseCount('weather_daily_syncs', 1);
        $this->assertSame(12.4, (float) WeatherDailyRecord::query()
            ->whereDate('forecast_date', '2026-08-30')
            ->value('max_temp_c'));

        Carbon::setTestNow();
    }

    public function test_home_returns_weather_from_database_ordered_by_date(): void
    {
        Carbon::setTestNow('2026-08-30 10:00:00');
        config()->set('services.weatherapi.key', null);
        $this->createRecord('2026-08-31', 10.5, 'Niebla');
        $this->createRecord('2026-08-30', 8.2, 'Chubasco ligero');
        $this->createRecord('2026-08-29', 9.0, 'Nublado');

        Sanctum::actingAs(User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
        ]));

        $this->getJson('/api/inicio/overview')
            ->assertOk()
            ->assertJsonPath('weather.location.name', 'Valdivia')
            ->assertJsonPath('weather.location.region', 'Región de Los Ríos')
            ->assertJsonPath('weather.days.0.date', '2026-08-30')
            ->assertJsonPath('weather.days.0.condition.text', 'Chubasco ligero')
            ->assertJsonPath('weather.days.1.date', '2026-08-31')
            ->assertJsonCount(2, 'weather.days');

        Carbon::setTestNow();
    }

    public function test_future_forecast_is_updated_when_its_date_becomes_today(): void
    {
        config()->set('services.weatherapi.key', 'weather-test-key');
        config()->set('services.weatherapi.base_url', 'https://weather.test/v1');

        $earlyForecast = $this->singleDayForecastPayload(
            '2026-09-06',
            17.8,
            20,
            'Parcialmente nublado',
        );
        $sameDayForecast = $this->singleDayForecastPayload(
            '2026-09-06',
            11.4,
            95,
            'Lluvia fuerte',
        );

        Http::preventStrayRequests();
        Http::fake([
            'weather.test/*' => Http::sequence()
                ->push($earlyForecast, 200)
                ->push($sameDayForecast, 200),
        ]);

        Carbon::setTestNow('2026-09-01 08:00:00');
        $this->artisan('weather:sync-valdivia', ['--days' => 7])->assertSuccessful();

        $initial = WeatherDailyRecord::query()
            ->where('forecast_date', '2026-09-06')
            ->firstOrFail();

        $this->assertSame(17.8, (float) $initial->max_temp_c);
        $this->assertSame(20, $initial->chance_of_rain);
        $this->assertSame('2026-09-01', $initial->created_at->toDateString());
        $this->assertSame('2026-09-01', $initial->fetched_at->toDateString());
        $initialId = $initial->id;

        Carbon::setTestNow('2026-09-06 08:00:00');
        $this->artisan('weather:sync-valdivia', ['--days' => 7])->assertSuccessful();

        $refreshed = WeatherDailyRecord::query()->findOrFail($initialId);

        $this->assertDatabaseCount('weather_daily_records', 1);
        $this->assertDatabaseCount('weather_daily_syncs', 2);
        $this->assertSame('2026-09-06', $refreshed->forecast_date->toDateString());
        $this->assertSame(11.4, (float) $refreshed->max_temp_c);
        $this->assertSame(95, $refreshed->chance_of_rain);
        $this->assertSame('Lluvia fuerte', $refreshed->condition_text);
        $this->assertSame('2026-09-01', $refreshed->created_at->toDateString());
        $this->assertSame('2026-09-06', $refreshed->fetched_at->toDateString());
        Http::assertSentCount(2);

        Carbon::setTestNow();
    }

    public function test_first_home_request_queries_weatherapi_and_following_users_read_the_database(): void
    {
        Carbon::setTestNow('2026-08-30 10:00:00');
        config()->set('services.weatherapi.key', 'weather-test-key');
        config()->set('services.weatherapi.base_url', 'https://weather.test/v1');
        Http::preventStrayRequests();
        Http::fake([
            'weather.test/*' => Http::response($this->forecastPayload(), 200),
        ]);

        $firstUser = User::factory()->create(['active' => true, 'user_type' => 'staff']);
        $secondUser = User::factory()->create(['active' => true, 'user_type' => 'staff']);

        Sanctum::actingAs($firstUser);
        $this->getJson('/api/inicio/overview')
            ->assertOk()
            ->assertJsonPath('weather.days.0.date', '2026-08-30')
            ->assertJsonPath('weather.days.1.date', '2026-08-31');

        Sanctum::actingAs($secondUser);
        $this->getJson('/api/inicio/overview')
            ->assertOk()
            ->assertJsonPath('weather.days.0.condition.text', 'Chubasco ligero')
            ->assertJsonCount(2, 'weather.days');

        Http::assertSentCount(1);
        $this->assertDatabaseCount('weather_daily_records', 2);
        $this->assertDatabaseCount('weather_daily_syncs', 1);
        $this->assertDatabaseHas('weather_daily_syncs', [
            'sync_date' => '2026-08-30',
            'status' => WeatherDailySync::STATUS_COMPLETED,
            'records_count' => 2,
        ]);

        Carbon::setTestNow();
    }

    public function test_failed_daily_query_is_recorded_and_is_not_repeated_by_other_users(): void
    {
        Carbon::setTestNow('2026-08-30 10:00:00');
        config()->set('services.weatherapi.key', 'weather-test-key');
        config()->set('services.weatherapi.base_url', 'https://weather.test/v1');
        Http::preventStrayRequests();
        Http::fake([
            'weather.test/*' => Http::response([
                'error' => ['message' => 'Límite temporal del proveedor'],
            ], 503),
        ]);
        $this->createRecord('2026-08-30', 8.2, 'Dato almacenado');

        Sanctum::actingAs(User::factory()->create([
            'active' => true,
            'user_type' => 'staff',
        ]));

        $this->getJson('/api/inicio/overview')
            ->assertOk()
            ->assertJsonPath('weather.days.0.condition.text', 'Dato almacenado')
            ->assertJsonCount(1, 'weather.days');

        $this->getJson('/api/inicio/overview')
            ->assertOk()
            ->assertJsonPath('weather.days.0.condition.text', 'Dato almacenado')
            ->assertJsonCount(1, 'weather.days');

        Http::assertSentCount(1);
        $this->assertDatabaseHas('weather_daily_syncs', [
            'sync_date' => '2026-08-30',
            'status' => WeatherDailySync::STATUS_FAILED,
            'records_count' => 0,
            'error_message' => 'Límite temporal del proveedor',
        ]);

        Carbon::setTestNow();
    }

    public function test_sync_fails_cleanly_when_the_server_key_is_missing(): void
    {
        config()->set('services.weatherapi.key', null);
        Http::preventStrayRequests();

        $this->artisan('weather:sync-valdivia')
            ->expectsOutput('WeatherAPI no está configurado. Define WEATHERAPI_KEY.')
            ->assertFailed();

        $this->assertDatabaseCount('weather_daily_records', 0);
        $this->assertDatabaseCount('weather_daily_syncs', 0);
    }

    private function createRecord(string $date, float $maxTemp, string $condition): void
    {
        WeatherDailyRecord::query()->create([
            'provider' => 'weatherapi',
            'location_key' => 'valdivia-los-rios-cl',
            'location_name' => 'Valdivia',
            'administrative_region' => 'Región de Los Ríos',
            'provider_region' => 'Los Lagos',
            'country' => 'Chile',
            'latitude' => -39.8142,
            'longitude' => -73.2459,
            'timezone' => 'America/Santiago',
            'forecast_date' => $date,
            'record_type' => 'forecast',
            'min_temp_c' => 4.5,
            'max_temp_c' => $maxTemp,
            'avg_temp_c' => 7.2,
            'max_wind_kph' => 18.4,
            'total_precip_mm' => 4.2,
            'avg_humidity' => 88,
            'chance_of_rain' => 85,
            'condition_text' => $condition,
            'condition_code' => 1240,
            'icon_url' => 'https://cdn.weatherapi.com/weather/64x64/day/353.png',
            'sunrise' => '07:16 AM',
            'sunset' => '06:21 PM',
            'provider_localtime' => now(),
            'fetched_at' => now(),
        ]);
    }

    private function forecastPayload(): array
    {
        return [
            'location' => [
                'name' => 'Valdivia',
                'region' => 'Los Lagos',
                'country' => 'Chile',
                'lat' => -39.8,
                'lon' => -73.25,
                'tz_id' => 'America/Santiago',
                'localtime' => '2026-08-30 10:00',
            ],
            'forecast' => [
                'forecastday' => [
                    [
                        'date' => '2026-08-30',
                        'day' => [
                            'mintemp_c' => 6.6,
                            'maxtemp_c' => 8.2,
                            'avgtemp_c' => 7.4,
                            'maxwind_kph' => 23.8,
                            'totalprecip_mm' => 7.4,
                            'avghumidity' => 91,
                            'daily_chance_of_rain' => 85,
                            'condition' => [
                                'text' => 'Chubasco ligero',
                                'icon' => '//cdn.weatherapi.com/weather/64x64/day/353.png',
                                'code' => 1240,
                            ],
                        ],
                        'astro' => ['sunrise' => '07:16 AM', 'sunset' => '06:21 PM'],
                    ],
                    [
                        'date' => '2026-08-31',
                        'day' => [
                            'mintemp_c' => 4.5,
                            'maxtemp_c' => 11.1,
                            'avgtemp_c' => 7.5,
                            'maxwind_kph' => 12.2,
                            'totalprecip_mm' => 0.3,
                            'avghumidity' => 90,
                            'daily_chance_of_rain' => 20,
                            'condition' => [
                                'text' => 'Niebla',
                                'icon' => '//cdn.weatherapi.com/weather/64x64/day/248.png',
                                'code' => 1135,
                            ],
                        ],
                        'astro' => ['sunrise' => '07:14 AM', 'sunset' => '06:22 PM'],
                    ],
                ],
            ],
        ];
    }

    private function singleDayForecastPayload(
        string $date,
        float $maxTemp,
        int $chanceOfRain,
        string $condition,
    ): array {
        $payload = $this->forecastPayload();
        $forecastDay = $payload['forecast']['forecastday'][0];
        $forecastDay['date'] = $date;
        $forecastDay['day']['maxtemp_c'] = $maxTemp;
        $forecastDay['day']['daily_chance_of_rain'] = $chanceOfRain;
        $forecastDay['day']['condition']['text'] = $condition;
        $payload['location']['localtime'] = $date.' 08:00';
        $payload['forecast']['forecastday'] = [$forecastDay];

        return $payload;
    }
}
