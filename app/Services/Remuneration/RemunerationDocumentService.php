<?php

namespace App\Services\Remuneration;

use App\Models\HumanResources\HrDocumentControl;
use App\Models\HumanResources\HrDocumentRequirement;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RemunerationDocumentService
{
    /**
     * @param  array{search?:string,status?:string,page?:int,per_page?:int}  $filters
     * @return array<string, mixed>
     */
    public function staffMatrix(array $filters): array
    {
        $requirements = HrDocumentRequirement::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $search = trim((string) ($filters['search'] ?? ''));
        $statusFilter = (string) ($filters['status'] ?? '');
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(5, min(50, (int) ($filters['per_page'] ?? 12)));

        $staffQuery = Staff::query()
            ->where('active', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('rut', 'like', "%{$search}%")
                        ->orWhereHas('cargo', fn ($cargo) => $cargo->where('name', 'like', "%{$search}%"));
                });
            });

        $orderedStaffIds = $staffQuery
            ->orderBy('full_name')
            ->orderBy('id')
            ->pluck('id');

        $controls = $this->controlsFor($orderedStaffIds, $requirements->pluck('id'));
        $statusByStaff = $this->statusMap($orderedStaffIds, $requirements, $controls);

        if ($statusFilter !== '') {
            $orderedStaffIds = $orderedStaffIds
                ->filter(fn (int $staffId): bool => $this->matchesStaffFilter(
                    $statusByStaff->get($staffId, collect()),
                    $statusFilter,
                ))
                ->values();
        }

        $total = $orderedStaffIds->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $pageIds = $orderedStaffIds->slice(($page - 1) * $perPage, $perPage)->values();

        $staffById = Staff::query()
            ->with('cargo:id,name')
            ->whereIn('id', $pageIds)
            ->get(['id', 'full_name', 'rut', 'cargo_id', 'status'])
            ->keyBy('id');

        $rows = $pageIds
            ->map(function (int $staffId) use ($staffById, $requirements, $controls) {
                $staff = $staffById->get($staffId);
                if (! $staff) {
                    return null;
                }

                $staffControls = $controls->get($staffId, collect())->keyBy('document_requirement_id');
                $documents = $requirements
                    ->map(fn (HrDocumentRequirement $requirement): array => $this->serializeDocument(
                        $requirement,
                        $staffControls->get($requirement->id),
                    ));

                return [
                    'id' => $staff->id,
                    'full_name' => $staff->full_name,
                    'rut' => $staff->rut,
                    'position' => $staff->cargo?->name,
                    'status' => $staff->status,
                    'document_summary' => [
                        'total' => $documents->count(),
                        'pending' => $documents->whereIn('status', [
                            'pending',
                            'pending_delivery',
                            'pending_signature',
                            'pending_both',
                        ])->count(),
                        'expired' => $documents->where('status', 'expired')->count(),
                        'expiring' => $documents->where('status', 'expiring')->count(),
                        'current' => $documents->where('status', 'current')->count(),
                    ],
                    'documents' => $documents->values(),
                ];
            })
            ->filter()
            ->values();

        return [
            'data' => $rows,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
            'from' => $total === 0 ? null : (($page - 1) * $perPage) + 1,
            'to' => $total === 0 ? null : min($page * $perPage, $total),
            'requirements' => $requirements->map(fn ($requirement) => $this->serializeRequirement($requirement))->values(),
            'summary' => $this->summary($orderedStaffIds, $requirements, $statusByStaff),
        ];
    }

    /**
     * @param  Collection<int, int>  $staffIds
     * @param  Collection<int, int>  $requirementIds
     * @return Collection<int, Collection<int, HrDocumentControl>>
     */
    private function controlsFor(Collection $staffIds, Collection $requirementIds): Collection
    {
        if ($staffIds->isEmpty() || $requirementIds->isEmpty()) {
            return collect();
        }

        return HrDocumentControl::query()
            ->whereIn('staff_id', $staffIds)
            ->whereIn('document_requirement_id', $requirementIds)
            ->get([
                'id',
                'staff_id',
                'document_requirement_id',
                'issued_at',
                'expires_at',
                'delivered_at',
                'signed_at',
                'file_path',
                'original_name',
                'mime_type',
                'file_size',
                'notes',
                'updated_at',
            ])
            ->groupBy('staff_id');
    }

    /**
     * @param  Collection<int, int>  $staffIds
     * @param  Collection<int, HrDocumentRequirement>  $requirements
     * @param  Collection<int, Collection<int, HrDocumentControl>>  $controls
     * @return Collection<int, Collection<int, string>>
     */
    private function statusMap(Collection $staffIds, Collection $requirements, Collection $controls): Collection
    {
        return $staffIds->mapWithKeys(function (int $staffId) use ($requirements, $controls): array {
            $staffControls = $controls->get($staffId, collect())->keyBy('document_requirement_id');

            return [$staffId => $requirements->map(
                fn (HrDocumentRequirement $requirement): string => $this->currentStatus(
                    $requirement,
                    $staffControls->get($requirement->id),
                )
            )];
        });
    }

    /**
     * @param  Collection<int, string>  $statuses
     */
    private function matchesStaffFilter(Collection $statuses, string $filter): bool
    {
        return match ($filter) {
            'pending' => $statuses->contains(fn (string $status) => str_starts_with($status, 'pending')),
            'expired' => $statuses->contains('expired'),
            'expiring' => $statuses->contains('expiring'),
            'current' => $statuses->isNotEmpty()
                && $statuses->every(fn (string $status): bool => $status === 'current'),
            default => true,
        };
    }

    /**
     * @param  Collection<int, int>  $staffIds
     * @param  Collection<int, HrDocumentRequirement>  $requirements
     * @param  Collection<int, Collection<int, string>>  $statusByStaff
     * @return array<string, int>
     */
    private function summary(Collection $staffIds, Collection $requirements, Collection $statusByStaff): array
    {
        $statuses = $staffIds
            ->flatMap(fn (int $staffId) => $statusByStaff->get($staffId, collect()));

        return [
            'staff' => $staffIds->count(),
            'requirements' => $requirements->count(),
            'expected' => $staffIds->count() * $requirements->count(),
            'current' => $statuses->where('current')->count(),
            'expiring' => $statuses->where('expiring')->count(),
            'expired' => $statuses->where('expired')->count(),
            'pending' => $statuses->filter(fn (string $status) => str_starts_with($status, 'pending'))->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRequirement(HrDocumentRequirement $requirement): array
    {
        return [
            'id' => $requirement->id,
            'code' => $requirement->code,
            'name' => $requirement->name,
            'description' => $requirement->description,
            'requires_delivery' => $requirement->requires_delivery,
            'requires_signature' => $requirement->requires_signature,
            'validity_mode' => $requirement->validity_mode,
            'validity_months' => $requirement->validity_months,
            'alert_days' => $requirement->alert_days,
            'is_required' => $requirement->is_required,
            'active' => $requirement->active,
            'sort_order' => $requirement->sort_order,
            'controls_count' => (int) ($requirement->controls_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDocument(
        HrDocumentRequirement $requirement,
        ?HrDocumentControl $control,
    ): array {
        return [
            'requirement' => $this->serializeRequirement($requirement),
            'compliance' => $control ? [
                'id' => $control->id,
                'issued_at' => $control->issued_at?->format('Y-m-d'),
                'expires_at' => $control->expires_at?->format('Y-m-d'),
                'delivered_at' => $control->delivered_at?->format('Y-m-d H:i'),
                'signed_at' => $control->signed_at?->format('Y-m-d H:i'),
                'has_file' => filled($control->file_path),
                'original_name' => $control->original_name,
                'mime_type' => $control->mime_type,
                'file_size' => $control->file_size,
                'notes' => $control->notes,
                'updated_at' => $control->updated_at?->format('Y-m-d H:i'),
                'download_url' => filled($control->file_path)
                    ? "/api/remuneraciones/documents/compliances/{$control->id}/download"
                    : null,
            ] : null,
            'status' => $this->currentStatus($requirement, $control),
        ];
    }

    public function currentStatus(
        HrDocumentRequirement $requirement,
        ?HrDocumentControl $control,
    ): string {
        if (! $control) {
            return $requirement->requires_delivery && $requirement->requires_signature
                ? 'pending_both'
                : ($requirement->requires_signature ? 'pending_signature' : 'pending_delivery');
        }

        $deliveryPending = $requirement->requires_delivery && ! $control->delivered_at;
        $signaturePending = $requirement->requires_signature && ! $control->signed_at;

        if ($deliveryPending || $signaturePending) {
            return match (true) {
                $deliveryPending && $signaturePending => 'pending_both',
                $deliveryPending => 'pending_delivery',
                default => 'pending_signature',
            };
        }

        if (! $control->expires_at) {
            return 'current';
        }

        if ($control->expires_at->isBefore(today())) {
            return 'expired';
        }

        $alertDays = max(0, (int) $requirement->alert_days);
        if ($control->expires_at->lessThanOrEqualTo(today()->addDays($alertDays))) {
            return 'expiring';
        }

        return 'current';
    }

    public function expirationDate(
        HrDocumentRequirement $requirement,
        ?string $issuedAt,
        ?string $manualExpiration,
    ): ?string {
        return match ($requirement->validity_mode) {
            HrDocumentRequirement::VALIDITY_MONTHS => Carbon::parse($issuedAt ?: today())
                ->addMonthsNoOverflow((int) $requirement->validity_months)
                ->toDateString(),
            HrDocumentRequirement::VALIDITY_MANUAL => $manualExpiration,
            default => null,
        };
    }
}
