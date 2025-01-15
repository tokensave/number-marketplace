<?php

namespace App\Telegram\Traits;

use App\Enums\StatusNumberEnum;
use App\Enums\TypeNumberEnum;
use App\Enums\UserTypeEnum;
use App\Jobs\DeactivateNumberJob;
use App\Telegram\ButtonsConstruct;
use Carbon\Carbon;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

trait Buyer
{
    use Support;

    public function buyer(): void
    {
        $buttons = [];
        $buyer = $this->buyerService->storeBuyer($this->chat->chat_id);
        $btn_return = $this->getTextForMsg('btn_return');
        $btn_buy = $this->getTextForMsg('btn_buy_number');
        $btn_get_numbers = $this->getTextForMsg('btn_get_buyer_numbers');
        $btn_stats = $this->getTextForMsg('btn_stats_number');
        if (!$buyer->enabled) {
            $msg = $this->getTextForMsg('text_no_active');
        } else {
            $msg = $this->getTextForMsg('text_action');
            $buttons = array_merge([
                Button::make($btn_buy)->action('buyNumbers'),
                Button::make($btn_get_numbers)->action('getNumbersBuyer'),
                Button::make($btn_stats)->action('getBuyerStatistics')
            ], $buttons);
        }
        $buttons[] = Button::make($btn_return)->action('back');
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($msg, $keyboard);
    }

    public function buyNumbers(): void
    {
        $btn_provider = $this->getTextForMsg('btn_provider');
        $text_tg_provider = $this->getTextForMsg('text_tg_provider');
        $text_whatsApp_provider = $this->getTextForMsg('text_whatsApp_provider');
        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = [
            Button::make($text_tg_provider)->action('getNumbersSalesmen')->param('provider', TypeNumberEnum::telegram->name),
            Button::make($text_whatsApp_provider)->action('getNumbersSalesmen')->param('provider', TypeNumberEnum::whatsapp->name),
            Button::make($btn_return)->action('buyer'),
        ];
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($btn_provider, $keyboard);
    }

    public function getNumbersSalesmen(): void
    {
        $buttons = [];
        $btn_return = $this->getTextForMsg('btn_return');
        $provider = $this->data->get('provider');
        $numbers = $this->buyerService->getNumbersSalesman($provider);
        if (count($numbers) === 0) {
            $msg = $this->getTextForMsg('text_no_numbers_for_sale');
        } else {
            $msg = $this->getTextForMsg('text_for_buy_numbers');
            foreach ($numbers as $number) {
                $buttons = array_merge(
                    [
                        Button::make($number->number)->action('byuNumber')->param('number', $number->number)
                    ], $buttons);
            }
        }
        $buttons[] = Button::make($btn_return)->action('buyNumbers');
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($msg, $keyboard);
    }

    public function byuNumber(): void
    {
        $number = $this->numberService->getNumber($this->data->get('number'));
        //добавляем покупателя на номер и заводим счетчик попыток отправки кода(доделать включение номера в процесс работы)
        $this->numberStateService->pendingCode($this->chat->chat_id, $number->type_number->name, $number->number);
        $this->numberService->updateStatusNumber($number->type_number->name, $number->number);
        $msg = $this->getTextForMsg('text_action');
        $btn_return = $this->getTextForMsg('btn_return');
        $btn_get_code = $this->getTextForMsg('btn_get_code');
        $buttons = [
            Button::make($btn_get_code)->action('getCode')->param('number', $number->number),
            Button::make($btn_return)->action('buyNumbers'),
        ];
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($msg, $keyboard);
    }

    public function getCode(): void
    {
        $text_await_code = $this->getTextForMsg('text_await_code');
        $this->chat->message($text_await_code)->send();
        //получаем состояние по номеру, покупателю чтобы отправить сообщение
        $number_state = $this->numberStateService->getPendingCodeNumber($this->data->get('number'), $this->chat->chat_id);
        if (!$number_state) {
            $msg_error = $this->getTextForMsg('text_error_buy');
            $btn_return = $this->getTextForMsg('btn_return');
            $buttons = [Button::make($btn_return)->action('buyNumbers')];
            $keyboard = Keyboard::make()->buttons($buttons);
            $this->sendMsg($msg_error, $keyboard);
        } else {
            $this->processNumberState($number_state);
        }
    }

