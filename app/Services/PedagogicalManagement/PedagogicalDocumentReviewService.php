<?php

namespace App\Services\PedagogicalManagement;

use App\Enums\PedagogicalManagement\AiReportStatus;
use App\Enums\PedagogicalManagement\InstrumentWorkflowStatus;
use App\Enums\PedagogicalManagement\PrintRequestStatus;
use App\Enums\PedagogicalManagement\ReviewDecision;
use App\Models\PedagogicalManagement\PedagogicalGuidanceDocument;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentAiReport;
use App\Models\PedagogicalManagement\PedagogicalInstrumentPrintRequest;
use App\Models\PedagogicalManagement\PedagogicalInstrumentReview;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedagogicalDocumentReviewService
{
    public function __construct(
        private readonly AuditEventWriter $audit,
        private readonly PedagogicalReportProjectionService $statistics,
    ) {}

    /** @param array<string,mixed> $data */
    public function decide(PedagogicalInstrument $instrument, array $data, User $actor, Request $request): PedagogicalInstrumentReview
    {
        $decision = ReviewDecision::from((string) $data['decision']);
        $review = DB::transaction(function () use ($instrument, $data, $actor, $decision): PedagogicalInstrumentReview {
            $locked = PedagogicalInstrument::query()
                ->with('latestFile')
                ->whereKey($instrument->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->workflow_status, [InstrumentWorkflowStatus::Submitted, InstrumentWorkflowStatus::Resubmitted], true)) {
                throw ValidationException::withMessages([
                    'decision' => 'Este instrumento ya tiene una resolución vigente. Espera una nueva rectificación antes de revisarlo nuevamente.',
                ]);
            }
            $file = $locked->latestFile;
            if (! $file) {
                throw ValidationException::withMessages(['decision' => 'El instrumento no tiene un archivo disponible para revisar.']);
            }

            $shareAiReport = $decision === ReviewDecision::RectificationRequested
                && (bool) ($data['share_ai_report'] ?? false);
            $aiReport = $this->resolveAiReport($locked, $file->id, $data, $shareAiReport);
            $guidance = $this->resolveGuidanceDocuments($locked, (array) ($data['guidance_document_ids'] ?? []));
            $review = PedagogicalInstrumentReview::query()->create([
                'instrument_id' => $locked->id,
                'instrument_file_id' => $file->id,
                'decision' => $decision,
                'coordinator_notes' => isset($data['coordinator_notes']) ? trim((string) $data['coordinator_notes']) : null,
                'ai_report_id' => $aiReport?->id,
                'share_ai_report' => $shareAiReport,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);
            if ($guidance->isNotEmpty()) {
                $review->guidanceDocuments()->sync($guidance->pluck('id')->all());
            }

            $approved = $decision !== ReviewDecision::RectificationRequested;
            $locked->forceFill([
                'workflow_status' => $decision->workflowStatus(),
                'approved_at' => $approved ? now() : null,
                'updated_by' => $actor->id,
            ])->save();

            if ($approved) {
                PedagogicalInstrumentPrintRequest::query()->firstOrCreate(
                    ['review_id' => $review->id],
                    [
                        'school_id' => $locked->school_id,
                        'instrument_id' => $locked->id,
                        'instrument_file_id' => $file->id,
                        'status' => PrintRequestStatus::Pending,
                    ],
                );
            }

            return $review;
        }, 3);

        $this->audit->write(
            'pedagogical.instrument.document_reviewed',
            $decision->value,
            $instrument,
            actor: $actor,
            schoolId: $instrument->school_id,
            academicYearId: $instrument->academic_year_id,
            after: [
                'review_uuid' => $review->uuid,
                'decision' => $decision->value,
                'file_uuid' => $review->instrumentFile()->value('uuid'),
                'share_ai_report' => $review->share_ai_report,
                'guidance_documents' => $review->guidanceDocuments()->pluck('uuid')->all(),
            ],
            request: $request,
        );

        try {
            $this->statistics->projectReview($review);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $review->fresh()->load([
            'instrument:id,owner_user_id,school_id,academic_year_id',
            'instrumentFile',
            'reviewer:id,name',
            'guidanceDocuments',
            'aiReport.instrumentFile',
            'aiReport.requester:id,name',
            'printRequest',
        ]);
    }

    /** @param array<string,mixed> $data */
    private function resolveAiReport(PedagogicalInstrument $instrument, int $fileId, array $data, bool $share): ?PedagogicalInstrumentAiReport
    {
        $reportUuid = trim((string) ($data['ai_report_id'] ?? ''));
        if ($reportUuid === '') {
            if ($share) {
                throw ValidationException::withMessages(['ai_report_id' => 'Selecciona un informe IA completado antes de compartirlo.']);
            }

            return null;
        }

        $report = PedagogicalInstrumentAiReport::query()->where('uuid', $reportUuid)->firstOrFail();
        if ((int) $report->instrument_id !== (int) $instrument->id || (int) $report->instrument_file_id !== $fileId) {
            throw ValidationException::withMessages(['ai_report_id' => 'El informe IA no corresponde a la versión actual del instrumento.']);
        }
        if ($report->status !== AiReportStatus::Completed) {
            throw ValidationException::withMessages(['ai_report_id' => 'El informe IA todavía no está disponible para esta decisión.']);
        }

        return $report;
    }

    private function resolveGuidanceDocuments(PedagogicalInstrument $instrument, array $uuids)
    {
        $uuids = array_values(array_unique(array_filter(array_map('strval', $uuids))));
        $documents = PedagogicalGuidanceDocument::query()
            ->where('school_id', $instrument->school_id)
            ->where('active', true)
            ->whereIn('uuid', $uuids)
            ->get();
        if ($documents->count() !== count($uuids)) {
            throw ValidationException::withMessages(['guidance_document_ids' => 'Uno o más documentos de orientación no están habilitados para este establecimiento.']);
        }

        return $documents;
    }
}
