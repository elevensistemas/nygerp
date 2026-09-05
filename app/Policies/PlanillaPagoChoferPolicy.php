<?php

namespace App\Policies;

use App\Models\PlanillaPagoChofer;
use App\Models\User;

class PlanillaPagoChoferPolicy
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
        return true;
    }

    public function view(User $user, PlanillaPagoChofer $planilla): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return ! $user->isReadOnly() && ! $user->isTransportista();
    }

    public function confirm(User $user, PlanillaPagoChofer $planilla): bool
    {
        return ! $user->isReadOnly() && ! $user->isTransportista() && $planilla->estado === PlanillaPagoChofer::ESTADO_BORRADOR;
    }

    public function conciliate(User $user, PlanillaPagoChofer $planilla): bool
    {
        return ! $user->isReadOnly() && ! $user->isTransportista();
    }

    public function delete(User $user, PlanillaPagoChofer $planilla): bool
    {
        return ! $user->isReadOnly()
            && ! $user->isTransportista()
            && $planilla->isDeletableUnprocessed();
    }
}
