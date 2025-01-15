<?php

namespace App\Models;

use App\Enums\LogTelegramTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogTelegram extends Model
{
    use HasFactory;

    protected $fillable = [
      'chat_id',
      'data',
      'type',
    ];

    protected $casts = [
        'type' => LogTelegramTypeEnum::class,
    ];
}
