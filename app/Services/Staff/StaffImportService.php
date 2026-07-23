<?php

namespace App\Services\Staff;

use App\Models\Cargo;
use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use App\Models\Staff;
use App\Models\User;
use App\Support\DateInput;
use App\Support\Rut;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

class StaffImportService
{
    /** @var array<string, string> */
    private const HEADER_ALIASES = [
        'nombre_completo' => 'full_name',
        'nombre' => 'full_name',
        'rut' => 'rut',
        'fecha_nacimiento' => 'birth_date',
        'correo_institucional' => 'institutional_email',
        'correo_personal' => 'personal_email',
        'telefono' => 'phone',
        'direccion' => 'address',
        'region' => 'region',
        'comuna' => 'commune',
        'cargo' => 'cargo',
        'departamentos' => 'departments',
        'tipo_contrato' => 'contract_type',
        'fecha_ingreso' => 'start_date',
        'fecha_termino' => 'end_date',
        'estado' => 'status',
        'jornada' => 'workday',
        'horas_contrato' => 'contract_hours',
        'titulo_profesional' => 'professional_title',
        'especialidad' => 'specialty',
        'registro_profesional' => 'professional_registration',
        'notas_internas' => 'internal_notes',
        'activo' => 'active',
        'recibe_ordenes_mantencion' => 'can_receive_maintenance_orders',
        'rol_mantencion' => 'maintenance_role',
    ];

    /**
     * @return array{processed:int,created:int,updated:int,skipped:int,error_count:int,errors:array<int, array{row:int,field:string,message:string}>}
     */
    public function import(UploadedFile $file, bool $updateExisting, ?User $actor): array
    {
        $rows = $this->readRows($file);
        $catalogs = $this->catalogs();
        $result = [
            'processed' => count($rows),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'error_count' => 0,
            'errors' => [],
        ];
        $identities = [];

        foreach ($rows as $row) {
            $rowNumber = (int) $row['_row'];
            [$payload, $departmentIds, $mappingErrors] = $this->prepareRow($row, $catalogs);

            $errors = $mappingErrors;
            $validator = Validator::make($payload, $this->rules(), $this->messages());
            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    foreach ($messages as $message) {
                        $errors[] = ['field' => $field, 'message' => $message];
                    }
                }
            }

            [$staff, $identityErrors] = $this->resolveExistingStaff($payload, $updateExisting);
            $errors = [...$errors, ...$identityErrors];

            $identity = $payload['rut'] ?: ($payload['institutional_email'] ?: null);
            if ($identity && isset($identities[$identity])) {
                $errors[] = [
                    'field' => $payload['rut'] ? 'rut' : 'institutional_email',
                    'message' => "El registro está repetido en las filas {$identities[$identity]} y {$rowNumber}.",
                ];
            }

            if ($identity) {
                $identities[$identity] = $rowNumber;
            }

            $errors = [...$errors, ...$this->accountConflicts($payload, $staff)];

            if ($errors !== []) {
                $result['skipped']++;
                foreach ($errors as $error) {
                    $result['errors'][] = [
                        'row' => $rowNumber,
                        'field' => $error['field'],
                        'message' => $error['message'],
                    ];
                }
                continue;
            }

