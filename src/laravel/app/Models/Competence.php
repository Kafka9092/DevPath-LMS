<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competence extends Model
{
    
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'beginner_level',
        'junior_level',
        'middle_level',
        'senior_level',
    ];

    protected $casts = [
        'beginner_level' => 'boolean',
        'junior_level'   => 'boolean',
        'middle_level'   => 'boolean',
        'senior_level'   => 'boolean',
    ];
}
