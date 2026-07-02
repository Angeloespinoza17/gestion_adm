<?php

namespace App\Services\ApoyoProfesional;

use App\Models\ApoyoProfesional\ApoyoAtencion;
use App\Models\ApoyoProfesional\ApoyoDerivacion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApoyoProfesionalDerivationService
{
    public function store(array $payload, User $user): ApoyoDerivacion
    {
        return DB::transaction(function () use ($payload, $user) {
            $attention = ApoyoAtencion::query()->findOrFail($payload['attention_id']);

            $derivation = ApoyoDerivacion::query()->create([
                'attention_id' => $attention->id,
                'student_profile_id' => $attention->student_profile_id,
                'destination_professional_id' => $payload['destination_professional_id'] ?? null,
                'destination_user_id' => $payload['destination_user_id'] ?? null,
                'origin_area_slug' => $payload['origin_area_slug'] ?? $attention->professional_area_slug,
                'origin_area_name' => $payload['origin_area_name'] ?? $attention->professional_area_name,
                'destination_area_slug' => $payload['destination_area_slug'],
                'destination_area_name' => $payload['destination_area_name'],
                'urgency_level' => $payload['urgency_level'],
                'confidentiality_level' => $payload['confidentiality_level'] ?? $attention->confidentiality_level,
                'status' => $payload['status'] ?? 'enviada',
                'reason' => $payload['reason'],
                'description' => $payload['description'] ?? null,
                'derived_at' => Carbon::parse($payload['derived_at'])->format('Y-m-d H:i:s'),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->syncAttentionFlags($attention, $derivation, $user);

            return $this->loadDerivation($derivation);
        });
    }

    public function update(ApoyoDerivacion $derivation, array $payload, User $user): ApoyoDerivacion
    {
        return DB::transaction(function () use ($derivation, $payload, $user) {
            $derivation->fill([
                'destination_professional_id' => $payload['destination_professional_id'] ?? $derivation->destination_professional_id,
                'destination_user_id' => $payload['destination_user_id'] ?? $derivation->destination_user_id,
                'destination_area_slug' => $payload['destination_area_slug'],
                'destination_area_name' => $payload['destination_area_name'],
                'urgency_level' => $payload['urgency_level'],
                'confidentiality_level' => $payload['confidentiality_level'] ?? $derivation->confidentiality_level,
                'status' => $payload['status'],
                'reason' => $payload['reason'],
                'description' => $payload['description'] ?? null,
                'updated_by' => $user->id,
            ])->save();

            $this->syncAttentionFlags($derivation->attention()->firstOrFail(), $derivation, $user);

            return $this->loadDerivation($derivation);
        });
    }

    public function respond(ApoyoDerivacion $derivation, array $payload, User $user): ApoyoDerivacion
    {
        $status = $payload['status'];

        $derivation->forceFill([
            'status' => $status,
            'destination_response' => $payload['destination_response'] ?? $derivation->destination_response,
            'response_at' => now(),
            'closed_at' => $status === 'cerrada' ? now() : $derivation->closed_at,
            'updated_by' => $user->id,
        ])->save();

        return $this->loadDerivation($derivation);
    }

    private function syncAttentionFlags(ApoyoAtencion $attention, ApoyoDerivacion $derivation, User $user): void
    {
        $updates = [
            'status' => $derivation->destination_area_slug === 'direccion' ? 'escalada' : 'derivada',
            'updated_by' => $user->id,
        ];

        if ($derivation->destination_area_slug === 'direccion' && !$attention->escalated_to_direction_at) {
            $updates['escalated_to_direction_at'] = now();
        }

        if ($derivation->destination_area_slug === 'convivencia_escolar' && !$attention->derived_to_convivencia_at) {
            $updates['derived_to_convivencia_at'] = now();
        }

        if ($derivation->destination_area_slug === 'pie' && !$attention->derived_to_pie_at) {
            $updates['derived_to_pie_at'] = now();
        }

        $attention->forceFill($updates)->save();
    }

    private function loadDerivation(ApoyoDerivacion $derivation): ApoyoDerivacion
    {
        return $derivation->fresh([
            'attention:id,student_profile_id,professional_role_name,professional_area_name,reason_summary,status,confidentiality_level',
            'student:id,first_name,last_name,registered_name,rut',
            'destinationProfessional:id,user_id,staff_id,area_slug,area_name,professional_role_name',
            'destinationProfessional.staff:id,full_name',
            'destinationUser:id,name',
            'documents.uploadedBy:id,name',
        ]);
    }
}
