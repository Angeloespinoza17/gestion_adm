<?php

namespace App\Services\StudentHealth;

use App\Models\SocialWork\MedicalCertificate;
use App\Models\StudentProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentMedicalLeaveRegistrationService
{
    /** @param array<string, mixed> $attributes */
    public function register(array $attributes, int $registeredBy): MedicalCertificate
    {
        $studentId = (int) $attributes['student_profile_id'];
        $isPermanent = (bool) $attributes['is_permanent'];
        $startsOn = Carbon::parse($attributes['starts_on'])->startOfDay();
        $endsOn = $isPermanent
            ? null
            : Carbon::parse($attributes['ends_on'])->startOfDay();

        return DB::transaction(function () use ($attributes, $studentId, $registeredBy, $isPermanent, $startsOn, $endsOn): MedicalCertificate {
            // Enfermería e Inspectoría usan esta misma fila como mutex para serializar
            // registros simultáneos de una alumna antes de comprobar el período.
            StudentProfile::query()
                ->select('id')
                ->lockForUpdate()
                ->findOrFail($studentId);

            if (! $isPermanent) {
                $this->rejectOverlappingTemporaryCertificate($studentId, $startsOn, $endsOn);
            }

            return MedicalCertificate::query()->create([
                'student_profile_id' => $studentId,
                'issued_on' => today(),
                'covers_from' => $startsOn,
                'covers_to' => $endsOn,
                'certificate_type' => 'licencia_medica',
                'administrative_summary' => trim((string) $attributes['reason']),
                'expires_on' => $endsOn,
                'status' => $this->storedStatus($startsOn, $endsOn, $isPermanent),
                'confidentiality' => 'restringido',
                'is_permanent' => $isPermanent,
                'source_module' => $attributes['source_module'],
                'registered_by' => $registeredBy,
            ]);
        }, 3);
    }

    private function rejectOverlappingTemporaryCertificate(int $studentId, Carbon $startsOn, Carbon $endsOn): void
    {
        $overlap = MedicalCertificate::query()
            ->select(['id', 'covers_from', 'covers_to', 'source_module'])
            ->where('student_profile_id', $studentId)
            ->where('is_permanent', false)
            ->whereNotNull('covers_from')
            ->whereNotNull('covers_to')
            ->where('covers_from', '<=', $endsOn->copy()->endOfDay())
            ->where('covers_to', '>=', $startsOn)
            ->orderBy('covers_from')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $overlap) {
            return;
        }

        $source = match ($overlap->source_module) {
            'infirmary' => 'Enfermería',
            'inspectoria' => 'Inspectoría',
            default => 'el registro médico compartido',
        };
        $message = sprintf(
            'Ya existe una licencia o certificado temporal para esta alumna entre el %s y el %s, ingresado desde %s. Revisa el registro compartido antes de volver a ingresarlo.',
            $overlap->covers_from->format('d-m-Y'),
            $overlap->covers_to->format('d-m-Y'),
            $source,
        );

        throw ValidationException::withMessages([
            'starts_on' => $message,
        ]);
    }

    private function storedStatus(Carbon $startsOn, ?Carbon $endsOn, bool $isPermanent): string
    {
        if ($isPermanent) {
            return 'permanente';
        }

        if ($startsOn->isAfter(today())) {
            return 'programada';
        }

        return $endsOn?->isBefore(today()) ? 'finalizada' : 'vigente';
    }
}
