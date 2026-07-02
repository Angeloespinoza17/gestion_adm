<?php

namespace App\Http\Controllers\Infirmary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Infirmary\UploadInfirmaryDocumentRequest;
use App\Models\Infirmary\InfirmaryAccident;
use App\Models\Infirmary\InfirmaryAttention;
use App\Models\Infirmary\InfirmaryDocument;
use App\Models\Infirmary\InfirmaryMedicationAuthorization;
use App\Services\Infirmary\InfirmaryAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class InfirmaryDocumentController extends Controller
{
    public function __construct(
        private readonly InfirmaryAccessService $accessService,
    ) {
    }

    public function storeForAttention(UploadInfirmaryDocumentRequest $request, InfirmaryAttention $attention): JsonResponse
    {
        $this->authorize('update', $attention);

        return $this->storeDocument($request->validated(), $request->file('document'), $attention, $request->user()?->id, $attention->student_profile_id);
    }

    public function storeForAccident(UploadInfirmaryDocumentRequest $request, InfirmaryAccident $accident): JsonResponse
    {
        $this->authorize('update', $accident);

        return $this->storeDocument($request->validated(), $request->file('document'), $accident, $request->user()?->id, $accident->student_profile_id);
    }

    public function storeForAuthorization(UploadInfirmaryDocumentRequest $request, InfirmaryMedicationAuthorization $authorization): JsonResponse
    {
        $this->authorize('update', $authorization);

        return $this->storeDocument($request->validated(), $request->file('document'), $authorization, $request->user()?->id, $authorization->student_profile_id);
    }

    public function download(InfirmaryDocument $document): Response
    {
        abort_unless($this->accessService->canViewModule(request()->user()), 403);

        return Storage::disk('public')->download($document->file_path, $document->original_name);
    }

    public function destroy(InfirmaryDocument $document): JsonResponse
    {
        abort_unless($this->canDeleteDocument($document), 403);

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'message' => 'Documento eliminado correctamente.',
        ]);
    }

    private function storeDocument(array $payload, UploadedFile $file, Model $subject, ?int $userId, ?int $studentId): JsonResponse
    {
        $directory = sprintf('infirmary/%s/%d', class_basename($subject), $subject->getKey());
        $path = $file->storePubliclyAs(
            $directory,
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName(),
            ['disk' => 'public']
        );

        $document = $subject->documents()->create([
            'student_profile_id' => $payload['student_profile_id'] ?? $studentId,
            'category' => $payload['category'] ?? 'otro',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'notes' => $payload['notes'] ?? null,
            'uploaded_by' => $userId,
        ]);

        return response()->json([
            'message' => 'Documento cargado correctamente.',
            'data' => $document->load('uploadedBy:id,name'),
        ], 201);
    }

    private function canDeleteDocument(InfirmaryDocument $document): bool
    {
        $user = request()->user();
        $documentableType = $document->documentable_type;

        return match ($documentableType) {
            InfirmaryAttention::class => $this->accessService->canEditAttention($user),
            InfirmaryAccident::class => $this->accessService->canManageAccidents($user),
            InfirmaryMedicationAuthorization::class => $this->accessService->canManageMedication($user),
            default => false,
        };
    }
}
