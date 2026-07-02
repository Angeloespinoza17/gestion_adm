<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ContractSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'contract_signer_id',
        'name',
        'rut',
        'position',
        'signer_type',
        'signature_image_path',
        'sort_order',
        'use_signature_image',
        'observations',
    ];

    protected $appends = [
        'signature_image_url',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'use_signature_image' => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function getSignatureImageUrlAttribute(): ?string
    {
        if (!$this->signature_image_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->signature_image_path);
        $parts = parse_url((string) $url);

        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return $url;
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(ContractSigner::class, 'contract_signer_id');
    }
}
