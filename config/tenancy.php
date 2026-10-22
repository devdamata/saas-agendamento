<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domínio base dos salões
    |--------------------------------------------------------------------------
    |
    | Cada salão é acessado por um subdomínio deste domínio: {slug}.{domain}.
    | Em produção: app.seudominio.com.br. Em desenvolvimento, "localhost"
    | funciona sem configurar DNS, porque o navegador resolve *.localhost.
    |
    */

    'domain' => env('TENANCY_DOMAIN', 'localhost'),

];
