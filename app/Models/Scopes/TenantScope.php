<?php

namespace App\Models\Scopes;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Filtra pelo tenant atual. Sem tenant definido a consulta falha,
     * em vez de devolver dados de todos os salões.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id()
            ?? throw TenantNotResolvedException::forModel($model::class);

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
