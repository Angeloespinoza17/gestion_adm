<?php

namespace App\Services\StudentHealth;

use App\Models\SocialWork\MedicalCertificate;
use App\Models\StudentProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StudentMedicalLeaveRegistrationService
{
    /** @param array<string, mixed> $attributes */
    public function register(array $attributes, int $registeredBy, ?UploadedFile $attachment = null): MedicalCertificate
    {
        $studentId = (int) $attributes['student_profile_id'];
        $isPermanent = (bool) $attributes['is_permanent'];
        $startsOn = Carbon::parse($attributes['starts_on'])->startOfDay();
        $endsOn = $isPermanent
            ? null
            : Carbon::parse($attributes['ends_on'])->startOfDay();
        $attachmentData = $attachment ? $this->storeAttachment($attachment, $registeredBy) : [];

        try {
            return DB::transaction(function () use ($attributes, $studentId, $registeredBy, $isPermanent, $startsOn, $endsOn, $attachmentData): MedicalCertificate {
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
                    ...$attachmentData,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            if (filled($attachmentData['private_path'] ?? null)) {
                Storage::disk('local')->delete($attachmentData['private_path']);
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        MedicalCertificate $certificate,
        array $attributes,
        int $updatedBy,
        ?UploadedFile $attachment = null,
    ): MedicalCertificate {
        $isPermanent = (bool) $attributes['is_permanent'];
        $startsOn = Carbon::parse($attributes['starts_on'])->startOfDay();
        $endsOn = $isPermanent
            ? null
            : Carbon::parse($attributes['ends_on'])->startOfDay();
        $attachmentData = $attachment ? $this->storeAttachment($attachment, $updatedBy) : [];
        $previousPrivatePath = null;

        try {
            $updatedCertificate = DB::transaction(function () use (
                $certificate,
                $attributes,
                $updatedBy,
                $isPermanent,
                $startsOn,
                $endsOn,
                $attachmentData,
                &$previousPrivatePath,
            ): MedicalCertificate {
                // Conserva el mismo orden de bloqueos que el alta para serializar las
                // comprobaciones de período de una alumna y evitar superposiciones.
                StudentProfile::query()
                    ->select('id')
                    ->lockForUpdate()
                    ->findOrFail($certificate->student_profile_id);

                $lockedCertificate = MedicalCertificate::query()
                    ->lockForUpdate()
                    ->findOrFail($certificate->id);

                if (! $isPermanent) {
                    $this->rejectOverlappingTemporaryCertificate(
                        (int) $lockedCertificate->student_profile_id,
                        $startsOn,
                        $endsOn,
                        $lockedCertificate->id,
                    );
                }

                if ($attachmentData !== []) {
                    $previousPrivatePath = $lockedCertificate->getRawOriginal('private_path');
                }

                $lockedCertificate->forceFill([
                    'covers_from' => $startsOn,
                    'covers_to' => $endsOn,
                    'administrative_summary' => trim((string) $attributes['reason']),
                    'expires_on' => $endsOn,
                    'status' => $this->storedStatus($startsOn, $endsOn, $isPermanent),
                    'is_permanent' => $isPermanent,
                    'updated_by' => $updatedBy,
                    ...$attachmentData,
                ])->save();

                return $lockedCertificate->refresh();
            }, 3);
        } catch (Throwable $exception) {
            if (filled($attachmentData['private_path'] ?? null)) {
                Storage::disk('local')->delete($attachmentData['private_path']);
            }

            throw $exception;
        }

        if (filled($previousPrivatePath) && $previousPrivatePath !== ($attachmentData['private_path'] ?? null)) {
            Storage::disk('local')->delete($previousPrivatePath);
        }

        return $updatedCertificate;
    }

    /** @return array<string, mixed> */
    private function storeAttachment(UploadedFile $attachment, int $registeredBy): array
    {
        $extension = strtolower((string) ($attachment->extension() ?: $attachment->getClientOriginalExtension()));
        $extension = in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'], true)
            ? $extension
            : 'bin';
        $directory = 'student-health/medical-leaves/'.now()->format('Y/m');
        $path = $attachment->storeAs($directory, Str::uuid().'.'.$extension, 'local');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('No fue posible almacenar el respaldo médico.');
        }

        return [
            'private_path' => $path,
            'original_name' => mb_substr(basename($attachment->getClientOriginalName()), 0, 255),
            'mime_type' => $attachment->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $attachment->getSize(),
            'sha256' => hash_file('sha256', $attachment->getRealPath()),
            'uploaded_by' => $registeredBy,
        ];
    }

    private function rejectOverlappingTemporaryCertificate(
        int $studentId,
        Carbon $startsOn,
        Carbon $endsOn,
        ?int $exceptCertificateId = null,
    ): void {
        $overlap = MedicalCertificate::query()
            ->select(['id', 'covers_from', 'covers_to', 'source_module'])
            ->where('student_profile_id', $studentId)
            ->where('is_permanent', false)
            ->whereNotNull('covers_from')
            ->whereNotNull('covers_to')
            ->when($exceptCertificateId, fn ($query) => $query->where('id', '<>', $exceptCertificateId))
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
