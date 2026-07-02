<?php

namespace App\Services\Pme;

use App\Models\Pme\PmeStudentSepClassification;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class PmeStudentSepImportService
{
    /**
     * @return array{created:int,updated:int,rows:int}
     */
    public function import(UploadedFile $file, int $academicYearId, User $actor, ?string $source = null): array
    {
        $rows = $this->parseSpreadsheet($file);
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $academicYearId, $actor, $source, &$created, &$updated) {
            foreach ($rows as $row) {
                $rut = trim((string) ($row['rut'] ?? ''));
                $classification = $this->normalizeClassification((string) ($row['clasificacion'] ?? $row['classification'] ?? 'pendiente_validacion'));
                if ($rut === '' || $classification === null) {
                    continue;
                }

                $student = StudentProfile::query()->where('rut', $rut)->first();
                if (!$student) {
                    continue;
                }

                $enrollment = StudentEnrollment::query()
                    ->where('student_profile_id', $student->id)
                    ->where('academic_year_id', $academicYearId)
                    ->latest('id')
                    ->first();

                $record = PmeStudentSepClassification::query()->withTrashed()->firstOrNew([
                    'student_profile_id' => $student->id,
                    'academic_year_id' => $academicYearId,
                ]);

                $exists = $record->exists;
                $record->fill([
                    'course_section_id' => $enrollment?->course_section_id,
                    'classification' => $classification,
                    'loaded_at' => now()->toDateString(),
                    'source' => $source ?: 'Carga masiva',
                    'state' => 'vigente',
                    'observations' => $row['observaciones'] ?? null,
                    'created_by' => $record->created_by ?: $actor->id,
                    'updated_by' => $actor->id,
                    'deleted_at' => null,
                ]);
                $record->save();

                $exists ? $updated++ : $created++;
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'rows' => $rows->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    private function parseSpreadsheet(UploadedFile $file): Collection
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        return match ($extension) {
            'csv', 'txt' => $this->parseCsv($file->getRealPath()),
            'xlsx' => $this->parseXlsx($file->getRealPath()),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    private function parseCsv(string $path): Collection
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return collect();
        }

        $headers = [];
        $rows = collect();
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if ($headers === []) {
                $headers = $this->normalizeHeaders($data);
                continue;
            }

            if (count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows->push($this->combineHeaders($headers, $data));
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    private function parseXlsx(string $path): Collection
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return collect();
        }

        $sharedStrings = [];
        $sharedContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedContent !== false) {
            $sharedXml = simplexml_load_string($sharedContent);
            if ($sharedXml) {
                foreach ($sharedXml->si as $item) {
                    $sharedStrings[] = trim((string) implode('', (array) $item->t));
                }
            }
        }

        $sheetContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetContent === false) {
            return collect();
        }

        $sheetXml = simplexml_load_string($sheetContent);
        if (!$sheetXml) {
            return collect();
        }

        $rows = collect();
        $headers = [];
        foreach ($sheetXml->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $type = (string) $cell['t'];
                $value = (string) $cell->v;
                $values[] = $type === 's' ? ($sharedStrings[(int) $value] ?? null) : $value;
            }

            if ($headers === []) {
                $headers = $this->normalizeHeaders($values);
                continue;
            }

            $rows->push($this->combineHeaders($headers, $values));
        }

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $values
     * @return array<int, string>
     */
    private function normalizeHeaders(array $values): array
    {
        return collect($values)->map(fn ($value) => trim((string) mb_strtolower((string) $value, 'UTF-8')))->all();
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $values
     * @return array<string, string|null>
     */
    private function combineHeaders(array $headers, array $values): array
    {
        $row = [];
        foreach ($headers as $index => $header) {
            $row[$header] = isset($values[$index]) ? trim((string) $values[$index]) : null;
        }

        return $row;
    }

    private function normalizeClassification(string $value): ?string
    {
        $normalized = str_replace(['á', 'é', 'í', 'ó', 'ú', ' '], ['a', 'e', 'i', 'o', 'u', '_'], mb_strtolower(trim($value), 'UTF-8'));
        return match ($normalized) {
            'prioritaria', 'prioritario' => 'prioritaria',
            'preferente' => 'preferente',
            'sin_clasificacion_sep', 'sin_clasificacion' => 'sin_clasificacion_sep',
            'pendiente_validacion', 'pendiente' => 'pendiente_validacion',
            default => null,
        };
    }
}
