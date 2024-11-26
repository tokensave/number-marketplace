<?php

namespace App\Enums;

enum UserTypeEnum: string
{
    case buyer = 'buyer';
    case seller = 'seller';

    public function name(): string
    {
        return match ($this) {
            self::buyer => 'Покупатель',
            self::seller => 'Продавец',
        };
    }

}
