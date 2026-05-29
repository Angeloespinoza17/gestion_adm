<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceDependency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'distribution',
        'sector',
        'zone',
        'usage',
        'distribution_code',
        'floor_code',
        'dependency_code',
        'numbering',
        'active',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
        'numbering' => 'integer',
    ];

    public function workOrders()
    {
        return $this->hasMany(MaintenanceWorkOrder::class);
    }
}
