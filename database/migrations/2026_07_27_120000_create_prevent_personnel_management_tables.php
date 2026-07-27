<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prevent_staff_requirement_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('code', 60)->unique();
            $table->string('kind', 30)->default('training');
            $table->unsignedInteger('validity_months')->nullable();
            $table->boolean('requires_evidence')->default(true);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'sort_order'], 'prevent_staff_req_active_sort_idx');
            $table->index(['kind', 'active'], 'prevent_staff_req_kind_active_idx');
        });

        Schema::create('prevent_staff_compliances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('requirement_type_id')
                ->constrained('prevent_staff_requirement_types')
                ->restrictOnDelete();
            $table->foreignId('training_id')->nullable()->constrained('prevent_trainings')->nullOnDelete();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->boolean('is_not_applicable')->default(false);
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();
            $table->string('evidence_mime')->nullable();
            $table->unsignedBigInteger('evidence_size')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['staff_id', 'requirement_type_id'], 'prevent_staff_compliance_unique');
            $table->index(['expires_on', 'is_not_applicable'], 'prevent_staff_compliance_expiry_idx');
        });

        Schema::create('prevent_joint_committees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'starts_on'], 'prevent_joint_committees_active_idx');
        });

        Schema::create('prevent_joint_committee_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained('prevent_joint_committees')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('representation', 30)->default('trabajadores');
            $table->string('member_role', 30)->default('titular');
            $table->string('position_name', 100)->nullable();
            $table->date('joined_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['committee_id', 'staff_id'], 'prevent_joint_committee_staff_unique');
            $table->index(['committee_id', 'active'], 'prevent_joint_committee_staff_active_idx');
        });

        Schema::table('prevent_trainings', function (Blueprint $table) {
            $table->foreignId('requirement_type_id')
                ->nullable()
                ->after('name')
                ->constrained('prevent_staff_requirement_types')
                ->nullOnDelete();
        });

        Schema::table('prevent_training_participants', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('training_id')->constrained('staff')->nullOnDelete();
            $table->date('issued_on')->nullable()->after('compliance_status');
            $table->date('expires_on')->nullable()->after('issued_on');
            $table->index(['staff_id', 'compliance_status'], 'prevent_train_part_staff_status_idx');
        });

        $this->seedDefaultRequirements();
        $this->linkLegacyParticipants();
        $this->registerPersonnelModule();
    }

    private function seedDefaultRequirements(): void
    {
        $now = now();
        $requirements = [
            [
                'name' => 'Inducción de seguridad',
                'code' => 'INDUCCION_SEGURIDAD',
                'kind' => 'training',
                'validity_months' => 12,
                'sort_order' => 10,
                'description' => 'Inducción general de seguridad y prevención de riesgos.',
            ],
            [
                'name' => 'Obligación de Informar (ODI)',
                'code' => 'ODI',
                'kind' => 'document',
                'validity_months' => null,
                'sort_order' => 20,
                'description' => 'Constancia individual de información de riesgos laborales.',
            ],
            [
                'name' => 'Uso y manejo de extintores',
                'code' => 'USO_EXTINTORES',
                'kind' => 'training',
                'validity_months' => 24,
                'sort_order' => 30,
                'description' => 'Capacitación práctica en uso y manejo de extintores.',
            ],
            [
                'name' => 'Primeros auxilios',
                'code' => 'PRIMEROS_AUXILIOS',
                'kind' => 'training',
                'validity_months' => 24,
                'sort_order' => 40,
                'description' => 'Capacitación básica o certificada en primeros auxilios.',
            ],
        ];

        foreach ($requirements as $requirement) {
            DB::table('prevent_staff_requirement_types')->insertOrIgnore(array_merge($requirement, [
                'requires_evidence' => true,
                'is_mandatory' => true,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private function linkLegacyParticipants(): void
    {
        DB::table('prevent_training_participants')
            ->whereNull('staff_id')
            ->orderBy('id')
            ->eachById(function ($participants) {
                foreach ($participants as $participant) {
                    $staffId = DB::table('staff')
                        ->whereRaw('LOWER(full_name) = ?', [mb_strtolower(trim((string) $participant->employee_name))])
                        ->value('id');

                    if ($staffId) {
                        DB::table('prevent_training_participants')
                            ->where('id', $participant->id)
                            ->update(['staff_id' => $staffId]);
                    }
                }
            });
    }

    private function registerPersonnelModule(): void
    {
        if (! Schema::hasTable('system_modules')) {
            return;
        }

        $parentId = DB::table('system_modules')->where('slug', 'risk_prevention')->value('id');
        if (! $parentId) {
            return;
        }

        $now = now();
        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'risk_prevention_personnel',
            'name' => 'Gestión del personal',
            'frontend_route' => '/risk-prevention/personnel',
            'icon' => null,
            'sort_order' => 7,
            'active' => true,
            'parent_id' => $parentId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $moduleId = DB::table('system_modules')->where('slug', 'risk_prevention_personnel')->value('id');
        foreach ([
            'risk_prevention_personnel' => 7,
            'risk_prevention_documents' => 8,
            'risk_prevention_staff_documents' => 9,
            'risk_prevention_reports' => 10,
        ] as $slug => $sortOrder) {
            DB::table('system_modules')
                ->where('slug', $slug)
                ->update(['sort_order' => $sortOrder, 'updated_at' => $now]);
        }

        if (! $moduleId || ! Schema::hasTable('role_system_module')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['super_admin', 'administrador', 'direccion', 'rrhh', 'inspectoria', 'prevencion_riesgos'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_system_module')->insertOrIgnore([
                'role_id' => $roleId,
                'system_module_id' => $moduleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('prevent_training_participants', function (Blueprint $table) {
            $table->dropIndex('prevent_train_part_staff_status_idx');
            $table->dropConstrainedForeignId('staff_id');
            $table->dropColumn(['issued_on', 'expires_on']);
        });

        Schema::table('prevent_trainings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requirement_type_id');
        });

        Schema::dropIfExists('prevent_joint_committee_staff');
        Schema::dropIfExists('prevent_joint_committees');
        Schema::dropIfExists('prevent_staff_compliances');
        Schema::dropIfExists('prevent_staff_requirement_types');
    }
};
