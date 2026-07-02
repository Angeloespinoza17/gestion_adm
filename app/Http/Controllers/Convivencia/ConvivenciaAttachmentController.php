<?php

namespace App\Http\Controllers\Convivencia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Convivencia\UploadConvivenciaAttachmentRequest;
use App\Models\Convivencia\ConvivenciaAttachment;
use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaDailyLog;
use App\Models\Convivencia\ConvivenciaDerivation;
use App\Models\Convivencia\ConvivenciaInterview;
use App\Models\Convivencia\ConvivenciaMeasure;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Services\Convivencia\ConvivenciaAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ConvivenciaAttachmentController extends Controller
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {
    }

    public function storeForCase(UploadConvivenciaAttachmentRequest $request, ConvivenciaCase $case): JsonResponse
    {
        $this->authorize('update', $case);

        return $this->storeDocument($request->validated(), $request->file('document'), $case, $request->user()?->id, $case->student_profile_id, $case->is_sensitive);
    }

    public function storeForComplaint(UploadConvivenciaAttachmentRequest $request, ConvivenciaComplaint $complaint): JsonResponse
    {
        $this->authorize('update', $complaint);

        return $this->storeDocument($request->validated(), $request->file('document'), $complaint, $request->user()?->id, $complaint->affected_student_id, $complaint->is_sensitive);
    }

    public function storeForDerivation(UploadConvivenciaAttachmentRequest $request, ConvivenciaDerivation $derivation): JsonResponse
    {
        $this->authorize('update', $derivation);

        return $this->storeDocument($request->validated(), $request->file('document'), $derivation, $request->user()?->id, $derivation->student_profile_id, $derivation->is_sensitive);
    }

    public function storeForPlan(UploadConvivenciaAttachmentRequest $request, ConvivenciaPlan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        return $this->storeDocument($request->validated(), $request->file('document'), $plan, $request->user()?->id, null, $plan->is_sensitive);
    }

    public function storeForInterview(UploadConvivenciaAttachmentRequest $request, ConvivenciaInterview $interview): JsonResponse
    {
        $this->authorize('update', $interview);

        return $this->storeDocument($request->validated(), $request->file('document'), $interview, $request->user()?->id, $interview->student_profile_id, $interview->is_sensitive);
    }

    public function storeForMeasure(UploadConvivenciaAttachmentRequest $request, ConvivenciaMeasure $measure): JsonResponse
    {
        $this->authorize('update', $measure);

        return $this->storeDocument($request->validated(), $request->file('document'), $measure, $request->user()?->id, $measure->student_profile_id, $measure->is_sensitive);
    }

    public function storeForDailyLog(UploadConvivenciaAttachmentRequest $request, ConvivenciaDailyLog $dailyLog): JsonResponse
    {
        $this->authorize('update', $dailyLog);

        return $this->storeDocument($request->validated(), $request->file('document'), $dailyLog, $request->user()?->id, $dailyLog->student_profile_id, $dailyLog->is_sensitive);
    }

    public function storeForProtocolActivation(UploadConvivenciaAttachmentRequest $request, ConvivenciaProtocolActivation $activation): JsonResponse
    {
        abort_unless($this->accessService->canViewProtocolActivation($request->user(), $activation), 403);

        return $this->storeDocument($request->validated(), $request->file('document'), $activation, $request->user()?->id, $activation->case?->student_profile_id, true);
    }

    public function download(ConvivenciaAttachment $attachment): Response
    {
        abort_unless($this->canReadDocument($attachment), 403);

        return Storage::disk('public')->download($attachment->file_path, $attachment->original_name);
    }

    public function destroy(ConvivenciaAttachment $attachment): JsonResponse
    {
        abort_unless($this->canDeleteDocument($attachment), 403);

        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json([
            'message' => 'Adjunto eliminado correctamente.',
        ]);
    }

    private function storeDocument(array $payload, UploadedFile $file, Model $subject, ?int $userId, ?int $studentId, bool $defaultSensitive = false): JsonResponse
    {
        $directory = sprintf('convivencia/%s/%d', class_basename($subject), $subject->getKey());
        $path = $file->storePubliclyAs(
            $directory,
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName(),
            ['disk' => 'public']
        );

        $caseId = $subject instanceof ConvivenciaCase
            ? $subject->id
            : ($subject->getAttribute('case_id') ?: null);

        $attachment = $subject->attachments()->create([
            'case_id' => $caseId,
            'student_profile_id' => $payload['student_profile_id'] ?? $studentId,
            'category' => $payload['category'] ?? 'otro',
            'confidentiality_level' => $payload['confidentiality_level'] ?? 'general',
            'is_sensitive' => $defaultSensitive || in_array(($payload['confidentiality_level'] ?? 'general'), ['reservada', 'confidencial', 'alta_confidencialidad'], true),
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'notes' => $payload['notes'] ?? null,
            'uploaded_by' => $userId,
        ]);

        return response()->json([
            'message' => 'Adjunto cargado correctamente.',
            'data' => $attachment->load('uploadedBy:id,name'),
        ], 201);
    }

    private function canReadDocument(ConvivenciaAttachment $attachment): bool
    {
        $user = request()->user();
        $attachable = $attachment->attachable;

        return match (true) {
            $attachable instanceof ConvivenciaCase => $this->accessService->canViewCase($user, $attachable),
            $attachable instanceof ConvivenciaComplaint => $this->accessService->canViewComplaint($user, $attachable),
            $attachable instanceof ConvivenciaDerivation => $this->accessService->canViewDerivation($user, $attachable),
            $attachable instanceof ConvivenciaPlan => $this->accessService->canViewPlan($user, $attachable),
            $attachable instanceof ConvivenciaInterview => $this->accessService->canViewInterview($user, $attachable),
            $attachable instanceof ConvivenciaMeasure => $this->accessService->canViewMeasure($user, $attachable),
            $attachable instanceof ConvivenciaDailyLog => $this->accessService->canViewDailyLog($user, $attachable),
            $attachable instanceof ConvivenciaProtocolActivation => $this->accessService->canViewProtocolActivation($user, $attachable),
            default => false,
        };
    }

    private function canDeleteDocument(ConvivenciaAttachment $attachment): bool
    {
        $user = request()->user();
        $attachable = $attachment->attachable;

        return match (true) {
            $attachable instanceof ConvivenciaCase => $this->accessService->canEditCases($user),
            $attachable instanceof ConvivenciaComplaint => $this->accessService->canManageComplaints($user),
            $attachable instanceof ConvivenciaDerivation => $this->accessService->canManageInternalDerivations($user) || $this->accessService->canManageExternalDerivations($user),
            $attachable instanceof ConvivenciaPlan => $this->accessService->canManagePlans($user),
            $attachable instanceof ConvivenciaInterview => $this->accessService->canManageInterviews($user),
            $attachable instanceof ConvivenciaMeasure => $this->accessService->canManageMeasures($user),
            $attachable instanceof ConvivenciaDailyLog => $this->accessService->canManageDailyLogs($user),
            $attachable instanceof ConvivenciaProtocolActivation => $this->accessService->canActivateProtocols($user),
            default => false,
        };
    }
}
