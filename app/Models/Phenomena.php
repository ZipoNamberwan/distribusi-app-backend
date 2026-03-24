<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Phenomena extends Model
{
    use HasUuids;

    protected $fillable = [
        'description',
        'regency_id',
        'year_id',
        'month_id',
    ];

    public function year()
    {
        return $this->belongsTo(Year::class , 'year_id');
    }

    public function month()
    {
        return $this->belongsTo(Month::class , 'month_id');
    }

    public function regency()
    {
        return $this->belongsTo(Regency::class , 'regency_id');
    }
}