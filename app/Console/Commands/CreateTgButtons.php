<?php

namespace App\Console\Commands;

use App\Models\TextForTg;
use Illuminate\Console\Command;

class CreateTgButtons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tg:buttons:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создать кнопки для Telegram из конфигурационного файла';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $buttons = config('tg_buttons');

        if (empty($buttons)) {
            $this->error('Конфигурационный файл tg_buttons пуст или не существует.');
            return 1;
        }

        foreach ($buttons as $button) {
            TextForTg::updateOrCreate(
                ['slug' => $button['slug']],
                [
                    'name' => $button['name'],
                    'value' => $button['value'],
                ]
            );
            $this->info("Кнопка '{$button['name']}' создана или обновлена.");
        }

        $this->info('Все кнопки успешно созданы/обновлены.');
        return 0;
    }
}
