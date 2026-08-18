<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'psychology.access' => 'Acceder al módulo de Psicología',
        'psychology.referrals.create' => 'Crear derivaciones a Psicología',
        'psychology.referrals.view_own' => 'Ver derivaciones propias a Psicología',
        'psychology.referrals.view_all' => 'Ver todas las derivaciones de Psicología',
        'psychology.referrals.assign' => 'Asignar derivaciones de Psicología',
        'psychology.referrals.update' => 'Gestionar derivaciones de Psicología',
        'psychology.cases.create' => 'Abrir casos de Psicología',
        'psychology.cases.view_assigned' => 'Ver casos asignados de Psicología',
        'psychology.cases.view_all' => 'Ver todos los casos de Psicología',
        'psychology.cases.reassign' => 'Reasignar casos de Psicología',
        'psychology.sessions.create' => 'Registrar actividades de Psicología',
        'psychology.sessions.view_private' => 'Ver notas privadas de Psicología',
        'psychology.risk.create' => 'Registrar evaluaciones de riesgo',
        'psychology.risk.view' => 'Ver evaluaciones de riesgo',
        'psychology.documents.upload' => 'Subir documentos de Psicología',
        'psychology.documents.download' => 'Descargar documentos de Psicología',
        'psychology.cases.close' => 'Cerrar casos de Psicología',
        'psychology.cases.reopen' => 'Reabrir casos de Psicología',
        'psychology.reports.aggregate' => 'Ver reportes agregados de Psicología',
        'psychology.reports.nominal' => 'Ver reportes nominales de Psicología',
        'psychology.config.manage' => 'Administrar configuración de Psicología',
        'psychology.audit.view' => 'Ver auditoría de Psicología',
        'psychology.sensitive.override' => 'Acceder excepcionalmente a contenido psicológico sensible',
    ];

    public function up(): void
    {
        Schema::create('psychology_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60);
            $table->string('slug', 80);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['type', 'slug']);
            $table->index(['type', 'active', 'sort_order'], 'psychology_catalog_lookup_idx');
        });

        Schema::create('psychology_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('value_type', 30)->default('string');
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('psychology_referrals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable()->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('course_section_id')->nullable()->constrained('course_sections')->nullOnDelete();
            $table->foreignId('referred_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('suggested_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin_area', 100)->default('inspectoria');
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 40)->default('draft');
            $table->string('suggested_urgency', 20)->default('medium');
            $table->string('professional_priority', 20)->nullable();
            $table->string('primary_reason', 160);
            $table->text('secondary_reasons')->nullable();
            $table->longText('observed_facts');
            $table->date('approximate_started_on')->nullable();
            $table->text('people_involved')->nullable();
            $table->text('measures_taken')->nullable();
            $table->text('known_previous_interventions')->nullable();
            $table->text('observed_risk_indicators')->nullable();
            $table->boolean('immediate_response_needed')->default(false);
            $table->boolean('guardian_informed')->default(false);
            $table->string('guardian_contact_status', 40)->default('not_contacted');
            $table->text('observations')->nullable();
            $table->text('information_request')->nullable();
            $table->text('information_response')->nullable();
            $table->text('shared_decision_note')->nullable();
            $table->longText('internal_decision_note')->nullable();
            $table->boolean('purpose_declaration_accepted')->default(false);
            $table->dateTime('referred_at')->nullable();
            $table->dateTime('first_reviewed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'professional_priority', 'created_at'], 'psychology_referral_queue_idx');
            $table->index(['student_profile_id', 'status'], 'psychology_referral_student_status_idx');
            $table->index(['referred_by_user_id', 'status'], 'psychology_referral_author_idx');
            $table->index(['assigned_user_id', 'status'], 'psychology_referral_assignee_idx');
            $table->unique(['source_type', 'source_id'], 'psychology_referral_source_unique');
        });

        Schema::create('psychology_referral_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('psychology_referrals')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('reason')->nullable();
            $table->text('shared_note')->nullable();
            $table->longText('internal_note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('changed_at');
            $table->timestamps();
            $table->index(['referral_id', 'changed_at'], 'psychology_referral_history_idx');
        });

        Schema::create('psychology_cases', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('origin_referral_id')->nullable()->constrained('psychology_referrals')->nullOnDelete();
            $table->foreignId('responsible_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 40)->default('open');
            $table->string('priority', 20)->default('medium');
            $table->string('confidentiality', 40)->default('psychology_team');
            $table->string('general_reason', 191);
            $table->text('categories')->nullable();
            $table->text('objectives')->nullable();
            $table->text('next_action')->nullable();
            $table->date('next_review_on')->nullable();
            $table->dateTime('opened_at');
            $table->dateTime('last_activity_at')->nullable();
            $table->string('guardian_information_status', 40)->default('pending');
            $table->dateTime('closed_at')->nullable();
            $table->text('closure_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['responsible_user_id', 'status', 'priority'], 'psychology_case_workload_idx');
            $table->index(['student_profile_id', 'status'], 'psychology_case_student_idx');
            $table->index(['status', 'last_activity_at'], 'psychology_case_inactivity_idx');
        });

        Schema::table('psychology_referrals', function (Blueprint $table) {
            $table->foreignId('case_id')->nullable()->after('assigned_user_id')->constrained('psychology_cases')->nullOnDelete();
        });

        Schema::create('psychology_case_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('psychology_cases')->cascadeOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained('psychology_referrals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 30)->default('primary');
            $table->text('reason')->nullable();
            $table->dateTime('expected_first_review_at')->nullable();
            $table->dateTime('assigned_at');
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['case_id', 'role', 'ended_at'], 'psychology_case_assignment_active_idx');
            $table->index(['user_id', 'ended_at'], 'psychology_assignment_workload_idx');
        });

        Schema::create('psychology_case_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('psychology_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('participation_role', 80)->nullable();
            $table->string('visibility', 40)->default('interdisciplinary_team');
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['case_id', 'user_id']);
        });

        Schema::create('psychology_intervention_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('psychology_cases')->cascadeOnDelete();
            $table->foreignId('responsible_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('current_version')->default(1);
            $table->date('review_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['case_id', 'status']);
        });

        Schema::create('psychology_intervention_plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('psychology_intervention_plans')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('general_situation');
            $table->text('general_objective');
            $table->longText('specific_objectives')->nullable();
            $table->longText('planned_actions')->nullable();
            $table->text('responsibles')->nullable();
            $table->string('frequency', 100)->nullable();
            $table->date('estimated_start_on')->nullable();
            $table->date('estimated_end_on')->nullable();
            $table->text('monitoring_indicators')->nullable();
            $table->text('participants')->nullable();
            $table->text('family_coordination')->nullable();
            $table->text('teacher_coordination')->nullable();
            $table->text('coexistence_coordination')->nullable();
            $table->text('external_coordination')->nullable();
            $table->text('review_result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['plan_id', 'version']);
        });

        Schema::create('psychology_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('psychology_cases')->cascadeOnDelete();
            $table->foreignId('responsible_user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 80);
            $table->date('activity_on');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('modality', 40)->nullable();
            $table->string('location', 160)->nullable();
            $table->text('participants')->nullable();
            $table->text('objective')->nullable();
            $table->longText('institutional_summary')->nullable();
            $table->longText('private_note')->nullable();
            $table->text('result')->nullable();
            $table->text('agreements')->nullable();
            $table->text('next_steps')->nullable();
            $table->date('next_action_on')->nullable();
            $table->string('attendance_status', 40)->nullable();
            $table->string('visibility', 40)->default('psychology_team');
            $table->text('referral_feedback')->nullable();
            $table->string('status', 30)->default('draft');
            $table->dateTime('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['case_id', 'activity_on'], 'psychology_activity_case_date_idx');
            $table->index(['type', 'activity_on']);
        });

        Schema::create('psychology_activity_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('psychology_activities')->cascadeOnDelete();
            $table->longText('content');
            $table->string('visibility', 40)->default('psychology_team');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('psychology_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('psychology_cases')->cascadeOnDelete();
            $table->string('risk_type', 80);
            $table->string('level', 20);
            $table->text('structured_indicators')->nullable();
            $table->text('professional_rationale')->nullable();
            $table->text('immediate_action')->nullable();
            $table->foreignId('response_responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('response_at')->nullable();
            $table->string('protocol_reference', 160)->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['case_id', 'level', 'status']);
        });

        Schema::create('psychology_protective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_assessment_id')->constrained('psychology_risk_assessments')->cascadeOnDelete();
            $table->text('action');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('action_at');
            $table->text('result')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('psychology_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('psychology_cases')->cascadeOnDelete();
            $table->string('action_type', 80)->nullable();
            $table->boolean('guardian_informed')->default(false);
            $table->dateTime('informed_at')->nullable();
            $table->string('contact_method', 60)->nullable();
            $table->foreignId('contacted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_result', 100)->nullable();
            $table->boolean('consent_required')->default(false);
            $table->string('status', 30)->default('pending');
            $table->boolean('institutional_exception')->default(false);
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('psychology_external_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('psychology_cases')->cascadeOnDelete();
            $table->string('institution', 191);
            $table->string('institution_type', 100)->nullable();
            $table->text('general_reason');
            $table->date('referred_on');
            $table->foreignId('referred_by')->constrained('users')->restrictOnDelete();
            $table->boolean('guardian_informed')->default(false);
            $table->string('reception_status', 40)->default('pending');
            $table->date('response_on')->nullable();
            $table->text('general_result')->nullable();
            $table->boolean('follow_up_pending')->default(true);
            $table->date('next_contact_on')->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'next_contact_on']);
        });

        Schema::create('psychology_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('psychology_cases')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('psychology_activities')->nullOnDelete();
            $table->foreignId('responsible_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('type', 80)->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 30)->default('pending');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('remind_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('completion_evidence')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['responsible_user_id', 'status', 'due_at'], 'psychology_task_owner_due_idx');
            $table->index(['case_id', 'status']);
        });

        Schema::create('psychology_shared_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('psychology_cases')->cascadeOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained('psychology_referrals')->nullOnDelete();
            $table->longText('content');
            $table->string('visibility', 40)->default('referral_feedback');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('psychology_case_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('psychology_cases')->cascadeOnDelete();
            $table->string('closure_type', 80);
            $table->text('reason');
            $table->text('result_summary');
            $table->text('recommendations')->nullable();
            $table->dateTime('closed_at');
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('psychology_case_reopenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('psychology_cases')->cascadeOnDelete();
            $table->text('reason');
            $table->dateTime('reopened_at');
            $table->foreignId('reopened_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('psychology_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('psychology_cases')->nullOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained('psychology_referrals')->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('psychology_activities')->nullOnDelete();
            $table->string('category', 80);
            $table->string('description')->nullable();
            $table->string('private_path');
            $table->string('original_name');
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('visibility', 40)->default('private_psychology');
            $table->string('status', 30)->default('active');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['case_id', 'category']);
        });

        Schema::create('psychology_exports', function (Blueprint $table) {
            $table->id();
            $table->string('format', 20)->default('csv');
            $table->string('status', 30)->default('pending')->index();
            $table->json('filters')->nullable();
            $table->string('private_path')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['created_by', 'created_at']);
        });

        Schema::create('psychology_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('ip_address', 45)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id'], 'psychology_audit_subject_idx');
        });

        $this->registerAccess();
        $this->seedCatalogsAndSettings();
    }

    public function down(): void
    {
        $this->unregisterAccess();
        foreach ([
            'psychology_audit_events', 'psychology_exports', 'psychology_documents', 'psychology_case_reopenings',
            'psychology_case_closures', 'psychology_shared_feedback', 'psychology_tasks',
            'psychology_external_referrals', 'psychology_consents', 'psychology_protective_actions',
            'psychology_risk_assessments', 'psychology_activity_addenda', 'psychology_activities',
            'psychology_intervention_plan_versions', 'psychology_intervention_plans',
            'psychology_case_participants', 'psychology_case_assignments',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('psychology_referrals', fn (Blueprint $table) => $table->dropConstrainedForeignId('case_id'));
        Schema::dropIfExists('psychology_cases');
        Schema::dropIfExists('psychology_referral_status_history');
        Schema::dropIfExists('psychology_referrals');
        Schema::dropIfExists('psychology_settings');
        Schema::dropIfExists('psychology_catalog_items');
    }

    private function registerAccess(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }
        $now = now();
        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $name, 'description' => 'Permiso granular del módulo confidencial de Psicología.',
                'active' => true, 'updated_at' => $now, 'created_at' => $now,
            ]);
        }
        if (! Schema::hasTable('system_modules')) {
            return;
        }
        DB::table('system_modules')->updateOrInsert(['slug' => 'psychology'], [
            'name' => 'Psicología Escolar', 'frontend_route' => '/psychology', 'icon' => 'bx-brain',
            'sort_order' => 29, 'active' => true, 'parent_id' => null, 'updated_at' => $now, 'created_at' => $now,
        ]);
        if (Schema::hasTable('permission_groups')) {
            $moduleId = DB::table('system_modules')->where('slug', 'psychology')->value('id');
            DB::table('permission_groups')->updateOrInsert(['slug' => 'psychology'], [
                'name' => 'Psicología Escolar', 'description' => 'Permisos del módulo de Psicología.',
                'system_module_id' => $moduleId, 'sort_order' => 1, 'active' => true,
                'updated_at' => $now, 'created_at' => $now,
            ]);
            if (Schema::hasTable('permission_group_permission')) {
                $groupId = DB::table('permission_groups')->where('slug', 'psychology')->value('id');
                foreach (DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id') as $permissionId) {
                    DB::table('permission_group_permission')->insertOrIgnore(['permission_group_id' => $groupId, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }

        if (! Schema::hasTable('roles')) {
            return;
        }
        foreach ([
            'inspectoria' => ['Inspector/a', 'Área derivante con acceso limitado a sus derivaciones.'],
            'psicologo' => ['Psicólogo/a', 'Profesional del equipo de Psicología Escolar.'],
            'coordinador_psicologia' => ['Coordinador/a de Psicología', 'Coordinación del equipo de Psicología Escolar.'],
            'convivencia_escolar' => ['Convivencia Escolar', 'Acceso interdisciplinario expresamente autorizado.'],
            'direccion' => ['Dirección', 'Acceso exclusivo a indicadores agregados.'],
        ] as $slug => [$name, $description]) {
            DB::table('roles')->updateOrInsert(['slug' => $slug], [
                'name' => $name, 'description' => $description, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $rolePermissions = [
            'inspectoria' => ['psychology.access', 'psychology.referrals.create', 'psychology.referrals.view_own', 'psychology.documents.upload'],
            'psicologo' => ['psychology.access', 'psychology.referrals.view_own', 'psychology.referrals.update', 'psychology.cases.create', 'psychology.cases.view_assigned', 'psychology.sessions.create', 'psychology.sessions.view_private', 'psychology.risk.create', 'psychology.risk.view', 'psychology.documents.upload', 'psychology.documents.download', 'psychology.cases.close', 'psychology.reports.aggregate'],
            'coordinador_psicologia' => array_values(array_diff(array_keys(self::PERMISSIONS), ['psychology.sensitive.override'])),
            'convivencia_escolar' => ['psychology.access', 'psychology.cases.view_assigned', 'psychology.reports.aggregate'],
            'direccion' => ['psychology.access', 'psychology.reports.aggregate'],
        ];
        if (Schema::hasTable('permission_role')) {
            foreach ($rolePermissions as $roleSlug => $slugs) {
                $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
                if (! $roleId) {
                    continue;
                }
                foreach (DB::table('permissions')->whereIn('slug', $slugs)->pluck('id') as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
        if (Schema::hasTable('role_system_module')) {
            $moduleId = DB::table('system_modules')->where('slug', 'psychology')->value('id');
            foreach (DB::table('roles')->whereIn('slug', array_keys($rolePermissions))->pluck('id') as $roleId) {
                DB::table('role_system_module')->insertOrIgnore(['role_id' => $roleId, 'system_module_id' => $moduleId, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    private function seedCatalogsAndSettings(): void
    {
        $now = now();
        $catalogs = [
            'referral_reason' => ['bienestar_emocional' => 'Bienestar emocional', 'convivencia' => 'Convivencia', 'familia' => 'Situación familiar', 'aprendizaje' => 'Impacto socioemocional en aprendizaje', 'otro' => 'Otro'],
            'activity_type' => ['student_interview' => 'Entrevista individual con estudiante', 'guardian_interview' => 'Entrevista con apoderado', 'teacher_interview' => 'Entrevista con docente', 'crisis_intervention' => 'Intervención en crisis', 'case_meeting' => 'Reunión de caso', 'follow_up' => 'Seguimiento', 'external_referral' => 'Derivación externa'],
            'risk_type' => ['self' => 'Riesgo para sí mismo', 'others' => 'Riesgo para terceros', 'rights_violation' => 'Vulneración de derechos', 'violence' => 'Violencia', 'emotional_crisis' => 'Crisis emocional', 'neglect' => 'Abandono o desprotección', 'substance_use' => 'Consumo problemático', 'serious_family' => 'Situación familiar grave', 'other' => 'Otro'],
            'closure_type' => ['objectives_met' => 'Objetivos cumplidos', 'external_continuity' => 'Continuidad en red externa', 'withdrawal' => 'Retiro del establecimiento', 'orientation_only' => 'Orientación breve', 'other' => 'Otro'],
            'document_category' => ['referral' => 'Derivación', 'consent' => 'Consentimiento', 'report' => 'Informe', 'background' => 'Antecedente', 'coordination' => 'Registro de coordinación', 'external' => 'Documento externo', 'evidence' => 'Evidencia', 'other' => 'Otro'],
        ];
        foreach ($catalogs as $type => $items) {
            $sort = 1;
            foreach ($items as $slug => $name) {
                DB::table('psychology_catalog_items')->insert(['type' => $type, 'slug' => $slug, 'name' => $name, 'active' => true, 'sort_order' => $sort++, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        foreach ([
            'first_review_hours' => ['48', 'integer'], 'first_intervention_hours' => ['120', 'integer'],
            'inactive_days' => ['14', 'integer'], 'reiteration_days' => ['90', 'integer'],
            'anonymization_threshold' => ['5', 'integer'], 'max_file_kb' => ['10240', 'integer'],
            'closure_approval_required' => ['0', 'boolean'],
        ] as $key => [$value, $type]) {
            DB::table('psychology_settings')->insert(['key' => $key, 'value' => $value, 'value_type' => $type, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function unregisterAccess(): void
    {
        if (Schema::hasTable('system_modules')) {
            DB::table('system_modules')->where('slug', 'psychology')->delete();
        }
        if (! Schema::hasTable('permissions')) {
            return;
        }
        $ids = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
        if (Schema::hasTable('permission_role')) {
            DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        }
        if (Schema::hasTable('permission_group_permission')) {
            DB::table('permission_group_permission')->whereIn('permission_id', $ids)->delete();
        }
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
