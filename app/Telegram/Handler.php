<?php

namespace App\Telegram;

use App\Enums\LogTelegramTypeEnum;
use App\Enums\StatusNumberEnum;
use App\Enums\TypeNumberEnum;
use App\Models\LogTelegram;
use App\Services\BuyerService;
use App\Services\NumberService;
use App\Services\NumberStateService;
use App\Services\NumberStatisticService;
use App\Services\SalesmanService;
use App\Services\UserStatisticsService;
use App\Telegram\Traits\Buyer;
use App\Telegram\Traits\Salesman;
use App\Telegram\Traits\Support;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Stringable;

class Handler extends WebhookHandler
{
    use Support, Salesman, Buyer;

    protected SalesmanService $salesmanService;
    protected BuyerService $buyerService;
    protected NumberService $numberService;
    protected NumberStateService $numberStateService;
    protected NumberStatisticService $numberStatisticService;
    protected UserStatisticsService $userStatisticsService;

    public function __construct(
        SalesmanService        $salesmanService,
        NumberService          $numberService,
        NumberStateService     $numberStateService,
        BuyerService           $buyerService,
        NumberStatisticService $numberStatisticService,
        UserStatisticsService  $userStatisticsService,
    )
    {
        parent::__construct();
        // Инициализация сервисов
        $this->salesmanService = $salesmanService;
        $this->numberService = $numberService;
        $this->numberStateService = $numberStateService;
        $this->buyerService = $buyerService;
        $this->numberStatisticService = $numberStatisticService;
        $this->userStatisticsService = $userStatisticsService;
    }

