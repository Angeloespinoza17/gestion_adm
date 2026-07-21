<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('gender', 50)->nullable()->after('birthdate');
            $table->string('nationality', 100)->nullable()->after('gender');
            $table->string('commune', 100)->nullable()->after('address');
            $table->date('school_admission_date')->nullable()->after('commune');
            $table->string('previous_school')->nullable()->after('school_admission_date');
            $table->string('emergency_contact_name')->nullable()->after('previous_school');
            $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_name');
            $table->string('religion', 100)->nullable()->after('emergency_contact_phone');
            $table->boolean('accepts_religion_classes')->nullable()->after('religion');
            $table->string('ethnicity', 100)->nullable()->after('accepts_religion_classes');

            $table->string('guardian_passport', 50)->nullable()->after('guardian_rut');
            $table->string('guardian_commune', 100)->nullable()->after('guardian_address');
            $table->boolean('guardian_photo_authorization')->nullable()->after('guardian_commune');
            $table->boolean('guardian_pickup_authorization')->nullable()->after('guardian_photo_authorization');
            $table->string('guardian_marital_status', 100)->nullable()->after('guardian_pickup_authorization');
            $table->string('guardian_education_level', 150)->nullable()->after('guardian_marital_status');
            $table->string('guardian_last_education_level')->nullable()->after('guardian_education_level');
            $table->string('guardian_occupation', 150)->nullable()->after('guardian_last_education_level');

            $table->string('guardian_backup_passport', 50)->nullable()->after('guardian_backup_rut');
            $table->string('guardian_backup_commune', 100)->nullable()->after('guardian_backup_address');
            $table->boolean('guardian_backup_photo_authorization')->nullable()->after('guardian_backup_commune');
            $table->boolean('guardian_backup_pickup_authorization')->nullable()->after('guardian_backup_photo_authorization');
            $table->string('guardian_backup_marital_status', 100)->nullable()->after('guardian_backup_pickup_authorization');
            $table->string('guardian_backup_education_level', 150)->nullable()->after('guardian_backup_marital_status');
            $table->string('guardian_backup_last_education_level')->nullable()->after('guardian_backup_education_level');
            $table->string('guardian_backup_occupation', 150)->nullable()->after('guardian_backup_last_education_level');

            $table->decimal('height_cm', 6, 2)->nullable()->after('health_insurance');
            $table->decimal('weight_kg', 6, 2)->nullable()->after('height_cm');
            $table->string('blood_type', 10)->nullable()->after('weight_kg');
            $table->text('food_allergies')->nullable()->after('blood_type');
            $table->text('contraindicated_medications')->nullable()->after('medication_allergies_details');
            $table->boolean('fit_for_physical_education')->nullable()->after('contraindicated_medications');
            $table->boolean('has_private_school_insurance')->nullable()->after('fit_for_physical_education');
            $table->string('healthcare_provider')->nullable()->after('has_private_school_insurance');
            $table->text('health_observations')->nullable()->after('healthcare_provider');

            $table->boolean('is_pie_participant')->nullable()->after('health_observations');
            $table->string('pie_permanence_type', 100)->nullable()->after('is_pie_participant');
            $table->text('pie_diagnosis')->nullable()->after('pie_permanence_type');
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->string('registration_number', 50)->nullable()->after('enrollment_status');
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropColumn('registration_number');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'nationality',
                'commune',
                'school_admission_date',
                'previous_school',
                'emergency_contact_name',
                'emergency_contact_phone',
                'religion',
                'accepts_religion_classes',
                'ethnicity',
                'guardian_passport',
                'guardian_commune',
                'guardian_photo_authorization',
                'guardian_pickup_authorization',
                'guardian_marital_status',
                'guardian_education_level',
                'guardian_last_education_level',
                'guardian_occupation',
                'guardian_backup_passport',
                'guardian_backup_commune',
                'guardian_backup_photo_authorization',
                'guardian_backup_pickup_authorization',
                'guardian_backup_marital_status',
                'guardian_backup_education_level',
                'guardian_backup_last_education_level',
                'guardian_backup_occupation',
                'height_cm',
                'weight_kg',
                'blood_type',
                'food_allergies',
                'contraindicated_medications',
                'fit_for_physical_education',
                'has_private_school_insurance',
                'healthcare_provider',
                'health_observations',
                'is_pie_participant',
                'pie_permanence_type',
                'pie_diagnosis',
            ]);
        });
    }
};
