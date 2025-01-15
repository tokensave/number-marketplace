<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Resources\BuyerResource;
use App\MoonShine\Resources\NumberResource;
use App\MoonShine\Resources\SalesmanResource;
use App\MoonShine\Resources\StatisticsResource;
use App\MoonShine\Resources\TextForTgResource;
use MoonShine\Providers\MoonShineApplicationServiceProvider;
use MoonShine\MoonShine;
use MoonShine\Menu\MenuGroup;
use MoonShine\Menu\MenuItem;
use MoonShine\Resources\MoonShineUserResource;
use MoonShine\Resources\MoonShineUserRoleResource;
use MoonShine\Contracts\Resources\ResourceContract;
use MoonShine\Menu\MenuElement;
use MoonShine\Pages\Page;
use Closure;

class MoonShineServiceProvider extends MoonShineApplicationServiceProvider
{
    /**
     * @return list<ResourceContract>
     */
    protected function resources(): array
    {
        return [];
    }

    protected function pages(): array
    {
        return [];
    }

    protected function menu(): array
    {
        return [
            MenuItem::make('Покупатели', new BuyerResource())
                ->icon('heroicons.user-group'),
            MenuItem::make('Продавцы', new SalesmanResource())
                ->icon('heroicons.users'),
//            MenuItem::make('Статистика', new StatisticsResource()),
            MenuItem::make('Кнопки для Бота', new TextForTgResource())
                ->icon('heroicons.pencil'),
        ];
    }

    /**
     * @return array
     */
    protected function theme(): array
    {
        return [];
    }
}
