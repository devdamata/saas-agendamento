<?php

namespace App\Actions\Clients;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;

/**
 * Cadastra um cliente no salão atual, identificado pelo telefone.
 *
 * O usuário é global (pode ser cliente de vários salões): se o telefone já
 * existe, o cadastro dele é reaproveitado sem sobrescrever nome ou e-mail,
 * e só o vínculo de cliente com este salão é criado.
 */
class RegisterClient
{
    /**
     * @param  bool  $whatsappOptIn  Autorização (LGPD) para receber mensagens; só liga, nunca desliga um opt-in existente.
     */
    public function handle(string $name, string $phone, ?string $email = null, bool $whatsappOptIn = false): User
    {
        $user = User::firstOrCreate(
            ['phone' => User::normalizePhone($phone)],
            ['name' => $name, 'email' => $email ?: null],
        );

        if (blank($user->email) && filled($email)) {
            $user->email = $email;
        }

        if ($whatsappOptIn) {
            $user->whatsapp_opt_in = true;
        }

        $user->save();

        Membership::firstOrCreate(['user_id' => $user->id, 'role' => MembershipRole::Client]);

        return $user;
    }
}
