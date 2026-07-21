<?php

namespace App\Http\Controllers\Informatica;

use App\Http\Controllers\Controller;
use App\Models\It\ItEquipment;
use App\Models\It\ItEquipmentAttachment;
use App\Models\It\ItEquipmentLoan;
use App\Models\It\ItEquipmentMaintenanceReport;
use App\Models\InventoryItem;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Informatica\InformaticaAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InformaticaCatalogController extends Controller
{
    public function __construct(
        private readonly InformaticaAccessService $accessService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->accessService->canViewModule($request->user()), 403);

        $students = StudentProfile::query()
            ->with(['enrollments.courseSection'])
            ->orderBy('first_name')
            ->limit(500)
            ->get()
            ->map(function (StudentProfile $student) {
                $enrollment = $student->preferredEnrollment();

                return [
                    'id' => $student->id,
                    'name' => $student->registered_name_resolved,
                    'rut' => $student->rut,
                    'course' => $enrollment?->snapshot_course_display_name,
                ];
            });

        return response()->json([
            'equipment_types' => $this->toOptions(ItEquipment::TYPE_OPTIONS),
            'equipment_statuses' => $this->toOptions(ItEquipment::STATUS_OPTIONS),
            'loan_statuses' => $this->toOptions(ItEquipmentLoan::STATUS_OPTIONS),
            'requester_types' => $this->toOptions(ItEquipmentLoan::REQUESTER_TYPES),
            'return_conditions' => $this->toOptions(ItEquipmentLoan::RETURN_CONDITION_OPTIONS),
            'maintenance_types' => $this->toOptions(ItEquipmentMaintenanceReport::TYPE_OPTIONS),
            'maintenance_statuses' => $this->toOptions(ItEquipmentMaintenanceReport::STATUS_OPTIONS),
            'attachment_categories' => $this->toOptions(ItEquipmentAttachment::CATEGORY_OPTIONS),
            'report_periods' => $this->toOptions(['daily', 'weekly', 'monthly', 'semestral', 'annual']),
            'users' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'email', 'staff_id', 'student_id']),
            'staff' => Staff::query()->orderBy('full_name')->get(['id', 'full_name', 'rut', 'institutional_email', 'phone']),
            'students' => $students,
            'equipment' => ItEquipment::query()->orderBy('internal_code')->get(['id', 'internal_code', 'equipment_type', 'brand', 'model', 'status', 'active']),
            'inventory_assets' => InventoryItem::query()
                ->with(['category:id,name,slug', 'subcategory:id,category_id,name,slug', 'dependency:id,code,name', 'responsibleUser:id,name,email'])
                ->where('item_type', 'asset')
                ->where('active', true)
                ->whereHas('category', fn ($query) => $query->whereIn('slug', ['tecnologia', 'audiovisual']))
                ->whereDoesntHave('itEquipment')
                ->orderBy('code')
                ->get(),
            'brands' => ItEquipment::query()->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand'),
            'locations' => ItEquipment::query()->whereNotNull('location_name')->distinct()->orderBy('location_name')->pluck('location_name'),
            'capabilities' => $this->accessService->capabilities($request->user()),
        ]);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value:string,label:string}>
     */
    private function toOptions(array $values): array
    {
        return collect($values)
            ->map(fn (string $value) => [
                'value' => $value,
                'label' => str($value)->replace('_', ' ')->title()->toString(),
            ])
            ->values()
            ->all();
    }
}
