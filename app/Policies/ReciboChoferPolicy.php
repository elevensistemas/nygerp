<?php

namespace App\Policies;

use App\Models\ReciboChofer;
use App\Models\User;

class ReciboChoferPolicy
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

    public function view(User $user, ReciboChofer $recibo): bool
    {
        return true;
    }

    public function update(User $user, ReciboChofer $recibo): bool
    {
        if ($user->isReadOnly()) {
            return false;
        }

        return ! in_array($recibo->estado, [ReciboChofer::ESTADO_EN_PLANILLA, ReciboChofer::ESTADO_PAGADO, ReciboChofer::ESTADO_ANULADO], true);
    }

    public function anular(User $user, ReciboChofer $recibo): bool
    {
        if ($user->isReadOnly()) {
            return false;
        }

        return $recibo->estado !== ReciboChofer::ESTADO_PAGADO;
    }

    public function updateItem(User $user, ReciboChofer $recibo): bool
    {
        return $this->update($user, $recibo);
    }
}
