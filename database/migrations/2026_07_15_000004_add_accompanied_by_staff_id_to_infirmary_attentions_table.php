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
            $table->foreignId('accompanied_by_staff_id')
                ->nullable()
                ->after('accompanied_by_type')
                ->constrained('staff')
                ->nullOnDelete();
        });

        DB::table('infirmary_attentions')
            ->where('accompanied_by_type', 'profesor')
            ->whereNull('accompanied_by_name')
            ->update(['accompanied_by_name' => 'Profesor']);

        DB::table('infirmary_attentions')
            ->where('accompanied_by_type', 'profesor')
            ->update(['accompanied_by_type' => 'otro']);
    }

    public function down(): void
    {
        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accompanied_by_staff_id');
        });
    }
};
