<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Professional;
use App\Models\User;

/**
 * Cadastro de profissionais: só administradores do salão. Profissional com
 * agendamentos não é apagado; deve ser desativado.
 */
class ProfessionalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function view(User $user, Professional $professional): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function update(User $user, Professional $professional): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function delete(User $user, Professional $professional): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin) && ! $professional->appointments()->exists();
    }
}
