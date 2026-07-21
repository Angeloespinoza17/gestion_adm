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
            $table->string('subject_type', 20)->default('student')->after('id');
            $table->foreignId('staff_id')
                ->nullable()
                ->after('student_profile_id')
                ->constrained('staff')
                ->nullOnDelete();
            $table->string('staff_full_name_snapshot', 160)->nullable()->after('student_rut_snapshot');
            $table->string('staff_rut_snapshot', 20)->nullable()->after('staff_full_name_snapshot');
            $table->string('staff_cargo_snapshot', 160)->nullable()->after('staff_rut_snapshot');

            $table->index(['subject_type', 'attended_at'], 'inf_attn_subject_date_idx');
            $table->index(['staff_id', 'attended_at'], 'inf_attn_staff_date_idx');
        });

        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->unsignedBigInteger('student_profile_id')->nullable()->change();
            $table->string('student_full_name_snapshot', 160)->nullable()->change();
        });

        Schema::table('infirmary_medication_administrations', function (Blueprint $table) {
            $table->foreignId('staff_id')
                ->nullable()
                ->after('student_profile_id')
                ->constrained('staff')
                ->nullOnDelete();
            $table->index(['staff_id', 'administered_at'], 'inf_admin_staff_date_idx');
        });

        Schema::table('infirmary_medication_administrations', function (Blueprint $table) {
            $table->unsignedBigInteger('student_profile_id')->nullable()->change();
        });

        $this->registerNavigationModule();
    }

    public function down(): void
    {
        $this->removeNavigationModule();

        DB::table('infirmary_medication_administrations')
            ->whereNotNull('staff_id')
            ->delete();

        DB::table('infirmary_attentions')
            ->where('subject_type', 'staff')
            ->delete();

        Schema::table('infirmary_medication_administrations', function (Blueprint $table) {
            $table->dropIndex('inf_admin_staff_date_idx');
            $table->dropConstrainedForeignId('staff_id');
            $table->unsignedBigInteger('student_profile_id')->nullable(false)->change();
        });

        Schema::table('infirmary_attentions', function (Blueprint $table) {
            $table->dropIndex('inf_attn_subject_date_idx');
            $table->dropIndex('inf_attn_staff_date_idx');
            $table->dropConstrainedForeignId('staff_id');
            $table->dropColumn([
                'subject_type',
                'staff_full_name_snapshot',
                'staff_rut_snapshot',
                'staff_cargo_snapshot',
            ]);
            $table->unsignedBigInteger('student_profile_id')->nullable(false)->change();
            $table->string('student_full_name_snapshot', 160)->nullable(false)->change();
        });
    }

    private function registerNavigationModule(): void
    {
        if (! Schema::hasTable('system_modules')) {
            return;
        }

        $parentId = DB::table('system_modules')->where('slug', 'infirmary')->value('id');

        if (! $parentId) {
            return;
        }

        $now = now();

        DB::table('system_modules')->updateOrInsert(
            ['slug' => 'infirmary_staff_attentions'],
            [
                'name' => 'Atención a funcionarios',
                'frontend_route' => '/infirmary/staff-attentions',
                'icon' => null,
                'sort_order' => 3,
                'active' => true,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        if (! Schema::hasTable('role_system_module')) {
            return;
        }

        $moduleId = DB::table('system_modules')->where('slug', 'infirmary_staff_attentions')->value('id');
        $roleIds = DB::table('role_system_module')
            ->where('system_module_id', $parentId)
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            DB::table('role_system_module')->updateOrInsert(
                ['role_id' => $roleId, 'system_module_id' => $moduleId],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    private function removeNavigationModule(): void
    {
        if (! Schema::hasTable('system_modules')) {
            return;
        }

        $moduleId = DB::table('system_modules')->where('slug', 'infirmary_staff_attentions')->value('id');

        if (! $moduleId) {
            return;
        }

        if (Schema::hasTable('role_system_module')) {
            DB::table('role_system_module')->where('system_module_id', $moduleId)->delete();
        }

        DB::table('system_modules')->where('id', $moduleId)->delete();
    }
};
