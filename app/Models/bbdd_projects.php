<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class bbdd_projects extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'memory',
        'dbname',
        'dbuser',
        'dbpwd',
        'aproved',
        'provider',
        'replicas',
        'workgroup_id',
        'cluster_id',
    ];

}
