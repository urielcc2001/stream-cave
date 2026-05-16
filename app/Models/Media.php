<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'title', 'type', 'year', 'description',
        'poster_path', 'backdrop_path', 'file_path',
        'rating', 'duration',
    ];

    public function seasons()
    {
        return $this->hasMany(Season::class);
    }

    public function genres()
    {
        return $this->belongsToMany(Genre::class);
    }
}
