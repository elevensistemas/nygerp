<?php

namespace App\Policies;

use App\Models\DriverImportRun;
use App\Models\User;

class DriverImportRunPolicy
{
    public function before(User $user, $ability)
    {
        if ($user->isAdminOrSuper()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return ! $user->isTransportista();
    }

    public function view(User $user, DriverImportRun $run): bool
    {
        return ! $user->isTransportista();
    }

    public function import(User $user): bool
    {
        return ! $user->isReadOnly() && ! $user->isTransportista();
    }
}
