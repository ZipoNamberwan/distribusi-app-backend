<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Confirmation extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public function input()
    {
        return $this->belongsTo(Input::class, 'input_id');
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function errorType()
    {
        return $this->belongsTo(ErrorType::class, 'error_type_id');
    }
}
