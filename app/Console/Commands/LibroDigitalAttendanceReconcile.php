<?php

namespace App\Console\Commands;

use App\Contracts\LibroDigital\SigeIntegrationGateway;
use App\Models\LibroDigital\Book;
use App\Models\User;
use App\Services\LibroDigital\FeatureFlagService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

class LibroDigitalAttendanceReconcile extends Command
{
    protected $signature = 'lcd:attendance:reconcile
        {--year= : Año calendario o ID académico}
        {--month= : Mes 1-12}
        {--school= : ID interno del establecimiento}
        {--book= : ID público o interno; obligatorio al ejecutar}
        {--file= : Evidencia JSON exportada por un flujo oficial/manual aprobado}
        {--actor= : ID del usuario autorizado}
        {--execute : Registra una conciliación manual}
        {--approval-reference= : Referencia de revisión institucional}
        {--json}';

    protected $description = 'Compara cierres LCD con evidencia externa; no declara envío oficial ni modifica asistencia.';

    public function handle(SigeIntegrationGateway $gateway, FeatureFlagService $features): int
    {
        $month = (int) $this->option('month');
        $yearValue = trim((string) $this->option('year'));
        $year = DB::table('academic_years')->where(fn ($query) => $query
            ->where('id', (int) $yearValue)->orWhere('year', (int) $yearValue))->first();
        if (! $year || $month < 1 || $month > 12) {
            $this->error('Debes indicar un año académico existente y un mes entre 1 y 12.');

            return self::INVALID;
        }

        $books = Book::query()->where('academic_year_id', $year->id)
            ->when($this->option('school'), fn ($query, $id) => $query->where('school_id', (int) $id))
            ->when($this->option('book'), function ($query): void {
                $identifier = (string) $this->option('book');
                $query->where(fn ($bookQuery) => ctype_digit($identifier)
                    ? $bookQuery->whereKey((int) $identifier)->orWhere('public_id', $identifier)
                    : $bookQuery->where('public_id', $identifier));
            })
            ->withCount(['monthlyAttendanceClosures' => fn ($query) => $query->where('month', $month)])
            ->orderBy('id')->get();
        $payload = [
            'dry_run' => ! $this->option('execute'),
            'academic_year_id' => $year->id,
            'month' => $month,
            'driver' => $gateway->driver(),
            'books' => $books->map(fn (Book $book) => [
                'public_id' => $book->public_id,
                'school_id' => $book->school_id,
                'monthly_closures' => $book->monthly_attendance_closures_count,
            ])->all(),
            'official_submission_performed' => false,
        ];
        if (! $this->option('execute')) {
            $this->line($this->option('json')
                ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : "Libros candidatos: {$books->count()}. No se registró ni envió nada.");

            return self::SUCCESS;
        }
        if ($books->count() !== 1 || $gateway->driver() !== 'manual') {
            $this->error('La ejecución exige un único --book y el driver manual aprobado.');

            return self::FAILURE;
        }
        $book = $books->first();
        if (! $features->enabled('lcd_sige_reconciliation_enabled', $book->school_id)) {
            $this->error('La conciliación SIGE está deshabilitada para el establecimiento.');

            return self::FAILURE;
        }
        $actor = User::query()->find((int) $this->option('actor'));
        if (! $actor || ! $actor->hasPermission('libro_digital.closures.manage') || blank($this->option('approval-reference'))) {
            $this->error('Se requiere actor autorizado y --approval-reference.');

            return self::INVALID;
        }
        $file = realpath((string) $this->option('file'));
        if ($file === false || ! is_file($file) || ! is_readable($file)) {
            $this->error('La evidencia JSON indicada no existe o no se puede leer.');

            return self::INVALID;
        }
        try {
            $input = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('La evidencia JSON no es válida: '.$exception->getMessage());

            return self::INVALID;
        }
        if (! is_array($input)) {
            $this->error('La evidencia debe ser un objeto JSON.');

            return self::INVALID;
        }
        $input['month'] = $month;
        $input['evidence_reference'] = hash('sha256', (string) $this->option('approval-reference').'|'.hash_file('sha256', $file));
        $result = $gateway->reconcile($book, $input, $actor);
        $differences = count($result['differences'] ?? []);
        $this->line($this->option('json')
            ? json_encode(['recorded' => true, 'official' => false, 'difference_count' => $differences], JSON_PRETTY_PRINT)
            : "<info>Conciliación manual registrada: {$differences} diferencias; no se declaró envío oficial.</info>");

        return $differences === 0 ? self::SUCCESS : self::FAILURE;
    }
}