    public function start($edit = 0): void
    {
        $buyer_btn = $this->getTextForMsg('btn_buyer');
        $seller_btn = $this->getTextForMsg('btn_seller');
        $buttons = Keyboard::make()->buttons([
            Button::make($buyer_btn)->action('buyer'),
            Button::make($seller_btn)->action('salesman'),
        ]);
        $msg = $this->getTextForMsg('text_hello');
        $msg = str_replace('#name#', $this->chat->name, $msg);
        $this->sendMsg($msg, $buttons, (int)$edit);
    }
    //логика обработки входящих сообщений
    public function handleChatMessage(Stringable $text): void
    {
        try {
            $create_number = false;
            //состояние при добавлении номера
            $addNumberState = $this->numberStateService->getAddNumberState($this->chat->chat_id);

            if ($addNumberState) {
                $this->processNumberInput($addNumberState);
                $create_number = true;
            }

            //состояния при ожидании кода
            $codeNumberState = $this->numberStateService->getCodeNumberState($this->chat->chat_id);

            if (!empty($codeNumberState) && !$create_number) {
                // Проверим, является ли сообщение ответом
                if ($this->message->replyToMessage()) {
                    $this->processReplyCodeInput();
                }
            }

        } catch (\Exception $exception) {
            // Логируем исключение для отладки
            LogTelegram::create([
                'chat_id' => $this->chat->chat_id,
                'data' => $exception->getMessage(),
                'type' => LogTelegramTypeEnum::unsuccessful
            ]);
            $text_unknown = $this->getTextForMsg('text_unknown_command');
            $btn_return = $this->getTextForMsg('btn_return');
            $buttons = Keyboard::make()->buttons([
                Button::make($btn_return)->action('start'),
            ]);
            $this->sendMsg($text_unknown, $buttons);
        }
    }
    private function processReplyCodeInput(): void
    {
        // Получаем текст исходного сообщения, на которое был ответ
        $originalMessage = $this->message->replyToMessage()->text();

        // Ищем номер в исходном сообщении (например, он был в уведомлении)
        preg_match('/номера\s*:\s*(\d+)/i', $originalMessage, $matches);
        if (count($matches) < 2) {
            $msg = $this->getTextForMsg('text_error_unknown_number');
            $this->sendMsg($msg);
            return;
        }

        $number_input = $matches[1]; // Номер из исходного сообщения
        $code_input = trim($this->message->text()); // Код из ответа

        // по номеру получаем нужное состояние и покупателя
        $necessaryState = $this->numberStateService->getCodeNumberBuyerId($number_input);
        $buyer_id = $necessaryState->buyer_id;

        $text_code_salesman = $this->getTextForMsg('text_code_sending_salesman');
        $text_code_salesman = str_replace('#number#', $necessaryState->number, $text_code_salesman);

        Telegraph::chat($necessaryState->seller_id)->message($text_code_salesman)->send();

        $text_code_buyer = $this->getTextForMsg('text_code_sending_buyer');
        $text_code_buyer = str_replace('#number#', $necessaryState->number, $text_code_buyer);
        $text_code_buyer = str_replace('#code#', $code_input, $text_code_buyer);
        $btn_code_true = $this->getTextForMsg('btn_code_successful');
        $btn_code_false = $this->getTextForMsg('btn_code_unsuccessful');

        // Отправляем код покупателю
        Telegraph::chat($buyer_id)->message($text_code_buyer)
            ->keyboard(
                Keyboard::make()->buttons([
                    Button::make($btn_code_true)->action('codeReceived')->param('number', $necessaryState->number),
                    Button::make($btn_code_false)->action('codeFalseReceived')->param('number', $necessaryState->number),
                ])
            )
            ->send();
    }
    //логика обработки входящих номеров от продавца(сотрудника)
    private function processNumberInput($number_state): void
    {
        try {
            // Получаем введенные номера от продавца
            $numbers = $this->message?->text();

            if (empty($numbers)) {
                $text_false = $this->getTextForMsg('text_dispute_numbers_false');
                $btn_return = $this->getTextForMsg('btn_return');
                $buttons = Keyboard::make()->buttons([
                    Button::make($btn_return)->action('salesman'),
                ]);
                $this->sendMsg($text_false, $buttons);

                //удаляем состояние
                $this->numberStateService->deleteAddNumberState($this->chat->chat_id);
                return;
            }

            $number_array = explode("\n", $numbers);

            // Сохраняем номера, в зависимости от провайдера
            match ($number_state->provider) {
                TypeNumberEnum::telegram->name => $this->saveNumber($number_array, TypeNumberEnum::telegram),
                TypeNumberEnum::whatsapp->name => $this->saveNumber($number_array, TypeNumberEnum::whatsapp),
            };

        } catch (\Exception $exception) {
            // Логируем исключение для отладки
            LogTelegram::create([
                'chat_id' => $this->chat->chat_id,
                'data' => $exception->getMessage(),
                'type' => LogTelegramTypeEnum::unsuccessful
            ]);

            // Удаляем состояние
            $this->numberStateService->deleteAddNumberState($this->chat->chat_id);

            // Можно добавить сообщение об ошибке для пользователя
            $text_bad = $this->getTextForMsg('text_bad_command_number');
            $btn_return = $this->getTextForMsg('btn_return');
            $buttons = Keyboard::make()->buttons([
                Button::make($btn_return)->action('salesman'),
            ]);
            $this->sendMsg($text_bad, $buttons);
        }
    }
    //логика сохранения номера
    public function saveNumber($number, $provider): void
    {
        $count_numbers = $this->numberStatisticService->getCountNumbers($provider->name);
        $salesman = $this->salesmanService->getSalesman($this->chat->chat_id);
        $this->numberStateService->deleteAddNumberState($this->chat->chat_id);


        if ($salesman) {
            $text_count = $this->getTextForMsg('text_count_all');
            $text_count = str_replace('#count_numbers#', $count_numbers, $text_count);
            $this->reply($text_count);

            $count = 0;
            // Сохраняем номер для продавца
            foreach ($number as $num) {
                if (preg_match('/^\d{10}$/', $num)) {
                    // Если номер прошел валидацию (только цифры и ровно 10 символов)
                    $salesman->numbers()->create([
                        'number' => $num,
                        'type_number' => $provider,
                        'status_number' => StatusNumberEnum::pending
                    ]);

                    $count += 1;

                    // Обновляем состояние номера на ожидающий код
                    $this->numberStateService->createStateForNumber($this->chat->chat_id, $num, $provider->name);
                } else {
                    // Если номер не прошел валидацию
                    $text_validate = $this->getTextForMsg('text_numbers_validate');
                    $text_validate = str_replace('#number#', $num, $text_validate);
                    $this->reply($text_validate);
                }
            }
        } else {
            $text_sal_bad = $this->getTextForMsg('text_salesman_validate');
            $this->sendMsg($text_sal_bad);
        }

        $text_add = $this->getTextForMsg('text_add_number_handler');
        $text_add = str_replace('#count#', $count, $text_add);
        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = Keyboard::make()->buttons([
            Button::make($btn_return)->action('salesman'),
        ]);
        $this->sendMsg($text_add, $buttons);
    }

//    public function getUserStatistics(): void
//    {
//        $buyer = $this->data->get('buyer');
//
//        if (!$buyer) {
//            $salesman = $this->salesmanService->getSalesman($this->chat->chat_id);
//            $telegram = $this->numberService->getTelegramNumbers($salesman);
//            $whatsapp = $this->numberService->getWhatsAppNumbers($salesman);
//
//            if (count($telegram) > 0) {
//                $active = $this->numberService->getTelegramNumbers($salesman, StatusNumberEnum::active);
//                $deactivate = $this->numberService->getTelegramNumbers($salesman, StatusNumberEnum::failed);
//                $pending = $this->numberService->getTelegramNumbers($salesman, StatusNumberEnum::pending);
//                $count_numbers = $this->numberStatisticService->getCountNumbers(TypeNumberEnum::telegram->name);
//                $this->userStatisticsService->createStatistics($salesman->uuid, UserTypeEnum::seller->name, $salesman->name, TypeNumberEnum::telegram->name, count($active), count($deactivate), count($pending));
//                $message = "<b>🔵 Telegram 🔵</b>" .
//                    "\n\nВсего номеров в очереди: " . $count_numbers . "\n\n" .
//                    "Купленные номера: " . count($active) . "\n\n" .
//                    "Номера в ожидании: " . count($pending) . "\n\n" .
//                    "Слетевшие номера: " . count($deactivate);
//                $this->chat->message($message)->send();
//            }
//            if (count($whatsapp) > 0) {
//                $active = $this->numberService->getWhatsAppNumbers($salesman, StatusNumberEnum::active);
//                $deactivate = $this->numberService->getWhatsAppNumbers($salesman, StatusNumberEnum::failed);
//                $pending = $this->numberService->getWhatsAppNumbers($salesman, StatusNumberEnum::pending);
//                $count_numbers = $this->numberStatisticService->getCountNumbers(TypeNumberEnum::telegram->name);
//                $this->userStatisticsService->createStatistics($salesman->uuid, UserTypeEnum::seller->name, $salesman->name, TypeNumberEnum::whatsapp->name, count($active), count($deactivate), count($pending));
//                $message = "<b>🟢 WhatsApp 🟢</b>" .
//                    "\n\nВсего номеров в очереди: " . $count_numbers . "\n\n" .
//                    "Купленные номера: " . count($active) . "\n\n" .
//                    "Номера в ожидании: " . count($pending) . "\n\n" .
//                    "Слетевшие номера: " . count($deactivate);
//                $this->chat->message($message)->send();
//            }
//            if (count($telegram) === 0 && count($whatsapp) === 0) {
//                (new ButtonsConstruct($this->chat, "<b>Нет статистики.</b>", 'Назад', 'salesman'))->storeButton();
//            }
//        } else {
//            $telegram = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, null, TypeNumberEnum::telegram);
//            $whatsapp = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, null, TypeNumberEnum::whatsapp);
//            $buyerModel = $this->buyerService->getBuyer($this->chat->chat_id);
//
//            if (count($telegram) > 0) {
//                $active = count($telegram->where('status_number', StatusNumberEnum::active));
//                $deactivate = count($telegram->where('status_number', StatusNumberEnum::failed));
//                $this->userStatisticsService->createStatistics($this->chat->chat_id, UserTypeEnum::buyer->name, $buyerModel->name, TypeNumberEnum::telegram->name, $active, $deactivate, null);
//                $message = "<b>🔵 Telegram 🔵</b>" . "\n\n" .
//                    "Купленные номера: " . $active . "\n\n" .
//                    "Слетевшие номера: " . $deactivate;
//                $this->chat->message($message)->send();
//            }
//            if (count($whatsapp) > 0) {
//                $active = count($whatsapp->where('status_number', StatusNumberEnum::active));
//                $deactivate = count($whatsapp->where('status_number', StatusNumberEnum::failed));
//                $this->userStatisticsService->createStatistics($this->chat->chat_id, UserTypeEnum::buyer->name, $buyerModel->name, TypeNumberEnum::whatsapp->name, $active, $deactivate, null);
//                $message = "<b>🟢 WhatsApp 🟢</b>" . "\n\n" .
//                    "Купленные номера: " . $active . "\n\n" .
//                    "Слетевшие номера: " . $deactivate;
//                $this->chat->message($message)->send();
//            }
//            if (count($telegram) === 0 && count($whatsapp) === 0) {
//                (new ButtonsConstruct($this->chat, "<b>❌ Нет статистики. ❌ </b>", 'Назад', 'storeBuyer'))->storeButton();
//            }
//        }
//    }


//    public function disputeNumber(): void
//    {
//        $number = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, StatusNumberEnum::active, null, $this->data->get('number'));
//
//        if (!$number) {
//            (new ButtonsConstruct($this->chat, '❌ Ошибка: Не удалось получить номер. Попробуйте еще раз. ❌', 'Назад', 'getNumbersBuyer'))
//                ->storeButton();
//            return;
//        }
//
//        $updateAt = Carbon::parse($number->updated_at);
//        $now = Carbon::now();
//
//        $diffMinutes = abs($now->diffInMinutes($updateAt, false)); // получаем положительное значение
//
//        if ($diffMinutes > 10) {
//            (new ButtonsConstruct($this->chat, "❌ Номер {$number->number} уже засчитан ❌", 'Назад', 'getNumbersBuyer'))
//                ->storeButton();
//        } else {
//            $salesman_chat = $number->salesman->uuid;
//            Telegraph::chat($salesman_chat)
//                ->message("❌ Номер {$number->number} ушел в дистут. ❌")
//                ->send();
//            $number->update(['status_number' => StatusNumberEnum::failed]);
//            (new ButtonsConstruct($this->chat, "🆘 Номер {$number->number} отправлен в диспут 🆘", 'Назад', 'getNumbersBuyer'))
//                ->storeButton();
//        }
//    }


