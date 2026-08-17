<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'social_work.dashboard.view' => 'Ver dashboard de Trabajo Social',
        'social_work.students.view' => 'Ver listado social de estudiantes',
        'social_work.student_profile.view' => 'Ver ficha social de estudiantes',
        'social_work.cases.view' => 'Ver casos de Trabajo Social',
        'social_work.cases.create' => 'Crear casos de Trabajo Social',
        'social_work.cases.update' => 'Editar casos de Trabajo Social',
        'social_work.cases.assign' => 'Asignar casos de Trabajo Social',
        'social_work.cases.close' => 'Cerrar casos de Trabajo Social',
        'social_work.cases.reopen' => 'Reabrir casos de Trabajo Social',
        'social_work.closed_case.correct' => 'Corregir casos cerrados',
        'social_work.confidential.view' => 'Ver información confidencial de Trabajo Social',
        'social_work.highly_confidential.view' => 'Ver información altamente confidencial',
        'social_work.interviews.manage' => 'Gestionar entrevistas sociales',
        'social_work.actions.manage' => 'Gestionar acciones sociales',
        'social_work.protocols.manage' => 'Gestionar protocolos sociales',
        'social_work.alerts.manage' => 'Gestionar alertas sociales',
        'social_work.referrals.create' => 'Crear derivaciones sociales',
        'social_work.referrals.manage' => 'Gestionar derivaciones sociales',
        'social_work.pedagogical_reports.request' => 'Solicitar informes pedagógicos',
        'social_work.pedagogical_reports.respond' => 'Responder informes pedagógicos',
        'social_work.junaeb.manage' => 'Gestionar prestaciones JUNAEB',
        'social_work.medical.manage' => 'Gestionar apoyos médicos sociales',
        'social_work.medical_documents.view' => 'Descargar certificados médicos',
        'social_work.reports.create' => 'Crear informes sociales',
        'social_work.reports.approve' => 'Aprobar informes sociales',
        'social_work.reports.export' => 'Exportar información social',
        'social_work.templates.manage' => 'Administrar plantillas sociales',
        'social_work.audit.view' => 'Ver auditoría de Trabajo Social',
    ];

    public function up(): void
    {
        Schema::create('social_work_risk_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('source', 50)->index();
            $table->string('operator', 20);
            $table->decimal('threshold', 12, 2)->nullable();
            $table->unsignedInteger('period_days')->nullable();
            $table->string('result_level', 20)->index();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->json('conditions')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('social_work_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('social_work_cases')->nullOnDelete();
            $table->dateTime('assessed_at')->index();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('suggested_level', 20);
            $table->string('final_level', 20)->index();
            $table->json('indicators');
            $table->json('source_snapshot');
            $table->string('evaluation_origin', 30)->default('manual');
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('manually_overridden')->default(false);
            $table->text('override_justification')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['student_profile_id', 'assessed_at'], 'sw_risk_student_assessed_idx');
        });

        Schema::create('social_work_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('deduplication_key', 191)->unique();
            $table->string('type', 80)->index();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('social_work_cases')->nullOnDelete();
            $table->dateTime('alerted_at')->index();
            $table->string('severity', 20)->index();
            $table->string('reason');
            $table->json('evidence')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable()->index();
            $table->string('status', 30)->default('nueva')->index();
            $table->string('recommended_action')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('confidentiality', 30)->default('interno');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['responsible_user_id', 'status']);
            $table->index(['type', 'status']);
        });

        Schema::create('social_work_alert_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('social_work_alerts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment');
            $table->timestamps();
        });

        Schema::create('social_work_form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('type', 60)->index();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('social_work_form_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('social_work_form_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 30)->default('borrador');
            $table->date('effective_from')->nullable();
            $table->json('schema');
            $table->json('role_visibility')->nullable();
            $table->string('confidentiality', 30)->default('restringido');
            $table->longText('print_template')->nullable();
            $table->string('header')->nullable();
            $table->string('footer')->nullable();
            $table->json('signature_blocks')->nullable();
            $table->string('change_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['template_id', 'version']);
        });

        Schema::create('social_work_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_version_id')->constrained('social_work_form_template_versions')->restrictOnDelete();
            $table->nullableMorphs('subject');
            $table->json('data');
            $table->string('status', 30)->default('borrador');
            $table->string('confidentiality', 30)->default('restringido');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('social_work_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('social_work_cases')->nullOnDelete();
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->string('type', 60)->index();
            $table->string('title');
            $table->string('status', 30)->default('borrador')->index();
            $table->string('confidentiality', 30)->default('restringido');
            $table->unsignedInteger('current_version')->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('social_work_report_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('social_work_reports')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->json('source_sections')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->json('excluded_sections')->nullable();
            $table->string('status', 30)->default('borrador');
            $table->string('change_reason')->nullable();
            $table->string('pdf_private_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['report_id', 'version']);
        });

        Schema::create('social_work_documents', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('documentable');
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('social_work_cases')->nullOnDelete();
            $table->string('category', 80)->index();
            $table->string('description')->nullable();
            $table->string('private_path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 30)->default('vigente');
            $table->string('confidentiality', 30)->default('restringido')->index();
            $table->json('tags')->nullable();
            $table->date('valid_until')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('social_work_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('ip_address', 45)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id'], 'sw_audit_subject_idx');
        });

        $this->registerAccess();
    }

    public function down(): void
    {
        $this->unregisterAccess();
        Schema::dropIfExists('social_work_audit_events');
        Schema::dropIfExists('social_work_documents');
        Schema::dropIfExists('social_work_report_versions');
        Schema::dropIfExists('social_work_reports');
        Schema::dropIfExists('social_work_form_submissions');
        Schema::dropIfExists('social_work_form_template_versions');
        Schema::dropIfExists('social_work_form_templates');
        Schema::dropIfExists('social_work_alert_comments');
        Schema::dropIfExists('social_work_alerts');
        Schema::dropIfExists('social_work_risk_assessments');
        Schema::dropIfExists('social_work_risk_rules');
    }

    private function registerAccess(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        foreach (self::PERMISSIONS as $slug => $name) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug, 'name' => $name, 'description' => 'Permiso del módulo confidencial de Trabajo Social.',
                'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        if (! Schema::hasTable('system_modules')) {
            return;
        }

        DB::table('system_modules')->insertOrIgnore([
            'slug' => 'social_work', 'name' => 'Trabajo Social', 'frontend_route' => '/social-work',
            'icon' => 'bx-heart-circle', 'sort_order' => 28, 'active' => true,
            'parent_id' => null, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function unregisterAccess(): void
    {
        if (Schema::hasTable('system_modules')) {
            $moduleIds = DB::table('system_modules')->where('slug', 'social_work')->pluck('id');
            if (Schema::hasTable('role_system_module')) {
                DB::table('role_system_module')->whereIn('system_module_id', $moduleIds)->delete();
            }
            DB::table('system_modules')->where('slug', 'social_work')->delete();
        }
        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id');
            if (Schema::hasTable('permission_role')) DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            if (Schema::hasTable('permission_group_permission')) DB::table('permission_group_permission')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
