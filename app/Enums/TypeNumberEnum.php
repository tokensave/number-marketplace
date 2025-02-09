<?php

namespace App\Enums;

enum TypeNumberEnum: string
{
    case whatsapp = 'whatsapp';
    case telegram = 'telegram';

    public function name(): string
    {
        return match ($this) {
            self::whatsapp => 'whatsapp',
            self::telegram => 'telegram',
        };
    }

}
