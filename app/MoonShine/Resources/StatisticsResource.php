<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use App\Enums\TypeNumberEnum;
use App\Enums\UserTypeEnum;
use Illuminate\Database\Eloquent\Model;
use App\Models\Statistics;

use MoonShine\Components\Card;
use MoonShine\Components\Dropdown;
use MoonShine\Fields\Text;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;

/**
 * @extends ModelResource<Statistics>
 */
class StatisticsResource extends ModelResource
{
    protected string $model = Statistics::class;

    protected string $title = 'Статистика';

    public function search(): array
    {
        return ['name'];
    }


    public function getActiveActions(): array
    {
        return ['view', 'delete'];
    }

    public function indexFields(): array
    {
        return [
            Text::make('Тип пользователя', 'type')->badge(
                fn($item, Field $field) => match ($item) {
                    UserTypeEnum::buyer->name() => 'green',
                    UserTypeEnum::seller->name() => 'red',
                    default => 'gray',
                }),
            Text::make('Имя', 'name'),
            Text::make('Мессенджер', 'provider_number'
            )->badge(
                fn($item, Field $field) => match ($item) {
                    TypeNumberEnum::whatsapp->value => 'green',
                    TypeNumberEnum::telegram->value => 'blue',
                    default => 'gray',
                })
        ];
    }

    public function detailFields(): array
    {
        return [
            Text::make('Мессенджер', 'provider_number'),
            Text::make('Купленные', 'count_active',),
            Text::make('Слетевшие', 'count_deactivate',),
            Text::make('В ожидании', 'count_pending',),
        ];
    }

    /**
     * @param Statistics $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [];
    }
}