    //логика кнопок продавца(сотрудника)
//    public function salesman()
//    {
//        $uuid = $this->chat->chat_id;
//        $buyer = $this->buyerService->getBuyer($uuid);
//
//        if (!$buyer) {
//            $this->reply($this->salesmanService->storeSalesman($uuid));
//            $salesman = $this->salesmanService->getSalesman($uuid);
//
//            if ($salesman->enabled != true) {
//                $this->chat
//                    ->message('🚫 <b>Ваш аккаунт не активирован. Обратитесь к администратору.</b> 🚫')
//                    ->send();
//            } else {
//                $this->chat
//                ->message('<b>Выберите действие.</b>')
//                ->keyboard(
//                    Keyboard::make()->buttons(
//                        [
//                            Button::make('Добавить номер')->action('addNumbers'),
//                            Button::make('Статусы номеров')->action('getNumbersStatus'),
//                            Button::make('Ваша статистика')->action('getUserStatistics'),
//                            Button::make('Назад')->action('start'),
//                        ])
//                )
//                ->send();
//            }
//        } else {
//            (new ButtonsConstruct($this->chat, "🚫 <b>Вы уже зарегистрированы как покупатель.</b> 🚫", 'Назад', 'start'))->storeButton();
//        }
//    }

    //выбор провайдера для добавления номера продавцом
//    public function addNumbers()
//    {
//        $this->chat
//            ->message('<b>Выберете провайдера</b>')
//            ->keyboard(
//                Keyboard::make()->buttons(
//                    [
//                        Button::make('🔵 Telegram 🔵')->action('addNumbersSalesmen')->param('provider', TypeNumberEnum::telegram->name),
//                        Button::make('🟢 WhatsApp 🟢')->action('addNumbersSalesmen')->param('provider', TypeNumberEnum::whatsapp->name),
//                        Button::make('Назад')->action('salesman'),
//                    ])
//            )
//            ->send();
//    }

