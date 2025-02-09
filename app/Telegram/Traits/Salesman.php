<?php

namespace App\Telegram\Traits;

use App\Enums\StatusNumberEnum;
use App\Enums\TypeNumberEnum;
use App\Enums\UserTypeEnum;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

trait Salesman
{
    // Этот трейт отвечает за функционал Продавца
    use Support;

    //Основная функция
    public function salesman(): void
    {
        $buttons = [];
        $salesman = $this->salesmanService->storeSalesman($this->chat->chat_id, $this->chat->name);
        $btn_return = $this->getTextForMsg('btn_return');
        $btn_add_number = $this->getTextForMsg('btn_add_number_seller');
        $btn_status = $this->getTextForMsg('btn_status_number_seller');
        $btn_statistics = $this->getTextForMsg('btn_stats_number');
        if (!$salesman->enabled) {
            $msg = $this->getTextForMsg('text_no_active');
        } else {
            $msg = $this->getTextForMsg('text_action');
            $buttons = array_merge([
                Button::make($btn_add_number)->action('addNumbers'),
                Button::make($btn_status)->action('getNumbersStatus'),
                Button::make($btn_statistics)->action('getSellerStatistics')
            ], $buttons);
        }
        $buttons[] = Button::make($btn_return)->action('return');
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($msg, $keyboard);
    }

    //Функция по добавлению номеров
    public function addNumbers(): void
    {
        $btn_provider = $this->getTextForMsg('btn_provider');
        $text_tg_provider = $this->getTextForMsg('text_tg_provider');
        $text_whatsApp_provider = $this->getTextForMsg('text_whatsApp_provider');
        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = [
            Button::make($text_tg_provider)->action('addNumbersSalesmen')->param('provider', TypeNumberEnum::telegram->name),
            Button::make($text_whatsApp_provider)->action('addNumbersSalesmen')->param('provider', TypeNumberEnum::whatsapp->name),
            Button::make($btn_return)->action('salesman'),
        ];
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($btn_provider, $keyboard);
    }

    //Функция добавления номеров продавцом
    public function addNumbersSalesmen(): void
    {
        $provider = $this->data->get('provider');

        //создание состояния для добавления номера
        $this->numberStateService->storeAddNumberState($this->chat->chat_id, $provider);

        $text_add_tg_number = $this->getTextForMsg('text_add_tg_number');
        $text_add_whatsApp_number = $this->getTextForMsg('text_add_whatsApp_number');
        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = [Button::make($btn_return)->action('addNumbers')];
        $keyboard = Keyboard::make()->buttons($buttons);
        switch ($provider) {
            case TypeNumberEnum::telegram->name :
                $this->sendMsg($text_add_tg_number, $keyboard);
                break;
            case TypeNumberEnum::whatsapp->name :
                $this->sendMsg($text_add_whatsApp_number, $keyboard);
                break;
        }
    }

    //Функция проверки статусов номеров
    public function getNumbersStatus(): void
    {
        $msg = $this->getTextForMsg('text_status_number');
        $btn_active = $this->getTextForMsg('btn_active_number');
        $btn_deactivate = $this->getTextForMsg('btn_deactivate_number');
        $btn_bought = $this->getTextForMsg('btn_bought_number');
        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = [
            Button::make($btn_active)->action('getPendingNumbers'),
            Button::make($btn_deactivate)->action('getDeactivateNumbers'),
            Button::make($btn_bought)->action('getBoughtNumbers'),
            Button::make($btn_return)->action('salesman'),
        ];
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($msg, $keyboard);
    }

    //Функция отражения активных номеров
    public function getPendingNumbers(): void
    {
        $salesmen = $this->salesmanService->getSalesman($this->chat->chat_id);

        $numbers = $this->numberService->getPendingNumbers($salesmen);

        if ($numbers->isEmpty()) {
            $msg = $this->getTextForMsg('text_no_active_numbers');
            $this->reply($msg);
            return;
        }

        $buttons = [];

        foreach ($numbers as $number) {
            $buttons[] = Button::make($number->number)->action('deleteNumbers')->param('number', $number->number);
        }
        //удалено подсчет номеров
        $btn_delete = $this->getTextForMsg('btn_delete_number');
        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = array_merge($buttons, [
            Button::make($btn_return)->action('getNumbersStatus')
        ]);
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($btn_delete, $keyboard);

    }

