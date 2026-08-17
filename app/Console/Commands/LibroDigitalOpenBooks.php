<?php

namespace App\Console\Commands;

use App\Models\LibroDigital\Book;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\CompliancePreflightService;
use App\Services\LibroDigital\WorkflowStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LibroDigitalOpenBooks extends Command
{
    protected $signature = 'lcd:open-books
        {--year= : Año calendario o ID académico}
        {--school= : ID interno del establecimiento}
        {--actor= : ID del usuario autorizado}
        {--execute : Abre libros en pending_preflight que superen todos los controles}
        {--approval-reference= : Acta o ticket institucional}
        {--json}';

    protected $description = 'Previsualiza o abre libros preparados, sin crear libros ni omitir preflight.';

    public function handle(
        CompliancePreflightService $preflight,
        WorkflowStateMachine $workflows,
        AuditEventWriter $audit,
    ): int {
        $yearValue = trim((string) $this->option('year'));
        $year = DB::table('academic_years')->where(fn ($query) => $query
            ->where('id', (int) $yearValue)->orWhere('year', (int) $yearValue))->first();
        if (! $year) {
            $this->error('Debes indicar un año académico existente.');

            return self::INVALID;
        }
        $books = Book::query()->where('academic_year_id', $year->id)
            ->when($this->option('school'), fn ($query, $id) => $query->where('school_id', (int) $id))
            ->where('status', 'pending_preflight')->orderBy('school_id')->orderBy('id')->get();
        $evaluated = $books->map(function (Book $book) use ($preflight): array {
            $global = $preflight->run($book->school_id);
            $roster = $book->teachingGroups()->whereHas('rosterSnapshots', fn ($query) => $query->where('status', 'sealed'))->exists();
            $teacher = $book->teachingGroups()->whereHas('teacherAssignments', fn ($query) => $query->where('active', true))->exists();

            return [
                'book' => $book,
                'public_id' => $book->public_id,
                'code' => $book->code,
                'school_id' => $book->school_id,
                'ready' => (bool) $global['ready'] && $roster && $teacher,
                'blocker_count' => count($global['blockers']) + (int) ! $roster + (int) ! $teacher,
            ];
        });
        if (! $this->option('execute')) {
            $payload = ['dry_run' => true, 'year_id' => $year->id, 'books' => $evaluated->map(fn ($row) => collect($row)->except('book')->all())->all()];
            $this->line($this->option('json')
                ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : "Libros preparados: {$books->count()}; listos: ".$evaluated->where('ready', true)->count().'. No se modificó nada.');

            return $evaluated->contains(fn ($row) => ! $row['ready']) ? self::FAILURE : self::SUCCESS;
        }

        $actor = User::query()->find((int) $this->option('actor'));
        if (! $actor || ! $actor->hasPermission('libro_digital.books.manage') || blank($this->option('approval-reference'))) {
            $this->error('La ejecución exige actor autorizado y --approval-reference.');

            return self::INVALID;
        }
        if ($evaluated->contains(fn ($row) => ! $row['ready'])) {
            $this->error('Hay libros con bloqueadores; no se abrió ninguno.');

            return self::FAILURE;
        }

        $correlationId = (string) Str::ulid();
        foreach ($evaluated as $row) {
            /** @var Book $book */
            $book = $row['book'];
            DB::transaction(function () use ($book, $actor, $workflows): void {
                $locked = Book::query()->lockForUpdate()->findOrFail($book->id);
                $workflows->assertCan('book', $locked->status->value, 'open');
                $locked->forceFill([
                    'status' => 'open',
                    'opened_at' => now('UTC'),
                    'opened_by' => $actor->id,
                    'revision' => ((int) $locked->revision) + 1,
                    'lock_version' => ((int) $locked->lock_version) + 1,
                    'updated_by' => $actor->id,
                ])->save();
            }, 3);
            $fresh = $book->fresh();
            $audit->write(
                'lcd.book.opened_by_command',
                'open',
                $fresh,
                actor: $actor,
                schoolId: $fresh->school_id,
                academicYearId: $fresh->academic_year_id,
                before: ['status' => 'pending_preflight'],
                after: ['status' => 'open', 'approval_reference_hash' => hash('sha256', (string) $this->option('approval-reference'))],
                entityRevision: $fresh->revision,
                correlationId: $correlationId,
            );
        }

        $this->info('Libros abiertos: '.$evaluated->count().'. Correlation ID: '.$correlationId);

        return self::SUCCESS;
    }
}
