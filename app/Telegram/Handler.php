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
                } else {
                    $text_unknown = $this->getTextForMsg('text_unknown_command');
                    $btn_return = $this->getTextForMsg('btn_return');
                    $buttons = Keyboard::make()->buttons([
                        Button::make($btn_return)->action('start'),
                    ]);
                    $this->sendMsg($text_unknown, $buttons, 0);
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
            $this->sendMsg($text_unknown, $buttons, 0);
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
            $count = 0;
            //Отправляем текст с очередью
            $text_count = $this->getTextForMsg('text_count_all');
            $text_count = str_replace('#count_numbers#', $count_numbers, $text_count);
            $this->reply($text_count);
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

                    $text_add = $this->getTextForMsg('text_add_number_handler');
                    $text_add = str_replace('#count#', $count, $text_add);
                    $this->sendMsg($text_add, null, 0);
                } else {
                    // Если номер не прошел валидацию
                    $text_validate = $this->getTextForMsg('text_numbers_validate');
                    $text_validate = str_replace('#number#', $num, $text_validate);

                    $this->sendMsg($text_validate, null, 0);
                }
            }
        } else {
            $text_sal_bad = $this->getTextForMsg('text_salesman_validate');
            $this->sendMsg($text_sal_bad);
        }
        $text_change = $this->getTextForMsg('text_action');
        $btn_return = $this->getTextForMsg('btn_return');
        $buttons = Keyboard::make()->buttons([
            Button::make($btn_return)->action('salesman'),
        ]);
        $this->sendMsg($text_change, $buttons, 0);
    }
}
