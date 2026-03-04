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
}
