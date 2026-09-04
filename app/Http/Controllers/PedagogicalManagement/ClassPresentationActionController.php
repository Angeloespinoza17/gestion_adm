<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Resources\PedagogicalManagement\ClassPresentationResource;
use App\Models\PedagogicalManagement\ClassPresentation;
use App\Services\PedagogicalManagement\ClassPresentations\ClassPresentationService;
use Illuminate\Http\JsonResponse;

class ClassPresentationActionController extends Controller
{
    public function regenerate(ClassPresentation $presentation, ClassPresentationService $service): JsonResponse
    {
        $this->authorize('regenerate', $presentation);
        $newVersion = $service->regenerate($presentation->load(['referenceFiles', 'learningObjectives']), request()->user(), request());

        return response()->json(['data' => new ClassPresentationResource($newVersion)], 202);
    }

    public function retry(ClassPresentation $presentation, ClassPresentationService $service): JsonResponse
    {
        $this->authorize('regenerate', $presentation);
        $presentation = $service->retry($presentation, request()->user(), request());

        return response()->json(['data' => new ClassPresentationResource($presentation)], 202);
    }

    public function archive(ClassPresentation $presentation, ClassPresentationService $service): JsonResponse
    {
        $this->authorize('archive', $presentation);
        $service->archive($presentation, request()->user(), request());

        return response()->json(['message' => 'Presentación archivada sin eliminar sus versiones ni archivos.']);
    }
}
