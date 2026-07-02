<?php

namespace App\Http\Controllers\Informatica;

use App\Http\Controllers\Controller;
use App\Http\Requests\Informatica\UploadItEquipmentAttachmentRequest;
use App\Models\It\ItEquipment;
use App\Models\It\ItEquipmentAttachment;
use App\Models\It\ItEquipmentLoan;
use App\Models\It\ItEquipmentMaintenanceReport;
use App\Services\Informatica\InformaticaAccessService;
use App\Services\Informatica\ItEquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ItEquipmentAttachmentController extends Controller
{
    public function __construct(
        private readonly ItEquipmentService $equipmentService,
        private readonly InformaticaAccessService $accessService,
    ) {
    }

    public function storeForEquipment(UploadItEquipmentAttachmentRequest $request, ItEquipment $equipment): JsonResponse
    {
        abort_unless($this->accessService->canEditEquipment($request->user()), 403);

        $attachment = $this->equipmentService->storeAttachment(
            $equipment,
            $equipment,
            $request->file('document'),
            $request->user(),
            $request->validated()['category'] ?? 'documento',
            $request->validated()['notes'] ?? null
        );

        return response()->json([
            'message' => 'Adjunto cargado correctamente.',
            'data' => $attachment->load('uploadedBy:id,name'),
        ], 201);
    }

    public function storeForLoan(UploadItEquipmentAttachmentRequest $request, ItEquipmentLoan $loan): JsonResponse
    {
        abort_unless(
            $this->accessService->canCreateLoans($request->user())
            || $this->accessService->canReturnLoans($request->user())
            || $this->accessService->canCancelLoans($request->user()),
            403
        );

        $attachment = $this->equipmentService->storeAttachment(
            $loan,
            $loan->equipment()->firstOrFail(),
            $request->file('document'),
            $request->user(),
            $request->validated()['category'] ?? 'documento',
            $request->validated()['notes'] ?? null
        );

        return response()->json([
            'message' => 'Adjunto cargado correctamente.',
            'data' => $attachment->load('uploadedBy:id,name'),
        ], 201);
    }

    public function storeForMaintenance(UploadItEquipmentAttachmentRequest $request, ItEquipmentMaintenanceReport $report): JsonResponse
    {
        abort_unless(
            $this->accessService->canCreateMaintenance($request->user())
            || $this->accessService->canEditMaintenance($request->user())
            || $this->accessService->canCloseMaintenance($request->user()),
            403
        );

        $attachment = $this->equipmentService->storeAttachment(
            $report,
            $report->equipment()->firstOrFail(),
            $request->file('document'),
            $request->user(),
            $request->validated()['category'] ?? 'documento',
            $request->validated()['notes'] ?? null
        );

        return response()->json([
            'message' => 'Adjunto cargado correctamente.',
            'data' => $attachment->load('uploadedBy:id,name'),
        ], 201);
    }

    public function download(Request $request, ItEquipmentAttachment $attachment)
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        return Storage::disk('public')->download($attachment->file_path, $attachment->original_name);
    }

    public function destroy(Request $request, ItEquipmentAttachment $attachment): JsonResponse
    {
        $this->assertCanDeleteAttachment($request->user(), $attachment);
        $this->equipmentService->removeAttachment($attachment);

        return response()->json([
            'message' => 'Adjunto eliminado correctamente.',
        ]);
    }

    private function assertCanDeleteAttachment($user, ItEquipmentAttachment $attachment): void
    {
        $attachableType = $attachment->attachable_type;

        $allowed = match ($attachableType) {
            ItEquipment::class => $this->accessService->canEditEquipment($user),
            ItEquipmentLoan::class => $this->accessService->canReturnLoans($user) || $this->accessService->canCancelLoans($user),
            ItEquipmentMaintenanceReport::class => $this->accessService->canEditMaintenance($user) || $this->accessService->canCloseMaintenance($user),
            default => false,
        };

        abort_unless($allowed, 403);
    }
}
