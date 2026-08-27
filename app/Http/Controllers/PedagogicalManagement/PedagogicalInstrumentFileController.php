<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\StoreInstrumentFileRequest;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentFile;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\PedagogicalManagement\PedagogicalInstrumentFileService;
use App\Services\PedagogicalManagement\PedagogicalInstrumentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PedagogicalInstrumentFileController extends Controller
{
    public function store(
        StoreInstrumentFileRequest $request,
        PedagogicalInstrument $instrument,
        PedagogicalInstrumentService $service,
    ): JsonResponse {
        $file = $service->addFileVersion($instrument, $request->instrumentFile(), $request->user(), $request);

        return response()->json(['data' => [
            'file' => ['id' => $file->uuid, 'version' => $file->version, 'original_filename' => $file->original_filename],
        ]], 201);
    }

    public function view(
        PedagogicalInstrument $instrument,
        PedagogicalInstrumentFile $file,
        PedagogicalInstrumentFileService $files,
        AuditEventWriter $audit,
    ): BinaryFileResponse {
        return $this->serve($instrument, $file, $files, $audit, false);
    }

    public function download(
        PedagogicalInstrument $instrument,
        PedagogicalInstrumentFile $file,
        PedagogicalInstrumentFileService $files,
        AuditEventWriter $audit,
    ): BinaryFileResponse {
        return $this->serve($instrument, $file, $files, $audit, true);
    }

    private function serve(
        PedagogicalInstrument $instrument,
        PedagogicalInstrumentFile $file,
        PedagogicalInstrumentFileService $files,
        AuditEventWriter $audit,
        bool $download,
    ): BinaryFileResponse {
        abort_unless((int) $file->instrument_id === (int) $instrument->id, 404);
        $this->authorize('download', $instrument);
        $audit->write(
            'pedagogical.instrument.file_accessed', $download ? 'download' : 'view', $instrument,
            actor: request()->user(), schoolId: $instrument->school_id, academicYearId: $instrument->academic_year_id,
            after: ['file_uuid' => $file->uuid, 'version' => $file->version], request: request(),
        );
        $isPdf = $file->mime_type === 'application/pdf';
        $disposition = $download || ! $isPdf ? 'attachment' : 'inline';
        $safeFilename = str_replace(['"', "\r", "\n"], '', $file->original_filename);

        return response()->file($files->absolutePath($file), [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => $disposition.'; filename="'.$safeFilename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
