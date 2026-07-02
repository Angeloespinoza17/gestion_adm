<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('registered_name')->nullable()->after('last_name');
            $table->text('tardiness_semester_one_notes')->nullable()->after('observations');
            $table->text('absence_notes')->nullable()->after('tardiness_semester_one_notes');

            $table->string('guardian_role', 100)->nullable()->after('guardian_relationship');
            $table->string('guardian_rut', 20)->nullable()->after('guardian_name');
            $table->string('guardian_address')->nullable()->after('guardian_phone');

            $table->string('guardian_backup_name')->nullable()->after('guardian_email');
            $table->string('guardian_backup_relationship', 100)->nullable()->after('guardian_backup_name');
            $table->string('guardian_backup_role', 100)->nullable()->after('guardian_backup_relationship');
            $table->string('guardian_backup_rut', 20)->nullable()->after('guardian_backup_role');
            $table->string('guardian_backup_address')->nullable()->after('guardian_backup_rut');
            $table->string('guardian_backup_phone', 50)->nullable()->after('guardian_backup_address');
            $table->string('guardian_backup_email')->nullable()->after('guardian_backup_phone');

            $table->string('lives_with', 100)->nullable()->after('guardian_backup_email');
            $table->unsignedInteger('siblings_in_school')->nullable()->after('lives_with');

            $table->string('father_name')->nullable()->after('siblings_in_school');
            $table->string('father_rut', 20)->nullable()->after('father_name');
            $table->string('father_nationality', 100)->nullable()->after('father_rut');
            $table->string('father_address')->nullable()->after('father_nationality');
            $table->string('father_email')->nullable()->after('father_address');
            $table->string('father_occupation', 150)->nullable()->after('father_email');
            $table->string('father_phone', 50)->nullable()->after('father_occupation');
            $table->date('father_birthdate')->nullable()->after('father_phone');
            $table->string('father_education_level', 150)->nullable()->after('father_birthdate');

            $table->string('mother_name')->nullable()->after('father_education_level');
            $table->string('mother_rut', 20)->nullable()->after('mother_name');
            $table->string('mother_nationality', 100)->nullable()->after('mother_rut');
            $table->string('mother_address')->nullable()->after('mother_nationality');
            $table->string('mother_email')->nullable()->after('mother_address');
            $table->string('mother_occupation', 150)->nullable()->after('mother_email');
            $table->string('mother_phone', 50)->nullable()->after('mother_occupation');
            $table->date('mother_birthdate')->nullable()->after('mother_phone');
            $table->string('mother_education_level', 150)->nullable()->after('mother_birthdate');

            $table->boolean('has_repeated_course')->nullable()->after('mother_education_level');
            $table->boolean('has_internet')->nullable()->after('has_repeated_course');
            $table->boolean('has_computer')->nullable()->after('has_internet');
            $table->string('health_insurance', 150)->nullable()->after('has_computer');
            $table->text('beneficiary_programs')->nullable()->after('health_insurance');
            $table->text('scholarships')->nullable()->after('beneficiary_programs');
            $table->boolean('has_judicial_process')->nullable()->after('scholarships');

            $table->boolean('has_chronic_illness')->nullable()->after('has_judicial_process');
            $table->text('chronic_illness_details')->nullable()->after('has_chronic_illness');
            $table->boolean('has_medication_allergies')->nullable()->after('chronic_illness_details');
            $table->text('medication_allergies_details')->nullable()->after('has_medication_allergies');
            $table->boolean('has_physical_restrictions')->nullable()->after('medication_allergies_details');
            $table->text('physical_restrictions_details')->nullable()->after('has_physical_restrictions');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'registered_name',
                'tardiness_semester_one_notes',
                'absence_notes',
                'guardian_role',
                'guardian_rut',
                'guardian_address',
                'guardian_backup_name',
                'guardian_backup_relationship',
                'guardian_backup_role',
                'guardian_backup_rut',
                'guardian_backup_address',
                'guardian_backup_phone',
                'guardian_backup_email',
                'lives_with',
                'siblings_in_school',
                'father_name',
                'father_rut',
                'father_nationality',
                'father_address',
                'father_email',
                'father_occupation',
                'father_phone',
                'father_birthdate',
                'father_education_level',
                'mother_name',
                'mother_rut',
                'mother_nationality',
                'mother_address',
                'mother_email',
                'mother_occupation',
                'mother_phone',
                'mother_birthdate',
                'mother_education_level',
                'has_repeated_course',
                'has_internet',
                'has_computer',
                'health_insurance',
                'beneficiary_programs',
                'scholarships',
                'has_judicial_process',
                'has_chronic_illness',
                'chronic_illness_details',
                'has_medication_allergies',
                'medication_allergies_details',
                'has_physical_restrictions',
                'physical_restrictions_details',
            ]);
        });
    }
};
