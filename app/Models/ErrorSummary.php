<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ErrorSummary extends Model
{
    use HasUuids;

    public function year()
    {
        return $this->belongsTo(Year::class, 'year_id');
    }

    public function month()
    {
        return $this->belongsTo(Month::class, 'month_id');
    }

    public function regency()
    {
        return $this->belongsTo(Regency::class, 'regency_id');
    }

    public function error()
    {
        return $this->belongsTo(Error::class, 'error_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
