<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infirmary_medication_authorizations', function (Blueprint $table) {
            $table->unsignedTinyInteger('daily_dose_count')->nullable()->after('frequency');
            $table->string('schedule_mode', 40)->default('fixed_time')->after('daily_dose_count');
        });

        Schema::create('infirmary_medication_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authorization_id');
            $table->foreign('authorization_id', 'inf_med_schedule_auth_fk')
                ->references('id')
                ->on('infirmary_medication_authorizations')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('dose_order');
            $table->time('scheduled_time')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['authorization_id', 'dose_order'], 'inf_med_schedule_auth_order_unique');
            $table->index(['active', 'scheduled_time'], 'inf_med_schedule_active_time_idx');
        });

        Schema::table('infirmary_medication_administrations', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->after('authorization_id');
            $table->foreign('schedule_id', 'inf_med_admin_schedule_fk')
                ->references('id')
                ->on('infirmary_medication_schedules')
                ->nullOnDelete();
            $table->date('scheduled_for_date')->nullable()->after('administered_at');
            $table->unique(['schedule_id', 'scheduled_for_date'], 'inf_med_admin_schedule_date_unique');
        });

        $this->backfillExistingSchedules();
    }

    public function down(): void
    {
        Schema::table('infirmary_medication_administrations', function (Blueprint $table) {
            $table->dropUnique('inf_med_admin_schedule_date_unique');
            $table->dropForeign('inf_med_admin_schedule_fk');
            $table->dropColumn(['schedule_id', 'scheduled_for_date']);
        });

        Schema::dropIfExists('infirmary_medication_schedules');

        Schema::table('infirmary_medication_authorizations', function (Blueprint $table) {
            $table->dropColumn(['daily_dose_count', 'schedule_mode']);
        });
    }

    private function backfillExistingSchedules(): void
    {
        DB::table('infirmary_medication_authorizations')
            ->select(['id', 'frequency', 'schedule_text', 'regimen_type'])
            ->orderBy('id')
            ->chunkById(200, function ($authorizations) {
                foreach ($authorizations as $authorization) {
                    if ($authorization->regimen_type === 'sos') {
                        DB::table('infirmary_medication_authorizations')
                            ->where('id', $authorization->id)
                            ->update([
                                'daily_dose_count' => null,
                                'schedule_mode' => 'flexible',
                            ]);

                        continue;
                    }

                    $times = $this->extractTimes((string) $authorization->schedule_text);
                    $doseCount = count($times) ?: $this->inferDoseCount((string) $authorization->frequency);
                    $scheduleMode = count($times) ? 'fixed_time' : 'flexible';

                    DB::table('infirmary_medication_authorizations')
                        ->where('id', $authorization->id)
                        ->update([
                            'daily_dose_count' => $doseCount,
                            'schedule_mode' => $scheduleMode,
                        ]);

                    $now = now();
                    $rows = [];

                    for ($doseOrder = 1; $doseOrder <= $doseCount; $doseOrder++) {
                        $rows[] = [
                            'authorization_id' => $authorization->id,
                            'dose_order' => $doseOrder,
                            'scheduled_time' => isset($times[$doseOrder - 1]) ? $times[$doseOrder - 1].':00' : null,
                            'active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    DB::table('infirmary_medication_schedules')->insert($rows);
                }
            });
    }

    /**
     * @return array<int, string>
     */
    private function extractTimes(string $scheduleText): array
    {
        preg_match_all('/(?:[01]\\d|2[0-3]):[0-5]\\d/', $scheduleText, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function inferDoseCount(string $frequency): int
    {
        $normalized = mb_strtolower(trim($frequency));

        if (preg_match('/cada\\s+(\\d+)\\s+horas?/', $normalized, $matches)) {
            $hours = max(1, (int) $matches[1]);

            return min(12, max(1, (int) round(24 / $hours)));
        }

        if (preg_match('/(\\d+)\\s+(?:veces|vez)/', $normalized, $matches)) {
            return min(12, max(1, (int) $matches[1]));
        }

        $wordCounts = [
            'una' => 1,
            'dos' => 2,
            'tres' => 3,
            'cuatro' => 4,
            'cinco' => 5,
            'seis' => 6,
        ];

        foreach ($wordCounts as $word => $count) {
            if (str_contains($normalized, $word)) {
                return $count;
            }
        }

        return 1;
    }
};
