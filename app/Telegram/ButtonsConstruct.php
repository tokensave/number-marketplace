<?php

namespace App\Telegram;

use DefStudio\Telegraph\Client\TelegraphResponse;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

class ButtonsConstruct
{
    private string $label;
    private string $action;
    private $chat;  // тип лучше указать точно, например TelegraphChat
    private string $message;

    public function __construct($chat, string $message, string $label, string $action)
    {
        $this->chat = $chat;  // Объект чата передаётся через конструктор
        $this->message = $message;
        $this->label = $label;
        $this->action = $action;
    }

    public function storeButton(): TelegraphResponse
    {
        return $this->chat
            ->message($this->message)
            ->keyboard(
                Keyboard::make()->buttons(
                    [
                        Button::make($this->label)->action($this->action),
                    ]
                )
            )
            ->send();
    }
}

