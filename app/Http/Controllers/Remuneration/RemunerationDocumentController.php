<?php

namespace App\Http\Controllers\Remuneration;

use App\Http\Controllers\Controller;
use App\Models\HumanResources\HrDocumentControl;
use App\Models\HumanResources\HrDocumentRequirement;
use App\Models\Staff;
use App\Services\Remuneration\RemunerationAccessService;
use App\Services\Remuneration\RemunerationAuditService;
use App\Services\Remuneration\RemunerationDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RemunerationDocumentController extends Controller
{
    public function __construct(
        private readonly RemunerationAccessService $accessService,
        private readonly RemunerationDocumentService $documentService,
        private readonly RemunerationAuditService $auditService,
    ) {}

    public function staff(Request $request): JsonResponse
    {
        $this->authorizeDocuments($request);

        $payload = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'expired', 'expiring', 'current'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return response()->json($this->documentService->staffMatrix($payload));
    }

    public function requirements(Request $request): JsonResponse
    {
        $this->authorizeDocuments($request);

        $requirements = HrDocumentRequirement::query()
            ->withCount('controls')
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (HrDocumentRequirement $requirement) => $this->documentService
                ->serializeRequirement($requirement));

        return response()->json([
            'data' => $requirements,
            'capabilities' => ['can_manage' => true],
        ]);
    }

    public function storeRequirement(Request $request): JsonResponse
    {
        $this->authorizeDocuments($request);
        $payload = $this->validatedRequirement($request);

        $requirement = HrDocumentRequirement::query()->create([
            ...$payload,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $this->auditService->log(
            'crear_requisito_documental',
            $requirement,
            $request->user(),
            [],
            $requirement->getAttributes(),
            'Creación de requisito documental de funcionarios.',
            $request,
        );

        return response()->json([
            'message' => 'Documento creado correctamente.',
            'data' => $this->documentService->serializeRequirement($requirement),
        ], 201);
    }

    public function updateRequirement(
        Request $request,
        HrDocumentRequirement $requirement,
    ): JsonResponse {
        $this->authorizeDocuments($request);
        $oldValues = $requirement->getAttributes();
        $requirement->fill([
            ...$this->validatedRequirement($request, $requirement),
            'updated_by' => $request->user()?->id,
        ])->save();

        $this->auditService->log(
            'actualizar_requisito_documental',
            $requirement,
            $request->user(),
            $oldValues,
            $requirement->getAttributes(),
            'Actualización de requisito documental de funcionarios.',
            $request,
        );

        return response()->json([
            'message' => 'Documento actualizado correctamente.',
            'data' => $this->documentService->serializeRequirement($requirement->loadCount('controls')),
        ]);
    }

    public function destroyRequirement(
        Request $request,
        HrDocumentRequirement $requirement,
    ): JsonResponse {
        $this->authorizeDocuments($request);
        $oldValues = $requirement->getAttributes();

        if ($requirement->controls()->exists()) {
            $requirement->forceFill([
                'active' => false,
                'updated_by' => $request->user()?->id,
            ])->save();
            $message = 'El documento tenía historial y fue archivado.';
            $archived = true;
        } else {
            $requirement->delete();
            $message = 'Documento eliminado correctamente.';
            $archived = false;
        }

        $this->auditService->log(
            $archived ? 'archivar_requisito_documental' : 'eliminar_requisito_documental',
            $requirement,
            $request->user(),
            $oldValues,
            $requirement->getAttributes(),
            $message,
            $request,
        );

        return response()->json(['message' => $message, 'archived' => $archived]);
    }

    public function saveCompliance(
        Request $request,
        Staff $staff,
        HrDocumentRequirement $requirement,
    ): JsonResponse {
        $this->authorizeDocuments($request);
        abort_unless($staff->active, 422, 'El funcionario no se encuentra activo.');
        abort_unless($requirement->active, 422, 'El documento se encuentra archivado.');

        $validator = validator($request->all(), [
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'delivered' => ['required', 'boolean'],
            'delivered_on' => ['nullable', 'date'],
            'signed' => ['required', 'boolean'],
            'signed_on' => ['nullable', 'date'],
            'remove_file' => ['nullable', 'boolean'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:20480'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $validator->after(function ($validator) use ($request, $requirement): void {
            $hasProgress = $request->boolean('delivered') || $request->boolean('signed');
            if (
                $hasProgress
                && $requirement->validity_mode === HrDocumentRequirement::VALIDITY_MANUAL
                && ! $request->filled('expires_at')
            ) {
                $validator->errors()->add('expires_at', 'Debe indicar la fecha de vencimiento.');
            }
        });

        $payload = $validator->validate();
        $control = HrDocumentControl::query()->firstOrNew([
            'staff_id' => $staff->id,
            'document_requirement_id' => $requirement->id,
        ]);
        $oldValues = $control->exists ? $control->getAttributes() : [];
        $previousPath = $control->file_path;
        $previousDisk = $control->file_disk ?: 'local';
        $file = $request->file('file');
        $fileData = $file instanceof UploadedFile
            ? $this->storeFile($file, $staff, $requirement)
            : [];

        $deliveredAt = $this->completionTimestamp(
            $requirement->requires_delivery && $request->boolean('delivered'),
            $payload['delivered_on'] ?? null,
            $control->delivered_at,
        );
        $signedAt = $this->completionTimestamp(
            $requirement->requires_signature && $request->boolean('signed'),
            $payload['signed_on'] ?? null,
            $control->signed_at,
        );
        $issuedAt = $payload['issued_at'] ?? null;
        $expirationBase = $issuedAt
            ?: ($deliveredAt?->toDateString() ?: $signedAt?->toDateString());
        $expiresAt = $this->documentService->expirationDate(
            $requirement,
            $expirationBase,
            $payload['expires_at'] ?? null,
        );

        try {
            DB::transaction(function () use (
                $control,
                $requirement,
                $request,
                $payload,
                $fileData,
                $deliveredAt,
                $signedAt,
                $issuedAt,
                $expiresAt,
            ): void {
                $control->fill([
                    'related_area' => 'rrhh',
                    'document_type' => $requirement->code,
                    'title' => $requirement->name,
                    'issued_at' => $issuedAt,
                    'expires_at' => $expiresAt,
                    'alert_days' => $requirement->alert_days,
                    'owner_area' => 'Remuneraciones',
                    'delivered_at' => $deliveredAt,
                    'delivered_by' => $deliveredAt ? $request->user()?->id : null,
                    'signed_at' => $signedAt,
                    'signed_by' => $signedAt ? $request->user()?->id : null,
                    'notes' => $payload['notes'] ?? null,
                    'updated_by' => $request->user()?->id,
                ]);

                if (! $control->exists) {
                    $control->created_by = $request->user()?->id;
                }

                if ($request->boolean('remove_file') && ! $fileData) {
                    $control->fill([
                        'file_path' => null,
                        'file_disk' => null,
                        'original_name' => null,
                        'mime_type' => null,
                        'file_size' => null,
                    ]);
                }

                if ($fileData) {
                    $control->fill($fileData);
                }

                $control->save();
                $control->status = match ($this->documentService->currentStatus($requirement, $control)) {
                    'current' => 'vigente',
                    'expiring' => 'por_vencer',
                    'expired' => 'vencido',
                    default => 'pendiente',
                };
                $control->save();
            }, 3);
        } catch (Throwable $exception) {
            if (isset($fileData['file_path'])) {
                Storage::disk('local')->delete($fileData['file_path']);
            }
            throw $exception;
        }

        if (
            $previousPath
            && (($fileData['file_path'] ?? null) || $request->boolean('remove_file'))
            && $previousPath !== ($fileData['file_path'] ?? null)
        ) {
            Storage::disk($previousDisk)->delete($previousPath);
        }

        $this->auditService->log(
            'actualizar_documento_funcionario',
            $control,
            $request->user(),
            $oldValues,
            $control->getAttributes(),
            'Actualización de entrega, firma o vigencia documental.',
            $request,
            ['staff_id' => $staff->id, 'requirement_id' => $requirement->id],
        );

        return response()->json([
            'message' => 'Estado documental actualizado correctamente.',
            'data' => $this->documentService->staffMatrix([
                'search' => $staff->rut ?: $staff->full_name,
                'per_page' => 5,
            ])['data'][0] ?? null,
        ], $control->wasRecentlyCreated ? 201 : 200);
    }

    public function download(
        Request $request,
        HrDocumentControl $control,
    ): StreamedResponse {
        $this->authorizeDocuments($request);

        $disk = in_array($control->file_disk, ['local', 'public'], true)
            ? $control->file_disk
            : 'local';
        abort_unless($control->file_path && Storage::disk($disk)->exists($control->file_path), 404);

        return Storage::disk($disk)->download(
            $control->file_path,
            $control->original_name ?: basename($control->file_path),
            [
                'Content-Type' => $control->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRequirement(
        Request $request,
        ?HrDocumentRequirement $requirement = null,
    ): array {
        $validator = validator($request->all(), [
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('hr_document_requirements', 'code')->ignore($requirement?->id),
            ],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:3000'],
            'requires_delivery' => ['required', 'boolean'],
            'requires_signature' => ['required', 'boolean'],
            'validity_mode' => [
                'required',
                Rule::in([
                    HrDocumentRequirement::VALIDITY_NONE,
                    HrDocumentRequirement::VALIDITY_MONTHS,
                    HrDocumentRequirement::VALIDITY_MANUAL,
                ]),
            ],
            'validity_months' => ['nullable', 'required_if:validity_mode,months', 'integer', 'min:1', 'max:1200'],
            'alert_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_required' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if (! $request->boolean('requires_delivery') && ! $request->boolean('requires_signature')) {
                $validator->errors()->add(
                    'requires_delivery',
                    'El documento debe requerir entrega, firma o ambas acciones.',
                );
            }
        });

        $payload = $validator->validate();
        $payload['name'] = trim($payload['name']);
        $payload['code'] = Str::slug(($payload['code'] ?? null) ?: $payload['name'], '_');
        $payload['description'] = filled($payload['description'] ?? null)
            ? trim((string) $payload['description'])
            : null;
        $payload['validity_months'] = $payload['validity_mode'] === HrDocumentRequirement::VALIDITY_MONTHS
            ? $payload['validity_months']
            : null;
        $payload['sort_order'] = $payload['sort_order'] ?? 100;

        $duplicate = HrDocumentRequirement::withTrashed()
            ->where('code', $payload['code'])
            ->when($requirement, fn ($query) => $query->where('id', '!=', $requirement->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'code' => 'Ya existe un documento con este código.',
            ]);
        }

        return $payload;
    }

    private function completionTimestamp(
        bool $completed,
        ?string $date,
        mixed $existing,
    ): ?Carbon {
        if (! $completed) {
            return null;
        }

        if ($date) {
            return Carbon::parse($date)->startOfDay();
        }

        return $existing ? Carbon::parse($existing) : now();
    }

    /**
     * @return array{file_path:string,file_disk:string,original_name:string,mime_type:?string,file_size:int}
     */
    private function storeFile(
        UploadedFile $file,
        Staff $staff,
        HrDocumentRequirement $requirement,
    ): array {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $filename = Str::uuid().'.'.$extension;
        $path = $file->storeAs(
            "remuneration/documents/{$staff->id}/{$requirement->id}",
            $filename,
            'local',
        );

        return [
            'file_path' => $path,
            'file_disk' => 'local',
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => (int) $file->getSize(),
        ];
    }

    private function authorizeDocuments(Request $request): void
    {
        abort_unless(
            $this->accessService->canManage(
                $request->user(),
                RemunerationAccessService::HR_MANAGEMENT_PERMISSION,
            ),
            403,
        );
    }
}
