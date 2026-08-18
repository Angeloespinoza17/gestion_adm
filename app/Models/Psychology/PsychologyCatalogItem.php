<?php

namespace App\Models\Psychology;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PsychologyCatalogItem extends Model
{
    use SoftDeletes;

    protected $table = 'psychology_catalog_items';

    protected $fillable = ['type', 'slug', 'name', 'description', 'active', 'sort_order', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];
}
