<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ContractSigner extends Model
{
    use HasFactory;

    public const TYPE_OPTIONS = [
        ['value' => 'representante_legal', 'label' => 'Representante legal'],
        ['value' => 'director', 'label' => 'Director/a'],
        ['value' => 'sostenedor', 'label' => 'Sostenedor/a'],
        ['value' => 'rrhh', 'label' => 'Encargado RRHH'],
        ['value' => 'funcionario', 'label' => 'Funcionario/a'],
    ];

    protected $fillable = [
        'name',
        'rut',
        'position',
        'signer_type',
        'signature_image_path',
        'active',
        'sort_order',
        'observations',
    ];

    protected $appends = [
        'signature_image_url',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
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

    public function contractSignatures(): HasMany
    {
        return $this->hasMany(ContractSignature::class);
    }
}
