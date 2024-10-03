<?php

namespace App\Telegram;

use App\Enums\StatusNumberEnum;
use App\Enums\TypeNumberEnum;
use App\Enums\UserTypeEnum;
use App\Jobs\DeactivateNumberJob;
use App\Services\BuyerService;
use App\Services\NumberService;
use App\Services\NumberStateService;
use App\Services\NumberStatisticService;
use App\Services\SalesmanService;
use App\Services\UserStatisticsService;
use Carbon\Carbon;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Stringable;

class Handler extends WebhookHandler
{
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
        // Инициализация свойства $salesmanService
        $this->salesmanService = $salesmanService;
        $this->numberService = $numberService;
        $this->numberStateService = $numberStateService;
        $this->buyerService = $buyerService;
        $this->numberStatisticService = $numberStatisticService;
        $this->userStatisticsService = $userStatisticsService;
    }

    public function start()
    {
        $this->reply('Здравствуйте!');

        $this->chat
            ->message('<b>Давайте сначало зарегистрируемся.</b>')
            ->keyboard(
                Keyboard::make()->buttons(
                    [
                        Button::make('Покупатель')->action('storeBuyer'),
                        Button::make('Продавец')->action('salesman'),
                    ])
            )
            ->send();
    }

    public function storeBuyer()
    {
        $uuid = $this->chat->chat_id;

        $salesman = $this->salesmanService->getSalesman($uuid);

        if (!$salesman) {

            $this->reply($this->buyerService->storeBuyer($uuid));

            $this->chat
                ->message('<b>Выберите действие.</b>')
                ->keyboard(
                    Keyboard::make()->buttons(
                        [
                            Button::make('Купить номер')->action('buyNumbers'),
                            Button::make('Ваши номера')->action('getNumbersBuyer'),
                            Button::make('Ваша статистика')->action('getUserStatistics')->param('buyer', true),
                            Button::make('Назад')->action('start'),
                        ])
                )
                ->send();
        } else {
            (new ButtonsConstruct($this->chat, "<b>Вы уже зарегистрированы как продавец.</b>", "Назад", "start"))->storeButton();
        }
    }

    public function buyNumbers()
    {
        $this->chat
            ->message('<b>Выберете провайдера</b>')
            ->keyboard(
                Keyboard::make()->buttons(
                    [
                        Button::make('🔵 Telegram')->action('getNumbersSalesmen')->param('provider', TypeNumberEnum::telegram->name),
                        Button::make('🟢 WhatsApp')->action('getNumbersSalesmen')->param('provider', TypeNumberEnum::whatsapp->name),
                        Button::make('Назад')->action('storeBuyer'),
                    ])
            )
            ->send();
    }

    //получение доступных номеров для покупателя
    public function getNumbersSalesmen()
    {
        $provider = $this->data->get('provider');

        $numbers = $this->buyerService->getNumbersSalesman($provider);

        if (count($numbers) < 1) {
            (new ButtonsConstruct($this->chat, "<b>Нет доступных номеров</b>", 'Назад', 'buyNumbers'))
                ->storeButton();
            return;
        }

        $buttons = [];

        foreach ($numbers as $number) {
            $buttons[] = Button::make($number->number)->action('byuNumber')->param('number', $number->number);
        }

        // Отправляем одно сообщение с кнопками номеров
        $this->chat
            ->message('<b>Доступные номера:</b>')
            ->keyboard(Keyboard::make()->buttons($buttons)->chunk(2)) // Разделим кнопки на строки по 2 в строке
            ->send();

        (new ButtonsConstruct($this->chat, "<b>Нажав на номер вы перейдете на его покупку</b>", 'Назад', 'buyNumbers'))
            ->storeButton();
    }

    //логика реализации покупки номера и создания состояния для получения кода
    public function byuNumber()
    {
        $number = $this->numberService->getNumber($this->data->get('number'));

        //добавляем покупателя на номер и заводим счетчик попыток отправки кода(доделать включение номера в процесс работы)
        $this->numberStateService->pendingCode($this->chat->chat_id, $number->type_number->name, $number->number);
        $this->numberService->updateStatusNumber($number->type_number->name, $number->number);

        $this->chat
            ->message('<b>Выберите действие:</b>')
            ->keyboard(
                Keyboard::make()->buttons(
                    [
                        Button::make('Получить код')->action('getCode')->param('number', $number->number),
                        Button::make('Назад')->action('buyNumbers'),
                    ])
            )
            ->send();
    }

    //логика получения кода и определение его статуса(кода)
    public function getCode()
    {
        $this->chat
            ->message('<b>Ожидайте код. Время ожидание 2 минуты. У продавца на отправку кода две попытки</b>')
//            ->keyboard(
//                Keyboard::make()->buttons(
//                    [
//                        Button::make('Код не пришел')->action('repeatGetCode')->param('number', $this->data->get('number')),
//                    ])
//            )
            ->send();

        //получаем состояние по номеру, покупателю чтобы отправить сообщение
        $number_state = $this->numberStateService->getPendingCodeNumber($this->data->get('number'), $this->chat->chat_id);

        if (!$number_state) {
            (new ButtonsConstruct($this->chat, "<b>Ошибка в покупке номера. Выберете другой номер.</b>", "Назад", "buyNumbers"))->storeButton();
        } else {
            $this->processNumberState($number_state);
        }
    }

