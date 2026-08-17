<?php
namespace App\Services\SocialWork;
use App\Models\SocialWork\JunaebBenefit;
use App\Models\SocialWork\JunaebDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class JunaebService {
    public function __construct(private readonly AuditService $audit) {}
    public function registerBenefit(array $data, User $user): JunaebBenefit {
        $existing = JunaebBenefit::withTrashed()->where('student_profile_id', $data['student_profile_id'])->where('benefit_type_id', $data['benefit_type_id'])->where('school_year', $data['school_year'])->first();
        if ($existing) throw ValidationException::withMessages(['student_profile_id' => 'Ya existe este beneficio para la estudiante y año seleccionados.']);
        return JunaebBenefit::create(array_merge($data, ['created_by' => $user->id, 'updated_by' => $user->id]));
    }
    public function deliver(JunaebBenefit $benefit, array $data, User $user): JunaebDelivery {
        if (($data['status'] ?? null) === 'entregada' && (empty($data['delivered_on']) || empty($data['receiver_name']))) throw ValidationException::withMessages(['receiver_name' => 'Una entrega completada requiere fecha y persona receptora.']);
        return DB::transaction(function () use ($benefit, $data, $user) {
            $items = $data['items'] ?? []; unset($data['items']);
            $number = JunaebDelivery::lockForUpdate()->whereYear('created_at', now()->year)->count() + 1;
            $delivery = $benefit->deliveries()->create(array_merge($data, ['folio' => sprintf('JUN-%d-%05d', now()->year, $number), 'responsible_user_id' => $data['responsible_user_id'] ?? $user->id, 'created_by' => $user->id]));
            foreach ($items as $item) $delivery->items()->create($item);
            if ($delivery->status === 'entregada') $benefit->update(['status' => 'entregado', 'updated_by' => $user->id]);
            $this->audit->record('junaeb.delivered', $delivery, $user, [], ['folio' => $delivery->folio, 'status' => $delivery->status]);
            return $delivery->load('items', 'benefit.student');
        });
    }
    public function cloneDelivery(JunaebDelivery $source, User $user): JunaebDelivery {
        return DB::transaction(function () use ($source, $user) {
            $clone = $source->replicate(['folio', 'delivered_on', 'receiver_name', 'receiver_relationship', 'signature_path']);
            $clone->fill(['folio' => sprintf('JUN-%d-%05d', now()->year, JunaebDelivery::lockForUpdate()->whereYear('created_at', now()->year)->count() + 1), 'status' => 'preparada', 'cloned_from_id' => $source->id, 'created_by' => $user->id]); $clone->save();
            foreach ($source->items as $item) $clone->items()->create($item->only(['name', 'quantity', 'unit', 'notes']));
            return $clone->load('items');
        });
    }
}
