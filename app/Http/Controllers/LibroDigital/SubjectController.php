<?php

namespace App\Http\Controllers\LibroDigital;

use App\Exceptions\LibroDigital\VersionConflictException;
use App\Http\Requests\LibroDigital\StoreSubjectRequest;
use App\Http\Requests\LibroDigital\UpdateSubjectRequest;
use App\Http\Resources\LibroDigital\SubjectResource;
use App\Models\Schedule\ScheduleSubject;
use App\Services\LibroDigital\AuditEventWriter;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubjectController extends LibroDigitalController
{
    /**
     * ScheduleSubject is a legacy, institution-wide catalog. Mutations are
     * deliberately governed by a dedicated global-catalog permission; no
     * school-scoped ownership is claimed where the source table has none.
     */
    public function __construct(
        LibroDigitalAccessContext $access,
        private readonly AuditEventWriter $audit,
    ) {
        parent::__construct($access);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ScheduleSubject::class);
        $this->school($request);
        $subjects = ScheduleSubject::query()
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->string('q')->toString()).'%';
                $query->where(fn (Builder $search) => $search->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->when($request->has('active'), fn (Builder $query) => $query->where('active', $request->boolean('active')))
            ->orderBy('name')->get();

        return $this->collectionResponse(SubjectResource::collection($subjects)->resolve($request));
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        $this->authorize('create', ScheduleSubject::class);
        $school = $this->school($request);
        $data = $request->safe()->only(['name', 'code', 'area', 'color', 'active']);
        $subject = ScheduleSubject::query()->create($data + ['active' => $data['active'] ?? true]);
        $this->audit->write('lcd.subject.created', 'create', $subject, actor: $request->user(), schoolId: $school->id, after: $subject->toArray(), request: $request);

        return $this->dataResponse((new SubjectResource($subject))->resolve($request), 201, $subject->updated_at?->getTimestamp() ?: 1);
    }

    public function update(UpdateSubjectRequest $request, string $subject): JsonResponse
    {
        $model = ScheduleSubject::query()->findOrFail($subject);
        $this->authorize('update', $model);
        $school = $this->school($request);
        $expected = (int) ($request->header('If-Match') ?: $request->input('lock_version'));
        $actual = $model->updated_at?->getTimestamp() ?: 1;
        if ($expected !== $actual) {
            throw new VersionConflictException($expected, $actual);
        }

        $before = $model->toArray();
        $model->fill($request->safe()->only(['name', 'code', 'area', 'color', 'active']));
        $model->updated_at = Carbon::createFromTimestamp(max(now()->getTimestamp(), $actual + 1));
        $model->save(['timestamps' => false]);
        $this->audit->write('lcd.subject.updated', 'update', $model, actor: $request->user(), schoolId: $school->id, before: $before, after: $model->toArray(), request: $request);

        return $this->dataResponse((new SubjectResource($model))->resolve($request), version: $model->updated_at->getTimestamp());
    }
}
