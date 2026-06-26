<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Characteristic extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'label'];

    public function characteristicScores(): HasMany
    {
        return $this->hasMany(CatCharacteristicScore::class);
    }
}