    public function processNumberState($number_state): void
    {
        dispatch(new DeactivateNumberJob($number_state->number, $number_state->buyer_id))->delay(now()->addMinutes(2));
        $text_for_salesman = $this->getTextForMsg('text_for_await_code_number');
        $text_for_salesman = str_replace('#number#', $number_state->number, $text_for_salesman);
        $btn_no_code = $this->getTextForMsg('btn_for_no_code_salesman');
        Telegraph::chat($number_state->seller_id)
            ->message($text_for_salesman)
            ->keyboard(
                Keyboard::make()->buttons(
                    [
                        Button::make($btn_no_code)->action('noCodeForSalesman')->param('number', $number_state->number),
                    ])
            )
            ->send();
    }

    public function noCodeForSalesman(): void
    {
        $number_state = $this->numberStateService->getPendingCodeNumberWihtSeller($this->data->get('number'), $this->chat->chat_id);
        if (!$number_state) {
            $msg = $this->getTextForMsg('text_deactivate_number');
            $msg = str_replace('#number#', $this->data->get('number'), $msg);
            Telegraph::chat($number_state->seller_id)->message($msg)->send();
        } else {
            $number_state->increment('request_count');
            $msg = $this->getTextForMsg('text_deactivate_repeat');
            $btn_deactivate = $this->getTextForMsg('btn_deactivate_number');
            $buttons = [Button::make($btn_deactivate)->action('deactivateNumber')->param('number', $number_state->number)];
            $keyboard = Keyboard::make()->buttons($buttons);
            Telegraph::chat($number_state->seller_id)->message($msg)->keyboard($keyboard)->send();
        }
    }

    public function deactivateNumber(): void
    {
        $state = $this->numberStateService->getDeactiveNumberWihtSeller($this->data->get('number'), $this->chat->chat_id);
        $msg = $this->getTextForMsg('text_deactivate_number');
        $msg = str_replace('#number#', $state->number, $msg);
        $btn_number_change = $this->getTextForMsg('btn_number_change');
        Telegraph::chat($state->buyer_id)->message($msg)->keyboard(Keyboard::make()->buttons(
            [Button::make($btn_number_change)->action('buyNumbers')]))->send();
        $state->delete();
        $number = $this->numberService->getNumber($this->data->get('number'));
        $number->update(['status_number' => StatusNumberEnum::failed]);
        $this->sendMsg($msg);
    }

    //положительный статус кода и реализация логики
    public function codeReceived(): void
    {
        $number_data = $this->data->get('number');
        $this->numberService->updateNumberWithBuyerUuid($number_data, $this->chat->chat_id, StatusNumberEnum::active);
        $number = $this->numberService->getNumber($number_data);
        $salesman_chat = $number->salesman->uuid;
        $text_number_true = $this->getTextForMsg('text_buy_number_true');
        $text_number_true = str_replace('#number#', $number_data, $text_number_true);
        Telegraph::chat($salesman_chat)->message($text_number_true)->send();
        $this->numberStateService->deleteCodeNumberState($this->chat->chat_id, $number_data);
        $this->chat->deleteKeyboard($this->messageId)->send();
        $btn_return = $this->getTextForMsg('btn_return');
        $text_add_number = $this->getTextForMsg('text_add_number_buyer');
        $buttons = [Button::make($btn_return)->action('buyNumbers')];
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($text_add_number, $keyboard);
    }

    public function codeFalseReceived(): void
    {
        $number_data = $this->data->get('number');
        $this->numberService->updateNumberWithBuyerUuid($number_data, $this->chat->chat_id, StatusNumberEnum::failed);
        $number = $this->numberService->getNumber($number_data);
        $salesman_chat = $number->salesman->uuid;
        $text_number_false = $this->getTextForMsg('text_buy_number_false');
        $text_number_false = str_replace('#number#', $number_data, $text_number_false);
        Telegraph::chat($salesman_chat)->message($text_number_false)->send();
        $this->numberStateService->deleteCodeNumberState($this->chat->chat_id, $number_data);
        $this->chat->deleteKeyboard($this->messageId)->send();
        $btn_return = $this->getTextForMsg('btn_return');
        $text_no_add_number_buyer = $this->getTextForMsg('text_no_add_number_buyer');
        $buttons = [Button::make($btn_return)->action('buyNumbers')];
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($text_no_add_number_buyer, $keyboard);
    }

