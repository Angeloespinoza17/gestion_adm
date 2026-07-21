<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infirmary_attention_treatments', function (Blueprint $table) {
            $table->json('treatment_categories')
                ->nullable()
                ->after('treatment_types');
            $table->string('derivation_type', 40)
                ->nullable()
                ->after('treatment_categories');
            $table->json('derivation_support_teams')
                ->nullable()
                ->after('derivation_type');
        });
    }

    public function down(): void
    {
        Schema::table('infirmary_attention_treatments', function (Blueprint $table) {
            $table->dropColumn('derivation_support_teams');
            $table->dropColumn('derivation_type');
            $table->dropColumn('treatment_categories');
        });
    }
};
