<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class cluster extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'domain',
        'workgroup_id',
    ];
}
