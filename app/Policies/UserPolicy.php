<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\User;

/**
 * Clientes do salão (painel): administradores e profissionais cadastram e
 * atualizam; ninguém apaga, porque o cadastro é compartilhado entre salões.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin, MembershipRole::Professional);
    }

    public function view(User $user, User $client): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin, MembershipRole::Professional);
    }

    public function create(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin, MembershipRole::Professional);
    }

    public function update(User $user, User $client): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin, MembershipRole::Professional);
    }

    public function delete(User $user, User $client): bool
    {
        return false;
    }
}
