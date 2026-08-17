<?php

namespace App\Services\HumanResources;

use App\Models\Cargo;
use App\Models\HumanResources\HrAbsenceRecord;
use App\Models\HumanResources\HrCvBankEntry;
use App\Models\HumanResources\HrJobProfile;
use App\Models\HumanResources\HrMedicalLeave;
use App\Models\HumanResources\HrPsycholaborInterview;
use App\Models\HumanResources\HrRecruitmentApplication;
use App\Models\HumanResources\HrRecruitmentVacancy;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class HrSpreadsheetImportService
{
    public function __construct(private readonly HrAbsenceLedgerService $ledger) {}

    public function preview(string $kind, UploadedFile $file, User $user): array
    {
        $payload = match ($kind) {
            'absences' => $this->previewAbsences($file),
            'recruitment' => $this->previewRecruitment($file),
            default => throw ValidationException::withMessages(['kind' => 'Tipo de importación no válido.']),
        };

        $token = (string) Str::uuid();
        Storage::disk('local')->put('hr-imports/'.$token.'.json', json_encode([
            'kind' => $kind,
            'user_id' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'created_at' => now()->toIso8601String(),
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return ['token' => $token, 'file_name' => $file->getClientOriginalName()] + $payload;
    }

    public function commit(string $kind, string $token, User $user): array
    {
        $path = 'hr-imports/'.$token.'.json';
        if (! preg_match('/^[0-9a-f-]{36}$/i', $token) || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['token' => 'La vista previa expiró o no es válida.']);
        }
        $preview = json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
        if (($preview['kind'] ?? null) !== $kind) {
            throw ValidationException::withMessages(['token' => 'La vista previa pertenece a otro importador.']);
        }
        if ((int) ($preview['user_id'] ?? 0) !== (int) $user->id && ! $user->isSuperAdmin()) {
            abort(403);
        }

        $result = DB::transaction(fn () => $kind === 'absences'
            ? $this->commitAbsences($preview, $user)
            : $this->commitRecruitment($preview, $user));
        Storage::disk('local')->delete($path);

        return $result;
    }

    private function previewAbsences(UploadedFile $file): array
    {
        $staff = Staff::query()->select('id', 'full_name', 'rut')->get();
        $licenseRows = $this->readWorksheet($file->getRealPath(), 1);
        $adminRows = $this->readWorksheet($file->getRealPath(), 2);
        $rows = [];

        foreach (array_slice($licenseRows, 1) as $offset => $row) {
            $nameCell = trim((string) ($row['A'] ?? ''));
            if ($nameCell === '') {
                continue;
            }
            $match = $this->matchStaff($nameCell, $staff);
            $startsOn = $this->dateValue($row['B'] ?? null);
            $endsOn = $this->dateValue($row['C'] ?? null);
            $warnings = [];
            if (! $match['staff_id']) {
                $warnings[] = 'No fue posible conciliar al funcionario; la fila quedará fuera de la importación.';
            }
            if (! $startsOn || ! $endsOn) {
                $warnings[] = 'Fecha de inicio o término inválida.';
            }
            $days = $startsOn && $endsOn
                ? CarbonImmutable::parse($startsOn)->diffInDays(CarbonImmutable::parse($endsOn)) + 1
                : 0;
            $rows[] = [
                'row' => $offset + 2,
                'sheet' => 'LICENCIAS MEDICAS',
                'record_type' => 'licencia_medica',
                'source_name' => $nameCell,
                'staff_id' => $match['staff_id'],
                'staff_name' => $match['staff_name'],
                'match_method' => $match['method'],
                'match_confidence' => $match['confidence'],
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'quantity' => $days,
                'rest_type' => $this->contains($row['D'] ?? '', 'parcial') ? 'parcial' : 'total',
                'status' => $this->contains($row['E'] ?? '', 'tramit') ? 'tramitada' : 'registrada',
                'can_import' => (bool) $match['staff_id'] && $startsOn && $endsOn,
                'warnings' => $warnings,
                'external_key' => hash('sha256', 'license|'.$this->normalizeName($nameCell).'|'.$startsOn.'|'.$endsOn),
            ];
        }

        foreach (array_slice($adminRows, 1) as $offset => $row) {
            $rut = trim((string) ($row['A'] ?? ''));
            $name = trim((string) ($row['B'] ?? ''));
            if ($name === '') {
                continue;
            }
            $match = $this->matchStaff($rut !== '' ? $name."\nRUT: ".$rut : $name, $staff);
            foreach (['C', 'D', 'E'] as $column) {
                $rawDate = $row[$column] ?? null;
                if (trim((string) $rawDate) === '') {
                    continue;
                }
                $date = $this->dateValue($rawDate, 2026);
                $halfDay = $this->contains($rawDate, '1/2');
                $warnings = [];
                if (! $match['staff_id']) {
                    $warnings[] = 'RUT o nombre sin coincidencia en funcionarios.';
                }
                if (! $date) {
                    $warnings[] = 'No se pudo interpretar la fecha del permiso administrativo.';
                }
                $rows[] = [
                    'row' => $offset + 2,
                    'sheet' => 'DIAS ADMINISTRATIVOS',
                    'record_type' => 'dia_administrativo',
                    'source_name' => $name,
                    'source_rut' => $rut,
                    'staff_id' => $match['staff_id'],
                    'staff_name' => $match['staff_name'],
                    'match_method' => $match['method'],
                    'match_confidence' => $match['confidence'],
                    'starts_on' => $date,
                    'ends_on' => $date,
                    'quantity' => $halfDay ? 0.5 : 1,
                    'rest_type' => $halfDay ? 'medio_dia' : 'dia_completo',
                    'status' => 'justificada',
                    'can_import' => (bool) $match['staff_id'] && $date,
                    'warnings' => $warnings,
                    'external_key' => hash('sha256', 'admin|'.$this->normalizeRut($rut).'|'.$date.'|'.($halfDay ? 'half' : 'full')),
                ];
            }
        }

        $importable = collect($rows)->where('can_import', true);

        return [
            'total_rows' => count($rows),
            'importable_rows' => $importable->count(),
            'unmatched_rows' => collect($rows)->whereNull('staff_id')->count(),
            'summary' => [
                'medical_leaves' => $importable->where('record_type', 'licencia_medica')->count(),
                'administrative_days' => $importable->where('record_type', 'dia_administrativo')->sum('quantity'),
            ],
            'rows' => $rows,
        ];
    }

    private function commitAbsences(array $preview, User $user): array
    {
        $created = 0;
        $skipped = 0;
        $unmatched = 0;
        foreach ($preview['payload']['rows'] ?? [] as $row) {
            if (! ($row['can_import'] ?? false)) {
                $unmatched++;

                continue;
            }
            if (HrAbsenceRecord::query()->withTrashed()->where('external_key', $row['external_key'])->exists()) {
                $skipped++;

                continue;
            }
            $medicalLeave = null;
            if ($row['record_type'] === 'licencia_medica') {
                $medicalLeave = HrMedicalLeave::create([
                    'staff_id' => $row['staff_id'],
                    'starts_at' => $row['starts_on'],
                    'ends_at' => $row['ends_on'],
                    'days' => $row['quantity'],
                    'affects_payroll' => true,
                    'status' => $row['status'],
                    'metadata' => [
                        'historical_import' => true,
                        'source_file' => $preview['file_name'],
                        'source_sheet' => $row['sheet'],
                        'source_row' => $row['row'],
                        'rest_type' => $row['rest_type'],
                    ],
                    'notes' => 'Precargada desde planilla histórica.',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }
            $record = HrAbsenceRecord::create([
                'staff_id' => $row['staff_id'],
                'medical_leave_id' => $medicalLeave?->id,
                'absence_type' => $row['record_type'],
                'starts_on' => $row['starts_on'],
                'ends_on' => $row['ends_on'],
                'quantity' => $row['quantity'],
                'unit' => 'dias',
                'rest_type' => $row['rest_type'],
                'status' => $row['status'],
                'source' => 'importacion_historica',
                'affects_attendance' => true,
                'affects_payroll' => $row['record_type'] === 'licencia_medica',
                'external_key' => $row['external_key'],
                'metadata' => [
                    'source_file' => $preview['file_name'],
                    'source_sheet' => $row['sheet'],
                    'source_row' => $row['row'],
                    'source_name' => $row['source_name'],
                    'match_method' => $row['match_method'],
                    'match_confidence' => $row['match_confidence'],
                ],
                'notes' => 'Precargada desde planilla histórica.',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $this->ledger->syncBalanceMovement($record, $user->id);
            $created++;
        }

        return compact('created', 'skipped', 'unmatched');
    }

    private function previewRecruitment(UploadedFile $file): array
    {
        $interviewSheet = $this->readWorksheet($file->getRealPath(), 1);
        $cvSheet = $this->readWorksheet($file->getRealPath(), 2);
        $interviews = [];
        $headerRow = $this->findHeaderRow($interviewSheet, ['postulante', 'cargo']);
        foreach (array_slice($interviewSheet, $headerRow + 1) as $offset => $row) {
            $name = trim((string) ($row['B'] ?? ''));
            $position = trim((string) ($row['C'] ?? ''));
            if ($name === '') {
                continue;
            }
            $evaluation = trim((string) ($row['D'] ?? ''));
            $hiring = trim((string) ($row['E'] ?? ''));
            $reconsider = trim((string) ($row['F'] ?? ''));
            $interviews[] = [
                'row' => $headerRow + $offset + 2,
                'name' => $name,
                'position' => $position ?: 'Cargo no informado',
                'evaluation' => $evaluation,
                'result' => $this->evaluationResult($evaluation),
                'induction_required' => $this->contains($evaluation, 'induccion'),
                'hiring_outcome' => $hiring,
                'stage' => $this->applicationStage($hiring),
                'reconsideration' => $this->reconsideration($reconsider),
                'reconsideration_notes' => $reconsider ?: null,
                'can_import' => true,
                'external_key' => hash('sha256', 'psycholabor|'.$this->normalizeName($name).'|'.$this->normalizeName($position)),
            ];
        }

        $candidates = [];
        $cvHeader = $this->findHeaderRow($cvSheet, ['postulante', 'contacto']);
        foreach (array_slice($cvSheet, $cvHeader + 1) as $offset => $row) {
            $name = trim((string) ($row['B'] ?? ''));
            if ($name === '') {
                continue;
            }
            $candidates[] = [
                'row' => $cvHeader + $offset + 2,
                'name' => $name,
                'position' => trim((string) ($row['C'] ?? '')),
                'phone' => $this->phoneValue($row['D'] ?? null),
                'email' => trim((string) ($row['E'] ?? '')),
                'availability' => trim((string) ($row['F'] ?? '')),
                'notes' => null,
                'can_import' => true,
            ];
        }

        return [
            'total_rows' => count($interviews) + count($candidates),
            'importable_rows' => count($interviews) + count($candidates),
            'summary' => [
                'interviews' => count($interviews),
                'cv_entries' => count($candidates),
                'positions' => collect($interviews)->pluck('position')->map(fn ($value) => $this->normalizeName($value))->unique()->count(),
            ],
            'rows' => array_merge(
                array_map(fn ($row) => $row + ['record_type' => 'entrevista'], $interviews),
                array_map(fn ($row) => $row + ['record_type' => 'curriculum'], $candidates),
            ),
            'interviews' => $interviews,
            'candidates' => $candidates,
        ];
    }

    private function commitRecruitment(array $preview, User $user): array
    {
        HrCvBankEntry::query()->whereNull('normalized_name')->each(function (HrCvBankEntry $candidate): void {
            $candidate->forceFill(['normalized_name' => $this->normalizeName($candidate->full_name)])->save();
        });
        $candidateCount = 0;
        $vacancyCount = 0;
        $interviewCount = 0;
        $skipped = 0;

        foreach ($preview['payload']['candidates'] ?? [] as $row) {
            [$candidate, $created] = $this->candidateFromRow($row, $user, $preview['file_name']);
            $candidateCount += $created ? 1 : 0;
        }

        foreach ($preview['payload']['interviews'] ?? [] as $row) {
            if (HrPsycholaborInterview::query()->withTrashed()->where('external_key', $row['external_key'])->exists()) {
                $skipped++;

                continue;
            }
            [$candidate, $created] = $this->candidateFromRow([
                'name' => $row['name'],
                'position' => $row['position'],
                'phone' => null,
                'email' => null,
                'availability' => null,
                'notes' => null,
            ], $user, $preview['file_name']);
            $candidateCount += $created ? 1 : 0;

            $vacancyKey = hash('sha256', 'historical-vacancy|'.$this->normalizeName($row['position']));
            $vacancy = HrRecruitmentVacancy::query()->where('external_key', $vacancyKey)->first();
            if (! $vacancy) {
                $cargo = $this->matchCargo($row['position']);
                $profile = HrJobProfile::query()->where('cargo_id', $cargo?->id)->first();
                if (! $profile) {
                    $profile = HrJobProfile::create([
                        'cargo_id' => $cargo?->id,
                        'code' => 'IMP-'.mb_strtoupper(substr(hash('sha256', $this->normalizeName($row['position'])), 0, 10)),
                        'title' => $row['position'],
                        'purpose' => 'Perfil recuperado desde el registro histórico de selección.',
                        'status' => 'borrador',
                        'notes' => 'Requiere completar propósito, responsabilidades, requisitos y competencias.',
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
                $vacancy = HrRecruitmentVacancy::create([
                    'job_profile_id' => $profile->id,
                    'cargo_id' => $cargo?->id,
                    'responsible_user_id' => $user->id,
                    'title' => $row['position'],
                    'vacancy_count' => 1,
                    'status' => 'cerrada',
                    'description' => 'Proceso histórico recuperado desde planilla.',
                    'external_key' => $vacancyKey,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
                $vacancyCount++;
            }

            $application = HrRecruitmentApplication::query()->firstOrCreate([
                'external_key' => hash('sha256', 'application|'.$row['external_key']),
            ], [
                'vacancy_id' => $vacancy->id,
                'cv_bank_entry_id' => $candidate->id,
                'stage' => $row['stage'],
                'source' => 'Importación histórica',
                'reconsideration' => $row['reconsideration'],
                'reconsideration_notes' => $row['reconsideration_notes'],
                'outcome' => $row['hiring_outcome'] ?: null,
                'notes' => 'Precargada desde '.$preview['file_name'].', fila '.$row['row'].'.',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            HrPsycholaborInterview::create([
                'application_id' => $application->id,
                'result' => $row['result'],
                'induction_required' => $row['induction_required'],
                'reconsideration' => $row['reconsideration'],
                'considerations' => $row['evaluation'] ?: null,
                'status' => $row['result'] === 'pendiente' ? 'pendiente_informe' : 'completada',
                'source' => 'Importación histórica',
                'external_key' => $row['external_key'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $interviewCount++;
        }

        return [
            'candidates_created' => $candidateCount,
            'vacancies_created' => $vacancyCount,
            'interviews_created' => $interviewCount,
            'skipped' => $skipped,
        ];
    }

    private function candidateFromRow(array $row, User $user, string $fileName): array
    {
        $normalized = $this->normalizeName($row['name']);
        $candidate = ! empty($row['email'])
            ? HrCvBankEntry::query()->where('email', $row['email'])->first()
            : null;
        $candidate ??= HrCvBankEntry::query()->where('normalized_name', $normalized)->first();
        $created = false;
        if (! $candidate) {
            $candidate = HrCvBankEntry::create([
                'full_name' => $row['name'],
                'normalized_name' => $normalized,
                'email' => $row['email'] ?: null,
                'phone' => $row['phone'] ?: null,
                'source' => 'Importación histórica',
                'desired_position' => $row['position'] ?: null,
                'availability' => $row['availability'] ?: null,
                'status' => 'banco_talento',
                'notes' => trim(($row['notes'] ?? '')."\nPrecargado desde ".$fileName),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $created = true;
        }

        return [$candidate, $created];
    }

    private function matchStaff(string $source, $staff): array
    {
        $rut = $this->extractRut($source);
        if ($rut !== '') {
            $rutMatch = $staff->first(fn ($person) => $this->normalizeRut($person->rut) === $rut);
            if ($rutMatch) {
                return ['staff_id' => $rutMatch->id, 'staff_name' => $rutMatch->full_name, 'method' => 'rut', 'confidence' => 100];
            }
        }

        $name = $this->normalizeName(preg_split('/\b(?:rut|run)\b\s*:?/iu', $source)[0] ?? $source);
        $exact = $staff->first(fn ($person) => $this->normalizeName($person->full_name) === $name);
        if ($exact) {
            return ['staff_id' => $exact->id, 'staff_name' => $exact->full_name, 'method' => 'nombre_exacto', 'confidence' => 100];
        }

        $scores = $staff->map(function ($person) use ($name): array {
            $target = $this->normalizeName($person->full_name);
            similar_text($name, $target, $percent);
            $sourceTokens = array_values(array_filter(explode(' ', $name)));
            $targetTokens = array_values(array_filter(explode(' ', $target)));
            $overlap = collect($sourceTokens)->filter(function (string $sourceToken) use ($targetTokens): bool {
                return collect($targetTokens)->contains(function (string $targetToken) use ($sourceToken): bool {
                    if ($sourceToken === $targetToken) {
                        return true;
                    }
                    similar_text($sourceToken, $targetToken, $tokenPercent);

                    return $tokenPercent >= 80;
                });
            })->count();
            $denominator = max(1, min(count($sourceTokens), count($targetTokens)));
            $tokenScore = ($overlap / $denominator) * 100;

            return ['person' => $person, 'score' => max($percent, $tokenScore)];
        })->sortByDesc('score')->values();
        $best = $scores->first();
        $second = $scores->get(1);
        if ($best && $best['score'] >= 75 && (! $second || $best['score'] - $second['score'] >= 5)) {
            return [
                'staff_id' => $best['person']->id,
                'staff_name' => $best['person']->full_name,
                'method' => 'nombre_aproximado',
                'confidence' => round($best['score'], 1),
            ];
        }

        return ['staff_id' => null, 'staff_name' => null, 'method' => 'sin_coincidencia', 'confidence' => round((float) ($best['score'] ?? 0), 1)];
    }

    private function matchCargo(string $title): ?Cargo
    {
        $normalized = $this->normalizeName($title);

        return Cargo::query()->get()->first(function (Cargo $cargo) use ($normalized): bool {
            $candidate = $this->normalizeName($cargo->name);

            return $candidate === $normalized || str_contains($normalized, $candidate) || str_contains($candidate, $normalized);
        });
    }

    private function readWorksheet(string $path, int $sheetNumber): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'No se pudo abrir el archivo XLSX.']);
        }
        try {
            $sharedStrings = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedXml !== false) {
                $shared = simplexml_load_string($sharedXml);
                foreach ($shared?->xpath('//*[local-name()="si"]') ?: [] as $item) {
                    $parts = $item->xpath('.//*[local-name()="t"]') ?: [];
                    $sharedStrings[] = implode('', array_map(fn ($part) => (string) $part, $parts));
                }
            }
            $sheetXml = $zip->getFromName('xl/worksheets/sheet'.$sheetNumber.'.xml');
            if ($sheetXml === false) {
                throw ValidationException::withMessages(['file' => 'No se encontró una de las hojas esperadas en la planilla.']);
            }
            $sheet = simplexml_load_string($sheetXml);
            $rows = [];
            foreach ($sheet?->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
                $values = [];
                foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    preg_match('/^[A-Z]+/', (string) $cell['r'], $match);
                    $column = $match[0] ?? '';
                    $type = (string) $cell['t'];
                    $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
                    $value = isset($valueNodes[0]) ? (string) $valueNodes[0] : '';
                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $parts = $cell->xpath('.//*[local-name()="t"]') ?: [];
                        $value = implode('', array_map(fn ($part) => (string) $part, $parts));
                    }
                    $values[$column] = $value;
                }
                $rows[] = $values;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function findHeaderRow(array $rows, array $needles): int
    {
        foreach ($rows as $index => $row) {
            $text = $this->normalizeName(implode(' ', $row));
            if (collect($needles)->every(fn ($needle) => str_contains($text, $needle))) {
                return $index;
            }
        }

        return 0;
    }

    private function dateValue(mixed $value, ?int $defaultYear = null): ?string
    {
        if (is_numeric($value) && (float) $value > 1000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) floor((float) $value))->format('Y-m-d');
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if ($defaultYear && preg_match('/^(\d{1,2})\s*[-\/]\s*(\d{1,2})/', $raw, $match)) {
            return CarbonImmutable::create($defaultYear, (int) $match[2], (int) $match[1])->format('Y-m-d');
        }
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $raw);
                if ($date && $date->format($format) === $raw) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function evaluationResult(string $value): string
    {
        $normalized = $this->normalizeName($value);
        if ($normalized === '') {
            return 'pendiente';
        }
        if (str_starts_with($normalized, 'no')) {
            return 'no_apto';
        }
        if (str_contains($normalized, 'consider') || str_contains($normalized, 'leve')) {
            return 'apto_con_observaciones';
        }

        return 'apto';
    }

    private function phoneValue(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return sprintf('%.0f', (float) $raw);
        }

        return $raw;
    }

    private function applicationStage(string $value): string
    {
        $normalized = $this->normalizeName($value);
        if ($normalized === 'si') {
            return 'contratado';
        }
        if (str_contains($normalized, 'renuncia')) {
            return 'retirado';
        }
        if ($normalized === 'no' || str_contains($normalized, 'vencimiento')) {
            return 'no_seleccionado';
        }

        return 'entrevista_psicolaboral';
    }

    private function reconsideration(string $value): ?string
    {
        $normalized = $this->normalizeName($value);
        if ($normalized === '') {
            return null;
        }
        if ($normalized === 'si') {
            return 'si';
        }
        if ($normalized === 'no' || str_contains($normalized, 'fuera')) {
            return 'no';
        }

        return 'condicional';
    }

    private function extractRut(string $value): string
    {
        if (preg_match('/\b(?:rut|run)\s*:?\s*([0-9.]{7,12}-?[0-9kK])\b/iu', $value, $match)) {
            return $this->normalizeRut($match[1]);
        }

        return '';
    }

    private function normalizeRut(?string $value): string
    {
        return mb_strtoupper(preg_replace('/[^0-9kK]/', '', (string) $value));
    }

    private function normalizeName(?string $value): string
    {
        $decoded = trim((string) $value);
        for ($attempt = 0; $attempt < 3 && str_contains($decoded, '%'); $attempt++) {
            $decoded = rawurldecode($decoded);
        }

        return Str::of($decoded)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    private function contains(mixed $value, string $needle): bool
    {
        return str_contains($this->normalizeName((string) $value), $this->normalizeName($needle));
    }
}
