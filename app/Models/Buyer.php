<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Buyer extends Model
{
    protected $fillable =
        [
            'uuid',
            'order_id',
            'enabled',
            'name',
        ];
}
