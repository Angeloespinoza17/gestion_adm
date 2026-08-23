<?php

namespace App\Http\Controllers\Psychology;

use App\Http\Controllers\Controller;
use App\Models\Psychology\PsychologyActivity;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyReferral;
use App\Models\Psychology\PsychologyTask;
use App\Services\Psychology\PsychologyAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PsychologyDashboardController extends Controller
{
    public function __construct(private readonly PsychologyAccessService $access) {}

    public function __invoke(Request $request): JsonResponse
    {
        $aggregateOnly = ! $request->user()->isSuperAdmin()
            && ! $this->access->isScopedPsychologist($request->user())
            && $request->user()->hasPermission('psychology.reports.aggregate')
            && ! $request->user()->hasPermission('psychology.referrals.view_all')
            && ! $request->user()->hasPermission('psychology.cases.view_all');
        $referrals = PsychologyReferral::query();
        $this->access->applyReferralVisibility($referrals, $request->user(), true);
        $cases = PsychologyCase::query();
        $this->access->applyCaseVisibility($cases, $request->user(), true);
        $caseIds = (clone $cases)->pluck('id');
        $scheduledActivities = PsychologyActivity::query()->whereIn('case_id', $caseIds)->where('activity_on', '>', now()->toDateString());
        $this->access->applyActivityVisibility($scheduledActivities, $request->user());
        $visibleTasks = PsychologyTask::query()->whereIn('case_id', $caseIds);
        $this->access->applyTaskVisibility($visibleTasks, $request->user());
        $inactiveSince = now()->subDays((int) config('psychology.inactive_days', 14));
        $metrics = [
            'referrals_received' => (clone $referrals)->whereNotNull('referred_at')->count(),
            'pending_review' => (clone $referrals)->whereIn('status', ['submitted', 'under_review'])->count(),
            'information_requested' => (clone $referrals)->where('status', 'information_requested')->count(),
            'active_cases' => (clone $cases)->whereNotIn('status', ['closed'])->count(),
            'high_priority_cases' => (clone $cases)->whereIn('priority', ['high', 'critical'])->where('status', '!=', 'closed')->count(),
            'inactive_cases' => (clone $cases)->where('status', '!=', 'closed')->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', $inactiveSince))->count(),
            'overdue_tasks' => (clone $visibleTasks)->whereIn('status', ['pending', 'in_progress'])->where('due_at', '<', now())->count(),
            'upcoming_followups' => PsychologyActivity::query()->whereIn('case_id', $caseIds)->whereBetween('next_action_on', [now()->toDateString(), now()->addDays(7)->toDateString()])->tap(fn ($query) => $this->access->applyActivityVisibility($query, $request->user()))->count(),
            'scheduled_sessions' => $scheduledActivities->count(),
            'closed_period' => (clone $cases)->whereBetween('closed_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'avg_first_response_hours' => 0,
        ];
        $responseExpression = DB::getDriverName() === 'sqlite' ? 'AVG((julianday(first_reviewed_at) - julianday(referred_at)) * 24)' : 'AVG(TIMESTAMPDIFF(HOUR, referred_at, first_reviewed_at))';
        $metrics['avg_first_response_hours'] = round((float) (clone $referrals)->whereNotNull('first_reviewed_at')->selectRaw("{$responseExpression} as average")->value('average'), 1);
        $byStatus = (clone $referrals)->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->orderByDesc('total')->get();
        $byUrgency = (clone $referrals)->selectRaw('COALESCE(professional_priority, suggested_urgency) as label, COUNT(*) as total')->groupByRaw('COALESCE(professional_priority, suggested_urgency)')->get();
        $monthly = (clone $referrals)->where('created_at', '>=', now()->subMonths(11)->startOfMonth())->get(['created_at'])->groupBy(fn ($r) => $r->created_at->format('Y-m'))->map->count()->map(fn ($total, $month) => ['month' => $month, 'total' => $total])->values();
        $workload = (clone $cases)->where('status', '!=', 'closed')->join('users', 'users.id', '=', 'psychology_cases.responsible_user_id')->select('users.name', DB::raw('COUNT(*) as total'))->groupBy('users.id', 'users.name')->orderByDesc('total')->get();

        return response()->json(['metrics' => $metrics, 'charts' => ['by_status' => $byStatus, 'by_urgency' => $byUrgency, 'monthly' => $monthly, 'workload' => $workload], 'recent' => ['referrals' => $aggregateOnly ? [] : (clone $referrals)->with(['student:id,first_name,last_name,registered_name', 'assignedUser:id,name'])->latest()->limit(8)->get(), 'tasks' => $aggregateOnly ? [] : (clone $visibleTasks)->with('case:id,code')->whereIn('status', ['pending', 'in_progress'])->orderBy('due_at')->limit(8)->get()]]);
    }
}
