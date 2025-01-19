<?php

namespace App\Services;

use App\Enums\StatusNumberEnum;
use App\Enums\TypeNumberEnum;
use App\Models\Buyer;
use App\Models\Number;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class BuyerService
{

    public function storeBuyer(string $uuid, string $name): Model|Builder
    {
        return Buyer::query()->firstOrCreate(['uuid' => $uuid], ['name' => $name]);
    }

    public function getBuyer(string $uuid): Model|Builder
    {
        return Buyer::query()->where('uuid', $uuid)->first();
    }

    public function getNumbersSalesman(string $provider): Collection|array
    {
        return match ($provider)
        {
            TypeNumberEnum::telegram->name => Number::query()
                ->where('status_number', StatusNumberEnum::pending)
                ->where('type_number', TypeNumberEnum::telegram)
                ->whereNull('buyer_uuid')
                ->orderBy('created_at')
                ->get(),

            TypeNumberEnum::whatsapp->name => Number::query()
                ->where('status_number', StatusNumberEnum::pending)
                ->where('type_number', TypeNumberEnum::whatsapp)
                ->whereNull('buyer_uuid')
                ->orderBy('created_at')
                ->get(),
        };
    }
}
