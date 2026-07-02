<?php

namespace App\Models;

use App\Models\Remuneration\RemunerationContractSetting;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Contract extends Model
{
    use HasFactory;

    public const STATUS_OPTIONS = [
        ['value' => 'borrador', 'label' => 'Borrador'],
        ['value' => 'generado', 'label' => 'Generado'],
        ['value' => 'enviado_firma', 'label' => 'Enviado a firma'],
        ['value' => 'firmado', 'label' => 'Firmado'],
        ['value' => 'anulado', 'label' => 'Anulado'],
        ['value' => 'vencido', 'label' => 'Vencido'],
    ];

    protected $fillable = [
        'staff_id',
        'contract_template_id',
        'contract_type',
        'start_date',
        'end_date',
        'position_name',
        'contract_hours',
        'workday',
        'base_salary',
        'allowances',
        'place_of_signature',
        'signature_date',
        'status',
        'rendered_content',
        'exported_word_path',
        'exported_pdf_path',
        'generated_at',
        'signed_at',
        'voided_at',
        'custom_variables',
        'observations',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'exported_word_url',
        'exported_pdf_url',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'signature_date' => 'date:Y-m-d',
        'generated_at' => 'datetime:Y-m-d H:i',
        'signed_at' => 'datetime:Y-m-d H:i',
        'voided_at' => 'datetime:Y-m-d H:i',
        'contract_hours' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'custom_variables' => 'array',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function getExportedWordUrlAttribute(): ?string
    {
        return $this->buildPublicUrl($this->exported_word_path);
    }

    public function getExportedPdfUrlAttribute(): ?string
    {
        return $this->buildPublicUrl($this->exported_pdf_path);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'contract_department')->withTimestamps();
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ContractSignature::class)->orderBy('sort_order');
    }

    public function remunerationSetting(): HasOne
    {
        return $this->hasOne(RemunerationContractSetting::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isSigned(): bool
    {
        return $this->status === 'firmado';
    }

    private function buildPublicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $url = Storage::disk('public')->url($path);
        $parts = parse_url((string) $url);

        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $url;
    }
}
