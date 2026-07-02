<?php

namespace App\Models\HumanResources;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class HumanResourcesModel extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }
}
