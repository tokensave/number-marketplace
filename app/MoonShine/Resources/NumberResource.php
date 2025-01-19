<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\Number;

use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;
use MoonShine\Fields\Text;
/**
 * @extends ModelResource<Number>
 */
class NumberResource extends ModelResource
{
    protected string $model = Number::class;

    protected string $title = 'Номера';

    protected array $with = ['salesman'];

    public function search(): array
    {
        return ['salesman.name'];
    }

    public function getActiveActions(): array
    {
        return ['view', 'delete', 'massDelete'];
    }

    /**
     * @return list<MoonShineComponent|Field>
     */
    public function fields(): array
    {
        return [
            Block::make([
                ID::make()->sortable(),
                Text::make('Номер телефона', 'number'),
                Text::make('Мессенджер', 'type_number', fn($item) => $item->type_number->value),
                Text::make('Статус', 'status_number', fn($item) => $item->status_number->name()),
                Text::make('Имя продавца', 'salesman.name'),

            ]),
        ];
    }

    /**
     * @param Number $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [];
    }

    public function redirectAfterSave(): string
    {
        return '/admin/resource/number-resource/index-page';
    }

    public function redirectAfterDelete(): string
    {
        return '/admin/resource/number-resource/index-page';
    }
}