            try {
                DB::transaction(function () use (&$staff, $payload, $departmentIds, $actor) {
                    $isNew = ! $staff;
                    $staffPayload = $payload;
                    $staffPayload['region'] = $staffPayload['region_id']
                        ? Region::query()->find($staffPayload['region_id'])?->short_name
                        : null;
                    $staffPayload['commune'] = $staffPayload['commune_id']
                        ? Commune::query()->find($staffPayload['commune_id'])?->name
                        : null;
                    $staffPayload['updated_by'] = $actor?->id;

                    if ($isNew) {
                        $staffPayload['created_by'] = $actor?->id;
                        $staff = Staff::query()->create($staffPayload);
                    } else {
                        $staff->update($staffPayload);
                    }

                    $staff->departments()->sync($departmentIds);
                    $this->syncAccessAccount($staff->fresh());
                });

                if ($staff->wasRecentlyCreated) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $result['skipped']++;
                $result['errors'][] = [
                    'row' => $rowNumber,
                    'field' => 'row',
                    'message' => 'No fue posible guardar la fila. Revisa sus datos e inténtalo nuevamente.',
                ];
            }
        }

        $result['error_count'] = count($result['errors']);
        $result['errors'] = array_slice($result['errors'], 0, 200);

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        return in_array($extension, ['csv', 'txt'], true)
            ? $this->readCsv($file->getRealPath())
            : $this->readXlsx($file->getRealPath());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'No se pudo leer el archivo CSV.']);
        }

        $firstLine = (string) fgets($handle);
        rewind($handle);
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $headerMap = [];
        $rows = [];
        $rowNumber = 0;

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            if ($rowNumber === 1) {
                $headerMap = $this->headerMap($values);
                continue;
            }

            $row = $this->mapValues($values, $headerMap, $rowNumber);
            if ($this->hasContent($row)) {
                $rows[] = $row;
            }
        }

        fclose($handle);
        $this->ensureRows($headerMap, $rows);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'No se pudo abrir el archivo XLSX.']);
        }

        $sharedStrings = $this->sharedStrings($zip);
        $dateStyles = $this->dateStyles($zip);
        $sheets = $this->sheetPaths($zip);
        $selected = collect($sheets)->first(fn (array $sheet) => $this->lookupKey($sheet['name']) === 'funcionarios')
            ?? $sheets[0]
            ?? null;

        if (! $selected) {
            $zip->close();
            throw ValidationException::withMessages(['file' => 'El XLSX no contiene una hoja de datos.']);
        }

        $content = $zip->getFromName($selected['path']);
        if ($content === false) {
            $zip->close();
            throw ValidationException::withMessages(['file' => 'No se pudo leer la hoja Funcionarios.']);
        }

        $sheet = $this->parseSheet($content, $sharedStrings, $dateStyles);
        $zip->close();

        $headerRow = null;
        $headerMap = [];
        for ($rowNumber = 1; $rowNumber <= min($sheet['max_row'], 20); $rowNumber++) {
            $candidate = $this->headerMap($sheet['cells'][$rowNumber] ?? []);
            if (in_array('full_name', $candidate, true)) {
                $headerRow = $rowNumber;
                $headerMap = $candidate;
                break;
            }
        }

        if ($headerRow === null) {
            throw ValidationException::withMessages([
                'file' => 'No se encontró la columna nombre_completo en las primeras 20 filas.',
            ]);
        }

        $rows = [];
        for ($rowNumber = $headerRow + 1; $rowNumber <= $sheet['max_row']; $rowNumber++) {
            $row = $this->mapValues($sheet['cells'][$rowNumber] ?? [], $headerMap, $rowNumber);
            if ($this->hasContent($row)) {
                $rows[] = $row;
            }
        }

        $this->ensureRows($headerMap, $rows);

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<int, string>
     */
    private function headerMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $key = $this->lookupKey((string) $header);
            if (isset(self::HEADER_ALIASES[$key])) {
                $map[(int) $index] = self::HEADER_ALIASES[$key];
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<int, string>  $headerMap
     * @return array<string, mixed>
     */
    private function mapValues(array $values, array $headerMap, int $rowNumber): array
    {
        $row = ['_row' => $rowNumber];
        foreach ($headerMap as $index => $field) {
            $row[$field] = $values[$index] ?? null;
        }

        return $row;
    }

    /**
     * @param  array<int, string>  $headerMap
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function ensureRows(array $headerMap, array $rows): void
    {
        if (! in_array('full_name', $headerMap, true)) {
            throw ValidationException::withMessages(['file' => 'La columna nombre_completo es obligatoria.']);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'El archivo no contiene funcionarios para importar.']);
        }

        if (count($rows) > 2000) {
            throw ValidationException::withMessages(['file' => 'El archivo supera el máximo de 2.000 filas por importación.']);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasContent(array $row): bool
    {
        foreach ($row as $field => $value) {
            if ($field !== '_row' && $value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $catalogs
     * @return array{0:array<string, mixed>,1:array<int, int>,2:array<int, array{field:string,message:string}>}
     */
    private function prepareRow(array $row, array $catalogs): array
    {
        $errors = [];
        $cargoId = $this->catalogId($row['cargo'] ?? null, $catalogs['cargos'], 'cargo', $errors);
        $regionId = $this->catalogId($row['region'] ?? null, $catalogs['regions'], 'region', $errors);
        $communeId = $this->communeId($row['commune'] ?? null, $regionId, $catalogs['communes'], $errors);
        $departmentIds = $this->departmentIds($row['departments'] ?? null, $catalogs['departments'], $errors);
        $contractType = $this->optionValue($row['contract_type'] ?? null, $catalogs['contract_types'], 'contract_type', $errors);
        $workday = $this->optionValue($row['workday'] ?? null, $catalogs['workdays'], 'workday', $errors);
        $status = $this->optionValue($row['status'] ?? null, $catalogs['statuses'], 'status', $errors) ?: 'activo';
        $maintenanceRole = $this->optionValue($row['maintenance_role'] ?? null, $catalogs['maintenance_roles'], 'maintenance_role', $errors);
        $active = $this->booleanValue($row['active'] ?? null, true, 'active', $errors);
        $canReceive = $this->booleanValue(
            $row['can_receive_maintenance_orders'] ?? null,
            false,
            'can_receive_maintenance_orders',
            $errors,
        );

        if (! $canReceive) {
            $maintenanceRole = null;
        }

        $contractHours = $this->nullableString($row['contract_hours'] ?? null);
        if ($contractHours !== null) {
            $contractHours = str_replace(',', '.', $contractHours);
        }

        $payload = [
            'full_name' => $this->nullableString($row['full_name'] ?? null),
            'rut' => Rut::normalize($this->nullableString($row['rut'] ?? null)),
            'birth_date' => DateInput::normalize($row['birth_date'] ?? null),
            'institutional_email' => $this->email($row['institutional_email'] ?? null),
            'personal_email' => $this->email($row['personal_email'] ?? null),
            'phone' => $this->nullableString($row['phone'] ?? null),
            'address' => $this->nullableString($row['address'] ?? null),
            'region_id' => $regionId,
            'commune_id' => $communeId,
            'cargo_id' => $cargoId,
            'contract_type' => $contractType,
            'start_date' => DateInput::normalize($row['start_date'] ?? null),
            'end_date' => $contractType === 'indefinido' ? null : DateInput::normalize($row['end_date'] ?? null),
            'status' => $status,
            'workday' => $workday,
            'contract_hours' => $contractHours,
            'professional_title' => $this->nullableString($row['professional_title'] ?? null),
            'specialty' => $this->nullableString($row['specialty'] ?? null),
            'professional_registration' => $this->nullableString($row['professional_registration'] ?? null),
            'internal_notes' => $this->nullableString($row['internal_notes'] ?? null),
            'active' => $active,
            'can_receive_maintenance_orders' => $canReceive,
            'maintenance_role' => $maintenanceRole,
        ];

        return [$payload, $departmentIds, $errors];
    }

    /** @return array<string, array<string, int|string>> */
    private function catalogs(): array
    {
        return [
            'cargos' => $this->modelCatalog(Cargo::query()->where('active', true)->get(['id', 'name', 'slug']), ['name', 'slug']),
            'departments' => $this->modelCatalog(Department::query()->get(['id', 'name', 'slug']), ['name', 'slug']),
            'regions' => $this->modelCatalog(Region::query()->get(['id', 'name', 'short_name', 'code', 'abbreviation', 'iso_code']), ['name', 'short_name', 'code', 'abbreviation', 'iso_code']),
            'communes' => Commune::query()->get(['id', 'region_id', 'name', 'code'])
                ->map(fn (Commune $commune) => [
                    'id' => $commune->id,
                    'region_id' => $commune->region_id,
                    'keys' => array_values(array_unique(array_filter([
                        $this->lookupKey($commune->name),
                        $this->lookupKey($commune->code),
                    ]))),
                ])->all(),
            'statuses' => $this->optionCatalog(Staff::STATUS_OPTIONS),
            'contract_types' => $this->optionCatalog(Staff::CONTRACT_TYPE_OPTIONS),
            'workdays' => $this->optionCatalog(Staff::WORKDAY_OPTIONS),
            'maintenance_roles' => $this->optionCatalog(Staff::MAINTENANCE_ROLE_OPTIONS),
        ];
    }

    /**
     * @param  iterable<int, object>  $models
     * @param  array<int, string>  $fields
     * @return array<string, int>
     */
    private function modelCatalog(iterable $models, array $fields): array
    {
        $map = [];
        foreach ($models as $model) {
            foreach ($fields as $field) {
                $key = $this->lookupKey((string) ($model->{$field} ?? ''));
                if ($key !== '') {
                    $map[$key] = (int) $model->id;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array{value:string,label:string}>  $options
     * @return array<string, string>
     */
    private function optionCatalog(array $options): array
    {
        $map = [];
        foreach ($options as $option) {
            $map[$this->lookupKey($option['value'])] = $option['value'];
            $map[$this->lookupKey($option['label'])] = $option['value'];
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $catalog
     * @param  array<int, array{field:string,message:string}>  $errors
     */
    private function catalogId(mixed $value, array $catalog, string $field, array &$errors): ?int
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        $id = $catalog[$this->lookupKey($value)] ?? null;
        if (! $id) {
            $errors[] = ['field' => $field, 'message' => "No se encontró {$field}: {$value}."];
        }

        return $id ? (int) $id : null;
    }

    /**
     * @param  array<int, array{id:int,region_id:int,keys:array<int, string>}>  $communes
     * @param  array<int, array{field:string,message:string}>  $errors
     */
    private function communeId(mixed $value, ?int $regionId, array $communes, array &$errors): ?int
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        $key = $this->lookupKey($value);
        $matches = collect($communes)->filter(fn (array $commune) => in_array($key, $commune['keys'], true));
        if ($regionId) {
            $matches = $matches->where('region_id', $regionId);
        }

        $match = $matches->first();
        if (! $match) {
            $errors[] = ['field' => 'commune', 'message' => "No se encontró la comuna: {$value} para la región indicada."];
        }

        return $match ? (int) $match['id'] : null;
    }

    /**
     * @param  array<string, int>  $catalog
     * @param  array<int, array{field:string,message:string}>  $errors
     * @return array<int, int>
     */
    private function departmentIds(mixed $value, array $catalog, array &$errors): array
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return [];
        }

        $ids = [];
        foreach (preg_split('/[;|]+/', $value) ?: [] as $department) {
            $department = trim($department);
            if ($department === '') {
                continue;
            }

            $id = $catalog[$this->lookupKey($department)] ?? null;
            if (! $id) {
                $errors[] = ['field' => 'departments', 'message' => "No se encontró el departamento: {$department}."];
                continue;
            }
            $ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, string>  $catalog
     * @param  array<int, array{field:string,message:string}>  $errors
     */
    private function optionValue(mixed $value, array $catalog, string $field, array &$errors): ?string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return null;
        }

        $resolved = $catalog[$this->lookupKey($value)] ?? null;
        if (! $resolved) {
            $errors[] = ['field' => $field, 'message' => "El valor {$value} no es válido para {$field}."];
        }

        return $resolved;
    }

    /**
     * @param  array<int, array{field:string,message:string}>  $errors
     */
    private function booleanValue(mixed $value, bool $default, string $field, array &$errors): bool
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return $default;
        }

        $key = $this->lookupKey($value);
        if (in_array($key, ['1', 'si', 's', 'true', 'activo', 'activa'], true)) {
            return true;
        }
        if (in_array($key, ['0', 'no', 'n', 'false', 'inactivo', 'inactiva'], true)) {
            return false;
        }

        $errors[] = ['field' => $field, 'message' => "Usa Sí o No en la columna {$field}."];

        return $default;
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'rut' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'institutional_email' => ['nullable', 'email', 'max:255'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'commune_id' => ['nullable', 'integer', 'exists:communes,id'],
            'cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'contract_type' => ['nullable', Rule::in(array_column(Staff::CONTRACT_TYPE_OPTIONS, 'value'))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(array_column(Staff::STATUS_OPTIONS, 'value'))],
            'workday' => ['nullable', Rule::in(array_column(Staff::WORKDAY_OPTIONS, 'value'))],
            'contract_hours' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'professional_title' => ['nullable', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'professional_registration' => ['nullable', 'string', 'max:255'],
            'internal_notes' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
            'can_receive_maintenance_orders' => ['required', 'boolean'],
            'maintenance_role' => [
                'nullable',
                'required_if:can_receive_maintenance_orders,true,1',
                Rule::in(array_column(Staff::MAINTENANCE_ROLE_OPTIONS, 'value')),
            ],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'full_name.required' => 'El nombre completo es obligatorio.',
            'institutional_email.email' => 'El correo institucional no tiene un formato válido.',
            'personal_email.email' => 'El correo personal no tiene un formato válido.',
            'end_date.after_or_equal' => 'La fecha de término debe ser igual o posterior a la fecha de ingreso.',
            'maintenance_role.required_if' => 'Debes indicar el rol de mantención cuando el funcionario recibe OT.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0:?Staff,1:array<int, array{field:string,message:string}>}
     */
    private function resolveExistingStaff(array $payload, bool $updateExisting): array
    {
        $byRut = $payload['rut'] ? Staff::query()->where('rut', $payload['rut'])->first() : null;
        $byEmail = $payload['institutional_email']
            ? Staff::query()->where('institutional_email', $payload['institutional_email'])->first()
            : null;
        $errors = [];

        if ($byRut && $byEmail && $byRut->id !== $byEmail->id) {
            $errors[] = [
                'field' => 'rut',
                'message' => 'El RUT y el correo institucional pertenecen a funcionarios distintos.',
            ];

            return [null, $errors];
        }

        $staff = $byRut ?: $byEmail;
        if ($staff && ! $updateExisting) {
            $errors[] = [
                'field' => $byRut ? 'rut' : 'institutional_email',
                'message' => 'El funcionario ya existe. Activa la actualización de registros existentes para modificarlo.',
            ];
        }

        return [$staff, $errors];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{field:string,message:string}>
     */
    private function accountConflicts(array $payload, ?Staff $staff): array
    {
        if (! $payload['institutional_email']) {
            return [];
        }

        $staffConflict = Staff::query()
            ->where('institutional_email', $payload['institutional_email'])
            ->when($staff, fn ($query) => $query->whereKeyNot($staff->id))
            ->exists();
        if ($staffConflict) {
            return [['field' => 'institutional_email', 'message' => 'El correo institucional ya pertenece a otro funcionario.']];
        }

        $user = User::query()->where('email', $payload['institutional_email'])->first();
        if ($user && $user->staff_id && (int) $user->staff_id !== (int) ($staff?->id ?? 0)) {
            return [['field' => 'institutional_email', 'message' => 'El correo institucional ya pertenece a otra cuenta de acceso.']];
        }

        return [];
    }

    private function syncAccessAccount(Staff $staff): void
    {
        $linkedUser = User::query()->where('staff_id', $staff->id)->first();

        if ($linkedUser) {
            $changes = [
                'name' => $staff->full_name,
                'cargo_id' => $staff->cargo_id,
                'active' => (bool) $staff->active,
                'user_type' => 'staff',
            ];
            if ($staff->institutional_email) {
                $changes['email'] = $staff->institutional_email;
            }
            $linkedUser->update($changes);

            return;
        }

        if (! $staff->rut || ! $staff->institutional_email) {
            return;
        }

        $user = User::query()->where('email', $staff->institutional_email)->first();
        if ($user) {
            $user->update([
                'name' => $staff->full_name,
                'staff_id' => $staff->id,
                'cargo_id' => $staff->cargo_id,
                'active' => (bool) $staff->active,
                'user_type' => 'staff',
            ]);

            return;
        }

        $user = new User([
            'name' => $staff->full_name,
            'email' => $staff->institutional_email,
            'password' => Hash::make($staff->rut),
            'user_type' => 'staff',
            'active' => (bool) $staff->active,
            'staff_id' => $staff->id,
            'cargo_id' => $staff->cargo_id,
        ]);
        $user->email_verified_at = now();
        $user->save();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function email(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        return $value ? mb_strtolower($value) : null;
    }

    private function lookupKey(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = Str::ascii(mb_strtolower(trim($value)));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $value), '_');
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $xml = simplexml_load_string($content);
        if ($xml === false) {
            return [];
        }

        $strings = [];
        foreach ($xml->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $strings[] = trim($this->xmlText($item));
        }

        return $strings;
    }

    /** @return array<int, bool> */
    private function dateStyles(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/styles.xml');
        if ($content === false) {
            return [];
        }

        $xml = simplexml_load_string($content);
        if ($xml === false) {
            return [];
        }

        $dateNumFmtIds = array_fill_keys([14, 15, 16, 17, 22, 27, 30, 36, 50, 57], true);
        foreach ($xml->xpath('//*[local-name()="numFmts"]/*[local-name()="numFmt"]') ?: [] as $format) {
            if (preg_match('/[ymd]/', strtolower((string) $format['formatCode']))) {
                $dateNumFmtIds[(int) $format['numFmtId']] = true;
            }
        }

        $styles = [];
        $index = 0;
        foreach ($xml->xpath('//*[local-name()="cellXfs"]/*[local-name()="xf"]') ?: [] as $xf) {
            $styles[$index++] = isset($dateNumFmtIds[(int) $xf['numFmtId']]);
        }

        return $styles;
    }

    /** @return array<int, array{name:string,path:string}> */
    private function sheetPaths(ZipArchive $zip): array
    {
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        if ($workbook === false || $rels === false) {
            return [['name' => 'Funcionarios', 'path' => 'xl/worksheets/sheet1.xml']];
        }

        $relMap = [];
        foreach ($rels->xpath('/*[local-name()="Relationships"]/*[local-name()="Relationship"]') ?: [] as $relationship) {
            $target = (string) $relationship['Target'];
            $relMap[(string) $relationship['Id']] = str_starts_with($target, '/')
                ? ltrim($target, '/')
                : 'xl/'.ltrim($target, '/');
        }

        $paths = [];
        foreach ($workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]') ?: [] as $sheet) {
            $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationshipId = (string) ($attributes['id'] ?? '');
            if ($relationshipId !== '' && isset($relMap[$relationshipId])) {
                $paths[] = ['name' => (string) $sheet['name'], 'path' => $relMap[$relationshipId]];
            }
        }

        return $paths;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @param  array<int, bool>  $dateStyles
     * @return array{cells:array<int, array<int, mixed>>,max_row:int}
     */
    private function parseSheet(string $content, array $sharedStrings, array $dateStyles): array
    {
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            return ['cells' => [], 'max_row' => 0];
        }

        $cells = [];
        $maxRow = 0;
        foreach ($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $rowIndex = (int) $row['r'];
            $maxRow = max($maxRow, $rowIndex);
            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                [$columnIndex] = $this->coordinate((string) $cell['r']);
                $cells[$rowIndex][$columnIndex] = $this->cellValue($cell, $sharedStrings, $dateStyles);
            }
        }

        return ['cells' => $cells, 'max_row' => $maxRow];
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @param  array<int, bool>  $dateStyles
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings, array $dateStyles): mixed
    {
        $type = (string) $cell['t'];
        $styleValue = (string) $cell['s'];
        $style = $styleValue !== '' ? (int) $styleValue : null;
        $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
        $inlineNodes = $cell->xpath('./*[local-name()="is"]') ?: [];
        $raw = isset($valueNodes[0]) ? (string) $valueNodes[0] : null;

        if ($type === 's') {
            return $raw !== null ? ($sharedStrings[(int) $raw] ?? null) : null;
        }
        if ($type === 'inlineStr') {
            return isset($inlineNodes[0]) ? trim($this->xmlText($inlineNodes[0])) : null;
        }
        if ($type === 'str') {
            return $raw;
        }
        if ($type === 'b') {
            return $raw === '1';
        }
        if ($raw === null || $raw === '') {
            return null;
        }
        if ($style !== null && ($dateStyles[$style] ?? false) && is_numeric($raw)) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $raw))->toDateString();
        }

        return is_numeric($raw) ? (float) $raw : $raw;
    }

    private function xmlText(SimpleXMLElement $element): string
    {
        $textNodes = $element->xpath('.//*[local-name()="t"]') ?: [];
        if ($textNodes !== []) {
            return implode('', array_map(static fn (SimpleXMLElement $node) => (string) $node, $textNodes));
        }

        return (string) $element;
    }

    /** @return array{0:int,1:int} */
    private function coordinate(string $reference): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', strtoupper($reference), $matches);
        $column = 0;
        foreach (str_split($matches[1] ?? 'A') as $letter) {
            $column = $column * 26 + (ord($letter) - 64);
        }

        return [$column, (int) ($matches[2] ?? 1)];
    }
}
