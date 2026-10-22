<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifica o salão pelo subdomínio do cabeçalho Host e o define como
 * tenant atual. Subdomínio sem salão cadastrado retorna 404.
 */
class IdentifyTenant
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->subdomainOf($request->getHost());

        $tenant = $slug === null ? null : Tenant::where('slug', $slug)->first();

        abort_if($tenant === null, Response::HTTP_NOT_FOUND);

        $this->tenantContext->set($tenant);

        // O parâmetro {tenant} do domínio da rota não deve chegar aos controllers.
        $request->route()?->forgetParameter('tenant');

        return $next($request);
    }

    /**
     * Devolve o primeiro nível do host abaixo do domínio base, ou null se o
     * host não for um subdomínio direto dele.
     */
    private function subdomainOf(string $host): ?string
    {
        $suffix = '.'.config('tenancy.domain');

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        return str_contains($subdomain, '.') ? null : $subdomain;
    }
}