    //логика добавления номеров продавцом
//    public function addNumbersSalesmen()
//    {
//        $provider = $this->data->get('provider');
//
//        //создание состояния для добавления номера
//        $this->numberStateService->storeAddNumberState($this->chat->chat_id, $provider);
//
//        switch ($provider) {
//            case TypeNumberEnum::telegram->name :
//                (new ButtonsConstruct($this->chat, "📝 Введите номер 🔵 Telegram 🔵 в соответствующем порядке:\n\n<b>9003233212</b>\n\nКаждый номер начинается с новой строки 📝", 'Отмена', 'salesman'))
//                    ->storeButton();
//                break;
//            case TypeNumberEnum::whatsapp->name :
//                (new ButtonsConstruct($this->chat, "📝 Введите номер 🟢 WhatsApp 🟢 в соответствующем порядке:\n\n<b>9003233212</b>\n\nКаждый номер начинается с новой строки 📝", 'Отмена', 'salesman'))
//                    ->storeButton();
//                break;
//        }
//    }

    //    public function getBuyerNumbers()
//    {
//        $salesmen = $this->salesmanService->getSalesman($this->chat->chat_id);
//        $numbers = $this->numberService->getActiveStatusNumbers($salesmen);
//
//        if ($numbers->isEmpty()) {
//            $this->reply('❌ Нет купленных номеров ❌');
//            return;
//        }
//
//        $buttons = [];
//
//        foreach ($numbers as $number) {
//            $buttons[] = Button::make($number->number)->action('disputeNumber')->param('number', $number->number);
//        }
//
//        $count_numbers = count($numbers);
//        // Отправляем одно сообщение с кнопками номеров
//        $this->chat
//            ->message("<b>Количество купленных номеров: {$count_numbers}</b>")
//            ->keyboard(Keyboard::make()->buttons($buttons)->chunk(2)) // Разделим кнопки на строки по 2 в строке
//            ->send();
//
//
//    }

