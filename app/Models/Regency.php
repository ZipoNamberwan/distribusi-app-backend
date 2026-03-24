<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Regency extends Model
{
    public $timestamps = false;

    public function parent()
    {
        return $this->belongsTo(Regency::class, 'parent_id');
    }
    public function children()
    {
        return $this->hasMany(Regency::class, 'parent_id');
    }

    public function phenomenas()
    {
        return $this->hasMany(Phenomena::class, 'regency_id');
    }
}
