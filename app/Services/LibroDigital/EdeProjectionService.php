<?php

namespace App\Services\LibroDigital;

use App\Exceptions\LibroDigital\LibroDigitalException;
use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\EdeVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EdeProjectionService
{
    public function __construct(private readonly CanonicalJson $canonical) {}

    /** @return array{records: array<string, array<int, array<string, mixed>>>, manifest: array<string, mixed>} */
    public function project(Book $book, EdeVersion $version): array
    {
        $mappings = $version->mappings()->where('active', true)->orderBy('id')->get();
        if ($mappings->isEmpty()) {
            throw new LibroDigitalException('La versión EDE no tiene mapeos activos.', 'COMPLIANCE_BLOCKER_EDE_MAPPINGS', 409);
        }

        $source = $this->sourceDataset($book);
        $records = [];
        $errors = [];

        foreach ($mappings->groupBy('source_entity') as $entity => $entityMappings) {
            foreach ($source[$entity] ?? [] as $index => $sourceRow) {
                $targetRows = [];
                foreach ($entityMappings as $mapping) {
                    $value = filled($mapping->source_field) ? Arr::get($sourceRow, $mapping->source_field) : null;
                    if ($value === null && $mapping->default_value !== null) {
                        $value = $mapping->default_value;
                    }
                    if ($mapping->required && ($value === null || $value === '')) {
                        $errors[] = [
                            'mapping' => $mapping->code,
                            'source_entity' => $entity,
                            'source_index' => $index,
                            'reason' => 'required_value_missing',
                        ];

                        continue;
                    }
                    $targetRows[$mapping->target_record_type][$mapping->target_field] = $this->transform($value, $mapping->transform_definition ?? []);
                }

                foreach ($targetRows as $type => $row) {
                    $records[$type][] = $row;
                }
            }
        }

        if ($errors !== []) {
            throw new LibroDigitalException(
                'La proyección EDE tiene campos obligatorios sin resolver.',
                'LCD_EDE_PROJECTION_INCOMPLETE',
                422,
                array_slice($errors, 0, 100),
            );
        }

        ksort($records);
        $recordCounts = collect($records)->map(fn (array $rows) => count($rows))->all();
        $manifest = [
            'ede_version' => ['code' => $version->code, 'version' => $version->version, 'source_hash' => $version->source_hash, 'schema_hash' => $version->schema_hash],
            'book' => ['public_id' => $book->public_id, 'revision' => $book->revision, 'lock_version' => $book->lock_version],
            'mapping_hashes' => $mappings->pluck('mapping_hash', 'code')->all(),
            'record_counts' => $recordCounts,
            'generated_at' => now('UTC')->toIso8601String(),
        ];
        $manifest['records_hash'] = $this->canonical->hash($records);
        $manifest['manifest_hash'] = $this->canonical->hash($manifest);

        return ['records' => $records, 'manifest' => $manifest];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function sourceDataset(Book $book): array
    {
        $enrollments = DB::table('lcd_enrollment_links')->where('book_id', $book->id)->orderBy('list_number')->get()->map(fn ($row) => (array) $row)->all();
        $sessions = DB::table('lcd_class_sessions')->where('book_id', $book->id)->orderBy('session_date')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $sessionIds = collect($sessions)->pluck('id');

        return [
            'book' => [[...$book->attributesToArray(), 'school' => $book->school?->attributesToArray(), 'academic_year' => $book->academicYear?->attributesToArray()]],
            'enrollment' => $enrollments,
            'session' => $sessions,
            'session_attendance' => DB::table('lcd_session_attendance')->whereIn('class_session_id', $sessionIds)->orderBy('class_session_id')->orderBy('student_profile_id')->get()->map(fn ($row) => (array) $row)->all(),
            'assessment' => DB::table('lcd_assessments')->where('book_id', $book->id)->orderBy('assessment_date')->get()->map(fn ($row) => (array) $row)->all(),
            'assessment_result' => DB::table('lcd_student_results')->whereIn('assessment_id', DB::table('lcd_assessments')->where('book_id', $book->id)->select('id'))->orderBy('assessment_id')->orderBy('student_profile_id')->get()->map(fn ($row) => (array) $row)->all(),
            'coexistence' => DB::table('lcd_coexistence_entries')->where('book_id', $book->id)->orderBy('happened_at')->get()->map(fn ($row) => (array) $row)->all(),
            'pie_support' => DB::table('lcd_pie_support_records')->where('book_id', $book->id)->orderBy('recorded_at')->get()->map(fn ($row) => (array) $row)->all(),
            'withdrawal' => DB::table('porter_student_withdrawals')->whereIn('student_profile_id', collect($enrollments)->pluck('student_profile_id'))->orderBy('withdrawn_at')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    /** @param array<string, mixed> $definition */
    private function transform(mixed $value, array $definition): mixed
    {
        return match ($definition['type'] ?? 'direct') {
            'direct' => $value,
            'string' => $value === null ? null : (string) $value,
            'integer' => $value === null ? null : (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'date' => $value === null ? null : Carbon::parse($value)->format((string) ($definition['format'] ?? 'Y-m-d')),
            'enum_map' => ($definition['values'] ?? [])[(string) $value] ?? ($definition['fallback'] ?? null),
            default => throw new LibroDigitalException('Transformación EDE no permitida.', 'LCD_EDE_TRANSFORM_NOT_ALLOWED', 422),
        };
    }
}
