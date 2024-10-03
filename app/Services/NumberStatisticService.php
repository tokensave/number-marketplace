<?php

namespace App\Services;

use App\Enums\StatusNumberEnum;
use App\Models\Number;

class NumberStatisticService
{
    //получение номеров для отражения очереди
    public function getCountNumbers(string $provider): int
    {
        return  Number::query()
            ->where('type_number', $provider)
            ->where('status_number', StatusNumberEnum::pending)
            ->count();
    }
}
