<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\Statistics;

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
                ID::make()->sortable(),
                Text::make(
                    'Тип пользователя',
                    'type',
                    fn($item) => $item->type->name()
                ),
                Text::make(
                    'Uuid',
                    'uuid',
                    fn($item) => $item->uuid
                )
                ,
                Text::make(
                    'Имя',
                    'name',
                    fn($item) => $item->name
                )
                ,
                Text::make(
                    'Мессенджер',
                    'provider_number',
                    fn($item) => $item->provider_number->value
                ),
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
