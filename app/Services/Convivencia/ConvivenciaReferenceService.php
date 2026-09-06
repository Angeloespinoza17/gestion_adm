<?php

namespace App\Services\Convivencia;

use App\Models\Convivencia\ConvivenciaCase;
use App\Models\Convivencia\ConvivenciaComplaint;
use App\Models\Convivencia\ConvivenciaPlan;
use App\Models\Convivencia\ConvivenciaProtocol;
use App\Models\Convivencia\ConvivenciaProtocolPart;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ConvivenciaReferenceService
{
    public function __construct(
        private readonly ConvivenciaAccessService $accessService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(string $type, array $filters, User $user): LengthAwarePaginator
    {
        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 120);
        $perPage = min(50, max(1, (int) ($filters['per_page'] ?? 20)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $selectedId = isset($filters['selected_id']) ? (int) $filters['selected_id'] : null;
        $includeInactive = filter_var($filters['include_inactive'] ?? false, FILTER_VALIDATE_BOOL);

        $query = $this->queryFor($type, $user);
        $this->applyFilters($query, $type, $search, $includeInactive);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $references = $paginator->getCollection()
            ->map(fn (Model $record): array => $this->toReference($type, $record));

        if ($selectedId && ! $references->contains('id', $selectedId)) {
            $selected = $this->queryFor($type, $user)->find($selectedId);

            if ($selected) {
                $references->prepend($this->toReference($type, $selected));
            }
        }

        $paginator->setCollection($references->values());

        return $paginator;
    }

    private function queryFor(string $type, User $user): Builder
    {
        return match ($type) {
            'cases' => $this->accessService->applyCaseVisibility(
                ConvivenciaCase::query()
                    ->select([
                        'id',
                        'folio',
                        'status',
                        'classification_label',
                        'course_section_id',
                        'student_profile_id',
                        'opened_at',
                    ])
                    ->with([
                        'student:id,first_name,last_name,registered_name',
                        'courseSection:id,display_name',
                    ]),
                $user,
            )->latest('opened_at')->latest('id'),
            'complaints' => $this->accessService->applyComplaintVisibility(
                ConvivenciaComplaint::query()
                    ->select([
                        'id',
                        'folio',
                        'status',
                        'situation_type_label',
                        'course_section_id',
                        'affected_student_id',
                        'received_at',
                    ])
                    ->with([
                        'affectedStudent:id,first_name,last_name,registered_name',
                        'courseSection:id,display_name',
                    ]),
                $user,
            )->latest('received_at')->latest('id'),
            'plans' => $this->accessService->applyPlanVisibility(
                ConvivenciaPlan::query()->select(['id', 'name', 'status', 'starts_on']),
                $user,
            )->orderBy('name')->orderBy('id'),
            'parts' => ConvivenciaProtocolPart::query()
                ->select(['id', 'category', 'code', 'title', 'active'])
                ->when(
                    ! $this->accessService->canManageProtocols($user),
                    fn (Builder $builder) => $builder->where('is_sensitive', false),
                )
                ->orderBy('category')
                ->orderBy('title')
                ->orderBy('id'),
            'protocols' => $this->accessService->applyProtocolVisibility(
                ConvivenciaProtocol::query()->select(['id', 'code', 'name', 'status', 'revision']),
                $user,
            )->orderBy('name')->orderBy('id'),
        };
    }

    private function applyFilters(Builder $query, string $type, string $search, bool $includeInactive): void
    {
        if ($type === 'protocols' && ! $includeInactive) {
            $query->where('status', 'activo');
        }

        if ($type === 'parts' && ! $includeInactive) {
            $query->where('active', true);
        }

        if ($search === '') {
            return;
        }

        $like = "%{$search}%";

        match ($type) {
            'cases' => $query->where(function (Builder $searchQuery) use ($like) {
                $searchQuery
                    ->where('folio', 'like', $like)
                    ->orWhere('classification_label', 'like', $like)
                    ->orWhereHas('student', fn (Builder $studentQuery) => $studentQuery
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('registered_name', 'like', $like))
                    ->orWhereHas('courseSection', fn (Builder $courseQuery) => $courseQuery
                        ->where('display_name', 'like', $like));
            }),
            'complaints' => $query->where(function (Builder $searchQuery) use ($like) {
                $searchQuery
                    ->where('folio', 'like', $like)
                    ->orWhere('situation_type_label', 'like', $like)
                    ->orWhereHas('affectedStudent', fn (Builder $studentQuery) => $studentQuery
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('registered_name', 'like', $like))
                    ->orWhereHas('courseSection', fn (Builder $courseQuery) => $courseQuery
                        ->where('display_name', 'like', $like));
            }),
            'parts' => $query->where(function (Builder $searchQuery) use ($like) {
                $searchQuery
                    ->where('title', 'like', $like)
                    ->orWhere('code', 'like', $like);
            }),
            'plans' => $query->where('name', 'like', $like),
            'protocols' => $query->where(function (Builder $searchQuery) use ($like) {
                $searchQuery
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            }),
        };
    }

    /**
     * @return array{id: int, label: string, status: string|null, secondary: string|null}
     */
    private function toReference(string $type, Model $record): array
    {
        return match ($type) {
            'cases' => [
                'id' => (int) $record->getKey(),
                'label' => $this->folioLabel(
                    (string) $record->folio,
                    $record->student?->registered_name_resolved ?: $record->classification_label,
                ),
                'status' => $record->status,
                'secondary' => $record->courseSection?->display_name,
            ],
            'complaints' => [
                'id' => (int) $record->getKey(),
                'label' => $this->folioLabel(
                    (string) $record->folio,
                    $record->affectedStudent?->registered_name_resolved ?: $record->situation_type_label,
                ),
                'status' => $record->status,
                'secondary' => $record->courseSection?->display_name,
            ],
            'plans' => [
                'id' => (int) $record->getKey(),
                'label' => (string) $record->name,
                'status' => $record->status,
                'secondary' => null,
            ],
            'parts' => [
                'id' => (int) $record->getKey(),
                'label' => $this->folioLabel((string) ($record->code ?? ''), $record->title),
                'status' => $record->active ? 'activo' : 'inactivo',
                'secondary' => $record->category,
            ],
            'protocols' => [
                'id' => (int) $record->getKey(),
                'label' => $this->folioLabel((string) ($record->code ?? ''), $record->name),
                'status' => $record->status,
                'secondary' => $record->revision ? "Revisión {$record->revision}" : null,
            ],
        };
    }

    private function folioLabel(string $folio, ?string $detail): string
    {
        $folio = trim($folio);
        $detail = trim((string) $detail);

        return $detail === '' ? $folio : trim("{$folio} · {$detail}", ' ·');
    }
}
