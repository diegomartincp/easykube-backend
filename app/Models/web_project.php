<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class web_project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'email',
        'prod',
        'token',
        'url',
        'ipname',
        'dns',
        'workgroup_id',
        'cluster_id'
    ];
}
