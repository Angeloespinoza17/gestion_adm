<?php

namespace App\Events\Psychology;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PsychologyRecordChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly string $recordType, public readonly int $recordId, public readonly string $status) {}
}
