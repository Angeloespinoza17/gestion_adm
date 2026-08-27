<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Enums\PedagogicalManagement\PrintRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\PedagogicalManagement\PedagogicalInstrumentPrintRequest;
use App\Services\PedagogicalManagement\PedagogicalInstrumentFileService;
use App\Services\PedagogicalManagement\PedagogicalPrintRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PedagogicalPrintRequestController extends Controller
{
    public function index(Request $request, PedagogicalPrintRequestService $service): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('pedagogical-print-requests.view'), 403);
        $filters = $request->validate([
            'status' => ['sometimes', Rule::enum(PrintRequestStatus::class)],
            'search' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'between:10,100'],
        ]);
        $query = PedagogicalInstrumentPrintRequest::query()
            ->with($service->relations())
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $builder, string $search) => $builder->whereHas('instrument', function (Builder $instrument) use ($search): void {
                $instrument->where('title', 'like', '%'.$search.'%')
                    ->orWhereHas('owner', fn (Builder $owner) => $owner->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('subject', fn (Builder $subject) => $subject->where('name', 'like', '%'.$search.'%'));
            }));
        $counts = (clone $query)->reorder()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $paginator = $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'in_process' THEN 1 WHEN status = 'printed' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')->paginate((int) ($filters['per_page'] ?? 15));

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (PedagogicalInstrumentPrintRequest $item): array => $this->serialize($item)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'status_counts' => $counts,
            ],
        ]);
    }

    public function file(
        Request $request,
        PedagogicalInstrumentPrintRequest $printRequest,
        PedagogicalInstrumentFileService $files,
    ): BinaryFileResponse {
        abort_unless(
            $request->user()?->hasPermission('pedagogical-print-requests.download')
                || $request->user()?->hasPermission('pedagogical-print-requests.print'),
            403,
        );
        $file = $printRequest->instrumentFile;
        $download = $request->boolean('download');
        $safeFilename = str_replace(['"', "\r", "\n"], '', $file->original_filename);

        return response()->file($files->absolutePath($file), [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$safeFilename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    public function action(
        Request $request,
        PedagogicalInstrumentPrintRequest $printRequest,
        PedagogicalPrintRequestService $service,
    ): JsonResponse {
        $data = $request->validate(['action' => ['required', Rule::in(['downloaded', 'printed', 'in_process', 'completed'])]]);
        $permission = match ($data['action']) {
            'downloaded' => 'pedagogical-print-requests.download',
            'printed' => 'pedagogical-print-requests.print',
            default => 'pedagogical-print-requests.complete',
        };
        abort_unless($request->user()?->hasPermission($permission), 403);
        $updated = $service->register($printRequest, $data['action'], $request->user());

        return response()->json(['message' => 'Estado de impresión actualizado.', 'data' => $this->serialize($updated)]);
    }

    private function serialize(PedagogicalInstrumentPrintRequest $item): array
    {
        return [
            'id' => $item->uuid,
            'status' => $item->status?->value ?? $item->status,
            'download_count' => $item->download_count,
            'print_count' => $item->print_count,
            'last_downloaded_at' => $item->last_downloaded_at?->toIso8601String(),
            'last_printed_at' => $item->last_printed_at?->toIso8601String(),
            'completed_at' => $item->completed_at?->toIso8601String(),
            'created_at' => $item->created_at?->toIso8601String(),
            'school' => $item->school ? ['id' => $item->school->id, 'name' => $item->school->name, 'rbd' => $item->school->rbd] : null,
            'instrument' => [
                'id' => $item->instrument->uuid,
                'title' => $item->instrument->title,
                'owner' => $item->instrument->owner ? ['id' => $item->instrument->owner->id, 'name' => $item->instrument->owner->name] : null,
                'subject' => $item->instrument->subject ? ['id' => $item->instrument->subject->id, 'name' => $item->instrument->subject->resolvedDisplayName()] : null,
                'courses' => $item->instrument->courses->map(fn ($course): array => ['id' => $course->id, 'name' => $course->display_name])->values(),
            ],
            'file' => [
                'id' => $item->instrumentFile->uuid,
                'version' => $item->instrumentFile->version,
                'original_filename' => $item->instrumentFile->original_filename,
                'file_size' => $item->instrumentFile->file_size,
                'page_count' => $item->instrumentFile->page_count,
            ],
            'review' => [
                'id' => $item->review->uuid,
                'decision' => $item->review->decision?->value ?? $item->review->decision,
                'notes' => $item->review->coordinator_notes,
                'reviewed_at' => $item->review->reviewed_at?->toIso8601String(),
                'reviewer' => $item->review->reviewer ? ['id' => $item->review->reviewer->id, 'name' => $item->review->reviewer->name] : null,
            ],
        ];
    }
}
