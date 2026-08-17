<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const REQUEST_TYPES = [
        'atraso' => 'Atraso / justificación',
        'malestar' => 'Malestar físico',
        'permiso_bano' => 'Permiso al baño',
        'utiles' => 'Útiles o materiales',
        'uniforme' => 'Uniforme / presentación',
        'convivencia' => 'Situación de convivencia',
        'contacto_apoderado' => 'Contacto con apoderado',
        'retiro' => 'Solicitud de retiro',
        'orientacion' => 'Orientación breve',
        'otro' => 'Otra solicitud',
    ];

    private const ACTIONS = [
        'resuelta' => 'Resuelta en el momento',
        'pase_emitido' => 'Pase emitido',
        'derivada_enfermeria' => 'Derivada a Enfermería',
        'derivada_convivencia' => 'Derivada a Convivencia',
        'derivada_direccion' => 'Derivada a Dirección',
        'apoderado_contactado' => 'Apoderado contactado',
        'seguimiento' => 'Requiere seguimiento',
    ];

    public function up(): void
    {
        Schema::table('social_work_referrals', function (Blueprint $table) {
            $table->foreignId('inspectoria_attention_id')
                ->nullable()
                ->after('id')
                ->unique()
                ->constrained('inspectoria_attentions')
                ->nullOnDelete();
        });

        $userNames = DB::table('users')->pluck('name', 'id');

        DB::table('inspectoria_attentions')
            ->whereNotNull('psychosocial_referral_user_id')
            ->orderBy('id')
            ->chunkById(100, function ($attentions) use ($userNames): void {
                foreach ($attentions as $attention) {
                    $actions = $this->jsonArray($attention->actions_taken);
                    if (! in_array('derivacion_psicosocial', $actions, true)) {
                        continue;
                    }

                    $requests = $this->labels($this->jsonArray($attention->request_types), self::REQUEST_TYPES);
                    $otherActions = $this->labels(
                        array_values(array_diff($actions, ['derivacion_psicosocial'])),
                        self::ACTIONS,
                    );
                    $reason = 'Derivación psicosocial';
                    if ($requests !== []) {
                        $reason .= ' · '.implode(', ', $requests);
                    }
                    $referredAt = $attention->psychosocial_referred_at ?: $attention->attended_at ?: $attention->created_at;

                    DB::table('social_work_referrals')->insertOrIgnore([
                        'inspectoria_attention_id' => $attention->id,
                        'student_profile_id' => $attention->student_profile_id,
                        'case_id' => null,
                        'course_section_id' => $attention->course_section_id,
                        'referral_date' => substr((string) $referredAt, 0, 10),
                        'source_unit' => 'Inspectoría',
                        'source_person' => $userNames[$attention->attended_by_user_id] ?? 'Inspectoría',
                        'reason' => Str::limit($reason, 255, ''),
                        'description' => $attention->brief_note,
                        'observed_background' => $requests === []
                            ? null
                            : 'Solicitud registrada: '.implode(', ', $requests).'.',
                        'previous_actions' => $otherActions === []
                            ? "Atención rápida de Inspectoría {$attention->attention_code}."
                            : "Atención rápida {$attention->attention_code}: ".implode(', ', $otherActions).'.',
                        'urgency' => $attention->priority === 'urgente' ? 'urgente' : 'normal',
                        'immediate_risk' => $attention->priority === 'urgente',
                        'contact_data' => null,
                        'received_at' => null,
                        'status' => 'enviada',
                        'assigned_user_id' => $attention->psychosocial_referral_user_id,
                        'response' => null,
                        'rejection_reason' => null,
                        'confidentiality' => 'restringido',
                        'created_by' => $attention->created_by,
                        'updated_by' => $attention->updated_by ?: $attention->created_by,
                        'created_at' => $attention->created_at ?: $referredAt,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('social_work_referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inspectoria_attention_id');
        });
    }

    /** @return array<int, string> */
    private function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<string, string>  $catalog
     * @return array<int, string>
     */
    private function labels(array $values, array $catalog): array
    {
        return array_values(array_filter(array_map(
            fn (string $value) => $catalog[$value] ?? null,
            $values,
        )));
    }
};
