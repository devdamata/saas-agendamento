<?php

namespace App\Models\Concerns;

/**
 * O Eloquent grava datas formatando o relógio local do Carbon, sem fuso:
 * 09:00 em São Paulo viraria 09:00 UTC. Este trait converte para o fuso da
 * aplicação (UTC) antes de gravar, para as colunas timestamptz guardarem o
 * instante certo venha a data em qualquer fuso.
 *
 * Atenção: em consultas (where), o query builder não converte; passe datas
 * já em UTC (->utc()).
 */
trait StoresDatesInAppTimezone
{
    /**
     * @param  mixed  $value
     * @return string|null
     */
    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)
            ->setTimezone(config('app.timezone'))
            ->format($this->getDateFormat());
    }
}
