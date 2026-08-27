<?php

namespace App\Models\PedagogicalManagement;

use App\Enums\PedagogicalManagement\AiReportStatus;
use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedagogicalInstrumentAiReport extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'status' => AiReportStatus::class,
            'report' => 'array',
            'usage' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrument::class, 'instrument_id');
    }

    public function instrumentFile(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrumentFile::class, 'instrument_file_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
