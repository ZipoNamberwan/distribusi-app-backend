<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SampleTarget extends Model
{
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

    public function category()
    {        
        return $this->belongsTo(Category::class, 'category_id');
    }
}
