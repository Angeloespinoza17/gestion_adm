<?php

namespace App\Http\Controllers\RelevantCalendar;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttachment;
use App\Services\RelevantCalendar\CalendarEventAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CalendarEventAttachmentController extends Controller
{
    public function __construct(
        private readonly CalendarEventAuditService $auditService,
    ) {
    }

    public function store(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('update', $calendarEvent);

        $payload = $request->validate([
            'document' => ['required', 'file', 'max:20480'],
        ]);

        /** @var UploadedFile $file */
        $file = $payload['document'];

        $path = $file->storeAs(
            sprintf('calendar-events/%d/attachments', $calendarEvent->id),
            now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName(),
            'local'
        );

        $attachment = $calendarEvent->attachments()->create([
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        $this->auditService->log(
            $calendarEvent,
            $request->user(),
            'attachment_uploaded',
            null,
            ['attachment_id' => $attachment->id, 'file_name' => $attachment->file_name],
            'Documento adjuntado al evento.',
        );

        return response()->json([
            'message' => 'Documento cargado correctamente.',
            'data' => $attachment->load('uploadedBy:id,name,email'),
        ], 201);
    }

    public function download(CalendarEventAttachment $calendarEventAttachment): StreamedResponse|JsonResponse
    {
        $calendarEvent = $calendarEventAttachment->event()->firstOrFail();
        $this->authorize('view', $calendarEvent);

        if (!Storage::disk('local')->exists($calendarEventAttachment->file_path)) {
            return response()->json(['message' => 'El archivo no está disponible.'], 404);
        }

        return Storage::disk('local')->download($calendarEventAttachment->file_path, $calendarEventAttachment->file_name);
    }

    public function destroy(CalendarEventAttachment $calendarEventAttachment): JsonResponse
    {
        $calendarEvent = $calendarEventAttachment->event()->firstOrFail();
        $this->authorize('update', $calendarEvent);

        $filePath = $calendarEventAttachment->file_path;
        $calendarEventAttachment->delete();

        if ($filePath) {
            Storage::disk('local')->delete($filePath);
        }

        $this->auditService->log(
            $calendarEvent,
            request()->user(),
            'attachment_deleted',
            ['file_path' => $filePath, 'file_name' => $calendarEventAttachment->file_name],
            null,
            'Documento eliminado del evento.',
        );

        return response()->json([
            'message' => 'Documento eliminado correctamente.',
        ]);
    }
}
