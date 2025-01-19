<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Enums\StatusNumberEnum;
use App\Enums\TypeNumberEnum;
use App\Enums\UserTypeEnum;
use App\Models\Number;
use App\Models\Statistics;
use MoonShine\Decorations\Divider;
use MoonShine\Decorations\Grid;
use MoonShine\Metrics\DonutChartMetric;
use MoonShine\Metrics\ValueMetric;
use MoonShine\Pages\Page;
use MoonShine\Components\MoonShineComponent;

class Dashboard extends Page
{
    /**
     * @return array<string, string>
     */
    public function breadcrumbs(): array
    {
        return [
            '#' => $this->title()
        ];
    }

    public function title(): string
    {
        return $this->title ?: 'Статистика';
    }

    /**
     * @return list<MoonShineComponent>
     */
    public function components(): array
    {
        return [
            Divider::make('Статистика по номерам')->centered(),

            Grid::make([
                DonutChartMetric::make('В очереди')
                    ->values([
                        'Telegram' => Number::query()
                            ->where([
                                'status_number' => StatusNumberEnum::pending,
                                'type_number' => TypeNumberEnum::telegram,
                                ])->count(),
                        'WhatsApp' => Number::query()
                            ->where([
                                'status_number' => StatusNumberEnum::pending,
                                'type_number' => TypeNumberEnum::whatsapp,
                            ])->count()
                    ])->columnSpan(4),
                DonutChartMetric::make('Купленные')
                    ->values([
                        'Telegram' => Number::query()
                            ->where([
                                'status_number' => StatusNumberEnum::active,
                                'type_number' => TypeNumberEnum::telegram,
                            ])->count(),
                        'WhatsApp' => Number::query()
                            ->where([
                                'status_number' => StatusNumberEnum::active,
                                'type_number' => TypeNumberEnum::whatsapp,
                            ])->count()
                    ])->columnSpan(4),
                DonutChartMetric::make('Слетевшие')
                    ->values([
                        'Telegram' => Number::query()
                            ->where([
                                'status_number' => StatusNumberEnum::failed,
                                'type_number' => TypeNumberEnum::telegram,
                            ])->count(),
                        'WhatsApp' => Number::query()
                            ->where([
                                'status_number' => StatusNumberEnum::failed,
                                'type_number' => TypeNumberEnum::whatsapp,
                            ])->count()
                    ])->columnSpan(4),
            ])

        ];
    }
}
