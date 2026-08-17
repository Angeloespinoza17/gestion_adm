<?php

namespace App\Http\Controllers\SocialWork;

use App\Http\Controllers\Controller;
use App\Models\SocialWork\Alert;
use App\Models\SocialWork\JunaebBenefit;
use App\Models\SocialWork\MedicalCertificate;
use App\Models\SocialWork\Referral;
use App\Models\SocialWork\SocialCase;
use App\Services\SocialWork\AccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AccessService $access): JsonResponse
    {
        $cases = $access->applyCaseVisibility(SocialCase::query(), $request->user());
        $base = clone $cases;
        return response()->json(['data' => [
            'active_cases' => (clone $base)->whereNotIn('status', ['cerrado', 'anulado'])->count(),
            'cases_by_status' => (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'cases_by_risk' => (clone $base)->selectRaw('risk_level, count(*) as total')->groupBy('risk_level')->pluck('total', 'risk_level'),
            'overdue_cases' => (clone $base)->whereNotIn('status', ['cerrado', 'anulado'])->where('due_at', '<', now())->count(),
            'inactive_cases' => (clone $base)->whereNotIn('status', ['cerrado', 'anulado'])->where('last_activity_at', '<', now()->subDays(30))->count(),
            'new_alerts' => Alert::where('status', 'nueva')->count(),
            'critical_alerts' => Alert::where('status', 'nueva')->where('severity', 'critico')->count(),
            'pending_referrals' => Referral::whereIn('status', ['enviada', 'recibida', 'en_revision', 'antecedentes_pendientes'])->count(),
            'pending_junaeb' => JunaebBenefit::where('status', 'pendiente')->count(),
            'expiring_certificates' => MedicalCertificate::where('status', 'vigente')->whereBetween('expires_on', [today(), today()->addDays(30)])->count(),
        ]]);
    }
}
