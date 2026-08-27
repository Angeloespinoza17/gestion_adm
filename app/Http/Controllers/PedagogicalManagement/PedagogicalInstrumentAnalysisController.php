<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Resources\PedagogicalManagement\AnalysisRunResource;
use App\Models\PedagogicalManagement\PedagogicalInstrument;
use App\Models\PedagogicalManagement\PedagogicalInstrumentAnalysisRun;
use App\Services\PedagogicalManagement\PedagogicalInstrumentAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedagogicalInstrumentAnalysisController extends Controller
{
    public function store(Request $request, PedagogicalInstrument $instrument, PedagogicalInstrumentAnalysisService $analysis): JsonResponse
    {
        $this->authorize('analyze', $instrument);
        $file = $instrument->latestFile()->first();
        abort_unless($file, 422, 'El instrumento no contiene un archivo analizable.');
        $run = $analysis->requestAnalysis($instrument, $file, $request->user(), $request);

        return response()->json(['data' => new AnalysisRunResource($run)], 202);
    }

    public function show(PedagogicalInstrument $instrument, PedagogicalInstrumentAnalysisRun $analysis): AnalysisRunResource
    {
        abort_unless((int) $analysis->instrument_id === (int) $instrument->id, 404);
        $this->authorize('view', $instrument);

        return new AnalysisRunResource($analysis->load(['validationResults.resolver', 'validationResults.events.performer']));
    }
}
