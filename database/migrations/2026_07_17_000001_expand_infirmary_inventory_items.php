<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infirmary_medications', function (Blueprint $table) {
            $table->string('inventory_type', 40)->default('medication')->after('id');
            $table->string('source_type', 40)->default('school')->after('inventory_type');
            $table->foreignId('student_profile_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('student_profiles')
                ->nullOnDelete();
            $table->string('received_from_guardian', 160)->nullable()->after('student_profile_id');
            $table->dateTime('received_at')->nullable()->after('received_from_guardian');

            $table->index(['inventory_type', 'active'], 'inf_med_inventory_type_active_idx');
            $table->index(['source_type', 'student_profile_id'], 'inf_med_source_student_idx');
        });
    }

    public function down(): void
    {
        Schema::table('infirmary_medications', function (Blueprint $table) {
            $table->dropIndex('inf_med_inventory_type_active_idx');
            $table->dropIndex('inf_med_source_student_idx');
            $table->dropForeign(['student_profile_id']);
            $table->dropColumn([
                'inventory_type',
                'source_type',
                'student_profile_id',
                'received_from_guardian',
                'received_at',
            ]);
        });
    }
};
