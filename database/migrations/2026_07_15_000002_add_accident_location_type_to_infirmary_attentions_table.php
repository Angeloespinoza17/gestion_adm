<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->string('accident_location_type', 40)
                ->nullable()
                ->after('attention_category');

            $table->index(['accident_location_type', 'attended_at'], 'inf_attn_accident_location_date_idx');
        });

        DB::table('infirmary_attentions')
            ->whereIn('attention_category', ['accidente_menor', 'accidente_mayor', 'accidente_escolar'])
            ->whereNotNull('dependency_id')
            ->update(['accident_location_type' => 'colegio']);

        DB::table('infirmary_attentions')
            ->whereIn('attention_category', ['accidente_menor', 'accidente_mayor', 'accidente_escolar'])
            ->whereNull('dependency_id')
            ->update(['accident_location_type' => 'trayecto']);
    }

    public function down(): void
    {
        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->dropIndex('inf_attn_accident_location_date_idx');
            $table->dropColumn('accident_location_type');
        });
    }
};
