<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\RiskPrevention\RiskMatrixVersion;
use App\Services\RiskPrevention\RiskMatrixAuditService;
use App\Services\RiskPrevention\RiskMatrixExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RiskMatrixExportController extends Controller
{
    public function __invoke(RiskMatrixVersion $version, Request $request, RiskMatrixExportService $exports, RiskMatrixAuditService $audit)
    {
        $this->authorize('export', $version);
        $historical = $request->query('format') === 'historical';
        $path = $exports->create($version, $historical);
        $audit->record($version, 'exported', [], ['format' => $historical ? 'xlsx_historical' : 'xlsx_modern']);

        return Storage::disk('local')->download($path, basename($path), ['Cache-Control' => 'no-store, private']);
    }
}
