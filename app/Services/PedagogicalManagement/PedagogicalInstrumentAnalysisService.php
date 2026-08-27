<?php

namespace App\Services\PedagogicalManagement;

use App\Contracts\PedagogicalManagement\PedagogicalInstrumentReviewerInterface;
use App\Enums\PedagogicalManagement\AnalysisRunStatus;
use App\Enums\PedagogicalManagement\InstrumentStatus;
use App\Exceptions\PedagogicalManagement\PedagogicalInstrumentException;
use App\Jobs\PedagogicalManagement\AnalyzePedagogicalInstrumentJob;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentAnalysisRun;
use App\Models\PedagogicalManagement\PedagogicalInstrumentFile;
use App\Models\User;
use App\Services\LibroDigital\AuditEventWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PedagogicalInstrumentAnalysisService
{
    public function __construct(
        private readonly PedagogicalInstrumentReviewerInterface $reviewer,
        private readonly PedagogicalInstrumentFileService $files,
        private readonly AuditEventWriter $audit,
    ) {}

    public function isConfigured(): bool
    {
        return $this->reviewer->isConfigured();
    }

    public function requestAnalysis(
        PedagogicalInstrument $instrument,
        PedagogicalInstrumentFile $file,
        User $actor,
        Request $request,
    ): PedagogicalInstrumentAnalysisRun {
        if (! $this->reviewer->isConfigured()) {
            throw new PedagogicalInstrumentException(
                'El motor determinístico de revisión no está disponible.',
                'DETERMINISTIC_REVIEW_NOT_CONFIGURED',
                503,
            );
        }
        if ($file->mime_type !== 'application/pdf') {
            throw new PedagogicalInstrumentException(
                'La revisión determinística solo admite PDF. Los documentos Word pueden revisarse desde el informe documental OpenAI.',
                'DETERMINISTIC_REVIEW_PDF_REQUIRED',
                422,
            );
        }

        $run = DB::transaction(function () use ($instrument, $file, $actor): PedagogicalInstrumentAnalysisRun {
            PedagogicalInstrument::query()->whereKey($instrument->id)->lockForUpdate()->firstOrFail();
            $existing = PedagogicalInstrumentAnalysisRun::query()
                ->where('instrument_file_id', $file->id)
                ->where('rules_version', $this->reviewer->promptVersion())
                ->whereIn('status', [AnalysisRunStatus::Pending->value, AnalysisRunStatus::Processing->value])
                ->latest('id')->first();
            if ($existing) {
                return $existing;
            }

            $created = PedagogicalInstrumentAnalysisRun::query()->create([
                'instrument_id' => $instrument->id,
                'instrument_file_id' => $file->id,
                'status' => AnalysisRunStatus::Pending,
                'extractor' => $this->reviewer->provider(),
                'extractor_version' => $this->reviewer->model(),
                'rules_version' => $this->reviewer->promptVersion(),
                'created_by' => $actor->id,
            ]);
            $instrument->forceFill(['status' => InstrumentStatus::PendingAnalysis, 'updated_by' => $actor->id])->save();

            return $created;
        }, 3);

        if ($run->wasRecentlyCreated) {
            AnalyzePedagogicalInstrumentJob::dispatch($run->id)
                ->onQueue((string) config('pedagogical_management.analysis.queue', 'default'))
                ->afterCommit();
            $this->audit->write(
                'pedagogical.instrument.analysis_requested', 'analyze', $instrument, actor: $actor,
                schoolId: $instrument->school_id, academicYearId: $instrument->academic_year_id,
                after: [
                    'analysis_uuid' => $run->uuid,
                    'file_uuid' => $file->uuid,
                    'provider' => $run->extractor,
                    'model' => $run->extractor_version,
                    'rules_version' => $run->rules_version,
                ],
                request: $request,
            );
        }

        return $run;
    }

    public function process(PedagogicalInstrumentAnalysisRun $run): void
    {
        $claimed = DB::transaction(function () use ($run): bool {
            $locked = PedagogicalInstrumentAnalysisRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== AnalysisRunStatus::Pending) {
                return false;
            }
            $locked->forceFill([
                'status' => AnalysisRunStatus::Processing,
                'started_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ])->save();
            $locked->instrument()->update(['status' => InstrumentStatus::Processing->value]);

            return true;
        }, 3);
        if (! $claimed) {
            return;
        }

        $run->refresh()->load([
            'instrument.owner:id,name',
            'instrument.subject:id,name',
            'instrument.courses:id,display_name',
            'instrumentFile',
        ]);
        $instrument = $run->instrument;
        $file = $run->instrumentFile;

        try {
            $review = $this->reviewer->review($instrument, $file, $this->files->absolutePath($file));
            $findings = $this->findings($review);
            $hasErrors = collect($findings)->contains(fn (array $finding): bool => $finding['outcome'] === 'fail');
            $hasSuggestions = collect($findings)->contains(fn (array $finding): bool => $finding['outcome'] !== 'fail');

            DB::transaction(function () use ($run, $review, $findings, $instrument, $hasErrors, $hasSuggestions): void {
                $locked = PedagogicalInstrumentAnalysisRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== AnalysisRunStatus::Processing) {
                    return;
                }
                $locked->validationResults()->delete();
                foreach ($findings as $finding) {
                    $locked->validationResults()->create($finding);
                }
                $locked->forceFill([
                    'status' => ($hasErrors || $hasSuggestions) ? AnalysisRunStatus::CompletedWithWarnings : AnalysisRunStatus::Completed,
                    'extractor_version' => $review['model'],
                    'extracted_text' => $review['extracted_text'] ?? null,
                    'normalized_text' => $review['normalized_text'] ?? null,
                    'extracted_data' => [
                        'summary' => $review['summary'],
                        'analysis' => $review['extracted_data'] ?? [],
                        'provider' => $this->reviewer->provider(),
                        'extractor_version' => $review['model'],
                        'rules_version' => $this->reviewer->promptVersion(),
                        'usage' => $review['usage'],
                    ],
                    'finished_at' => now(),
                ])->save();
                $instrument->forceFill(['status' => match (true) {
                    $hasErrors => InstrumentStatus::ReviewRequired,
                    $hasSuggestions => InstrumentStatus::ValidatedWithWarnings,
                    default => InstrumentStatus::Validated,
                }])->save();
            }, 3);
        } catch (Throwable $exception) {
            DB::transaction(function () use ($run): void {
                $locked = PedagogicalInstrumentAnalysisRun::query()->whereKey($run->id)->lockForUpdate()->first();
                if ($locked && $locked->status === AnalysisRunStatus::Processing) {
                    $locked->forceFill([
                        'status' => AnalysisRunStatus::Pending,
                        'error_code' => 'DETERMINISTIC_REVIEW_RETRY_PENDING',
                        'error_message' => 'La revisión determinística será reintentada por la cola.',
                    ])->save();
                    $locked->instrument()->update(['status' => InstrumentStatus::PendingAnalysis->value]);
                }
            }, 3);
            throw $exception;
        }
    }

    public function markFailed(PedagogicalInstrumentAnalysisRun|int $run, string $code, string $safeMessage): void
    {
        $runId = $run instanceof PedagogicalInstrumentAnalysisRun ? $run->id : $run;
        DB::transaction(function () use ($runId, $code, $safeMessage): void {
            $locked = PedagogicalInstrumentAnalysisRun::query()->whereKey($runId)->lockForUpdate()->first();
            if (! $locked || $locked->status?->terminal()) {
                return;
            }
            $locked->validationResults()->firstOrCreate(['code' => $code], [
                'category' => 'technical',
                'severity' => 'critical',
                'outcome' => 'fail',
                'reliability' => 'system',
                'title' => 'No fue posible revisar el instrumento',
                'message' => $safeMessage,
                'is_blocking' => false,
            ]);
            $locked->forceFill([
                'status' => AnalysisRunStatus::Failed,
                'error_code' => $code,
                'error_message' => $safeMessage,
                'finished_at' => now(),
            ])->save();
            $locked->instrument()->update(['status' => InstrumentStatus::Failed->value]);
        }, 3);
    }

    /** @param array<string,mixed> $review @return list<array<string,mixed>> */
    private function findings(array $review): array
    {
        if (isset($review['findings']) && is_array($review['findings'])) {
            return collect($review['findings'])
                ->map(fn (array $finding): array => $this->deterministicFinding($finding))
                ->values()
                ->all();
        }

        $findings = [];
        foreach ((array) $review['errors'] as $index => $item) {
            $findings[] = $this->finding('error', (int) $index, (array) $item);
        }
        foreach ((array) $review['suggestions'] as $index => $item) {
            $findings[] = $this->finding('suggestion', (int) $index, (array) $item);
        }

        return $findings;
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    private function finding(string $type, int $index, array $item): array
    {
        $isError = $type === 'error';

        return [
            'code' => strtoupper($type).'_'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            'category' => $type,
            'severity' => $isError ? 'error' : 'info',
            'outcome' => $isError ? 'fail' : 'warning',
            'reliability' => 'ai_generated',
            'title' => $item['title'],
            'message' => $item['description'],
            'source_excerpt' => $item['evidence'] !== '' ? $item['evidence'] : null,
            'page_number' => ((int) $item['page']) > 0 ? (int) $item['page'] : null,
            'is_blocking' => false,
        ];
    }

    /** @param array<string,mixed> $finding @return array<string,mixed> */
    private function deterministicFinding(array $finding): array
    {
        $outcome = (string) ($finding['outcome'] ?? 'not_evaluable');

        return [
            'code' => (string) $finding['code'],
            'category' => $outcome === 'fail' ? 'error' : 'suggestion',
            'severity' => (string) ($finding['severity'] ?? 'warning'),
            'outcome' => $outcome,
            'reliability' => (string) ($finding['reliability'] ?? 'not_evaluable'),
            'field_path' => $finding['field_path'] ?? null,
            'title' => (string) $finding['title'],
            'message' => (string) $finding['message'],
            'detected_value' => $finding['detected_value'] ?? null,
            'expected_value' => $finding['expected_value'] ?? null,
            'source_excerpt' => $finding['source_excerpt'] ?? null,
            'page_number' => $finding['page_number'] ?? null,
            'is_blocking' => (bool) ($finding['is_blocking'] ?? false),
        ];
    }
}
