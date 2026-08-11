<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biblioteca_lectores_temporales', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('rut', 30)->nullable()->index();
            $table->string('person_category', 40)->default('otro')->index();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('course_name', 120)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('linked_student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->foreignId('linked_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('source_system', 40)->nullable();
            $table->string('source_id', 80)->nullable();
            $table->json('source_metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_system', 'source_id'], 'bib_tmp_reader_source_unique');
            $table->index(['active', 'full_name'], 'bib_tmp_reader_active_name_idx');
        });

        Schema::table('biblioteca_obras', function (Blueprint $table) {
            $table->string('source_system', 40)->nullable()->after('source_metadata');
            $table->string('source_id', 80)->nullable()->after('source_system');
            $table->unique(['source_system', 'source_id'], 'bib_works_source_unique');
        });

        Schema::table('biblioteca_ejemplares', function (Blueprint $table) {
            $table->string('legacy_registration_number', 80)->nullable()->after('barcode')->index();
            $table->boolean('is_loanable')->default(true)->after('availability_status')->index();
            $table->string('loan_restriction', 40)->nullable()->after('is_loanable');
            $table->string('source_system', 40)->nullable()->after('loan_restriction');
            $table->string('source_id', 80)->nullable()->after('source_system');
            $table->json('source_metadata')->nullable()->after('source_id');
            $table->unique(['source_system', 'source_id'], 'bib_copies_source_unique');
        });

        Schema::table('biblioteca_prestamos', function (Blueprint $table) {
            $table->foreignId('biblioteca_lector_temporal_id')
                ->nullable()
                ->after('staff_id')
                ->constrained('biblioteca_lectores_temporales', indexName: 'bib_loan_tmp_reader_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('biblioteca_prestamos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('biblioteca_lector_temporal_id');
        });

        Schema::table('biblioteca_ejemplares', function (Blueprint $table) {
            $table->dropUnique('bib_copies_source_unique');
            $table->dropColumn([
                'legacy_registration_number',
                'is_loanable',
                'loan_restriction',
                'source_system',
                'source_id',
                'source_metadata',
            ]);
        });

        Schema::table('biblioteca_obras', function (Blueprint $table) {
            $table->dropUnique('bib_works_source_unique');
            $table->dropColumn(['source_system', 'source_id']);
        });

        Schema::dropIfExists('biblioteca_lectores_temporales');
    }
};
