<?php

namespace App\Telegram\Traits;

//use App\Models\LogTelegram;
//use App\Models\TextForTg;
use App\Enums\LogTelegramTypeEnum;
use App\Models\LogTelegram;
use App\Models\TextForTg;
use DefStudio\Telegraph\Client\TelegraphResponse;
use DefStudio\Telegraph\Keyboard\Keyboard;

trait Support
{

    protected function clearStorage(): void
    {

        $this->chat->storage()->forget('appeal');
        $this->chat->storage()->forget('appeal_file');
    }

    public function cancelAction(): void
    {
        $this->clearStorage();
//        $this->profileData(0);
    }

    protected function sendMsg(string $msg, Keyboard $keyboard = null, int $edit = 1): void
    {
        if ($keyboard) {
            if ($edit) {
                $response = $this->chat->edit($this->messageId)->html($msg)->keyboard($keyboard)->withoutPreview()->send();
            } else {
                $response = $this->chat->html($msg)->keyboard($keyboard)->withoutPreview()->send();
            }
        } else {
            if ($edit) {
                $response = $this->chat->edit($this->messageId)->html($msg)->withoutPreview()->send();
            } else {
                $response = $this->chat->html($msg)->withoutPreview()->send();
            }
        }

        $this->loggerResponse($response);
    }

    protected function loggerResponse(TelegraphResponse $response): void
    {
        if ($response->telegraphError()) {
            LogTelegram::create([
                'chat_id' => $this->chat->chat_id,
                'data' => $response->body(),
                'type' => LogTelegramTypeEnum::unsuccessful
            ]);
        } else {
            LogTelegram::create([
                'chat_id' => $this->chat->chat_id,
                'data' => $response->body(),
                'type' => LogTelegramTypeEnum::successful]);
        }
    }

    protected function recordUserActivity(): void
    {
        if ($this->chat->exists) {
            $this->chat->touch();
        }

    }

    public function switchToRU($value): string
    {
        $converter = array(
            'f' => 'а', ',' => 'б', 'd' => 'в', 'u' => 'г', 'l' => 'д', 't' => 'е', '`' => 'ё',
            ';' => 'ж', 'p' => 'з', 'b' => 'и', 'q' => 'й', 'r' => 'к', 'k' => 'л', 'v' => 'м',
            'y' => 'н', 'j' => 'о', 'g' => 'п', 'h' => 'р', 'c' => 'с', 'n' => 'т', 'e' => 'у',
            'a' => 'ф', '[' => 'х', 'w' => 'ц', 'x' => 'ч', 'i' => 'ш', 'o' => 'щ', 'm' => 'ь',
            's' => 'ы', ']' => 'ъ', "'" => "э", '.' => 'ю', 'z' => 'я',

            'F' => 'А', '<' => 'Б', 'D' => 'В', 'U' => 'Г', 'L' => 'Д', 'T' => 'Е', '~' => 'Ё',
            ':' => 'Ж', 'P' => 'З', 'B' => 'И', 'Q' => 'Й', 'R' => 'К', 'K' => 'Л', 'V' => 'М',
            'Y' => 'Н', 'J' => 'О', 'G' => 'П', 'H' => 'Р', 'C' => 'С', 'N' => 'Т', 'E' => 'У',
            'A' => 'Ф', '{' => 'Х', 'W' => 'Ц', 'X' => 'Ч', 'I' => 'Ш', 'O' => 'Щ', 'M' => 'Ь',
            'S' => 'Ы', '}' => 'Ъ', '"' => 'Э', '>' => 'Ю', 'Z' => 'Я',

            '@' => '"', '#' => '№', '$' => ';', '^' => ':', '&' => '?', '/' => '.', '?' => ',',
        );

        return strtr($value, $converter);
    }

    public function notifyError(string $msg): void
    {
        $this->sendMsg($msg, edit: 0);
        $this->clearStorage();
//        $this->profileData();
    }

    protected function getTextForMsg(string $slug):string
    {
        return TextForTg::whereSlug($slug)->first()->value ?? 'Ой текст не найден';
    }

}
