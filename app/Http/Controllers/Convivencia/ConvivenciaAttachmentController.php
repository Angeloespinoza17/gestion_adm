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
use App\Models\Convivencia\ConvivenciaPlanActivity;
use App\Models\Convivencia\ConvivenciaProtocolActivation;
use App\Services\Convivencia\ConvivenciaAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConvivenciaAttachmentController extends Controller
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {}

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

    public function storeForPlanActivity(UploadConvivenciaAttachmentRequest $request, ConvivenciaPlanActivity $activity): JsonResponse
    {
        $plan = $activity->action->plan;
        $this->authorize('update', $plan);

        return $this->storeDocument(
            $request->validated(),
            $request->file('document'),
            $activity,
            $request->user()?->id,
            null,
            $plan->is_sensitive,
        );
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
        abort_unless(
            $this->accessService->canActivateProtocols($request->user())
            && $this->accessService->canViewProtocolActivation($request->user(), $activation),
            403
        );

        return $this->storeDocument($request->validated(), $request->file('document'), $activation, $request->user()?->id, $activation->case?->student_profile_id, true);
    }

    public function download(ConvivenciaAttachment $attachment): StreamedResponse
    {
        abort_unless($this->canReadDocument($attachment), 403);

        $disk = $this->documentDisk($attachment);
        abort_unless($disk !== null, 404);

        return Storage::disk($disk)->download($attachment->file_path, $attachment->original_name);
    }

    public function destroy(ConvivenciaAttachment $attachment): JsonResponse
    {
        abort_unless($this->canDeleteDocument($attachment), 403);

        if ($attachment->file_path) {
            Storage::disk('local')->delete($attachment->file_path);
            // Legacy compatibility only. Historical public files require a
            // separate, audited remediation instead of an implicit migration.
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json([
            'message' => 'Adjunto eliminado correctamente.',
        ]);
    }

    private function storeDocument(array $payload, UploadedFile $file, Model $subject, ?int $userId, ?int $studentId, bool $defaultSensitive = false): JsonResponse
    {
        $directory = sprintf('convivencia-private/%s/%d', class_basename($subject), $subject->getKey());
        $extension = strtolower((string) ($file->guessExtension() ?: $file->extension() ?: 'bin'));
        $path = $file->storeAs(
            $directory,
            Str::uuid()->toString().'.'.$extension,
            ['disk' => 'local']
        );
        abort_if($path === false, 500, 'No fue posible almacenar el adjunto.');
        $originalName = preg_replace(
            '/[^\pL\pN._ -]+/u',
            '_',
            basename(str_replace(["\0", "\r", "\n"], '', $file->getClientOriginalName()))
        ) ?: 'documento.'.$extension;

        $caseId = $subject instanceof ConvivenciaCase
            ? $subject->id
            : ($subject->getAttribute('case_id') ?: null);

        $attachment = $subject->attachments()->create([
            'case_id' => $caseId,
            'student_profile_id' => $payload['student_profile_id'] ?? $studentId,
            'category' => $payload['category'] ?? 'otro',
            'confidentiality_level' => $payload['confidentiality_level'] ?? 'general',
            'is_sensitive' => $defaultSensitive || ! in_array(
                ($payload['confidentiality_level'] ?? 'general'),
                ConvivenciaAttachment::NON_SENSITIVE_CONFIDENTIALITY_LEVELS,
                true
            ),
            'file_path' => $path,
            'original_name' => Str::limit($originalName, 191, ''),
            'mime_type' => $file->getMimeType(),
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

        if (! $this->accessService->canViewAttachment($user, $attachment)) {
            return false;
        }

        return match (true) {
            $attachable instanceof ConvivenciaCase => $this->accessService->canViewCase($user, $attachable),
            $attachable instanceof ConvivenciaComplaint => $this->accessService->canViewComplaint($user, $attachable),
            $attachable instanceof ConvivenciaDerivation => $this->accessService->canViewDerivation($user, $attachable),
            $attachable instanceof ConvivenciaPlan => $this->accessService->canViewPlan($user, $attachable),
            $attachable instanceof ConvivenciaPlanActivity => $attachable->action?->plan
                ? $this->accessService->canViewPlan($user, $attachable->action->plan)
                : false,
            $attachable instanceof ConvivenciaInterview => $this->accessService->canViewInterview($user, $attachable),
            $attachable instanceof ConvivenciaMeasure => $this->accessService->canViewMeasure($user, $attachable),
            $attachable instanceof ConvivenciaDailyLog => $this->accessService->canViewDailyLog($user, $attachable),
            $attachable instanceof ConvivenciaProtocolActivation => $this->accessService->canViewProtocolActivation($user, $attachable),
            default => $attachment->case
                ? $this->accessService->canViewCase($user, $attachment->case)
                : false,
        };
    }

    private function canDeleteDocument(ConvivenciaAttachment $attachment): bool
    {
        if (! $this->canReadDocument($attachment)) {
            return false;
        }

        $user = request()->user();
        $attachable = $attachment->attachable;

        return match (true) {
            $attachable instanceof ConvivenciaCase => $this->accessService->canEditCases($user)
                && $this->accessService->canViewCase($user, $attachable),
            $attachable instanceof ConvivenciaComplaint => $this->accessService->canManageComplaints($user)
                && $this->accessService->canViewComplaint($user, $attachable),
            $attachable instanceof ConvivenciaDerivation => ($this->accessService->canManageInternalDerivations($user)
                || $this->accessService->canManageExternalDerivations($user))
                && $this->accessService->canViewDerivation($user, $attachable),
            $attachable instanceof ConvivenciaPlan => $this->accessService->canManagePlans($user)
                && $this->accessService->canViewPlan($user, $attachable),
            $attachable instanceof ConvivenciaPlanActivity => $attachable->action?->plan
                ? $this->accessService->canManagePlans($user)
                    && $this->accessService->canViewPlan($user, $attachable->action->plan)
                : false,
            $attachable instanceof ConvivenciaInterview => $this->accessService->canManageInterviews($user)
                && $this->accessService->canViewInterview($user, $attachable),
            $attachable instanceof ConvivenciaMeasure => $this->accessService->canManageMeasures($user)
                && $this->accessService->canViewMeasure($user, $attachable),
            $attachable instanceof ConvivenciaDailyLog => $this->accessService->canManageDailyLogs($user)
                && $this->accessService->canViewDailyLog($user, $attachable),
            $attachable instanceof ConvivenciaProtocolActivation => $this->accessService->canActivateProtocols($user)
                && $this->accessService->canViewProtocolActivation($user, $attachable),
            default => $attachment->case
                ? $this->accessService->canEditCases($user)
                    && $this->accessService->canViewCase($user, $attachment->case)
                : false,
        };
    }

    private function documentDisk(ConvivenciaAttachment $attachment): ?string
    {
        if (Storage::disk('local')->exists($attachment->file_path)) {
            return 'local';
        }

        if (Storage::disk('public')->exists($attachment->file_path)) {
            return 'public';
        }

        return null;
    }
}
