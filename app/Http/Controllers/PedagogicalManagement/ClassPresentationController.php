<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\ListClassPresentationsRequest;
use App\Http\Requests\PedagogicalManagement\StoreClassPresentationRequest;
use App\Http\Resources\PedagogicalManagement\ClassPresentationResource;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Services\PedagogicalManagement\ClassPresentations\ClassPresentationAccessService;
use App\Services\PedagogicalManagement\ClassPresentations\ClassPresentationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ClassPresentationController extends Controller
{
    public function index(ListClassPresentationsRequest $request, ClassPresentationAccessService $access): JsonResponse
    {
        $data = $request->validated();
        $query = $access->visibleQuery($request->user())
            ->select([
                'id', 'uuid', 'series_uuid', 'version', 'school_id', 'user_id', 'academic_year_id', 'course_id', 'subject_id', 'unit_id',
                'title', 'status', 'progress', 'presentation_provider', 'configuration', 'curricular_snapshot', 'model', 'prompt_name', 'prompt_version',
                'failure_code', 'failure_message', 'generated_at', 'archived_at', 'created_at', 'updated_at',
                'canva_brand_template_id', 'canva_brand_template_title', 'canva_status', 'canva_autofill_job_id', 'canva_design_id',
                'canva_design_url', 'canva_edit_url', 'canva_view_url', 'canva_thumbnail_url', 'canva_thumbnail_expires_at',
                'canva_urls_refreshed_at', 'canva_failure_code', 'canva_failure_message', 'canva_submitted_at', 'canva_completed_at',
            ])
            ->with(['school:id,name,rbd', 'academicYear:id,name,year', 'course:id,display_name', 'subject:id,name,code', 'unit:id,unit_code,official_title,friendly_focus', 'author:id,name', 'learningObjectives:id,code,description', 'files']);
        foreach (['school_id', 'academic_year_id', 'course_id', 'subject_id', 'unit_id', 'user_id', 'status'] as $filter) {
            $query->when($data[$filter] ?? null, fn (Builder $builder, $value) => $builder->where($filter, $value));
        }
        $query->when($data['year'] ?? null, fn (Builder $builder, $year) => $builder->whereHas('academicYear', fn (Builder $years) => $years->where('year', $year)))
            ->when($data['search'] ?? null, fn (Builder $builder, $search) => $builder->where('title', 'like', '%'.$search.'%'));
        $paginator = $query->latest('id')->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => ClassPresentationResource::collection($paginator->getCollection())->resolve($request),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
        ]);
    }

    public function store(StoreClassPresentationRequest $request, ClassPresentationService $service): JsonResponse
    {
        $this->authorize('create', ClassPresentation::class);
        $presentation = $service->create($request->validated(), array_values($request->file('reference_files', [])), $request->user(), $request);

        return response()->json(['data' => new ClassPresentationResource($this->detail($presentation))], 202);
    }

    public function show(ClassPresentation $presentation): ClassPresentationResource
    {
        $this->authorize('view', $presentation);

        return new ClassPresentationResource($this->detail($presentation));
    }

    public function status(ClassPresentation $presentation): JsonResponse
    {
        $this->authorize('view', $presentation);
        $presentation->refresh();
        $ownsCanvaConnection = (int) request()->user()?->id === (int) $presentation->user_id;

        return response()->json(['data' => [
            'id' => $presentation->uuid, 'status' => $presentation->status?->value ?? $presentation->status,
            'progress' => $presentation->progress, 'failure_code' => $presentation->failure_code,
            'failure_message' => $presentation->failure_message,
            'presentation_provider' => $presentation->presentation_provider ?: data_get($presentation->configuration, 'presentation_provider', 'powerpoint'),
            'canva' => [
                'status' => $presentation->canva_status?->value ?? $presentation->canva_status,
                'brand_template_id' => $presentation->canva_brand_template_id,
                'brand_template_title' => $presentation->canva_brand_template_title,
                'autofill_job_id' => $ownsCanvaConnection ? $presentation->canva_autofill_job_id : null,
                'design_id' => $presentation->canva_design_id,
                'design_url' => $ownsCanvaConnection ? $presentation->canva_design_url : null,
                'edit_url' => $ownsCanvaConnection ? $presentation->canva_edit_url : null,
                'view_url' => $ownsCanvaConnection ? $presentation->canva_view_url : null,
                'failure_code' => $presentation->canva_failure_code,
                'failure_message' => $presentation->canva_failure_message,
                'submitted_at' => $presentation->canva_submitted_at?->toIso8601String(),
                'completed_at' => $presentation->canva_completed_at?->toIso8601String(),
            ],
            'updated_at' => $presentation->updated_at?->toIso8601String(),
        ]]);
    }

    private function detail(ClassPresentation $presentation): ClassPresentation
    {
        $presentation = $presentation->fresh()->load([
            'school:id,name,rbd', 'academicYear:id,name,year', 'course:id,display_name',
            'subject:id,name,code', 'subject.catalogProfile:id,schedule_subject_id,display_name',
            'unit:id,unit_code,official_title,friendly_focus', 'author:id,name',
            'learningObjectives:id,code,description', 'files', 'generationRuns', 'referenceFiles:id,uuid,class_presentation_id,original_filename,mime_type,size',
        ]);
        $presentation->setAttribute('include_deck', true);

        return $presentation;
    }
}