    //    public function deleteNumbers()
//    {
//        $this->numberService->deleteNumber($this->data->get('number'));
//
//        $this->chat->deleteMessage($this->messageId)->send();
//
//        $this->reply('❌ Номер успешно удален! ❌');
//    }

    //    public function getNumbersStatus()
//    {
//        $this->chat
//            ->message('<b>Выберете статус номеров</b>')
//            ->keyboard(
//                Keyboard::make()->buttons(
//                    [
//                        Button::make('Активные')->action('getPendingNumbers'),
//                        Button::make('Деактивированы')->action('getDeactivateNumbers'),
//                        Button::make('Купленные')->action('getBuyerNumbers'),
//                        Button::make('Назад')->action('salesman'),
//                    ])
//            )
//            ->send();
//
//    }

    //    public function getPendingNumbers()
//    {
//        $salesmen = $this->salesmanService->getSalesman($this->chat->chat_id);
//
//        $numbers = $this->numberService->getPendingNumbers($salesmen);
//
//        if ($numbers->isEmpty()) {
//            $this->reply('❌ Нет активных номеров ❌');
//            return;
//        }
//
//        $buttons = [];
//
//        foreach ($numbers as $number) {
//            $buttons[] = Button::make($number->number)->action('deleteNumbers')->param('number', $number->number);
//        }
//        $count_numbers = count($numbers);
//
//        // Отправляем одно сообщение с кнопками номеров
//        $this->chat
//            ->message("<b>Количество номеров в очереди: {$count_numbers}</b>")
//            ->keyboard(Keyboard::make()->buttons($buttons)->chunk(2))// Разделим кнопки на строки по 2 в строки
//            ->send();
//
//        (new ButtonsConstruct($this->chat, "🆘 <b>Нажав на кнопку с номером он будет удален!</b> 🆘", "Назад", "getNumbersStatus"))->storeButton();
//    }

