<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_shifts', function (Blueprint $table) {
            $table->string('schedule_type', 20)->default('single')->after('staff_id');
            $table->foreignId('parent_shift_id')->nullable()->after('closed_by_user_id')->constrained('security_shifts')->nullOnDelete();
            $table->date('generated_for_date')->nullable()->after('parent_shift_id');
            $table->json('weekdays')->nullable()->after('scheduled_end_at');
            $table->time('template_start_time')->nullable()->after('weekdays');
            $table->time('template_end_time')->nullable()->after('template_start_time');
            $table->date('recurrence_starts_on')->nullable()->after('template_end_time');
            $table->date('recurrence_ends_on')->nullable()->after('recurrence_starts_on');

            $table->index(['parent_shift_id', 'generated_for_date'], 'security_shifts_parent_generated_idx');
            $table->index(['schedule_type', 'status'], 'security_shifts_schedule_status_idx');
        });

        DB::table('security_shifts')
            ->whereNull('coverage_label')
            ->update(['coverage_label' => 'Todo el colegio']);
    }

    public function down(): void
    {
        Schema::table('security_shifts', function (Blueprint $table) {
            $table->dropIndex('security_shifts_parent_generated_idx');
            $table->dropIndex('security_shifts_schedule_status_idx');
            $table->dropConstrainedForeignId('parent_shift_id');
            $table->dropColumn([
                'schedule_type',
                'generated_for_date',
                'weekdays',
                'template_start_time',
                'template_end_time',
                'recurrence_starts_on',
                'recurrence_ends_on',
            ]);
        });
    }
};
