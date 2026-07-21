<?php

namespace App\Services\Students;

use App\Support\DateInput;
use App\Support\Rut;
use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

class LirmiStudentPdfParser
{
    public function parseFile(string $path): array
    {
        $this->ensureImportMemoryLimit();
        try {
            $config = new Config;
            $config->setRetainImageContent(false);
            $pdf = (new Parser([], $config))->parseFile($path);
            $pages = array_map(
                static fn ($page) => $page->getText(),
                $pdf->getPages(),
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible leer el PDF. Verifica que el archivo no esté dañado ni protegido.',
                previous: $exception,
            );
        }

        return $this->parsePages($pages);
    }

    private function ensureImportMemoryLimit(): void
    {
        $current = ini_get('memory_limit');
        if ($current === false || $current === '-1') {
            return;
        }

        $bytes = match (strtolower(substr($current, -1))) {
            'g' => (int) $current * 1024 * 1024 * 1024,
            'm' => (int) $current * 1024 * 1024,
            'k' => (int) $current * 1024,
            default => (int) $current,
        };

        if ($bytes < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }
    }

    public function parsePages(array $pages): array
    {
        $records = [];
        $currentIndex = null;

        foreach ($pages as $pageIndex => $rawText) {
            $text = $this->normalizeText((string) $rawText);

            if (preg_match('/ANTECEDENTES DEL ESTUDIANTE/iu', $text)) {
                $records[] = $this->parseStudentPage($text, $pageIndex + 1);
                $currentIndex = array_key_last($records);

                continue;
            }

            if ($currentIndex !== null && preg_match('/APODERADOS Y FAMILIARES/iu', $text)) {
                $records[$currentIndex]['profile'] = array_merge(
                    $records[$currentIndex]['profile'],
                    $this->parseGuardians($text),
                    $this->parsePie($text),
                );
            }
        }

        if ($records === []) {
            throw new RuntimeException('El archivo no corresponde al formato de ficha de matrícula Lirmi.');
        }

        return $records;
    }

