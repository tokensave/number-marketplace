<?php

namespace App\Enums;

enum StatusNumberEnum: string
{
    case active = 'active';
    case pending = 'pending';
    case failed = 'failed';
    case payable = 'payable';

    public function name(): string
    {
        return match ($this) {
            self::active => 'Не оплаченый',
            self::pending => 'Ожидает',
            self::failed => 'Слетевший',
            self::payable => 'Оплаченый',
        };
    }

}
