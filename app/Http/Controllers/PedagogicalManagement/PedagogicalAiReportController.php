<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Resources\PedagogicalManagement\PedagogicalAiReportResource;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentAiReport;
use App\Services\PedagogicalManagement\PedagogicalAiReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedagogicalAiReportController extends Controller
{
    public function store(Request $request, PedagogicalInstrument $instrument, PedagogicalAiReportService $service): JsonResponse
    {
        $this->authorize('generateAiReport', $instrument);
        $file = $instrument->latestFile()->first();
        abort_unless($file, 422, 'El instrumento no tiene una versión disponible para informar.');
        $report = $service->requestReport($instrument, $file, $request->user(), $request);

        return response()->json(['data' => new PedagogicalAiReportResource($report->load('instrumentFile', 'requester:id,name'))], 202);
    }

    public function show(
        PedagogicalInstrument $instrument,
        PedagogicalInstrumentAiReport $aiReport,
    ): PedagogicalAiReportResource {
        abort_unless((int) $aiReport->instrument_id === (int) $instrument->id, 404);
        $this->authorize('generateAiReport', $instrument);

        return new PedagogicalAiReportResource($aiReport->load('instrumentFile', 'requester:id,name'));
    }
}
