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
            $table->dateTime('occurred_at')
                ->nullable()
                ->after('accident_location_type');

            $table->index(['occurred_at', 'attended_at'], 'inf_attn_occurred_registered_idx');
        });

        DB::table('infirmary_attentions')
            ->whereNull('occurred_at')
            ->update(['occurred_at' => DB::raw('attended_at')]);
    }

    public function down(): void
    {
        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->dropIndex('inf_attn_occurred_registered_idx');
            $table->dropColumn('occurred_at');
        });
    }
};
