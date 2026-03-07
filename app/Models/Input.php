<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Input extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'input';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function year()
    {
        return $this->belongsTo(Year::class, 'tahun');
    }
//the table input has bulan that FK to months table, can you modify the relation in month() method?   
    public function month()
    {
        return $this->belongsTo(Month::class, 'bulan');
    }
    public function regency()
    {        
        return $this->belongsTo(Regency::class, 'kode_kab');
    }

    public function syncStatus()
    {        
        return $this->belongsTo(SyncStatus::class, 'sync_status_id');
    }
}
