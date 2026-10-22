<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Product;
use App\Models\User;

/**
 * Estoque: só administradores do salão. Produto com movimentações não é
 * apagado; deve ser desativado.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    /**
     * Editar o cadastro e movimentar o estoque.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasRoleInCurrentTenant(MembershipRole::Admin) && ! $product->stockMovements()->exists();
    }
}