//    public function repeatGetCode()
//    {
//        $number_state = $this->numberStateService->getPendingCodeNumber($this->data->get('number'), $this->chat->chat_id);
//        if (!$number_state) {
//            $this->reply("Номер деактивирован: {$this->data->get('number')}.</b>");
//        } else {
//            $number_state->increment('request_count');
//            //здесь добавить 2 минутное ожидание
//            $this->processNumberState($number_state);
//        }
//
//    }

    private function processNumberState($number_state)
    {
        //добавить логику job 2 минуты и отправка номера в деактив
        dispatch(new DeactivateNumberJob($number_state->number, $number_state->buyer_id))->delay(now()->addMinutes(2));

        Telegraph::chat($number_state->seller_id)
            ->message("Покупатель ожидает код для номера: {$number_state->number}. Пожалуйста, введите код в течении 2 минут. Формат ввода <b>799999999:1234</b>")
            ->keyboard(
                Keyboard::make()->buttons(
                    [
                        Button::make('Код не пришел')->action('noCodeForSalesman')->param('number', $number_state->number),
                    ])
            )
            ->send();
    }

    public function noCodeForSalesman()
    {
        $number_state = $this->numberStateService->getPendingCodeNumberWihtSeller($this->data->get('number'), $this->chat->chat_id);
        if (!$number_state) {
            $this->reply("Номер деактивирован: {$this->data->get('number')}.");
        } else {
            $number_state->increment('request_count');
            Telegraph::chat($number_state->seller_id)
                ->message("Если код снова не пришел деактивируйте номер")
                ->keyboard(
                    Keyboard::make()->buttons(
                        [
                            Button::make('Деактивировать')->action('deactivateNumber')->param('number', $number_state->number),
                        ])
                )
                ->send();
        }
    }

    public function deactivateNumber()
    {
        $state = $this->numberStateService->getDeactiveNumberWihtSeller($this->data->get('number'), $this->chat->chat_id);

        Telegraph::chat($state->buyer_id)
            ->message("<b>Номер {$state->number} деактивирован.</b>")
            ->keyboard(
                Keyboard::make()->buttons(
                    [
                        Button::make('Выбрать другой номер')->action('buyNumbers'),
                    ])
            )
            ->send();

        $state->delete();
        $number = $this->numberService->getNumber($this->data->get('number'));
        $number->update(['status_number' => StatusNumberEnum::failed]);
        (new ButtonsConstruct($this->chat, "<b>Номер деактивирован: {$this->data->get('number')}.</b>", "Назад", "salesman"))->storeButton();
    }

    //логика кнопок продавца(сотрудника)
    public function salesman()
    {
        $uuid = $this->chat->chat_id;
        $buyer = $this->buyerService->getBuyer($uuid);

        if (!$buyer) {
            $this->reply($this->salesmanService->storeSalesman($uuid));

            $this->chat
                ->message('<b>Выберите действие.</b>')
                ->keyboard(
                    Keyboard::make()->buttons(
                        [
                            Button::make('Добавить номер')->action('addNumbers'),
                            Button::make('Статусы номеров')->action('getNumbersStatus'),
                            Button::make('Ваша статистика')->action('getUserStatistics'),
                            Button::make('Назад')->action('start'),
                        ])
                )
                ->send();
        } else {
            (new ButtonsConstruct($this->chat, "<b>Вы уже зарегистрированы как покупатель.</b>", 'Назад', 'start'))->storeButton();
        }

    }

    //выбор провайдера для добавления номера продавцом
    public function addNumbers()
    {
        $this->chat
            ->message('<b>Выберете провайдера</b>')
            ->keyboard(
                Keyboard::make()->buttons(
                    [
                        Button::make('🔵 Telegram')->action('addNumbersSalesmen')->param('provider', TypeNumberEnum::telegram->name),
                        Button::make('🟢 WhatsApp')->action('addNumbersSalesmen')->param('provider', TypeNumberEnum::whatsapp->name),
                        Button::make('Назад')->action('salesman'),
                    ])
            )
            ->send();
    }

    //логика добавления номеров продавцом
    public function addNumbersSalesmen()
    {
        $provider = $this->data->get('provider');

        //создание состояния для добавления номера
        $this->numberStateService->storeAddNumberState($this->chat->chat_id, $provider);

        switch ($provider) {
            case TypeNumberEnum::telegram->name :
                (new ButtonsConstruct($this->chat, "Введите номер Telegram в соответствующем порядке:\n\n<b>9003233212</b>\n\nКаждый номер начинается с новой строки", 'Отмена', 'salesman'))
                    ->storeButton();
                break;
            case TypeNumberEnum::whatsapp->name :
                (new ButtonsConstruct($this->chat, "Введите номер WhatsApp в соответствующем порядке:\n\n<b>9003233212</b>\n\nКаждый номер начинается с новой строки", 'Отмена', 'salesman'))
                    ->storeButton();
                break;
        }
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
                $this->processCodeInput();
            }
        } catch (\Exception $exception) {
            // Логируем исключение для отладки
            error_log($exception->getMessage());

            // Можно добавить сообщение об ошибке для пользователя
            (new ButtonsConstruct($this->chat, 'Я тебя не понимаю', 'Назад', 'start'))
                ->storeButton();
        }
    }

    //логика обработки входящих номеров от продавца(сотрудника)
    private function processNumberInput($number_state)
    {
        try {
            // Получаем введенные номера от продавца
            $numbers = $this->message?->text();

            if (empty($numbers)) {
                (new ButtonsConstruct($this->chat, 'Ошибка: Не удалось получить номер. Попробуйте еще раз.', 'Назад', 'salesman'))
                    ->storeButton();
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
            error_log($exception->getMessage());

            // Удаляем состояние
            $this->numberStateService->deleteAddNumberState($this->chat->chat_id);

            // Можно добавить сообщение об ошибке для пользователя
            (new ButtonsConstruct($this->chat, 'Произошла ошибка при обработке номера.', 'Назад', 'salesman'))
                ->storeButton();
        }
    }

    //логика сохранения номера
    public function saveNumber($number, $provider)
    {
        $count_numbers = $this->numberStatisticService->getCountNumbers($provider->name);
        $salesman = $this->salesmanService->getSalesman($this->chat->chat_id);
        $this->numberStateService->deleteAddNumberState($this->chat->chat_id);


        if ($salesman) {
            $this->reply("Перед вами очередь {$count_numbers} номеров.");

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
                    $this->reply("Ошибка: Номер '{$num}' должен содержать только цифры и состоять из 10 символов.");
                }
            }
        } else {
            $this->chat->message('Ошибка: Продавец не найден.')->send();
        }

        (new ButtonsConstruct($this->chat, "<b>Добавлено {$count} номеров!</b>", 'Назад', 'salesman'))
            ->storeButton();
    }

    //логика обработки входящих кодов для покупателя
    private function processCodeInput()
    {
        $code = $this->message?->text();
        $number = explode(":", $code);// Ожидаем формат "номер : код"

        if (empty($code) && count($number) != 2) {
            $this->chat->message('Ошибка: неверный формат. Введите номер и код в формате "номер : код".')->send();
            return;
        }

        $number_input = trim($number[0]);
        $code_input = trim($number[1]);

        //по номеру получаем нужное состояние и покупателя
        $necessaryState = $this->numberStateService->getCodeNumberBuyerId($number_input);
        $buyer_id = $necessaryState->buyer_id;

        Telegraph::chat($buyer_id)
            ->message("Код для номера {$necessaryState->number}: {$code_input}")
            ->keyboard(
                Keyboard::make()->buttons([
                    Button::make('Код успешен')->action('codeReceived')->param('number', $necessaryState->number),
                    Button::make('Код неуспешен')->action('codeFalseReceived')->param('number', $necessaryState->number),
                ])
            )
            ->send();

    }

    //положительный статус кода и реализация логики
    public function codeReceived()
    {
        $number_data = $this->data->get('number');

        $this->numberService->updateNumberWithBuyerUuid($number_data, $this->chat->chat_id, StatusNumberEnum::active);

        $this->numberStateService->deleteCodeNumberState($this->chat->chat_id, $number_data);

        (new ButtonsConstruct($this->chat, "<b>Номер добавлен!</b>", "Назад", "buyNumbers"))->storeButton();
    }

    //отрицательный статус кода и реализация логики
    public function codeFalseReceived()
    {
        $number_data = $this->data->get('number');

        $this->numberService->updateNumberWithBuyerUuid($number_data, $this->chat->chat_id, StatusNumberEnum::failed);

        $this->numberStateService->deleteCodeNumberState($this->chat->chat_id, $number_data);

        (new ButtonsConstruct($this->chat, "<b>Номер деактивирован!</b>", "Назад", "buyNumbers"))->storeButton();
    }

    public function getNumbersStatus()
    {
        $this->chat
            ->message('<b>Выберете статус номеров</b>')
            ->keyboard(
                Keyboard::make()->buttons(
                    [
                        Button::make('Активные')->action('getPendingNumbers'),
                        Button::make('Деактивированы')->action('getDeactivateNumbers'),
                        Button::make('Купленные')->action('getBuyerNumbers'),
                        Button::make('Назад')->action('salesman'),
                    ])
            )
            ->send();

    }

    public function getPendingNumbers()
    {
        $salesmen = $this->salesmanService->getSalesman($this->chat->chat_id);

        $numbers = $this->numberService->getPendingNumbers($salesmen);

        if ($numbers->isEmpty()) {
            $this->reply('Нет активных номеров');
            return;
        }

        $buttons = [];

        foreach ($numbers as $number) {
            $buttons[] = Button::make($number->number)->action('deleteNumbers')->param('number', $number->number);
        }
        $count_numbers = count($numbers);

        // Отправляем одно сообщение с кнопками номеров
        $this->chat
            ->message("<b>Количество номеров в очереди: {$count_numbers}</b>")
            ->keyboard(Keyboard::make()->buttons($buttons)->chunk(2))// Разделим кнопки на строки по 2 в строки
            ->send();

        (new ButtonsConstruct($this->chat, "<b>Нажав на кнопку с номером он будет удален!</b>", "Назад", "getNumbersStatus"))->storeButton();
    }

    public function getDeactivateNumbers()
    {
        $salesmen = $this->salesmanService->getSalesman($this->chat->chat_id);

        $numbers = $this->numberService->getDeactiveStatusNumbers($salesmen);

        if ($numbers->isEmpty()) {
            $this->reply('Нет неактивных номеров');
            return;
        }

        $buttons = [];

        foreach ($numbers as $number) {
            $buttons[] = Button::make($number->number)->action('blockNumber');
        }
        $count_numbers = count($numbers);

        // Отправляем одно сообщение с кнопками номеров
        $this->chat
            ->message("<b>Количество неактивных номеров: {$count_numbers}</b>")
            ->keyboard(Keyboard::make()->buttons($buttons)->chunk(2)) // Разделим кнопки на строки по 2 в строке
            ->send();

    }

    public function getNumbersBuyer()
    {
        $numbers = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, StatusNumberEnum::active);
        $buttons = [];

        foreach ($numbers as $number) {
            $buttons[] = Button::make($number->number)->action('disputeNumber')->param('number', $number->number);
        }
        $count_numbers = count($numbers);

        // Отправляем одно сообщение с кнопками номеров
        $this->chat
            ->message("<b>Количество купленных номеров: {$count_numbers}. Если номер слетел в течении 10 минут, то нажмите на него </b>")
            ->keyboard(Keyboard::make()->buttons($buttons)->chunk(2)) // Разделим кнопки на строки по 2 в строке
            ->send();
    }

    public function getBuyerNumbers()
    {
        $numbers = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, StatusNumberEnum::active);

        if ($numbers->isEmpty()) {
            $this->reply('Нет купленных номеров');
            return;
        }

        $buttons = [];

        foreach ($numbers as $number) {
            $buttons[] = Button::make($number->number)->action('disputeNumber')->param('number', $number->number);
        }

        $count_numbers = count($numbers);
        // Отправляем одно сообщение с кнопками номеров
        $this->chat
            ->message("<b>Количество купленных номеров: {$count_numbers}</b>")
            ->keyboard(Keyboard::make()->buttons($buttons)->chunk(2)) // Разделим кнопки на строки по 2 в строке
            ->send();


    }

    public function deleteNumbers()
    {
        $this->numberService->deleteNumber($this->data->get('number'));

        $this->chat->deleteMessage($this->messageId)->send();

        $this->reply('Номер успешно удален!');
    }

    public function getUserStatistics()
    {
        $buyer = $this->data->get('buyer');

        if (!$buyer) {
            $salesman = $this->salesmanService->getSalesman($this->chat->chat_id);
            $telegram = $this->numberService->getTelegramNumbers($salesman);
            $whatsapp = $this->numberService->getWhatsAppNumbers($salesman);

            if (count($telegram) > 0) {
                $active = $this->numberService->getTelegramNumbers($salesman, StatusNumberEnum::active);
                $deactivate = $this->numberService->getTelegramNumbers($salesman, StatusNumberEnum::failed);
                $pending = $this->numberService->getTelegramNumbers($salesman, StatusNumberEnum::pending);
                $this->userStatisticsService->createStatistics($salesman->uuid, UserTypeEnum::seller->name, TypeNumberEnum::telegram->name, count($active), count($deactivate), count($pending));
                $message = "<b>🔵 Telegram</b>" .
                    "\n\nНомера в ожидании: " . count($pending) . "\n\n" .
                    "Купленные номера: " . count($active) . "\n\n" .
                    "Слетевшие номера: " . count($deactivate);
                $this->chat->message($message)->send();
            }
            if (count($whatsapp) > 0) {
                $active = $this->numberService->getWhatsAppNumbers($salesman, StatusNumberEnum::active);
                $deactivate = $this->numberService->getWhatsAppNumbers($salesman, StatusNumberEnum::failed);
                $pending = $this->numberService->getWhatsAppNumbers($salesman, StatusNumberEnum::pending);
                $this->userStatisticsService->createStatistics($salesman->uuid, UserTypeEnum::seller->name, TypeNumberEnum::whatsapp->name, count($active), count($deactivate), count($pending));
                $message = "<b>🟢 WhatsApp</b>" .
                    "\n\nНомера в ожидании: " . count($pending) . "\n\n" .
                    "Купленные номера: " . count($active) . "\n\n" .
                    "Слетевшие номера: " . count($deactivate);
                $this->chat->message($message)->send();
            }
            if (count($telegram) === 0 && count($whatsapp) === 0) {
                (new ButtonsConstruct($this->chat, "<b>Нет статистики.</b>", 'Назад', 'salesman'))->storeButton();
            }
        } else {
            $telegram = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, null, TypeNumberEnum::telegram);
            $whatsapp = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, null, TypeNumberEnum::whatsapp);

            if (count($telegram) > 0) {
                $active = count($telegram->where('status_number', StatusNumberEnum::active));
                $deactivate = count($telegram->where('status_number', StatusNumberEnum::failed));
                $this->userStatisticsService->createStatistics($this->chat->chat_id, UserTypeEnum::buyer->name, TypeNumberEnum::telegram->name, $active, $deactivate, null);
                $message = "<b>🔵 Telegram</b>" . "\n\n" .
                    "Купленные номера: " . $active . "\n\n" .
                    "Слетевшие номера: " . $deactivate;
                $this->chat->message($message)->send();
            }
            if (count($whatsapp) > 0) {
                $active = count($whatsapp->where('status_number', StatusNumberEnum::active));
                $deactivate = count($whatsapp->where('status_number', StatusNumberEnum::failed));
                $this->userStatisticsService->createStatistics($this->chat->chat_id, UserTypeEnum::buyer->name, TypeNumberEnum::whatsapp->name, $active, $deactivate, null);
                $message = "<b>🟢 WhatsApp</b>" . "\n\n" .
                    "Купленные номера: " . $active . "\n\n" .
                    "Слетевшие номера: " . $deactivate;
                $this->chat->message($message)->send();
            }
            if (count($telegram) === 0 && count($whatsapp) === 0) {
                (new ButtonsConstruct($this->chat, "<b>Нет статистики.</b>", 'Назад', 'storeBuyer'))->storeButton();
            }
        }
    }

    public function blockNumber()
    {
        //
    }

    public function disputeNumber()
    {
        $number = $this->numberService->getWithBuyerNumbers($this->chat->chat_id, StatusNumberEnum::active, null, $this->data->get('number'));

        if (!$number)
            (new ButtonsConstruct($this->chat, 'Ошибка: Не удалось получить номер. Попробуйте еще раз.', 'Назад', 'getNumbersBuyer'))
                ->storeButton();

        $updateAt = Carbon::parse($number->updated_at);
        $now = Carbon::now();

        if ($now->diffInMinutes($updateAt) > 10) {
            (new ButtonsConstruct($this->chat, "Номер {$number->number} уже засчитан", 'Назад', 'getNumbersBuyer'))
                ->storeButton();
        } else {
            $salesman_chat = $number->salesman->uuid;
            Telegraph::chat($salesman_chat)
                ->message("Номер {$number->number} ушел в дистут.")
                ->send();
            $number->update(['status_number' => StatusNumberEnum::failed]);
            (new ButtonsConstruct($this->chat, "Номер {$number->number} отправлен в диспут", 'Назад', 'getNumbersBuyer'))
                ->storeButton();
        }

    }

}
