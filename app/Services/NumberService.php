<?php

namespace App\Services;

use App\Enums\StatusNumberEnum;
use App\Enums\TypeNumberEnum;
use App\Models\Number;

class NumberService
{
    public function getNumber(string $number)
    {
        return Number::query()->where('number', $number)->first();
    }

    public function updateNumberWithBuyerUuid(string $number, string $uuid, StatusNumberEnum $numberEnum): void
    {
        $number = Number::query()->where('number', $number)->first();

        $number->update(
            [
                'buyer_uuid' => $uuid,
                'status_number' => $numberEnum
            ]);
    }

    public function updateStatusNumber(string $provider, string $number): void
    {
        Number::query()->where('number', $number)->where('type_number', $provider)->update(['status_number' => StatusNumberEnum::payable]);
    }

    public function getPendingNumbers($salesmen)
    {
        return $salesmen->numbers()
            ->where('status_number', StatusNumberEnum::pending)
            ->whereNull('buyer_uuid')
            ->get();
    }

    public function getActiveStatusNumbers($salesman)
    {
        return $salesman->numbers()
            ->where('status_number', StatusNumberEnum::active)
            ->whereNotNull('buyer_uuid')
            ->get();
    }

    public function getDeactiveStatusNumbers($salesmen)
    {

        return $salesmen->numbers()
            ->where('status_number', StatusNumberEnum::failed)
            ->get();

    }

    public function getTelegramNumbers($salesmen, $status = null)
    {
        $query = $salesmen->numbers()
            ->where('type_number', TypeNumberEnum::telegram);

        if ($status)
            $query->where('status_number', $status);

        return $query->get();
    }

    public function getWhatsAppNumbers($salesmen, $status = null)
    {
        $query = $salesmen->numbers()
            ->where('type_number', TypeNumberEnum::whatsapp);

        if ($status)
            $query->where('status_number', $status);

        return $query->get();
    }

    public function getWithBuyerNumbers($buyer_uuid, $status = null, $provider = null, $number = null)
    {
        $query = Number::query()->where('buyer_uuid', $buyer_uuid);

        if ($status)
            $query->where('status_number', $status);

        if ($provider)
            $query->where('type_number', $provider);

        if ($number)
            return $query->where('number', $number)->first();
        else{
            return $query->get();
        }
    }

    public function deleteNumber(string $number)
    {
        Number::query()->where('number', $number)->delete();
    }
}