    private function parseStudentPage(string $text, int $page): array
    {
        $registeredName = $this->between($text, 'ANTECEDENTES DEL ESTUDIANTE\s+Nombre', 'Curso\s*:');
        [$firstName, $lastName] = $this->splitStudentName($registeredName);
        $chronicIllness = $this->between($text, 'Enfermedades\s+cr[oó]nicas', '¿?Apto para Educaci[oó]n\s+F[ií]sica\??');
        $medicationAllergies = $this->between($text, 'Alergias a\s+medicamentos', 'Medicamentos\s+contraindicados');

        return [
            'page' => $page,
            'profile' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'registered_name' => $registeredName,
                'rut' => Rut::normalize($this->between($text, 'RUN\s*:', 'Fecha de nacimiento')),
                'birthdate' => DateInput::normalize($this->value($text, 'Fecha de nacimiento\s+(\d{2}[\/-]\d{2}[\/-]\d{4})')),
                'gender' => $this->between($text, 'G[eé]nero', 'Nacionalidad'),
                'nationality' => $this->between($text, 'Nacionalidad', 'Direcci[oó]n'),
                'email' => $this->email($this->between($text, 'Correo electr[oó]nico', 'Tel[eé]fono Celular')),
                'phone' => $this->between($text, 'Tel[eé]fono Celular', 'Colegio procedencia'),
                'address' => $this->between($text, 'Direcci[oó]n', 'Comuna\s*:'),
                'commune' => $this->between($text, 'Comuna\s*:', 'Correo electr[oó]nico'),
                'school_admission_date' => DateInput::normalize($this->value($text, 'Fecha ingreso\s*:\s*(\d{2}[\/-]\d{2}[\/-]\d{4})')),
                'previous_school' => $this->between($text, 'Colegio procedencia', 'Nombre contacto\s+emergencia'),
                'emergency_contact_name' => $this->between($text, 'Nombre contacto\s+emergencia', 'Tel[eé]fono emergencia'),
                'emergency_contact_phone' => $this->between($text, 'Tel[eé]fono emergencia', 'Vive con'),
                'lives_with' => $this->between($text, 'Vive con', 'Religi[oó]n'),
                'religion' => $this->between($text, 'Religi[oó]n', '¿?Acepta clases de\s+religi[oó]n en el colegio\??'),
                'accepts_religion_classes' => $this->boolean($this->between($text, '¿?Acepta clases de\s+religi[oó]n en el colegio\??', 'Etnia')),
                'ethnicity' => $this->between($text, 'Etnia', 'ANTECEDENTES DE SALUD'),
                'height_cm' => $this->number($this->between($text, 'Estatura \(cm\)', 'Peso \(kg\)')),
                'weight_kg' => $this->number($this->between($text, 'Peso \(kg\)', 'Grupo Sangu[ií]neo')),
                'blood_type' => $this->between($text, 'Grupo Sangu[ií]neo', 'Alergias a alimentos'),
                'food_allergies' => $this->between($text, 'Alergias a alimentos', 'Alergias a\s+medicamentos'),
                'has_medication_allergies' => $this->medicalBoolean($medicationAllergies),
                'medication_allergies_details' => $this->medicalDetails($medicationAllergies),
                'contraindicated_medications' => $this->between($text, 'Medicamentos\s+contraindicados', 'Enfermedades\s+cr[oó]nicas'),
                'has_chronic_illness' => $this->medicalBoolean($chronicIllness),
                'chronic_illness_details' => $this->medicalDetails($chronicIllness),
                'fit_for_physical_education' => $this->boolean($this->between($text, '¿?Apto para Educaci[oó]n\s+F[ií]sica\??', 'Sistema de Previsi[oó]n')),
                'health_insurance' => $this->between($text, 'Sistema de Previsi[oó]n', '¿?Posee seguro\s+escolar privado\??'),
                'has_private_school_insurance' => $this->boolean($this->between($text, '¿?Posee seguro\s+escolar privado\??', 'Consultorio o cl[ií]nica\s+donde se atiende')),
                'healthcare_provider' => $this->between($text, 'Consultorio o cl[ií]nica\s+donde se atiende', 'Observaciones'),
                'health_observations' => $this->between($text, 'Observaciones', '(?:©|Firma Apoderado)'),
            ],
            'enrollment' => [
                'year' => $this->integer($this->value($text, 'FICHA DE MATR[IÍ]CULA\s+(\d{4})')),
                'course_name' => $this->between($text, 'Curso\s*:', 'Estado\s*:'),
                'source_status' => $this->between($text, 'Estado\s*:', 'N[uú]mero de matr[ií]cula\s*:'),
                'registration_number' => $this->between($text, 'N[uú]mero de matr[ií]cula\s*:', 'Fecha ingreso\s*:'),
                'enrolled_at' => DateInput::normalize($this->value($text, 'Fecha matr[ií]cula\s*:\s*(\d{2}[\/-]\d{2}[\/-]\d{4})')),
            ],
        ];
    }

    private function parseGuardians(string $text): array
    {
        $primary = $this->section($text, 'Apoderado titular', 'Apoderado suplente');
        $backup = $this->section($text, 'Apoderado suplente', '(?:PROGRAMA DE INTEGRACI[OÓ]N|El apoderado declara|©)');

        return array_merge(
            $this->guardianPayload($primary, 'guardian', 'Titular'),
            $this->guardianPayload($backup, 'guardian_backup', 'Suplente'),
        );
    }

    private function guardianPayload(?string $section, string $prefix, string $role): array
    {
        if (! $section || preg_match('/No existe un Apoderado/iu', $section)) {
            return [];
        }

        return [
            "{$prefix}_role" => $role,
            "{$prefix}_name" => $this->between($section, 'Nombres y Apellidos', 'Pasaporte'),
            "{$prefix}_passport" => $this->between($section, 'Pasaporte', 'RUN'),
            "{$prefix}_rut" => Rut::normalize($this->between($section, 'RUN', 'Parentesco')),
            "{$prefix}_relationship" => $this->between($section, 'Parentesco', 'Domicilio'),
            "{$prefix}_address" => $this->between($section, 'Domicilio', 'Comuna'),
            "{$prefix}_commune" => $this->between($section, 'Comuna', 'Tel[eé]fono Celular'),
            "{$prefix}_phone" => $this->between($section, 'Tel[eé]fono Celular', 'Correo electr[oó]nico'),
            "{$prefix}_email" => $this->email($this->between($section, 'Correo electr[oó]nico', 'Autorizaci[oó]n a que se\s+fotograf[ií]e o se grabe\s+a su estudiante')),
            "{$prefix}_photo_authorization" => $this->boolean($this->between($section, 'Autorizaci[oó]n a que se\s+fotograf[ií]e o se grabe\s+a su estudiante', 'Autorizado para\s+retirar del\s+establecimiento')),
            "{$prefix}_pickup_authorization" => $this->boolean($this->between($section, 'Autorizado para\s+retirar del\s+establecimiento', 'Estado civil')),
            "{$prefix}_marital_status" => $this->between($section, 'Estado civil', 'Nivel educacional'),
            "{$prefix}_education_level" => $this->between($section, 'Nivel educacional', '[UÚ]ltimo Nivel\s+educacional'),
            "{$prefix}_last_education_level" => $this->between($section, '[UÚ]ltimo Nivel\s+educacional', 'Ocupaci[oó]n'),
            "{$prefix}_occupation" => $this->after($section, 'Ocupaci[oó]n'),
        ];
    }

    private function parsePie(string $text): array
    {
        $section = $this->section($text, 'PROGRAMA DE INTEGRACI[OÓ]N', '(?:El apoderado declara|©)');

        if (! $section) {
            return [];
        }

        return [
            'is_pie_participant' => $this->boolean($this->between($section, 'Permanencia PIE', 'Tipo Permanencia')),
            'pie_permanence_type' => $this->between($section, 'Tipo Permanencia', 'Diagn[oó]stico'),
            'pie_diagnosis' => $this->after($section, 'Diagn[oó]stico'),
        ];
    }

    private function splitStudentName(?string $name): array
    {
        $tokens = preg_split('/\s+/u', trim((string) $name)) ?: [];

        if (count($tokens) <= 1) {
            return [$name, 'Sin apellido informado'];
        }

        $surnameLength = count($tokens) >= 3 ? 2 : 1;
        $lastName = implode(' ', array_splice($tokens, -$surnameLength));

        return [implode(' ', $tokens), $lastName];
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\r"], [' ', "\n"], $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function section(string $text, string $start, string $end): ?string
    {
        if (! preg_match('/(?:'.$start.')\s*(.*?)(?=\s*(?:'.$end.'))/iu', $text, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private function between(string $text, string $start, string $end): ?string
    {
        return $this->clean($this->section($text, $start, $end));
    }

    private function after(string $text, string $start): ?string
    {
        if (! preg_match('/(?:'.$start.')\s*(.*?)\s*$/iu', $text, $matches)) {
            return null;
        }

        return $this->clean($matches[1]);
    }

    private function value(string $text, string $pattern): ?string
    {
        if (! preg_match('/'.$pattern.'/iu', $text, $matches)) {
            return null;
        }

        return $this->clean($matches[1] ?? null);
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value, " \t\n\r\0\x0B.");

        if ($value === '' || preg_match('/^(?:sin informaci[oó]n|no registra informaci[oó]n)$/iu', $value)) {
            return null;
        }

        return $value;
    }

    private function email(?string $value): ?string
    {
        $value = $this->clean($value);

        return $value ? mb_strtolower($value) : null;
    }

    private function boolean(?string $value): ?bool
    {
        $value = $this->clean($value);
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower($value);
        if (preg_match('/^(?:s[ií]|si|yes)$/u', $normalized)) {
            return true;
        }

        if (preg_match('/^(?:no|ningun[oa]?)$/u', $normalized)) {
            return false;
        }

        return null;
    }

    private function medicalBoolean(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return $this->boolean($value) ?? true;
    }

    private function medicalDetails(?string $value): ?string
    {
        return $this->medicalBoolean($value) ? $value : null;
    }

    private function number(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function integer(?string $value): ?int
    {
        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }
}
