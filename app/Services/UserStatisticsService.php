<?php

namespace App\Services;

use App\Models\Statistics;

class UserStatisticsService
{

    public function createStatistics(string      $uuid,
                                     string      $type,
                                     string      $name,
                                     string|null $provider,
                                     int|null    $count_active,
                                     int|null    $count_deactivate,
                                     int|null    $count_pending,
    )
    {
        return Statistics::query()->updateOrCreate(
            ['uuid' => $uuid, 'type' => $type, 'provider_number' => $provider, 'name' => $name],
            [
                'count_active' => $count_active,
                'count_deactivate' => $count_deactivate,
                'count_pending' => $count_pending,
            ]);
    }

}