    //    public function getDeactivateNumbers()
//    {
//        $salesmen = $this->salesmanService->getSalesman($this->chat->chat_id);
//
//        $numbers = $this->numberService->getDeactiveStatusNumbers($salesmen);
//
//        if ($numbers->isEmpty()) {
//            $this->reply('❌ Нет неактивных номеров ❌');
//            return;
//        }
//
//        $buttons = [];
//
//        foreach ($numbers as $number) {
//            $buttons[] = Button::make($number->number)->action('blockNumber');
//        }
//        $count_numbers = count($numbers);
//
//        // Отправляем одно сообщение с кнопками номеров
//        $this->chat
//            ->message("<b>Количество неактивных номеров: {$count_numbers}</b>")
//            ->keyboard(Keyboard::make()->buttons($buttons)->chunk(2)) // Разделим кнопки на строки по 2 в строке
//            ->send();
//
//    }

    //логика обработки входящих кодов для покупателя
//    private function processCodeInput()
//    {
//        $code = $this->message?->text();
//        $number = explode(":", $code);// Ожидаем формат "номер : код"
//
//        if (empty($code) && count($number) != 2) {
//            $this->chat->message('Ошибка: неверный формат. Введите номер и код в формате "номер : код".')->send();
//            return;
//        }
//
//        $number_input = trim($number[0]);
//        $code_input = trim($number[1]);
//
//        //по номеру получаем нужное состояние и покупателя
//        $necessaryState = $this->numberStateService->getCodeNumberBuyerId($number_input);
//        $buyer_id = $necessaryState->buyer_id;
//
//        Telegraph::chat($buyer_id)
//            ->message("Код для номера {$necessaryState->number}: {$code_input}")
//            ->keyboard(
//                Keyboard::make()->buttons([
//                    Button::make('Код успешен')->action('codeReceived')->param('number', $necessaryState->number),
//                    Button::make('Код неуспешен')->action('codeFalseReceived')->param('number', $necessaryState->number),
//                ])
//            )
//            ->send();
//
//    }
    //    public function storeBuyer()
//    {
//        $uuid = $this->chat->chat_id;
//
//        $salesman = $this->salesmanService->getSalesman($uuid);
//
//        if (!$salesman) {
//
//            $this->reply($this->buyerService->storeBuyer($uuid));
//            $buyer = $this->buyerService->getBuyer($uuid);
//
//            if ($buyer->enabled != true) {
//                $this->chat
//                    ->message('🚫 <b>Ваш аккаунт не активирован. Обратитесь к администратору.</b> 🚫')
//                    ->send();
//            } else {
//                $this->chat
//                ->message('<b>Выберите действие.</b>')
//                ->keyboard(
//                    Keyboard::make()->buttons(
//                        [
//                            Button::make('Купить номер')->action('buyNumbers'),
//                            Button::make('Ваши номера')->action('getNumbersBuyer'),
//                            Button::make('Ваша статистика')->action('getUserStatistics')->param('buyer', true),
//                            Button::make('Назад')->action('start'),
//                        ])
//                    )
//                        ->send();
//            }
//        } else {
//            (new ButtonsConstruct($this->chat, "🚫 <b>Вы уже зарегистрированы как продавец.</b> 🚫", "Назад", "start"))->storeButton();
//        }
//    }

