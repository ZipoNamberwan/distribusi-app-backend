<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncStatus extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'sync_statuses';

    public $incrementing = false;

    protected $guarded = [];
}