    //Функция отражения неактивных номеров
    public function getDeactivateNumbers(): void
    {
        $salesmen = $this->salesmanService->getSalesman($this->chat->chat_id);

        $numbers = $this->numberService->getDeactiveStatusNumbers($salesmen);

        if ($numbers->isEmpty()) {
            $msg = $this->getTextForMsg('text_no_deactivate_numbers');
            $this->reply($msg);
            return;
        }

        $buttons = [];

        foreach ($numbers as $number) {
            $buttons[] = Button::make($number->number)->action('deleteNumbers')->param('number', $number->number);
        }

        $btn_delete = $this->getTextForMsg('btn_delete_number');
        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = array_merge($buttons, [
            Button::make($btn_return)->action('getNumbersStatus')
        ]);
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($btn_delete, $keyboard);

    }

    //Функция отражения купленных номеров
    public function getBoughtNumbers(): void
    {
        $salesmen = $this->salesmanService->getSalesman($this->chat->chat_id);

        $numbers = $this->numberService->getActiveStatusNumbers($salesmen);

        if ($numbers->isEmpty()) {
            $msg = $this->getTextForMsg('text_no_bought_numbers');
            $this->reply($msg);
            return;
        }

        $buttons = [];

        foreach ($numbers as $number) {
            //тут стоит заглушка
            $buttons[] = Button::make($number->number)->action('blockNumber');
        }

        $count_numbers = count($numbers);
        $text_count_number = $this->getTextForMsg('text_count_numbers');
        $text_count_number = str_replace('#numbers#', (string)$count_numbers, $text_count_number);
        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = array_merge($buttons, [
            Button::make($btn_return)->action('getNumbersStatus')
        ]);
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($text_count_number, $keyboard);
    }

    //Функция удаления номера
    public function deleteNumbers(): void
    {
        $this->numberService->deleteNumber($this->data->get('number'));

        $text_delete = $this->getTextForMsg('text_delete_number');
        $btn_return = $this->getTextForMsg('btn_return');
        $button = [Button::make($btn_return)->action('salesman')];
        $keyboard = Keyboard::make()->buttons($button);
        $this->sendMsg($text_delete, $keyboard);
    }

    public function getSellerStatistics(): void
    {
        $salesman = $this->salesmanService->getSalesman($this->chat->chat_id);
        $telegram = $this->numberService->getTelegramNumbers($salesman);
        $whatsapp = $this->numberService->getWhatsAppNumbers($salesman);
        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = Keyboard::make()->buttons([
            Button::make($btn_return)->action('salesman')
        ]);
        $text_change = $this->getTextForMsg('text_action');

        if (count($telegram) > 0) {
            $active = count($telegram->where('status_number', StatusNumberEnum::active));
            $deactivate = count($telegram->where('status_number', StatusNumberEnum::active));
            $pending = count($telegram->where('status_number', StatusNumberEnum::pending));
            $count_numbers = $this->numberStatisticService->getCountNumbers(TypeNumberEnum::telegram->name);
            $this->userStatisticsService->createStatistics($salesman->uuid, UserTypeEnum::seller->name(), $salesman->name, TypeNumberEnum::telegram->name, $active, $deactivate, $pending);
            $message = "<b>🔵 Telegram 🔵</b>" .
                "\n\nВсего номеров в очереди: " . $count_numbers . "\n\n" .
                "Купленные номера: " . $active . "\n\n" .
                "Номера в ожидании: " . $pending . "\n\n" .
                "Слетевшие номера: " . $deactivate;
            $this->sendMsg($message, null, 0);
        }
        if (count($whatsapp) > 0) {
            $active = count($whatsapp->where('status_number', StatusNumberEnum::active));
            $deactivate = count($whatsapp->where('status_number', StatusNumberEnum::active));
            $pending = count($whatsapp->where('status_number', StatusNumberEnum::pending));
            $count_numbers = $this->numberStatisticService->getCountNumbers(TypeNumberEnum::telegram->name);
            $this->userStatisticsService->createStatistics($salesman->uuid, UserTypeEnum::seller->name(), $salesman->name, TypeNumberEnum::whatsapp->name, $active, $deactivate, $pending);
            $message = "<b>🟢 WhatsApp 🟢</b>" .
                "\n\nВсего номеров в очереди: " . $count_numbers . "\n\n" .
                "Купленные номера: " . $active . "\n\n" .
                "Номера в ожидании: " . $pending . "\n\n" .
                "Слетевшие номера: " . $deactivate;
            $this->sendMsg($message, null, 0);
        }
        if (count($telegram) === 0 && count($whatsapp) === 0) {
            $text_no_stats = $this->getTextForMsg('text_no_stats');
            $this->sendMsg($text_no_stats);
        }
        $this->sendMsg($text_change, $buttons, 0);
    }

    public function return(): void
    {
        $this->start(1);
    }
}