    //    public function buyNumbers()
//    {
//        $this->chat
//            ->message('<b>Выберете провайдера</b>')
//            ->keyboard(
//                Keyboard::make()->buttons(
//                    [
//                        Button::make('🔵 Telegram 🔵')->action('getNumbersSalesmen')->param('provider', TypeNumberEnum::telegram->name),
//                        Button::make('🟢 WhatsApp 🟢')->action('getNumbersSalesmen')->param('provider', TypeNumberEnum::whatsapp->name),
//                        Button::make('Назад')->action('storeBuyer'),
//                    ])
//            )
//            ->send();
//    }

    //получение доступных номеров для покупателя
//    public function getNumbersSalesmen()
//    {
//        $provider = $this->data->get('provider');
//
//        $numbers = $this->buyerService->getNumbersSalesman($provider);
//
//        if (count($numbers) < 1) {
//            (new ButtonsConstruct($this->chat, "❌ <b>Нет доступных номеров</b> ❌", 'Назад', 'buyNumbers'))
//                ->storeButton();
//            return;
//        }
//
//        $buttons = [];
//
//        foreach ($numbers as $number) {
//            $buttons[] = Button::make($number->number)->action('byuNumber')->param('number', $number->number);
//        }
//
//        // Отправляем одно сообщение с кнопками номеров
//        $this->chat
//            ->message('<b>Доступные номера:</b>')
//            ->keyboard(Keyboard::make()->buttons($buttons)->chunk(2)) // Разделим кнопки на строки по 2 в строке
//            ->send();
//
//        (new ButtonsConstruct($this->chat, "💰 <b>Нажав на номер вы перейдете на его покупку</b> 💰", 'Назад', 'buyNumbers'))
//            ->storeButton();
//    }

    //логика реализации покупки номера и создания состояния для получения кода
//    public function byuNumber()
//    {
//        $number = $this->numberService->getNumber($this->data->get('number'));
//
//        //добавляем покупателя на номер и заводим счетчик попыток отправки кода(доделать включение номера в процесс работы)
//        $this->numberStateService->pendingCode($this->chat->chat_id, $number->type_number->name, $number->number);
//        $this->numberService->updateStatusNumber($number->type_number->name, $number->number);
//
//        $this->chat
//            ->message('<b>Выберите действие:</b>')
//            ->keyboard(
//                Keyboard::make()->buttons(
//                    [
//                        Button::make('Получить код')->action('getCode')->param('number', $number->number),
//                        Button::make('Назад')->action('buyNumbers'),
//                    ])
//            )
//            ->send();
//    }

    //логика получения кода и определение его статуса(кода)
//    public function getCode()
//    {
//        $this->chat
//            ->message('⏳ <b>Ожидайте код. Время ожидание 2 минуты. У продавца на отправку кода две попытки</b> ⏳')
//            ->send();
//
//        //получаем состояние по номеру, покупателю чтобы отправить сообщение
//        $number_state = $this->numberStateService->getPendingCodeNumber($this->data->get('number'), $this->chat->chat_id);
//
//        if (!$number_state) {
//            (new ButtonsConstruct($this->chat, "❌ <b>Ошибка в покупке номера. Выберете другой номер.</b> ❌", "Назад", "buyNumbers"))->storeButton();
//        } else {
//            $this->processNumberState($number_state);
//        }
//    }

