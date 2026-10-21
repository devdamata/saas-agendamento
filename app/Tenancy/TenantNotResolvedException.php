<?php

namespace App\Tenancy;

use RuntimeException;

class TenantNotResolvedException extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self("Nenhum tenant definido ao acessar [{$model}]. Defina o tenant no TenantContext ou remova o escopo explicitamente.");
    }
}
