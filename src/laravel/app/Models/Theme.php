<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'order',
        'module_id',
    ];


    public function module()
    {
        return $this->belongsTo(Module::class);
    }


    public function subtopics()
    {
        return $this->hasMany(Subtopic::class, 'theme_id');
    }
}
