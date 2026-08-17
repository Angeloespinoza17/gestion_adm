<?php

namespace App\Services\LibroDigital;

use App\Models\LibroDigital\RecordRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordRevisionWriter
{
    public function __construct(private readonly CanonicalJson $canonical) {}

    /** @param array<string, mixed>|null $payload */
    public function write(Model $record, int $schoolId, int $revision, User $actor, string $reason, ?array $payload = null): RecordRevision
    {
        $payload ??= $record->attributesToArray();
        $previous = RecordRevision::query()->where('revisable_type', $record::class)
            ->where('revisable_id', $record->getKey())->latest('revision')->first();

        return RecordRevision::query()->firstOrCreate(
            ['revisable_type' => $record::class, 'revisable_id' => $record->getKey(), 'revision' => $revision],
            [
                'school_id' => $schoolId,
                'previous_revision_id' => $previous?->id,
                'payload' => $payload,
                'payload_hash' => $this->canonical->hash($payload),
                'reason' => $reason,
                'created_by' => $actor->id,
            ],
        );
    }
}