    //    private function processNumberState($number_state): void
//    {
//        //добавить логику job 2 минуты и отправка номера в деактив
//        dispatch(new DeactivateNumberJob($number_state->number, $number_state->buyer_id))->delay(now()->addMinutes(2));
//
//        Telegraph::chat($number_state->seller_id)
//            ->message("⌛️ Покупатель ожидает код для номера: {$number_state->number}. Пожалуйста, введите код в течении 2 минут. ⌛️")
//            ->keyboard(
//                Keyboard::make()->buttons(
//                    [
//                        Button::make('Код не пришел')->action('noCodeForSalesman')->param('number', $number_state->number),
//                    ])
//            )
//            ->send();
//    }
//
//    public function noCodeForSalesman()
//    {
//        $number_state = $this->numberStateService->getPendingCodeNumberWihtSeller($this->data->get('number'), $this->chat->chat_id);
//        if (!$number_state) {
//            $this->reply("❌ Номер деактивирован: {$this->data->get('number')}. ❌");
//        } else {
//            $number_state->increment('request_count');
//            Telegraph::chat($number_state->seller_id)
//                ->message("Если код снова не пришел деактивируйте номер")
//                ->keyboard(
//                    Keyboard::make()->buttons(
//                        [
//                            Button::make('Деактивировать')->action('deactivateNumber')->param('number', $number_state->number),
//                        ])
//                )
//                ->send();
//        }
//    }
//
//    public function deactivateNumber()
//    {
//        $state = $this->numberStateService->getDeactiveNumberWihtSeller($this->data->get('number'), $this->chat->chat_id);
//
//        Telegraph::chat($state->buyer_id)
//            ->message("❌ <b>Номер {$state->number} деактивирован.</b> ❌")
//            ->keyboard(
//                Keyboard::make()->buttons(
//                    [
//                        Button::make('Выбрать другой номер')->action('buyNumbers'),
//                    ])
//            )
//            ->send();
//
//        $state->delete();
//        $number = $this->numberService->getNumber($this->data->get('number'));
//        $number->update(['status_number' => StatusNumberEnum::failed]);
//        (new ButtonsConstruct($this->chat, "❌ <b>Номер деактивирован: {$this->data->get('number')}.</b> ❌", "Назад", "salesman"))->storeButton();
//    }

    //    //положительный статус кода и реализация логики
//    public function codeReceived(): void
//    {
//        $number_data = $this->data->get('number');
//
//        $this->numberService->updateNumberWithBuyerUuid($number_data, $this->chat->chat_id, StatusNumberEnum::active);
//
//        $number = $this->numberService->getNumber($number_data);
//        $salesman_chat = $number->salesman->uuid;
//        Telegraph::chat($salesman_chat)
//            ->message("✅ Номер {$number->number} успешно куплен! ✅")
//            ->send();
//
//        $this->numberStateService->deleteCodeNumberState($this->chat->chat_id, $number_data);
//
//        $this->chat->deleteKeyboard($this->messageId)->send();
//
//        (new ButtonsConstruct($this->chat, "✅ <b>Номер добавлен!</b> ✅", "Назад", "buyNumbers"))->storeButton();
//    }
//отрицательный статус кода и реализация логики
//    public function codeFalseReceived(): void
//    {
//        $number_data = $this->data->get('number');
//
//        $this->numberService->updateNumberWithBuyerUuid($number_data, $this->chat->chat_id, StatusNumberEnum::failed);
//
//        $number = $this->numberService->getNumber($number_data);
//        $salesman_chat = $number->salesman->uuid;
//        Telegraph::chat($salesman_chat)
//            ->message("❌ Номер {$number->number} слетел! ❌")
//            ->send();
//
//        $this->numberStateService->deleteCodeNumberState($this->chat->chat_id, $number_data);
//
//        $this->chat->deleteKeyboard($this->messageId)->send();
//
//        (new ButtonsConstruct($this->chat, "❌ <b>Номер деактивирован!</b> ❌", "Назад", "buyNumbers"))->storeButton();
//    }

    //    public function getNumbersBuyer(): void
//    {
//        $numbers = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, StatusNumberEnum::active);
//        $buttons = [];
//
//        foreach ($numbers as $number) {
//            $buttons[] = Button::make($number->number)->action('disputeNumber')->param('number', $number->number);
//        }
//        $count_numbers = count($numbers);
//
//        // Отправляем одно сообщение с кнопками номеров
//        $this->chat
//            ->message("<b>Количество купленных номеров: {$count_numbers}. Если номер слетел в течении 10 минут, то нажмите на него </b>")
//            ->keyboard(Keyboard::make()->buttons($buttons)->chunk(2)) // Разделим кнопки на строки по 2 в строке
//            ->send();
//    }
}
