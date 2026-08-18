<?php

namespace App\Http\Controllers\Psychology;

use App\Http\Controllers\Controller;
use App\Models\Psychology\PsychologyCatalogItem;
use App\Services\Psychology\PsychologyAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PsychologyConfigurationController extends Controller
{
    public function __construct(private readonly PsychologyAuditService $audit) {}

    public function storeCatalog(Request $request): JsonResponse
    {
        $payload = $request->validate(['type' => ['required', 'string', 'max:60'], 'slug' => ['required', 'alpha_dash', 'max:80'], 'name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:1000'], 'active' => ['sometimes', 'boolean'], 'sort_order' => ['sometimes', 'integer', 'between:1,999']]);
        $item = PsychologyCatalogItem::query()->updateOrCreate(['type' => $payload['type'], 'slug' => $payload['slug']], $payload + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->audit->record('catalog.updated', $item, $request->user());

        return response()->json(['message' => 'Catálogo actualizado.', 'data' => $item], 201);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $payload = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['nullable', 'string', 'max:1000']]);
        foreach ($payload['settings'] as $key => $value) {
            DB::table('psychology_settings')->where('key', $key)->update(['value' => $value, 'updated_by' => $request->user()->id, 'updated_at' => now()]);
        }

        return response()->json(['message' => 'Parámetros actualizados.']);
    }

    public function audit(Request $request): JsonResponse
    {
        $filters = $request->validate(['action' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'between:5,100']]);

        return response()->json(DB::table('psychology_audit_events')->leftJoin('users', 'users.id', '=', 'psychology_audit_events.user_id')->select('psychology_audit_events.id', 'psychology_audit_events.action', 'psychology_audit_events.auditable_type', 'psychology_audit_events.auditable_id', 'psychology_audit_events.reason', 'psychology_audit_events.occurred_at', 'users.name as user_name')->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))->orderByDesc('occurred_at')->paginate($filters['per_page'] ?? 30));
    }
}
