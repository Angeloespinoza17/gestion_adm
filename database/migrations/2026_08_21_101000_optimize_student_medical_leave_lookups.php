<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_medical_certificates')) {
            return;
        }

        Schema::table('student_medical_certificates', function (Blueprint $table): void {
            $table->index(
                ['is_permanent', 'covers_from', 'covers_to'],
                'student_medical_leave_permanent_dates_idx',
            );
        });
    }

    public function down(): void
    {
        // Índice aditivo: se conserva para no degradar consultas en producción.
    }
};
