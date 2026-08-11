<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MaintenanceWorkOrder extends Model
{
    use HasFactory;

    protected $appends = [
        'photo_url',
        'closure_document_url',
    ];

    protected $fillable = [
        'source_key',
        'maintenance_dependency_id',
        'technical_area_id',
        'inventory_item_id',
        'dependency_component',
        'location_code',
        'location_distribution',
        'location_sector',
        'location_name',
        'location_usage',
        'reported_at',
        'requested_by',
        'assigned_to',
        'priority',
        'status',
        'due_date',
        'description',
        'resolution_notes',
        'photo_reference',
        'closure_document_reference',
        'closure_document_original_name',
        'closed_at',
        'closed_by_user_id',
    ];

    protected $casts = [
        'reported_at' => 'date',
        'due_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        $reference = trim((string) $this->photo_reference);

        if ($reference === '') {
            return null;
        }

        if (filter_var($reference, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string) parse_url($reference, PHP_URL_SCHEME));

            return in_array($scheme, ['http', 'https'], true) ? $reference : null;
        }

        if (! self::isManagedPhotoReference($reference)) {
            return null;
        }

        $url = Storage::disk('public')->url($reference);

        // Evita problemas cuando APP_URL no coincide con el host/protocolo real.
        // Si el Storage devuelve URL absoluta, la convertimos a ruta relativa.
        $parts = parse_url((string) $url);
        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return $url;
    }

    public static function isManagedPhotoReference(?string $reference): bool
    {
        $reference = str_replace('\\', '/', trim((string) $reference));

        return str_starts_with($reference, 'maintenance/work-orders/')
            && ! str_contains($reference, '../');
    }

    public function getClosureDocumentUrlAttribute(): ?string
    {
        $reference = trim((string) $this->closure_document_reference);

        if (! self::isManagedClosureDocumentReference($reference)) {
            return null;
        }

        $url = Storage::disk('public')->url($reference);
        $parts = parse_url((string) $url);

        if (is_array($parts) && isset($parts['path'])) {
            return $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return $url;
    }

    public static function isManagedClosureDocumentReference(?string $reference): bool
    {
        $reference = str_replace('\\', '/', trim((string) $reference));

        return str_starts_with($reference, 'maintenance/work-orders/closures/')
            && ! str_contains($reference, '../');
    }

    public function scopePendingClosure(Builder $query): Builder
    {
        return $query
            ->where('status', 'Terminado')
            ->where(function (Builder $query) {
                $query
                    ->whereNull('resolution_notes')
                    ->orWhereRaw("TRIM(resolution_notes) = ''");
            });
    }

    public function scopeClosedWithNote(Builder $query): Builder
    {
        return $query
            ->where('status', 'Terminado')
            ->whereNotNull('resolution_notes')
            ->whereRaw("TRIM(resolution_notes) <> ''");
    }

    public function hasClosureNote(): bool
    {
        return trim((string) $this->resolution_notes) !== '';
    }

    public function dependency()
    {
        return $this->belongsTo(MaintenanceDependency::class, 'maintenance_dependency_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function technicalArea()
    {
        return $this->belongsTo(MaintenanceDependency::class, 'technical_area_id');
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
