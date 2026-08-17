<?php

namespace App\Console\Commands;

use App\Models\LibroDigital\Book;
use App\Models\LibroDigital\EdeVersion;
use App\Models\LibroDigital\School;
use App\Models\User;
use App\Services\LibroDigital\EdeExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LibroDigitalEdeExportCommand extends Command
{
    protected $signature = 'lcd:ede:export
        {--school= : ID interno del establecimiento}
        {--year= : Año calendario o ID académico}
        {--book= : ID público o interno del libro; obligatorio para ejecutar}
        {--version= : ID público o interno de versión EDE activa}
        {--actor= : ID del usuario autorizado}
        {--execute : Crea la solicitud y encola la proyección}
        {--approval-reference= : Evidencia de autorización}
        {--json}';

    protected $description = 'Lista candidatos o encola una exportación EDE versionada; no ejecuta validación ni descarga automáticamente.';

    public function handle(EdeExportService $service): int
    {
        $school = School::query()->find((int) $this->option('school'));
        $yearValue = trim((string) $this->option('year'));
        $year = DB::table('academic_years')->where(fn ($query) => $query
            ->where('id', (int) $yearValue)->orWhere('year', (int) $yearValue))->first();
        if (! $school || ! $year) {
            $this->error('Debes indicar un establecimiento y año académico existentes.');

            return self::INVALID;
        }
        $books = Book::query()->where('school_id', $school->id)->where('academic_year_id', $year->id)
            ->when($this->option('book'), function ($query): void {
                $identifier = (string) $this->option('book');
                $query->where(fn ($bookQuery) => ctype_digit($identifier)
                    ? $bookQuery->whereKey((int) $identifier)->orWhere('public_id', $identifier)
                    : $bookQuery->where('public_id', $identifier));
            })->orderBy('id')->get();
        $payload = [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'candidate_count' => $books->count(),
            'candidates' => $books->map(fn (Book $book) => [
                'public_id' => $book->public_id,
                'code' => $book->code,
                'status' => $book->status->value,
                'revision' => $book->revision,
            ])->all(),
        ];
        if (! $this->option('execute')) {
            $payload['dry_run'] = true;
            $this->line($this->option('json')
                ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : "Candidatos EDE: {$books->count()}. Usa --execute con un único --book, --version, --actor y aprobación.");

            return self::SUCCESS;
        }
        if ($books->count() !== 1 || blank($this->option('version')) || blank($this->option('approval-reference'))) {
            $this->error('La ejecución exige un único --book, --version y --approval-reference.');

            return self::INVALID;
        }
        $versionIdentifier = (string) $this->option('version');
        $version = EdeVersion::query()->where('status', 'active')->where(function ($query) use ($versionIdentifier): void {
            if (ctype_digit($versionIdentifier)) {
                $query->whereKey((int) $versionIdentifier)->orWhere('public_id', $versionIdentifier);
            } else {
                $query->where('public_id', $versionIdentifier)->orWhere('version', $versionIdentifier);
            }
        })->first();
        $actor = User::query()->find((int) $this->option('actor'));
        if (! $version || ! $actor || ! $actor->hasPermission('libro_digital.ede.export')) {
            $this->error('Versión activa o actor autorizado inválidos.');

            return self::INVALID;
        }

        $export = $service->request($school, $books->first(), $version, $actor, [
            'approval_reference_hash' => hash('sha256', (string) $this->option('approval-reference')),
            'requested_via' => 'artisan',
        ]);
        $this->line($this->option('json')
            ? json_encode(['public_id' => $export->public_id, 'status' => $export->status->value], JSON_PRETTY_PRINT)
            : '<info>Exportación '.$export->public_id.' encolada en estado '.$export->status->value.'.</info>');

        return self::SUCCESS;
    }
}
