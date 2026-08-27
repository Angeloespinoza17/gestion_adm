<?php

namespace App\Http\Controllers\PedagogicalManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\PedagogicalManagement\SavePedagogicalGuidanceDocumentRequest;
use App\Models\PedagogicalManagement\PedagogicalGuidanceDocument;
use App\Services\LibroDigital\LibroDigitalAccessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PedagogicalGuidanceDocumentController extends Controller
{
    public function index(Request $request, LibroDigitalAccessContext $access): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('pedagogical-guidance.manage'), 403);
        $data = $request->validate(['school_id' => ['required', 'integer', 'exists:lcd_schools,id']]);
        abort_unless($access->canAccessSchool($request->user(), (int) $data['school_id']), 403);

        $documents = PedagogicalGuidanceDocument::query()
            ->where('school_id', $data['school_id'])
            ->orderByDesc('active')->orderBy('document_type')->orderBy('title')
            ->get()
            ->map(fn (PedagogicalGuidanceDocument $document): array => $this->serialize($document));

        return response()->json(['data' => $documents]);
    }

    public function store(SavePedagogicalGuidanceDocumentRequest $request, LibroDigitalAccessContext $access): JsonResponse
    {
        $data = $request->validated();
        abort_unless($access->canAccessSchool($request->user(), (int) $data['school_id']), 403);
        $document = PedagogicalGuidanceDocument::query()->create([
            ...$data,
            'active' => (bool) ($data['active'] ?? true),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Documento de orientación creado.', 'data' => $this->serialize($document)], 201);
    }

    public function update(
        SavePedagogicalGuidanceDocumentRequest $request,
        PedagogicalGuidanceDocument $guidanceDocument,
        LibroDigitalAccessContext $access,
    ): JsonResponse {
        $data = $request->validated();
        abort_unless($access->canAccessSchool($request->user(), (int) $guidanceDocument->school_id), 403);
        if ((int) $data['school_id'] !== (int) $guidanceDocument->school_id) {
            throw ValidationException::withMessages(['school_id' => 'Un documento de orientación no puede trasladarse a otro establecimiento.']);
        }
        $guidanceDocument->fill([
            ...$data,
            'updated_by' => $request->user()->id,
        ])->save();

        return response()->json(['message' => 'Documento de orientación actualizado.', 'data' => $this->serialize($guidanceDocument->fresh())]);
    }

    private function serialize(PedagogicalGuidanceDocument $document): array
    {
        return [
            'id' => $document->uuid,
            'school_id' => $document->school_id,
            'document_type' => $document->document_type,
            'title' => $document->title,
            'description' => $document->description,
            'content' => $document->content,
            'active' => $document->active,
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }
}
