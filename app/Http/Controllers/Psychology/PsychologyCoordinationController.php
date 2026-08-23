<?php

namespace App\Http\Controllers\Psychology;

use App\Http\Controllers\Controller;
use App\Models\Psychology\PsychologyCase;
use App\Models\Psychology\PsychologyCoordinationRequest;
use App\Services\Psychology\PsychologyCoordinationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PsychologyCoordinationController extends Controller
{
    public function __construct(private readonly PsychologyCoordinationService $service) {}

    public function store(Request $request, PsychologyCase $case): JsonResponse
    {
        $this->authorize('update', $case);
        abort_unless($request->user()->hasPermission('psychology.sessions.create'), 403);
        $coordination = $this->service->create($case, $this->validatedRequest($request), $request->user());

        return response()->json(['message' => 'Solicitud de coordinación enviada.', 'data' => $coordination], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'direction' => ['nullable', Rule::in(['incoming', 'outgoing'])],
            'status' => ['nullable', Rule::in(['pending', 'accepted', 'rejected'])],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ]);
        $user = $request->user();
        $query = PsychologyCoordinationRequest::query()
            ->when(($filters['direction'] ?? 'incoming') === 'incoming', fn ($items) => $items->where('recipient_user_id', $user->id), fn ($items) => $items->where('requester_user_id', $user->id))
            ->when($filters['status'] ?? null, fn ($items, $status) => $items->where('status', $status))
            ->with(['requester:id,name', 'recipient:id,name', 'responder:id,name'])
            ->latest();

        $page = $query->paginate($filters['per_page'] ?? 25);
        $page->through(fn (PsychologyCoordinationRequest $coordination) => [
            'id' => $coordination->id,
            'coordination_type' => $coordination->coordination_type,
            'subject' => $coordination->subject,
            'request_message' => $coordination->request_message,
            'requested_for' => $coordination->requested_for?->toDateString(),
            'status' => $coordination->status,
            'response_message' => $coordination->response_message,
            'responded_at' => $coordination->responded_at?->toIso8601String(),
            'created_at' => $coordination->created_at?->toIso8601String(),
            'requester' => $coordination->requester?->only(['id', 'name']),
            'recipient' => $coordination->recipient?->only(['id', 'name']),
            'responder' => $coordination->responder?->only(['id', 'name']),
            'direction' => $coordination->recipient_user_id === $user->id ? 'incoming' : 'outgoing',
        ]);

        return response()->json($page);
    }

    public function respond(Request $request, PsychologyCoordinationRequest $coordination): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'rejected'])],
            'response_message' => ['required', 'string', 'max:4000'],
        ]);
        $updated = $this->service->respond($coordination->loadMissing('requester'), $request->user(), $payload['status'], $payload['response_message']);

        return response()->json(['message' => 'Respuesta registrada.', 'data' => $updated]);
    }

    private function validatedRequest(Request $request): array
    {
        return $request->validate([
            'recipient_user_id' => ['required', 'integer', 'exists:users,id'],
            'coordination_type' => ['required', Rule::in(['meeting', 'information_request', 'case_review', 'classroom_support', 'family_support', 'protocol_coordination', 'other'])],
            'subject' => ['required', 'string', 'max:191'],
            'request_message' => ['required', 'string', 'max:4000'],
            'requested_for' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'recipient_user_id.required' => 'Selecciona el funcionario que recibirá la solicitud.',
            'subject.required' => 'Indica el asunto de la coordinación.',
            'request_message.required' => 'Describe el requerimiento de coordinación.',
        ]);
    }
}
