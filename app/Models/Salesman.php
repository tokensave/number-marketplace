<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salesman extends Model
{
    use HasFactory;

    protected $fillable =
        [
            'uuid',
            'enabled',
            'name',
        ];

    public function numbers()
    {
        return $this->hasMany(Number::class);
    }
}
