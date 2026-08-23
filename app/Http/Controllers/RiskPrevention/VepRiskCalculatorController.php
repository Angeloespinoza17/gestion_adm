<?php

namespace App\Http\Controllers\RiskPrevention;

use App\Http\Controllers\Controller;
use App\Models\RiskPrevention\RiskMethodology;
use App\Services\RiskPrevention\VepRiskCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VepRiskCalculatorController extends Controller
{
    public function __invoke(Request $request, VepRiskCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'probability' => ['required', 'integer', Rule::in([1, 2, 4])],
            'consequence' => ['required', 'integer', Rule::in([1, 2, 4])],
            'methodology_id' => ['nullable', 'integer', 'exists:prevent_risk_methodologies,id'],
            'calculated_score' => ['prohibited'], 'calculated_level' => ['prohibited'],
        ]);
        $methodology = isset($data['methodology_id'])
            ? RiskMethodology::query()->findOrFail($data['methodology_id'])
            : RiskMethodology::query()->where('code', config('risk_matrix.methodology_code'))->where('active', true)->latest('version_number')->firstOrFail();

        return response()->json(['data' => $calculator->calculate($data['probability'], $data['consequence'], $methodology)]);
    }
}
