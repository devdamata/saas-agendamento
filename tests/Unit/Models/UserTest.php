<?php

namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    #[DataProvider('telefones')]
    public function test_normaliza_telefone_para_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, User::normalizePhone($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function telefones(): array
    {
        return [
            'celular com máscara' => ['(11) 98765-4321', '+5511987654321'],
            'fixo só dígitos' => ['1133334444', '+551133334444'],
            'já em E.164' => ['+55 11 98765-4321', '+5511987654321'],
            'estrangeiro com DDI' => ['+1 415 555 2671', '+14155552671'],
        ];
    }
}
