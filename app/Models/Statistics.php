<?php

namespace App\Models;

use App\Enums\TypeNumberEnum;
use App\Enums\UserTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Statistics extends Model
{
    protected $fillable =
        [
            'uuid',
            'type',
            'name',
            'provider_number',
            'count_active',
            'count_deactivate',
            'count_pending',
        ];
}
