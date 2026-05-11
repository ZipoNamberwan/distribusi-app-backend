<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DownloadStatus extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $guarded = [];

    public function year()
    {
        return $this->belongsTo(Year::class, 'year_id');
    }

    public function month()
    {
        return $this->belongsTo(Month::class, 'month_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
