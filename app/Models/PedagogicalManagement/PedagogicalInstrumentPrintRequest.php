<?php

namespace App\Models\PedagogicalManagement;

use App\Enums\PedagogicalManagement\PrintRequestStatus;
use App\Models\LibroDigital\School;
use App\Models\PedagogicalManagement\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedagogicalInstrumentPrintRequest extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'status' => PrintRequestStatus::class,
            'download_count' => 'integer',
            'print_count' => 'integer',
            'last_downloaded_at' => 'datetime',
            'last_printed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrument::class, 'instrument_id');
    }

    public function instrumentFile(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrumentFile::class, 'instrument_file_id');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PedagogicalInstrumentReview::class, 'review_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
