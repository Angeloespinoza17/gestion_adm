<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class AccountingModel extends Model
{
    use HasFactory;

    protected $guarded = [];
}
