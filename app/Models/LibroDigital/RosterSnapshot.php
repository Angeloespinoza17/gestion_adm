<?php

namespace App\Models\LibroDigital;

use App\Models\LibroDigital\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RosterSnapshot extends LibroDigitalModel
{
    use HasPublicUlid;

    protected $table = 'lcd_roster_snapshots';

    protected function casts(): array
    {
        return ['effective_on' => 'date'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RosterSnapshotItem::class);
    }
}
