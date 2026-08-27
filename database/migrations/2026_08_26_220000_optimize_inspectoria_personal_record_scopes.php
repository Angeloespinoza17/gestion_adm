<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex(
            'inspectoria_attentions',
            ['attended_by_user_id', 'attended_at'],
            'insp_attention_owner_date_idx',
        );
        $this->addIndex(
            'inspectoria_attentions',
            ['inspector_staff_id', 'attended_at'],
            'insp_attention_staff_date_idx',
        );
        $this->addIndex(
            'inspectoria_daily_logs',
            ['registered_by_user_id', 'happened_at'],
            'insp_log_owner_date_idx',
        );
        $this->addIndex(
            'inspectoria_daily_logs',
            ['inspector_staff_id', 'happened_at'],
            'insp_log_staff_date_idx',
        );
        $this->addIndex(
            'social_work_referrals',
            ['created_by', 'referral_date'],
            'sw_referral_creator_date_idx',
        );
    }

    public function down(): void
    {
        // Migración productiva forward-only: conserva los índices operativos.
    }

    /** @param array<int, string> $columns */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
    }

};
