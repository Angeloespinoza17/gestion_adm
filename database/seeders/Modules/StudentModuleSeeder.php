<?php

namespace Database\Seeders\Modules;

use App\Models\StudentProfile;
use App\Models\StudentPromotion;
use Database\Seeders\AcademicCatalogSeeder;
use Database\Seeders\StudentTestingSeeder;
use Database\Seeders\Support\ModuleSeeder;

class StudentModuleSeeder extends ModuleSeeder
{
    public function run(): void
    {
        $this->call([
            AcademicCatalogSeeder::class,
            StudentTestingSeeder::class,
        ]);

        $this->seedDetailedProfiles();
        $this->seedPromotions();
    }

    private function seedDetailedProfiles(): void
    {
        $profiles = [
            '15555555-1' => [
                'registered_name' => 'María José Pérez Soto',
                'pickup_restriction' => true,
                'pickup_restriction_notes' => 'Solo retiro por madre, padre o apoderado registrado con autorización previa.',
                'porter_alert_notes' => 'Confirmar identidad y motivo del retiro si ocurre antes de las 15:00 horas.',
                'authorized_pickup_people' => [
                    [
                        'name' => 'Ana Soto',
                        'relationship' => 'Madre',
                        'rut' => '12222333-4',
                        'phone' => '+56920000001',
                    ],
                    [
                        'name' => 'Luis Pérez',
                        'relationship' => 'Padre',
                        'rut' => '13333444-5',
                        'phone' => '+56920000031',
                    ],
                ],
                'guardian_backup_name' => 'Luis Pérez',
                'guardian_backup_relationship' => 'Padre',
                'guardian_backup_role' => 'Apoderado suplente',
                'guardian_backup_rut' => '13333444-5',
                'guardian_backup_phone' => '+56920000031',
                'guardian_backup_email' => 'luis.perez@example.com',
                'has_internet' => true,
                'has_computer' => true,
                'health_insurance' => 'Fonasa',
                'beneficiary_programs' => 'SEP',
                'scholarships' => 'Beca de excelencia académica interna',
                'absence_notes' => 'Seguimiento por inasistencias justificadas durante mayo.',
            ],
            '16666666-2' => [
                'registered_name' => 'Josefa Antonia Carrasco Díaz',
                'guardian_backup_name' => 'María Díaz',
                'guardian_backup_relationship' => 'Abuela',
                'guardian_backup_role' => 'Contacto alternativo',
                'guardian_backup_rut' => '14444555-6',
                'guardian_backup_phone' => '+56920000042',
                'guardian_backup_email' => 'maria.diaz@example.com',
                'has_internet' => true,
                'has_computer' => false,
                'has_chronic_illness' => true,
                'chronic_illness_details' => 'Asma controlada con inhalador preventivo.',
                'has_medication_allergies' => true,
                'medication_allergies_details' => 'Alergia declarada a penicilina.',
                'porter_alert_notes' => 'Avisar a enfermería si presenta síntomas respiratorios antes del retiro.',
            ],
            '17777777-3' => [
                'registered_name' => 'Catalina Alejandra Rojas Fuentes',
                'general_status' => 'egresado',
                'has_repeated_course' => false,
                'has_internet' => true,
                'has_computer' => true,
                'beneficiary_programs' => 'PAE',
                'absence_notes' => 'Ficha histórica de estudiante egresada.',
            ],
        ];

        foreach ($profiles as $rut => $payload) {
            StudentProfile::query()
                ->where('rut', $rut)
                ->first()?->update($payload);
        }
    }

    private function seedPromotions(): void
    {
        $promotions = [
            [
                'rut' => '15555555-1',
                'from_year' => 2026,
                'to_year' => 2027,
                'from_level' => '7° básico',
                'from_section' => 'A',
                'to_level' => '8° básico',
                'to_section' => 'A',
                'status' => 'promovida',
                'notes' => 'Promoción registrada para seguimiento anual del módulo de estudiantes.',
            ],
            [
                'rut' => '15555555-1',
                'from_year' => 2027,
                'to_year' => 2028,
                'from_level' => '8° básico',
                'from_section' => 'A',
                'to_level' => '1° medio',
                'to_section' => 'B',
                'status' => 'promovida',
                'notes' => 'Cambio de nivel a enseñanza media con ajuste de paralelo.',
            ],
            [
                'rut' => '16666666-2',
                'from_year' => 2026,
                'to_year' => 2027,
                'from_level' => 'NT1',
                'from_section' => 'B',
                'to_level' => 'NT2',
                'to_section' => 'A',
                'status' => 'promovida',
                'notes' => 'Promoción base de educación parvularia.',
            ],
        ];

        foreach ($promotions as $promotion) {
            $student = $this->student($promotion['rut']);
            $fromYear = $this->academicYear($promotion['from_year']);
            $toYear = $this->academicYear($promotion['to_year']);
            $fromCourse = $this->course($promotion['from_year'], $promotion['from_level'], $promotion['from_section']);
            $toCourse = $this->course($promotion['to_year'], $promotion['to_level'], $promotion['to_section']);

            StudentPromotion::query()->updateOrCreate(
                [
                    'student_profile_id' => $student->id,
                    'from_academic_year_id' => $fromYear->id,
                    'to_academic_year_id' => $toYear->id,
                ],
                [
                    'from_course_section_id' => $fromCourse->id,
                    'to_course_section_id' => $toCourse->id,
                    'promotion_status' => $promotion['status'],
                    'notes' => $promotion['notes'],
                    'created_by' => $this->creator()->id,
                    'updated_by' => $this->creator()->id,
                ],
            );
        }
    }
}
