<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\Salesman;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;
use MoonShine\Fields\Switcher;
use MoonShine\Fields\Date;
use MoonShine\Fields\Text;

/**
 * @extends ModelResource<Salesman>
 */
class SalesmanResource extends ModelResource
{
    protected string $model = Salesman::class;

    protected string $title = 'Продавцы';

    protected array $with = ['numbers'];

    public function search(): array
    {
        return ['name'];
    }

    public function getActiveActions(): array
    {
        return ['update', 'delete', 'massDelete'];
    }

    protected bool $editInModal = true;

    /**
     * @return list<MoonShineComponent|Field>
     */
    public function fields(): array
    {
        return [
            Block::make([
                ID::make(''),
                Text::make('Имя', 'name'),
                Switcher::make('Активен', 'enabled'),
                Date::make('Создан', 'created_at'),
                Date::make('Обновлен', 'updated_at'),
            ]),
        ];
    }

    /**
     * @param Salesman $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }

    public function redirectAfterSave(): string
    {
        return '/admin/resource/salesman-resource/index-page';
    }

    public function redirectAfterDelete(): string
    {
        return '/admin/resource/salesman-resource/index-page';
    }
}
