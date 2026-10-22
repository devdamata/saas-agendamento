<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Para livros de movimentação (estoque, caixa, histórico de status):
 * lançamentos só são inseridos. Correções viram um novo lançamento.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(function (): void {
            throw new LogicException('Lançamentos de ['.static::class.'] não podem ser alterados; registre um novo lançamento de correção.');
        });

        static::deleting(function (): void {
            throw new LogicException('Lançamentos de ['.static::class.'] não podem ser apagados; registre um novo lançamento de correção.');
        });
    }
}
