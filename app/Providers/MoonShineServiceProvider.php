<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Resources\BuyerResource;
use App\MoonShine\Resources\NumberResource;
use App\MoonShine\Resources\NumbersResource;
use App\MoonShine\Resources\SalesmanResource;
use App\MoonShine\Resources\StatisticsResource;
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

    /**
     * @return list<Page>
     */
    protected function pages(): array
    {
        return [];
    }

    /**
     * @return Closure|list<MenuElement>
     */
    protected function menu(): array
    {
        return [
            MenuGroup::make(static fn() => __('moonshine::ui.resource.system'), [
                MenuItem::make(
                    static fn() => __('moonshine::ui.resource.admins_title'),
                    new MoonShineUserResource()
                ),
                // MenuItem::make(
                //     static fn() => __('moonshine::ui.resource.role_title'),
                //     new MoonShineUserRoleResource()
                // ),
                MenuItem::make('Номера', new NumberResource()),
                MenuItem::make('Покупатели', new BuyerResource()),
               MenuItem::make('Продавцы', new SalesmanResource()),
                MenuItem::make('Статистика', new StatisticsResource()),

            ]),

        ];
    }

    /**
     * @return Closure|array{css: string, colors: array, darkColors: array}
     */
    protected function theme(): array
    {
        return [];
    }
}
