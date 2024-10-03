<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NumberState extends Model
{
    use HasFactory;

    protected $fillable =
        [
            'seller_id',
            'buyer_id',
            'waiting_code',
            'number',
            'provider',
        ];
}
