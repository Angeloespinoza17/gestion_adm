<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\PreviewContractRequest;
use App\Http\Requests\Contracts\StoreContractRequest;
use App\Http\Requests\Contracts\UpdateContractRequest;
use App\Models\Contract;
use App\Models\ContractSigner;
use App\Models\ContractTemplate;
use App\Models\Department;
use App\Models\Staff;
use App\Models\User;
use App\Services\Contracts\ContractDocumentService;
use App\Services\Contracts\ContractRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractRenderer $renderer,
        private readonly ContractDocumentService $documentService,
    ) {
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'staff' => Staff::query()
                ->with(['cargo:id,name', 'departments:id,name,color'])
                ->where('active', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'rut', 'institutional_email', 'personal_email', 'cargo_id', 'contract_type', 'start_date', 'workday', 'contract_hours']),
            'templates' => ContractTemplate::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'contract_type', 'description', 'active']),
            'departments' => Department::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'color', 'active']),
            'signers' => ContractSigner::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'rut', 'position', 'signer_type', 'signature_image_path', 'active', 'sort_order']),
            'statuses' => Contract::STATUS_OPTIONS,
            'contract_types' => Staff::CONTRACT_TYPE_OPTIONS,
            'workdays' => Staff::WORKDAY_OPTIONS,
            'signer_types' => ContractSigner::TYPE_OPTIONS,
            'available_variables' => $this->renderer->availableVariables(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $staffId = $request->query('staff_id');
        $rut = trim((string) $request->query('rut'));
        $contractType = trim((string) $request->query('contract_type'));
        $status = trim((string) $request->query('status'));
        $departmentId = $request->query('department_id');
        $templateId = $request->query('contract_template_id');
        $dateFrom = trim((string) $request->query('start_date'));
        $dateTo = trim((string) $request->query('end_date'));

        $contracts = Contract::query()
            ->with([
                'staff:id,full_name,rut,institutional_email,personal_email',
                'staff.cargo:id,name',
                'template:id,name',
                'departments:id,name,color',
                'signatures:id,contract_id,name,rut,position,signer_type,sort_order',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('position_name', 'like', "%{$search}%")
                        ->orWhere('allowances', 'like', "%{$search}%")
                        ->orWhereHas('staff', function ($staffQuery) use ($search) {
                            $staffQuery
                                ->where('full_name', 'like', "%{$search}%")
                                ->orWhere('rut', 'like', "%{$search}%")
                                ->orWhere('institutional_email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('template', fn ($templateQuery) => $templateQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($staffId, fn ($query) => $query->where('staff_id', $staffId))
            ->when($rut !== '', fn ($query) => $query->whereHas('staff', fn ($staffQuery) => $staffQuery->where('rut', 'like', "%{$rut}%")))
            ->when($contractType !== '', fn ($query) => $query->where('contract_type', $contractType))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($templateId, fn ($query) => $query->where('contract_template_id', $templateId))
            ->when($departmentId, fn ($query) => $query->whereHas('departments', fn ($deptQuery) => $deptQuery->where('departments.id', $departmentId)))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('start_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('end_date', '<=', $dateTo));

        return response()->json(
            $contracts
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->paginate((int) $request->query('per_page', 15))
        );
    }

    public function preview(PreviewContractRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $staff = Staff::query()
            ->with(['cargo:id,name', 'departments:id,name,color', 'regionRecord:id,name,short_name', 'communeRecord:id,name'])
            ->findOrFail($payload['staff_id']);
        $template = ContractTemplate::query()->with('clauses')->findOrFail($payload['contract_template_id']);
        $departments = isset($payload['department_ids'])
            ? Department::query()->whereIn('id', $payload['department_ids'])->get(['id', 'name', 'color'])
            : $staff->departments;
        $signers = $this->selectedSigners($payload['signer_ids'] ?? []);
        $representativeLegal = $signers->firstWhere('signer_type', 'representante_legal')
            ?: ContractSigner::query()->where('signer_type', 'representante_legal')->where('active', true)->orderBy('sort_order')->first();

        $render = $this->renderer->render(
            $template,
            $staff,
            $template->clauses,
            $this->signatureBlocksFromProfiles($signers),
            array_merge($payload, ['departments' => $departments, 'generated_at' => now()]),
            $representativeLegal,
            $payload['custom_variables'] ?? [],
        );

        return response()->json([
            'data' => [
                'content' => $render['content'],
                'missing_variables' => $render['missing'],
                'placeholders' => $render['placeholders'],
                'signature_blocks' => $this->signatureBlocksFromProfiles($signers)->values(),
            ],
        ]);
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $contract = DB::transaction(function () use ($request, $payload) {
            return $this->persistContract(new Contract(), $payload, $request->user(), true);
        });

        return response()->json([
            'message' => 'Contrato creado correctamente.',
            'data' => $this->loadContract($contract),
        ], 201);
    }

    public function show(Contract $contract): JsonResponse
    {
        return response()->json([
            'data' => $this->loadContract($contract),
        ]);
    }

    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        if ($contract->isSigned() && !$request->user()?->hasPermission('editar_contratos_firmados')) {
            return response()->json([
                'message' => 'No puedes editar contratos firmados.',
            ], 422);
        }

        $contract = DB::transaction(function () use ($request, $contract) {
            return $this->persistContract($contract, $request->validated(), $request->user(), false);
        });

        return response()->json([
            'message' => 'Contrato actualizado correctamente.',
            'data' => $this->loadContract($contract),
        ]);
    }

    public function destroy(Contract $contract): JsonResponse
    {
        if ($contract->isSigned()) {
            return response()->json([
                'message' => 'Los contratos firmados no se eliminan; deben anularse.',
            ], 422);
        }

        $contractId = $contract->id;
        $contract->delete();
        Storage::disk('public')->deleteDirectory('contracts/' . $contractId);

        return response()->json([
            'message' => 'Contrato eliminado correctamente.',
        ]);
    }

    public function setStatus(Request $request, Contract $contract): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', Rule::in(array_column(Contract::STATUS_OPTIONS, 'value'))],
        ]);

        if ($contract->isSigned() && !$request->user()?->hasPermission('editar_contratos_firmados')) {
            return response()->json([
                'message' => 'No puedes modificar el estado de un contrato firmado sin permiso especial.',
            ], 422);
        }

        $contract->status = $payload['status'];

        if ($payload['status'] === 'firmado' && !$contract->signed_at) {
            $contract->signed_at = now();
        }

        if ($payload['status'] === 'anulado' && !$contract->voided_at) {
            $contract->voided_at = now();
        }

        if (in_array($payload['status'], ['generado', 'enviado_firma', 'firmado'], true) && !$contract->generated_at) {
            $contract->generated_at = now();
        }

        $contract->save();

        return response()->json([
            'message' => 'Estado del contrato actualizado correctamente.',
            'data' => $this->loadContract($contract->fresh()),
        ]);
    }

    public function downloadWord(Contract $contract): StreamedResponse
    {
        if (!$contract->exported_word_path || !Storage::disk('public')->exists($contract->exported_word_path)) {
            $path = $this->documentService->saveWordDocument($this->loadContract($contract));
            $contract->update(['exported_word_path' => $path]);
            $contract->refresh();
        }

        return Storage::disk('public')->download(
            $contract->exported_word_path,
            'contrato_' . $contract->id . '.doc'
        );
    }

    private function persistContract(Contract $contract, array $payload, ?User $user, bool $isNew): Contract
    {
        $departmentIds = $payload['department_ids'] ?? null;
        $signerIds = $payload['signer_ids'] ?? [];
        $customVariables = $payload['custom_variables'] ?? [];

        unset($payload['department_ids'], $payload['signer_ids']);

        $staff = Staff::query()
            ->with(['cargo:id,name', 'departments:id,name,color', 'regionRecord:id,name,short_name', 'communeRecord:id,name'])
            ->findOrFail($payload['staff_id'] ?? $contract->staff_id);
        $template = ContractTemplate::query()->with('clauses')->findOrFail($payload['contract_template_id'] ?? $contract->contract_template_id);
        $departments = is_array($departmentIds)
            ? Department::query()->whereIn('id', $departmentIds)->get(['id', 'name', 'color'])
            : $staff->departments;
        $signers = $this->selectedSigners($signerIds);
        $representativeLegal = $signers->firstWhere('signer_type', 'representante_legal')
            ?: ContractSigner::query()->where('signer_type', 'representante_legal')->where('active', true)->orderBy('sort_order')->first();

        $render = $this->renderer->render(
            $template,
            $staff,
            $template->clauses,
            $this->signatureBlocksFromProfiles($signers),
            array_merge($payload, ['departments' => $departments, 'generated_at' => now()]),
            $representativeLegal,
            $customVariables,
        );

        if (empty($payload['rendered_content'])) {
            $payload['rendered_content'] = $render['content'];
        }

        $remainingPlaceholders = $this->renderer->extractPlaceholders((string) $payload['rendered_content']);

        if (($payload['status'] ?? 'borrador') !== 'borrador' && !empty($render['missing']) && !empty($remainingPlaceholders)) {
            throw ValidationException::withMessages([
                'missing_variables' => 'Faltan variables obligatorias: ' . implode(', ', $render['missing']),
            ]);
        }

        if (($payload['status'] ?? 'borrador') !== 'borrador' && !empty($remainingPlaceholders)) {
            throw ValidationException::withMessages([
                'rendered_content' => 'Aún existen variables sin reemplazar en el contenido final.',
            ]);
        }

        $payload['updated_by'] = $user?->id;

        if ($isNew) {
            $payload['created_by'] = $user?->id;
        }

        if (($payload['status'] ?? 'borrador') === 'firmado' && !$contract->signed_at) {
            $payload['signed_at'] = now();
        }

        if (($payload['status'] ?? 'borrador') === 'anulado' && !$contract->voided_at) {
            $payload['voided_at'] = now();
        }

        if (in_array($payload['status'] ?? 'borrador', ['generado', 'enviado_firma', 'firmado'], true) && !$contract->generated_at) {
            $payload['generated_at'] = now();
        }

        $contract->fill($payload);
        $contract->save();

        if (is_array($departmentIds)) {
            $contract->departments()->sync($departmentIds);
        }

        $this->syncSignatureSnapshots($contract, $signers);

        if ($contract->rendered_content) {
            $wordPath = $this->documentService->saveWordDocument($this->loadContract($contract));
            $contract->update(['exported_word_path' => $wordPath]);
        }

        return $contract->fresh();
    }

    private function syncSignatureSnapshots(Contract $contract, Collection $signers): void
    {
        $contract->signatures()->delete();

        foreach ($signers->values() as $index => $signer) {
            $contract->signatures()->create([
                'contract_signer_id' => $signer->id,
                'name' => $signer->name,
                'rut' => $signer->rut,
                'position' => $signer->position,
                'signer_type' => $signer->signer_type,
                'signature_image_path' => $signer->signature_image_path,
                'sort_order' => $index + 1,
                'use_signature_image' => true,
                'observations' => $signer->observations,
            ]);
        }
    }

    private function selectedSigners(array $signerIds): Collection
    {
        if ($signerIds === []) {
            return collect();
        }

        $signers = ContractSigner::query()
            ->whereIn('id', $signerIds)
            ->get()
            ->keyBy('id');

        return collect($signerIds)
            ->map(fn ($id) => $signers->get($id))
            ->filter();
    }

    private function signatureBlocksFromProfiles(Collection $signers): Collection
    {
        return $signers->values()->map(function (ContractSigner $signer, int $index) {
            return [
                'id' => $signer->id,
                'name' => $signer->name,
                'rut' => $signer->rut,
                'position' => $signer->position,
                'signer_type' => $signer->signer_type,
                'signature_image_url' => $signer->signature_image_url,
                'sort_order' => $index + 1,
            ];
        });
    }

    private function loadContract(Contract $contract): Contract
    {
        return $contract->load([
            'staff:id,full_name,rut,address,commune,region,institutional_email,personal_email,phone,cargo_id,contract_type,start_date,workday,contract_hours,professional_title,specialty,region_id,commune_id',
            'staff.cargo:id,name',
            'staff.departments:id,name,color',
            'staff.regionRecord:id,name,short_name',
            'staff.communeRecord:id,name,region_id',
            'template:id,name,contract_type,description,body,available_variables',
            'template.clauses' => fn ($query) => $query->select('contract_clauses.id', 'title', 'clause_type', 'content', 'active')
                ->orderBy('contract_clause_template.sort_order'),
            'departments:id,name,color',
            'signatures:id,contract_id,contract_signer_id,name,rut,position,signer_type,signature_image_path,sort_order,use_signature_image,observations',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);
    }
}
