<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class project extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'production',
        'deployment',
        'port',
        'replicas',
        'url',
        'workgroup_id',
    ];

}
