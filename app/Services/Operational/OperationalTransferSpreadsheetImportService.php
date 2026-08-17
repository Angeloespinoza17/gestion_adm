<?php

namespace App\Services\Operational;

use App\Models\Operational\OperationalTransferProvider;
use App\Models\Operational\OperationalTransferRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class OperationalTransferSpreadsheetImportService
{
    public function __construct(
        private readonly OperationalTransferGenericRequesterService $genericRequester,
    ) {}

    public function preview(UploadedFile $file, User $user): array
    {
        $rawRows = $this->readFirstWorksheet($file->getRealPath());
        if (count($rawRows) < 2) {
            throw ValidationException::withMessages(['file' => 'La planilla no contiene filas para importar.']);
        }

        $headers = collect(array_shift($rawRows))
            ->mapWithKeys(fn ($value, $column) => [$this->normalizeHeader((string) $value) => $column]);
        foreach (['dia', 'pasajeros', 'salida', 'destino'] as $required) {
            if (! $headers->has($required)) {
                throw ValidationException::withMessages(['file' => 'Falta la columna obligatoria '.mb_strtoupper($required).'.']);
            }
        }

        $rows = [];
        foreach ($rawRows as $offset => $source) {
            $excelRow = $offset + 2;
            if (collect($source)->filter(fn ($value) => trim((string) $value) !== '')->isEmpty()) {
                continue;
            }
            $warnings = [];
            $date = $this->dateValue($this->column($source, $headers, 'dia'), $warnings);
            $departure = $this->timeValue($this->column($source, $headers, 'salida'), $warnings, 'salida');
            $return = $this->timeValue($this->column($source, $headers, 'retorno'), $warnings, 'retorno');
            $passengers = $this->integerValue($this->column($source, $headers, 'pasajeros'));
            $destination = trim((string) $this->column($source, $headers, 'destino'));
            $requester = trim((string) $this->column(
                $source,
                $headers,
                'solicitante',
                'nombre_solicitante',
                'docente',
                'responsable',
            ));
            if (! $date) {
                $warnings[] = 'La fecha no es válida y la fila no podrá importarse.';
            }
            if (! $departure) {
                $warnings[] = 'La hora de salida no es válida y la fila no podrá importarse.';
            }
            if ($passengers < 1) {
                $warnings[] = 'La cantidad de pasajeros debe ser mayor a cero.';
            }
            if ($destination === '') {
                $warnings[] = 'El destino está vacío.';
            }
            if ($requester === '') {
                $warnings[] = 'Sin datos de solicitante; se asignará el perfil institucional "Solicitante genérico".';
            }

            $status = $this->statusValues((string) $this->column($source, $headers, 'estado'));
            $rows[] = [
                'row' => $excelRow,
                'can_import' => $date !== null && $departure !== null && $passengers > 0 && $destination !== '',
                'warnings' => array_values(array_unique($warnings)),
                'transport_date' => $date,
                'transport_mode' => $this->transportMode((string) $this->column($source, $headers, 'modalidad')),
                'passenger_count' => $passengers,
                'departure_time' => $departure,
                'return_time' => $return,
                'destination' => $destination,
                'requester' => $requester,
                'reduced_mobility' => $this->booleanValue($this->column($source, $headers, 'movi_reducidad', 'movilidad_reducida')),
                'final_cost' => $this->moneyValue($this->column($source, $headers, 'costo')),
                'provider' => trim((string) $this->column($source, $headers, 'proveedor')),
                'approval_status' => 'importado_historico',
                'service_status' => $status['service_status'],
                'dte_status' => $this->dteStatus((string) $this->column($source, $headers, 'dte')),
                'payment_status' => $this->paymentStatus((string) $this->column($source, $headers, 'pago')),
                'legacy_status' => trim((string) $this->column($source, $headers, 'estado')),
            ];
        }

        $token = (string) Str::uuid();
        Storage::disk('local')->put('operational-transfer-imports/'.$token.'.json', json_encode([
            'user_id' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'created_at' => now()->toIso8601String(),
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return [
            'token' => $token,
            'file_name' => $file->getClientOriginalName(),
            'total_rows' => count($rows),
            'importable_rows' => collect($rows)->where('can_import', true)->count(),
            'rows' => $rows,
        ];
    }

    public function commit(string $token, User $user, ?array $selectedRows = null): array
    {
        if (! preg_match('/^[0-9a-f-]{36}$/i', $token)) {
            throw ValidationException::withMessages(['token' => 'La vista previa no es válida.']);
        }
        $path = 'operational-transfer-imports/'.$token.'.json';
        if (! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['token' => 'La vista previa expiró o ya fue importada.']);
        }
        $preview = json_decode(Storage::disk('local')->get($path), true, flags: JSON_THROW_ON_ERROR);
        if ((int) ($preview['user_id'] ?? 0) !== (int) $user->id && ! $user->isSuperAdmin()) {
            abort(403);
        }

        $selected = $selectedRows === null ? null : collect($selectedRows)->map(fn ($row) => (int) $row)->all();
        $rows = collect($preview['rows'] ?? [])->filter(function (array $row) use ($selected): bool {
            return (bool) ($row['can_import'] ?? false)
                && ($selected === null || in_array((int) $row['row'], $selected, true));
        });
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['rows' => 'No hay filas válidas seleccionadas para importar.']);
        }

        $result = DB::transaction(function () use ($rows, $user, $preview): array {
            $created = 0;
            $skipped = 0;
            $ids = [];
            foreach ($rows as $row) {
                $folio = $this->historicalFolio($row);
                if (OperationalTransferRequest::query()->where('folio', $folio)->exists()) {
                    $skipped++;

                    continue;
                }
                $passengerCount = (int) $row['passenger_count'];
                $requester = $this->genericRequester->resolve($row['requester'] ?? null, $user);
                $isGenericRequester = $this->genericRequester->isGeneric($requester);
                $warnings = array_values(array_unique($row['warnings'] ?? []));
                if (! $isGenericRequester && trim((string) ($row['requester'] ?? '')) !== '') {
                    $requesterRole = 'Personal institucional';
                } else {
                    $requesterRole = 'Solicitante no identificado';
                }
                $visibleObservations = 'Importado desde '.$preview['file_name'].'. Estado original: '.($row['legacy_status'] ?: 'sin estado').'.';
                if ($warnings !== []) {
                    $visibleObservations .= ' Advertencias de importación: '.implode(' ', $warnings);
                }
                $transfer = OperationalTransferRequest::create([
                    'folio' => $folio,
                    'requester_staff_id' => $requester->id,
                    'requested_by_user_id' => $user->id,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'requester_name_snapshot' => $requester->full_name,
                    'requester_role_snapshot' => $requesterRole,
                    'requester_unit_snapshot' => 'Gestión Operativa',
                    'activity_type' => 'salida_pedagogica',
                    'activity_name' => 'Traslado histórico a '.$row['destination'],
                    'transport_date' => $row['transport_date'],
                    'departure_time' => $row['departure_time'],
                    'return_time' => $row['return_time'],
                    'origin' => 'Colegio Nuestra Señora del Carmen',
                    'destination' => $row['destination'],
                    'transport_mode' => $row['transport_mode'],
                    'student_count' => max(0, $passengerCount - 1),
                    'adult_count' => min(1, $passengerCount),
                    'passenger_count' => $passengerCount,
                    'reduced_mobility' => $row['reduced_mobility'],
                    'visible_observations' => $visibleObservations,
                    'approval_status' => 'importado_historico',
                    'service_status' => $row['service_status'],
                    'dte_status' => $row['dte_status'],
                    'payment_status' => $row['payment_status'],
                    'legacy_imported' => true,
                    'executed_at' => $row['service_status'] === 'ejecutado' ? $row['transport_date'].' 23:59:00' : null,
                    'confirmed_at' => in_array($row['service_status'], ['confirmado', 'ejecutado'], true) ? $row['transport_date'].' 00:00:00' : null,
                ]);

                $provider = null;
                if ($row['provider'] !== '') {
                    $provider = OperationalTransferProvider::query()->firstOrCreate(
                        ['name' => $row['provider']],
                        ['active' => true, 'created_by' => $user->id, 'updated_by' => $user->id],
                    );
                }
                if ($provider || $row['final_cost'] !== null) {
                    $transfer->operation()->create([
                        'provider_id' => $provider?->id,
                        'final_cost' => $row['final_cost'],
                        'administrative_notes' => 'Información recuperada desde la planilla histórica.',
                        'updated_by' => $user->id,
                    ]);
                }
                $transfer->logs()->create([
                    'user_id' => $user->id,
                    'action' => 'importada_historica',
                    'old_status' => null,
                    'new_status' => 'importado_historico',
                    'details' => [
                        'source_file' => $preview['file_name'],
                        'source_row' => $row['row'],
                        'imported_by_user_id' => $user->id,
                        'requester_staff_id' => $requester->id,
                        'requester_strategy' => $isGenericRequester ? 'generic' : 'matched_by_exact_name',
                        'warnings' => $warnings,
                    ],
                ]);
                $ids[] = $transfer->id;
                $created++;
            }

            return compact('created', 'skipped', 'ids');
        });

        Storage::disk('local')->delete($path);

        return $result;
    }

    private function readFirstWorksheet(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages(['file' => 'El servidor no tiene habilitada la extensión ZIP requerida para leer XLSX.']);
        }
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

            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                throw ValidationException::withMessages(['file' => 'No se encontró la primera hoja del XLSX.']);
            }
            $sheet = simplexml_load_string($sheetXml);
            $rows = [];
            foreach ($sheet?->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
                $values = [];
                foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    $reference = (string) $cell['r'];
                    preg_match('/^[A-Z]+/', $reference, $match);
                    $column = $match[0] ?? $reference;
                    $type = (string) $cell['t'];
                    $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
                    $value = isset($valueNodes[0]) ? (string) $valueNodes[0] : '';
                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $textNodes = $cell->xpath('.//*[local-name()="t"]') ?: [];
                        $value = implode('', array_map(fn ($part) => (string) $part, $textNodes));
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

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
    }

    private function column(array $row, $headers, string ...$names): mixed
    {
        foreach ($names as $name) {
            $column = $headers->get($name);
            if ($column !== null) {
                return $row[$column] ?? null;
            }
        }

        return null;
    }

    private function dateValue(mixed $value, array &$warnings): ?string
    {
        if (is_numeric($value) && (float) $value > 1000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) floor((float) $value))->format('Y-m-d');
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2})([\/-])(\d{1,2})\2(0206)$/', $raw, $match)) {
            $corrected = $match[1].$match[2].$match[3].$match[2].'2026';
            $warnings[] = 'La fecha "'.$raw.'" se corrigió automáticamente a "'.$corrected.'"; revisar el dato histórico.';
            $raw = $corrected;
        }
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            $date = CarbonImmutable::createFromFormat('!'.$format, $raw);
            if ($date && $date->format($format) === $raw) {
                if ($date->year < 2000) {
                    $warnings[] = 'La fecha "'.$raw.'" parece contener un año erróneo.';

                    return null;
                }

                return $date->format('Y-m-d');
            }
        }
        $warnings[] = 'No se pudo interpretar la fecha "'.$raw.'".';

        return null;
    }

    private function timeValue(mixed $value, array &$warnings, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value >= 0 && (float) $value < 1) {
            $minutes = (int) round((float) $value * 1440);

            return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
        }
        $raw = str_replace('.', ':', trim((string) $value));
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $match)) {
            $hour = (int) $match[1];
            $minute = (int) $match[2];
            if ($hour < 24 && $minute < 60) {
                return sprintf('%02d:%02d', $hour, $minute);
            }
        }
        $warnings[] = 'No se pudo interpretar la hora de '.$field.' "'.$raw.'".';

        return null;
    }

    private function transportMode(string $value): string
    {
        $normalized = Str::of($value)->ascii()->lower()->toString();
        if (str_contains($normalized, 'solo ida')) {
            return 'solo_ida';
        }
        if (str_contains($normalized, 'solo vuelta')) {
            return 'solo_vuelta';
        }

        return 'ida_vuelta';
    }

    private function booleanValue(mixed $value): bool
    {
        return in_array(Str::of((string) $value)->ascii()->lower()->trim()->toString(), ['si', 'yes', '1', 'true'], true);
    }

    private function moneyValue(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return max(0, (int) round((float) $value));
        }
        $digits = preg_replace('/[^0-9-]/', '', (string) $value);

        return $digits === '' ? null : max(0, (int) $digits);
    }

    private function integerValue(mixed $value): int
    {
        if (is_numeric($value)) {
            return max(0, (int) round((float) $value));
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? 0 : max(0, (int) $digits);
    }

    private function statusValues(string $value): array
    {
        $status = Str::of($value)->ascii()->lower()->trim()->toString();
        if (str_contains($status, 'ejecut')) {
            return ['service_status' => 'ejecutado'];
        }
        if (str_contains($status, 'agend') || str_contains($status, 'confirm')) {
            return ['service_status' => 'confirmado'];
        }
        if (str_contains($status, 'cancel')) {
            return ['service_status' => 'cancelado'];
        }

        return ['service_status' => 'sin_gestion'];
    }

    private function dteStatus(string $value): string
    {
        $status = Str::of($value)->ascii()->lower()->trim()->toString();
        if ($status === '' || in_array($status, ['no', 'n/a', 'no aplica'], true)) {
            return 'no_aplica';
        }
        if (str_contains($status, 'recib') || str_contains($status, 'emit') || preg_match('/\d/', $status)) {
            return 'recibido';
        }

        return 'pendiente';
    }

    private function paymentStatus(string $value): string
    {
        $status = Str::of($value)->ascii()->lower()->trim()->toString();
        if (str_contains($status, 'pagad')) {
            return 'pagado';
        }
        if (str_contains($status, 'program')) {
            return 'programado';
        }
        if ($status !== '' && ! in_array($status, ['no', 'n/a', 'no aplica'], true)) {
            return 'solicitado';
        }

        return 'no_iniciado';
    }

    private function historicalFolio(array $row): string
    {
        $year = substr((string) $row['transport_date'], 0, 4);
        $signature = implode('|', [$row['transport_date'], $row['departure_time'], $row['destination'], $row['passenger_count'], $row['row']]);

        return 'TR-HIST-'.$year.'-'.strtoupper(substr(hash('sha256', $signature), 0, 10));
    }
}
