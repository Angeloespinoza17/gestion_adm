<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('porter_student_withdrawals', function (Blueprint $table) {
            $table->foreignId('inspector_staff_id')
                ->nullable()
                ->after('course_section_id')
                ->constrained('staff')
                ->nullOnDelete();
            $table->string('inspector_name_snapshot')
                ->nullable()
                ->after('course_name_snapshot');
            $table->index(['inspector_staff_id', 'withdrawn_at'], 'psw_inspector_withdrawn_idx');
        });
    }

    public function down(): void
    {
        Schema::table('porter_student_withdrawals', function (Blueprint $table) {
            $table->dropIndex('psw_inspector_withdrawn_idx');
            $table->dropConstrainedForeignId('inspector_staff_id');
            $table->dropColumn('inspector_name_snapshot');
        });
    }
};
