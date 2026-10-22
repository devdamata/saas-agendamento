<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\URL;

/**
 * Guarda o tenant (salão) da requisição ou job atual.
 *
 * Registrado como "scoped" no container, então é zerado a cada
 * requisição e a cada job da fila.
 *
 * Também define o parâmetro {tenant} padrão das URLs, para que route()
 * gere links no subdomínio do salão atual.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;

        URL::defaults(['tenant' => $tenant?->slug]);
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->set(null);
    }

    /**
     * Executa o callback como se o tenant informado fosse o atual,
     * restaurando o tenant anterior ao final.
     *
     * @template T
     *
     * @param  callable(Tenant): T  $callback
     * @return T
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            $this->set($previous);
        }
    }
}
