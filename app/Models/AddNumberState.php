<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddNumberState extends Model
{
    use HasFactory;

    protected $fillable =
        [
            'seller_id',
            'waiting_for_number',
            'provider'
        ];
}
