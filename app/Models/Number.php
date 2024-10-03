<?php

namespace App\Models;

use App\Enums\StatusNumberEnum;
use App\Enums\TypeNumberEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Number extends Model
{
    use HasFactory;

    protected $fillable =
        [
            'number',
            'type_number',
            'status_number',
            'salesman_id',
            'buyer_uuid'
        ];

    protected $casts =
        [
            'type_number' => TypeNumberEnum::class,
            'status_number' => StatusNumberEnum::class
        ];

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }
}
