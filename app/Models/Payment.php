<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['user_id', 'amount', 'status', 'reference'])]
class Payment extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
