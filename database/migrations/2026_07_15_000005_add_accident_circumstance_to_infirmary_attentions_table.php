<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->text('accident_circumstance')
                ->nullable()
                ->after('consultation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->dropColumn('accident_circumstance');
        });
    }
};
