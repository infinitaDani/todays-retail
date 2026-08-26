<?php

namespace App\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'status',
    ];
}
