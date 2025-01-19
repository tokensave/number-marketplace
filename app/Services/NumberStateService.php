<?php

namespace App\Services;

use App\Models\AddNumberState;
use App\Models\CodeNumberState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class NumberStateService
{
    //состояние при добавлении номеров сотрудником(продавцом)
    public function storeAddNumberState(string $seller_id, string $provider)
    {
        return AddNumberState::create(
            [
                'seller_id' => $seller_id,
                'waiting_for_number' => true,
                'provider' => $provider,
            ],
        );
    }

    //получение состояния при добавлении номеров сотрудником(продавцом)
    public function getAddNumberState(string $seller_id)
    {
        return AddNumberState::query()
            ->where('seller_id', $seller_id)->where('waiting_for_number', true)->first();
    }

    //удаление состояние при добавлении номеров сотрудником(продавцом)
    public function deleteAddNumberState(string $seller_id): void
    {
        AddNumberState::query()
            ->where('seller_id', $seller_id)->where('waiting_for_number', true)->delete();
    }

    public function getCodeNumberState(string $seller_id): Collection
    {
        return CodeNumberState::query()
            ->where('seller_id', $seller_id)->where('waiting_code', true)->whereNotNull('buyer_id')->get();
    }

    public function getCodeNumberBuyerId(string $number): Model
    {
        return CodeNumberState::query()
            ->where('number', $number)->first();
    }

    public function pendingCode(string $buyer_id, string $provider, string $number): Model|Builder
    {
        return CodeNumberState::updateOrCreate(
            ['number' => $number, 'provider' => $provider],
            ['buyer_id' => $buyer_id, 'request_count' => 1]
        );
    }

    public function getPendingCodeNumber(string $number, string $buyer_id): Model|Builder
    {
        return CodeNumberState::query()
            ->where('number', $number)
            ->where('buyer_id', $buyer_id)
            ->where('request_count', '<', 2)
            ->first();
    }

    public function getPendingCodeNumberWihtSeller(string $number, string $seller_id): Model|Builder
    {
        return CodeNumberState::query()
            ->where('number', $number)
            ->where('seller_id', $seller_id)
            ->where('request_count', '<', 2)
            ->first();
    }

    public function getDeactiveNumberWihtSeller(string $number, string $seller_id): Model|Builder
    {
        return CodeNumberState::query()
            ->where('number', $number)
            ->where('seller_id', $seller_id)
            ->where('request_count', '>=', 2)
            ->first();
    }

    public function createStateForNumber(string $seller_id, string $number, string $provider): void
    {
        CodeNumberState::create([
            'seller_id' => $seller_id,
            'number' => $number,
            'provider' => $provider,
        ]);
    }

    public function deleteCodeNumberState(string $buyer_id, string $number): void
    {
        CodeNumberState::where('buyer_id', $buyer_id)->where('number', $number)->delete();
    }

}
