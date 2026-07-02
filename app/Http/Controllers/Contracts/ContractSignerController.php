<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\StoreContractSignerRequest;
use App\Http\Requests\Contracts\UpdateContractSignerRequest;
use App\Models\ContractSigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ContractSignerController extends Controller
{
    public function catalogs(): JsonResponse
    {
        return response()->json([
            'signer_types' => ContractSigner::TYPE_OPTIONS,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $type = trim((string) $request->query('signer_type'));
        $active = $request->query('active');

        $signers = ContractSigner::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('signer_type', $type));

        if ($active !== null && $active !== '') {
            $signers->where('active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'data' => $signers
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreContractSignerRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $image = $request->file('signature_image');

        unset($payload['signature_image']);

        $signer = ContractSigner::query()->create($payload);

        if ($image instanceof UploadedFile) {
            $this->storeSignatureImage($signer, $image);
        }

        return response()->json([
            'message' => 'Firma creada correctamente.',
            'data' => $signer->fresh(),
        ], 201);
    }

    public function show(ContractSigner $contractSigner): JsonResponse
    {
        return response()->json([
            'data' => $contractSigner,
        ]);
    }

    public function update(UpdateContractSignerRequest $request, ContractSigner $contractSigner): JsonResponse
    {
        $payload = $request->validated();
        $image = $request->file('signature_image');

        unset($payload['signature_image']);

        $contractSigner->update($payload);

        if ($image instanceof UploadedFile) {
            $this->storeSignatureImage($contractSigner, $image);
        }

        return response()->json([
            'message' => 'Firma actualizada correctamente.',
            'data' => $contractSigner->fresh(),
        ]);
    }

    public function destroy(ContractSigner $contractSigner): JsonResponse
    {
        $signaturePath = $contractSigner->signature_image_path;
        $contractSigner->delete();

        if ($signaturePath) {
            Storage::disk('public')->delete($signaturePath);
        }

        return response()->json([
            'message' => 'Firma eliminada correctamente.',
        ]);
    }

    public function setActive(Request $request, ContractSigner $contractSigner): JsonResponse
    {
        $payload = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $contractSigner->update(['active' => $payload['active']]);

        return response()->json([
            'message' => 'Estado de la firma actualizado correctamente.',
            'data' => $contractSigner->fresh(),
        ]);
    }

    private function storeSignatureImage(ContractSigner $signer, UploadedFile $image): void
    {
        if ($signer->signature_image_path) {
            Storage::disk('public')->delete($signer->signature_image_path);
        }

        $path = $image->storePubliclyAs(
            sprintf('contracts/signers/%d', $signer->id),
            now()->format('Ymd_His') . '_' . uniqid() . '.' . $image->getClientOriginalExtension(),
            ['disk' => 'public']
        );

        $signer->update(['signature_image_path' => $path]);
    }
}
