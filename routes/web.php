<?php

use Illuminate\Support\Facades\Route;

/*
| Rotas dos salões ({slug}.{tenancy.domain}). Precisam vir antes das rotas
| do domínio principal, que não têm restrição de domínio.
*/
Route::domain('{tenant}.'.config('tenancy.domain'))
    ->middleware('tenant')
    ->name('tenant.')
    ->group(function () {
        // Provisório: será substituído pela área do cliente.
        Route::get('/', function () {
            return view('welcome');
        })->name('home');
    });

Route::get('/', function () {
    return view('welcome');
});
