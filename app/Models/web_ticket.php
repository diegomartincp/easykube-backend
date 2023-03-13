<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class web_ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'action', //0 Crear //1 Replicas //2 Borrar
        'replicas',
        'description',
        'user_id',
        'web_project_id',
    ];
}
