<?php

namespace App\Enums;

enum MembershipRole: string
{
    case Admin = 'admin';
    case Professional = 'profissional';
    case Client = 'cliente';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Professional => 'Profissional',
            self::Client => 'Cliente',
        };
    }
}
