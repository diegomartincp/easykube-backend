<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class invitation_code extends Model
{
    use HasFactory;
    protected $fillable = [
        'workgroup_id',
        'code',
    ];
}
