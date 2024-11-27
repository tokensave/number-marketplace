<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use DefStudio\Telegraph\Models\TelegraphBot;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('register-commands', function () {
    $bot = TelegraphBot::find(1);
    $bot->registerCommands([
        'start' => 'Главное меню',
    ])->send();
});
