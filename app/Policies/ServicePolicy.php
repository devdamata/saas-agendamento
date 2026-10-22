<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Service;
use App\Models\User;

/**
 * Cadastro de serviços: só administradores do salão. Serviço com
 * agendamentos não é apagado; deve ser desativado.
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function view(User $user, Service $service): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function update(User $user, Service $service): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin) && ! $service->appointments()->exists();
    }
}
