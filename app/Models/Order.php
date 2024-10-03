<?php

namespace App\Models;

use App\Enums\TypeOrderEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable =
        [
            'order_uuid',
            'status_order',
            'buyer_id'
        ];

    protected $casts =
        [
            'status_order' => TypeOrderEnum::class,
        ];
}
