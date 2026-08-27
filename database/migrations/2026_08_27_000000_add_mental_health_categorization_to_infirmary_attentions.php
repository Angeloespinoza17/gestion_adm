<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('infirmary_attentions')) {
            return;
        }

        if (! Schema::hasColumn('infirmary_attentions', 'mental_health_event_type')) {
            Schema::table('infirmary_attentions', function (Blueprint $table) {
                $table->string('mental_health_event_type', 40)
                    ->nullable()
                    ->after('attention_category');
            });
        }

        if (! Schema::hasColumn('infirmary_attentions', 'self_harm_injury_type')) {
            Schema::table('infirmary_attentions', function (Blueprint $table) {
                $table->string('self_harm_injury_type', 40)
                    ->nullable()
                    ->after('mental_health_event_type');
            });
        }

        if (! Schema::hasIndex('infirmary_attentions', 'inf_attn_mental_health_date_idx')) {
            Schema::table('infirmary_attentions', function (Blueprint $table) {
                $table->index(
                    ['mental_health_event_type', 'attended_at'],
                    'inf_attn_mental_health_date_idx',
                );
            });
        }

        if (! Schema::hasIndex('infirmary_attentions', 'inf_attn_self_harm_date_idx')) {
            Schema::table('infirmary_attentions', function (Blueprint $table) {
                $table->index(
                    ['self_harm_injury_type', 'attended_at'],
                    'inf_attn_self_harm_date_idx',
                );
            });
        }
    }

    public function down(): void
    {
        // Migración productiva forward-only: conserva las categorías clínicas registradas.
    }
};
