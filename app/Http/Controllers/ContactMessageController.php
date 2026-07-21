<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));

        $query = ContactMessage::query()
            ->with('handledBy:id,name,email')
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($builder) => $builder->where('status', $status));

        $items = $query
            ->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'read' THEN 1 WHEN 'responded' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->query('per_page', 15), 50));

        return response()->json($items);
    }

    public function catalogs(): JsonResponse
    {
        return response()->json([
            'statuses' => $this->statuses(),
            'stats' => [
                'total' => ContactMessage::query()->count(),
                'new' => ContactMessage::query()->where('status', ContactMessage::STATUS_NEW)->count(),
                'read' => ContactMessage::query()->where('status', ContactMessage::STATUS_READ)->count(),
                'responded' => ContactMessage::query()->where('status', ContactMessage::STATUS_RESPONDED)->count(),
                'archived' => ContactMessage::query()->where('status', ContactMessage::STATUS_ARCHIVED)->count(),
            ],
        ]);
    }

    public function show(ContactMessage $contactMessage): JsonResponse
    {
        if ($contactMessage->status === ContactMessage::STATUS_NEW) {
            $contactMessage->forceFill([
                'status' => ContactMessage::STATUS_READ,
                'read_at' => $contactMessage->read_at ?: now(),
            ])->save();
        }

        return response()->json([
            'data' => $contactMessage->fresh('handledBy:id,name,email'),
        ]);
    }

    public function update(UpdateContactMessageRequest $request, ContactMessage $contactMessage): JsonResponse
    {
        $status = $request->input('status');

        $payload = [
            'status' => $status,
            'internal_notes' => $request->input('internal_notes') ?: null,
            'handled_by' => $request->user()?->id,
        ];

        if (in_array($status, [ContactMessage::STATUS_READ, ContactMessage::STATUS_RESPONDED], true)) {
            $payload['read_at'] = $contactMessage->read_at ?: now();
        }

        if ($status === ContactMessage::STATUS_RESPONDED) {
            $payload['responded_at'] = $contactMessage->responded_at ?: now();
        }

        $contactMessage->update($payload);

        return response()->json([
            'message' => 'Mensaje actualizado correctamente.',
            'data' => $contactMessage->fresh('handledBy:id,name,email'),
        ]);
    }

    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return response()->json([
            'message' => 'Mensaje eliminado correctamente.',
        ]);
    }

    private function statuses(): array
    {
        return [
            ['value' => ContactMessage::STATUS_NEW, 'label' => 'Nuevo'],
            ['value' => ContactMessage::STATUS_READ, 'label' => 'Leído'],
            ['value' => ContactMessage::STATUS_RESPONDED, 'label' => 'Respondido'],
            ['value' => ContactMessage::STATUS_ARCHIVED, 'label' => 'Archivado'],
        ];
    }
}
