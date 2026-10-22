<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Para todo model de negócio com coluna tenant_id.
 *
 * - Aplica o TenantScope em todas as consultas.
 * - Preenche tenant_id com o tenant atual ao criar.
 * - Impede gravar registro em outro tenant ou mover registro de tenant.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model) {
            $currentId = app(TenantContext::class)->id()
                ?? throw TenantNotResolvedException::forModel(static::class);

            $model->tenant_id ??= $currentId;

            if ((int) $model->tenant_id !== $currentId) {
                throw new LogicException('Tentativa de criar ['.static::class.'] em um tenant diferente do atual.');
            }
        });

        static::updating(function (self $model) {
            if ($model->isDirty('tenant_id')) {
                throw new LogicException('Não é permitido mover ['.static::class.'] para outro tenant.');
            }
        });
    }

    /**
     * Consulta ignorando o tenant. Usar só em rotinas que atravessam
     * salões de propósito (ex.: agendador de lembretes).
     */
    public static function withoutTenancy(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
