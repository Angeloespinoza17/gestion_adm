<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('region')->constrained('regions')->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->after('region_id')->constrained('communes')->nullOnDelete();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE staff MODIFY rut VARCHAR(20) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commune_id');
            $table->dropConstrainedForeignId('region_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE staff MODIFY rut VARCHAR(20) NOT NULL');
        }
    }
};
