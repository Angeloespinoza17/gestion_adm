<?php

namespace App\Models\Supply;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyRequest extends Model
{
    use HasFactory;

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_READY_TO_QUOTE = 'ready_to_quote';
    public const STATUS_QUOTED = 'quoted';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'folio', 'title', 'destination', 'needed_by', 'status', 'notes', 'review_notes',
        'created_by', 'updated_by', 'reviewed_by', 'submitted_at', 'quoted_at',
    ];

    protected $casts = [
        'needed_by' => 'date',
        'submitted_at' => 'datetime',
        'quoted_at' => 'datetime',
    ];

    protected $appends = ['status_label'];

    public static function statusOptions(): array
    {
        return [
            ['value' => self::STATUS_SUBMITTED, 'label' => 'Enviada'],
            ['value' => self::STATUS_UNDER_REVIEW, 'label' => 'En revisión'],
            ['value' => self::STATUS_READY_TO_QUOTE, 'label' => 'Lista para cotizar'],
            ['value' => self::STATUS_QUOTED, 'label' => 'Cotización emitida'],
            ['value' => self::STATUS_REJECTED, 'label' => 'Rechazada'],
        ];
    }

    public static function statuses(): array
    {
        return array_column(self::statusOptions(), 'value');
    }

    public function getStatusLabelAttribute(): string
    {
        $label = collect(self::statusOptions())->firstWhere('value', $this->status)['label'] ?? null;

        return (string) ($label ?: $this->status ?: 'Sin estado');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplyRequestItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(SupplyRequestStatusLog::class)->latest('created_at')->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
