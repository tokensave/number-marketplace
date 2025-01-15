<?php

declare(strict_types=1);

namespace App\MoonShine\Resources;

use Illuminate\Database\Eloquent\Model;
use App\Models\TextForTg;

use MoonShine\Exceptions\FieldException;
use MoonShine\Fields\Text;
use MoonShine\Fields\Textarea;
use MoonShine\Resources\ModelResource;
use MoonShine\Decorations\Block;
use MoonShine\Fields\ID;
use MoonShine\Fields\Field;
use MoonShine\Components\MoonShineComponent;

/**
 * @extends ModelResource<TextForTg>
 */
class TextForTgResource extends ModelResource
{
    protected string $model = TextForTg::class;

    protected string $title = 'Настройки кнопок Бота';

    /**
     * @return list<MoonShineComponent|Field>
     * @throws FieldException
     */
    public function fields(): array
    {
        return [
            Block::make([
                Text::make('Кнопка', 'slug')
                    ->readonly()
                    ->hideOnIndex(),
                Text::make('Название', 'name')->required(),
                Textarea::make('Значение', 'value')
                    ->hint('Для сообщений можно использовать html теги')
                    ->customAttributes(['rows' => 10])
                    ->required(),

            ]),
        ];
    }

    /**
     * @param TextForTg $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    public function rules(Model $item): array
    {
        return [
            'slug' => ['required', 'string'],
            'name' => ['required', 'string'],
        ];
    }
}
