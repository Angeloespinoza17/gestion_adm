<?php

namespace App\Http\Controllers\Inspectoria;

use App\Http\Controllers\Controller;
use App\Models\PorterStudentWithdrawal;
use App\Services\Inspectoria\InspectoriaAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectoriaWithdrawalController extends Controller
{
    public function __construct(private readonly InspectoriaAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::WITHDRAWALS), 403);

        $scope = $request->query('scope') === 'history' ? 'history' : 'today';
        $search = trim((string) $request->query('search'));
        $courseSectionId = $request->integer('course_section_id') ?: null;
        $status = trim((string) $request->query('status'));
        $dateFrom = $request->filled('date_from') ? $request->date('date_from') : null;
        $dateTo = $request->filled('date_to') ? $request->date('date_to') : null;

        $query = PorterStudentWithdrawal::query()
            ->with([
                'studentProfile:id,first_name,last_name,registered_name,rut,general_status',
                'courseSection:id,display_name,section_name',
                'academicYear:id,name,year',
                'registeredBy:id,name',
                'authorizedBy:id,name',
            ]);

        $this->access->scopeToAssignedCourses($query, $request->user(), 'course_section_id');

        $query
            ->when($scope === 'today', fn (Builder $builder) => $builder->whereDate('withdrawn_at', today()))
            ->when($scope === 'history', function (Builder $builder) use ($dateFrom, $dateTo) {
                $builder->whereDate('withdrawn_at', '<', today())
                    ->when($dateFrom, fn (Builder $filtered) => $filtered->whereDate('withdrawn_at', '>=', $dateFrom))
                    ->when($dateTo, fn (Builder $filtered) => $filtered->whereDate('withdrawn_at', '<=', $dateTo));
            })
            ->when($courseSectionId, fn (Builder $builder) => $builder->where('course_section_id', $courseSectionId))
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $filtered) use ($search) {
                    $filtered->where('student_full_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('student_rut_snapshot', 'like', "%{$search}%")
                        ->orWhere('person_name', 'like', "%{$search}%")
                        ->orWhere('person_rut', 'like', "%{$search}%")
                        ->orWhere('course_name_snapshot', 'like', "%{$search}%");
                });
            });

        return response()->json(
            $query->latest('withdrawn_at')->paginate(min(max($request->integer('per_page', 15), 1), 100))
        );
    }

    public function show(Request $request, PorterStudentWithdrawal $withdrawal): JsonResponse
    {
        abort_unless($this->access->can($request->user(), InspectoriaAccessService::WITHDRAWALS), 403);
        abort_unless($this->access->canAccessCourse($request->user(), $withdrawal->course_section_id), 403);

        $withdrawal->load([
            'studentProfile:id,first_name,last_name,registered_name,rut,general_status',
            'courseSection:id,display_name,section_name',
            'academicYear:id,name,year',
            'registeredBy:id,name',
            'authorizedBy:id,name',
            'cancelledBy:id,name',
        ]);

        return response()->json(['data' => $withdrawal]);
    }
}
