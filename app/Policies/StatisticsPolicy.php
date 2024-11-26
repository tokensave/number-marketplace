<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\Statistics;
use MoonShine\Models\MoonshineUser;

class StatisticsPolicy
{
    use HandlesAuthorization;

    public function viewAny(MoonshineUser $user): bool
    {
        return true;
    }

    public function view(MoonshineUser $user, Statistics $item): bool
    {
        return true;
    }

    public function create(MoonshineUser $user): bool
    {
        return true;
    }

    public function update(MoonshineUser $user, Statistics $item): bool
    {
        return true;
    }

    public function delete(MoonshineUser $user, Statistics $item): bool
    {
        return true;
    }

    public function restore(MoonshineUser $user, Statistics $item): bool
    {
        return true;
    }

    public function forceDelete(MoonshineUser $user, Statistics $item): bool
    {
        return true;
    }

    public function massDelete(MoonshineUser $user): bool
    {
        return true;
    }
}
