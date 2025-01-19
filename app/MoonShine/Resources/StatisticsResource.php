<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

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
        return ['delete'];
    }

    /**
     * @return list<MoonShineComponent|Field>
     */
    public function fields(): array
    {
        return [
            Block::make([
                Text::make(
                    'Тип пользователя',
                    'type',
                    fn($item) => $item->type->name()
                )->sortable(),
                Text::make(
                    'Имя',
                    'name',
                    fn($item) => $item->name
                )->sortable(),
                Text::make(
                    'Мессенджер',
                    'provider_number',
                    fn($item) => $item->provider_number->value
                )->sortable(),
                Text::make(
                    'Купленные',
                    'count_active',
                ),
                Text::make(
                    'Слетевшие',
                    'count_deactivate',
                ),
                Text::make(
                    'В ожидании',
                    'count_pending',
                ),
            ]),
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