    public function getNumbersBuyer(): void
    {
        $buttons = [];
        $numbers = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, StatusNumberEnum::active);
        if ($numbers->isEmpty()) {
            $msg = $this->getTextForMsg('text_no_bought_numbers');
            $this->reply($msg);
            return;
        }

        foreach ($numbers as $number) {
            $buttons[] = Button::make($number->number)->action('disputeNumber')->param('number', $number->number);
        }
        $count_numbers = count($numbers);
        $text_bought_numbers = $this->getTextForMsg('text_bought_numbers');
        $text_bought_numbers = str_replace('#count_numbers#', $count_numbers, $text_bought_numbers);
        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = array_merge($buttons, [Button::make($btn_return)->action('buyer')]);
        // Отправляем одно сообщение с кнопками номеров
        $keyboard = Keyboard::make()->buttons($buttons);
        $this->sendMsg($text_bought_numbers, $keyboard);
    }

    public function disputeNumber(): void
    {
        $number = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, StatusNumberEnum::active, null, $this->data->get('number'));

        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = [Button::make($btn_return)->action('getNumbersBuyer')];
        $keyboard = Keyboard::make()->buttons($buttons);
        if (!$number) {
            $msg = $this->getTextForMsg('text_dispute_numbers_false');
            $this->sendMsg($msg, $keyboard);
            return;
        }

        $updateAt = Carbon::parse($number->updated_at);
        $now = Carbon::now();

        $diffMinutes = abs($now->diffInMinutes($updateAt, false)); // получаем положительное значение

        if ($diffMinutes > 10) {
            $msg = $this->getTextForMsg('text_number_dispute_not_added');
            $msg = str_replace('#number#', $number->number, $msg);
        } else {
            $salesman_chat = $number->salesman->uuid;
            $msg = $this->getTextForMsg('text_number_dispute_added');
            $msg = str_replace('#number#', $number->number, $msg);
            Telegraph::chat($salesman_chat)->message($msg)->send();
            $number->update(['status_number' => StatusNumberEnum::failed]);
        }
        $this->sendMsg($msg, $keyboard);
    }

    public function getBuyerStatistics(): void
    {
        $telegram = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, null, TypeNumberEnum::telegram);
        $whatsapp = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, null, TypeNumberEnum::whatsapp);
        $buyerModel = $this->buyerService->getBuyer($this->chat->chat_id);

        if (count($telegram) > 0) {
            $active = count($telegram->where('status_number', StatusNumberEnum::active));
            $deactivate = count($telegram->where('status_number', StatusNumberEnum::failed));
            $this->userStatisticsService->createStatistics($this->chat->chat_id, UserTypeEnum::buyer->name, $buyerModel->name, TypeNumberEnum::telegram->name, $active, $deactivate, null);
            $message = "<b>🔵 Telegram 🔵</b>" . "\n\n" .
                "Купленные номера: " . $active . "\n\n" .
                "Слетевшие номера: " . $deactivate;
            $this->chat->message($message)->send();
        }
        if (count($whatsapp) > 0) {
            $active = count($whatsapp->where('status_number', StatusNumberEnum::active));
            $deactivate = count($whatsapp->where('status_number', StatusNumberEnum::failed));
            $this->userStatisticsService->createStatistics($this->chat->chat_id, UserTypeEnum::buyer->name, $buyerModel->name, TypeNumberEnum::whatsapp->name, $active, $deactivate, null);
            $message = "<b>🟢 WhatsApp 🟢</b>" . "\n\n" .
                "Купленные номера: " . $active . "\n\n" .
                "Слетевшие номера: " . $deactivate;
            $this->chat->message($message)->send();
        }
        if (count($telegram) === 0 && count($whatsapp) === 0) {
            (new ButtonsConstruct($this->chat, "<b>❌ Нет статистики. ❌ </b>", 'Назад', 'storeBuyer'))->storeButton();
        }
    }

    public function back(): void
    {
        $this->start(1);
    }
}
