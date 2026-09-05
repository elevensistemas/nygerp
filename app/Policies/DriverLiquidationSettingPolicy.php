<?php

namespace App\Policies;

use App\Models\DriverLiquidationSetting;
use App\Models\User;

class DriverLiquidationSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isReadOnly() && ! $user->isTransportista();
    }

    public function create(User $user): bool
    {
        return ! $user->isReadOnly() && ! $user->isTransportista();
    }

    public function update(User $user, DriverLiquidationSetting $setting): bool
    {
        return ! $user->isReadOnly() && ! $user->isTransportista();
    }

    public function delete(User $user, DriverLiquidationSetting $setting): bool
    {
        return ! $user->isReadOnly() && ! $user->isTransportista();
    }
}
