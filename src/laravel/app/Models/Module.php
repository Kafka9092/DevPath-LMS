<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    
    use HasFactory;

    protected $fillable = [
        'module_number',
        'title',
        'description',
        'course_id',

    ];


    public function course()
    {
        return $this->belongsTo(Course::class);
    }


    public function themes()
    {
        return $this->hasMany(Theme::class, 'module_id');
    }
}
