<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Episode extends Model
{
    protected $fillable = ['season_id', 'number', 'title', 'description', 'file_path', 'duration'];

    public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
