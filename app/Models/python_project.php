<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class python_project extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'aproved',
        'provider',
        'replicas',
        'port',
        'workgroup_id',
        'cluster_id',
        'deleted'
    ];
}
