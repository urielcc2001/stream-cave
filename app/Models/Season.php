<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    protected $fillable = ['media_id', 'number', 'title'];

    public function media()
    {
        return $this->belongsTo(Media::class);
    }

    public function episodes()
    {
        return $this->hasMany(Episode::class);
    }
}
