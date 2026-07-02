<?php

namespace App\Http\Controllers\ApoyoProfesional;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApoyoProfesional\UploadApoyoAdjuntoRequest;
use App\Models\ApoyoProfesional\ApoyoAdjunto;
use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\ApoyoProfesional\ApoyoEntrevista;
use App\Models\ApoyoProfesional\ApoyoPlan;
use App\Models\ApoyoProfesional\ApoyoSeguimiento;
use App\Services\ApoyoProfesional\ApoyoProfesionalAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ApoyoProfesionalDocumentController extends Controller
{
    public function __construct(
        private readonly ApoyoProfesionalAccessService $accessService,
    ) {
    }

    public function storeForAttention(UploadApoyoAdjuntoRequest $request, ApoyoAtencion $attention): JsonResponse
    {
        $this->authorize('update', $attention);

        return $this->storeDocument($request->validated(), $request->file('document'), $attention, $request->user()?->id, $attention->student_profile_id);
    }

    public function storeForDerivation(UploadApoyoAdjuntoRequest $request, ApoyoDerivacion $derivation): JsonResponse
    {
        $this->authorize('update', $derivation);

        return $this->storeDocument($request->validated(), $request->file('document'), $derivation, $request->user()?->id, $derivation->student_profile_id);
    }

    public function storeForFollowUp(UploadApoyoAdjuntoRequest $request, ApoyoSeguimiento $followUp): JsonResponse
    {
        $this->authorize('update', $followUp);

        return $this->storeDocument($request->validated(), $request->file('document'), $followUp, $request->user()?->id, $followUp->student_profile_id);
    }

    public function storeForPlan(UploadApoyoAdjuntoRequest $request, ApoyoPlan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        return $this->storeDocument($request->validated(), $request->file('document'), $plan, $request->user()?->id, $plan->student_profile_id);
    }

    public function storeForInterview(UploadApoyoAdjuntoRequest $request, ApoyoEntrevista $interview): JsonResponse
    {
        $this->authorize('update', $interview);

        return $this->storeDocument($request->validated(), $request->file('document'), $interview, $request->user()?->id, $interview->student_profile_id);
    }

    public function download(ApoyoAdjunto $document): Response
    {
        abort_unless($this->accessService->canViewModule(request()->user()), 403);

        return Storage::disk('public')->download($document->file_path, $document->original_name);
    }

    public function destroy(ApoyoAdjunto $document): JsonResponse
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
        $directory = sprintf('apoyo-profesional/%s/%d', class_basename($subject), $subject->getKey());
        $path = $file->storePubliclyAs(
            $directory,
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName(),
            ['disk' => 'public']
        );

        $document = $subject->documents()->create([
            'student_profile_id' => $payload['student_profile_id'] ?? $studentId,
            'category' => $payload['category'] ?? 'otro',
            'confidentiality_level' => $payload['confidentiality_level'] ?? 'general',
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

    private function canDeleteDocument(ApoyoAdjunto $document): bool
    {
        $user = request()->user();

        return match ($document->documentable_type) {
            ApoyoAtencion::class => $document->documentable && $this->accessService->canDeleteAttention($user),
            ApoyoDerivacion::class => $this->accessService->canCreateDerivation($user) || $this->accessService->canRespondDerivation($user),
            ApoyoSeguimiento::class => $this->accessService->canCreateFollowUp($user),
            ApoyoPlan::class => $this->accessService->canCreatePlan($user),
            ApoyoEntrevista::class => $this->accessService->canCreateAttention($user),
            default => false,
        };
    }
}
