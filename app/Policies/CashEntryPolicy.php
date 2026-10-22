<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\CashEntry;
use App\Models\User;

/**
 * Caixa: só administradores do salão. Lançamentos não são alterados nem
 * apagados (livro de movimentação).
 */
class CashEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function view(User $user, CashEntry $cashEntry): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function update(User $user, CashEntry $cashEntry): bool
    {
        return false;
    }

    public function delete(User $user, CashEntry $cashEntry): bool
    {
        return false;
    }
}
