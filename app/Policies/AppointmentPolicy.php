<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Appointment;
use App\Models\User;

/**
 * Agenda: administradores e profissionais do salão. Agendamentos não são
 * apagados; o histórico fica pelo status (cancelado, faltou).
 */
class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin, MembershipRole::Professional);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin, MembershipRole::Professional);
    }

    public function create(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin, MembershipRole::Professional);
    }

    /**
     * Mudança de status (confirmar, concluir, cancelar, faltou).
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin, MembershipRole::Professional);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return false;
    }
}
