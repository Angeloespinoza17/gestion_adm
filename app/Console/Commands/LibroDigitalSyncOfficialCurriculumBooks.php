<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\LibroDigital\School;
use App\Models\User;
use App\Services\LibroDigital\OfficialCurriculumSyncService;
use Illuminate\Console\Command;
use Throwable;

class LibroDigitalSyncOfficialCurriculumBooks extends Command
{
    protected $signature = 'lcd:curriculum:sync-official-books
        {--school= : ID, public_id o RBD del establecimiento}
        {--year= : ID o año académico}
        {--actor= : ID del usuario autorizado}
        {--execute : Activa la oferta, prepara libros y abre solo los que superen preflight}
        {--approval-reference= : Acta, ticket o solicitud institucional}
        {--backup-reference= : Respaldo previo obligatorio si se ejecuta en producción}
        {--json}';

    protected $description = 'Sincroniza la oferta oficial confirmada desde Currículum Nacional y prepara sus libros de forma idempotente.';

    public function handle(OfficialCurriculumSyncService $sync): int
    {
        try {
            $school = $this->school((string) $this->option('school'));
            $year = $this->year($school, (string) $this->option('year'));

            if (! $this->option('execute')) {
                $this->output($sync->preview($school, $year));

                return self::SUCCESS;
            }

            $actor = User::query()->find((int) $this->option('actor'));
            $approval = trim((string) $this->option('approval-reference'));
            if (! $actor
                || ! $actor->hasPermission('libro_digital.subject_catalog.manage')
                || ! $actor->hasPermission('libro_digital.books.manage')
                || $approval === '') {
                $this->components->error('La ejecución exige actor con permisos de catálogo/libros y --approval-reference.');

                return self::INVALID;
            }
            if (app()->environment('production') && blank($this->option('backup-reference'))) {
                $this->components->error('En producción debes crear el respaldo previo y declarar --backup-reference.');

                return self::INVALID;
            }

            $result = $sync->execute($school, $year, $actor, $approval);
            $this->output($result);
            if ((int) ($result['books_blocked'] ?? 0) > 0) {
                $this->components->warn('La oferta y los libros quedaron preparados, pero faltan asignaciones docentes explícitas para abrir todos los libros.');

                return self::FAILURE;
            }

            $this->components->info('Oferta oficial sincronizada y libros abiertos con controles satisfechos.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function school(string $value): School
    {
        $value = trim($value);
        if ($value === '') {
            throw new \RuntimeException('Debes indicar un establecimiento LCD activo mediante --school.');
        }
        $school = School::query()->where('active', true)->where(function ($query) use ($value): void {
            if (ctype_digit($value)) {
                $query->orWhere('id', (int) $value);
            }
            $query->orWhere('public_id', $value)->orWhere('rbd', $value);
        })->first();
        if (! $school) {
            throw new \RuntimeException('Debes indicar un establecimiento LCD activo mediante --school.');
        }

        return $school;
    }

    private function year(School $school, string $value): AcademicYear
    {
        $value = trim($value);
        if (! ctype_digit($value)) {
            throw new \RuntimeException('Debes indicar un año académico activo del establecimiento mediante --year.');
        }
        $year = $school->academicYears()->wherePivot('active', true)->where(function ($query) use ($value): void {
            $query->where('academic_years.id', (int) $value)->orWhere('academic_years.year', (int) $value);
        })->first();
        if (! $year) {
            throw new \RuntimeException('Debes indicar un año académico activo del establecimiento mediante --year.');
        }

        return $year;
    }

    /** @param array<string, mixed> $payload */
    private function output(array $payload): void
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->line($json);
    }
}
