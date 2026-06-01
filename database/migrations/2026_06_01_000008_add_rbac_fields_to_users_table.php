<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('cargo_id')
                ->nullable()
                ->after('id')
                ->constrained('cargos')
                ->nullOnDelete();

            $table->string('user_type')->nullable()->after('cargo_id');
            $table->boolean('active')->default(true)->after('user_type');

            // Preparado para futuras relaciones (sin FK por ahora).
            $table->unsignedBigInteger('student_id')->nullable()->after('active');
            $table->unsignedBigInteger('guardian_id')->nullable()->after('student_id');
            $table->unsignedBigInteger('staff_id')->nullable()->after('guardian_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cargo_id');
            $table->dropColumn([
                'user_type',
                'active',
                'student_id',
                'guardian_id',
                'staff_id',
            ]);
        });
    }
};

