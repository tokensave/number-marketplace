<?php

namespace App\Services;

use App\Models\Salesman;

class SalesmanService
{
    public function storeSalesman(string $uuid): string
    {
        Salesman::query()->firstOrCreate(['uuid' => $uuid]);

        return 'Добро пожаловать!';
    }

    public function getSalesman(string $uuid)
    {
        return Salesman::query()->where('uuid', $uuid)->first();
    }

}
