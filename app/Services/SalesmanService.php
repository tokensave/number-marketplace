<?php

namespace App\Services;

use App\Models\Salesman;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesmanService
{
    public function storeSalesman(string $uuid, string $name): Model|Builder
    {
        return Salesman::query()->firstOrCreate(['uuid' => $uuid], ['name' => $name]);
    }

    public function getSalesman(string $uuid): Model|Builder|null
    {
        return Salesman::query()->where('uuid', $uuid)->first();
    }

}
