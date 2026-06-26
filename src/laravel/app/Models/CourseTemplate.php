<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseTemplate extends Model
{
    
    use HasFactory;


    protected $fillable = [
        'structure',
        'structure_hash',
        'level_id',
    ];

    protected $casts = [
        'structure' => 'array',
    ];

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }
}
