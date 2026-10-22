<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MembershipRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'phone', 'email', 'password', 'whatsapp_opt_in', 'whatsapp_opt_in_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'whatsapp_opt_in' => 'boolean',
            'whatsapp_opt_in_at' => 'datetime',
        ];
    }

    /**
     * Registra quando a autorização de WhatsApp (LGPD) foi dada ou retirada.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->isDirty('whatsapp_opt_in')) {
                $user->whatsapp_opt_in_at = $user->whatsapp_opt_in ? now() : null;
            }
        });
    }

    /**
     * Normaliza o telefone para E.164. Sem "+" na frente, números com 10 ou
     * 11 dígitos são tratados como brasileiros (DDD + número).
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $isInternational = str_starts_with(trim($phone), '+');

        return ! $isInternational && in_array(strlen($digits), [10, 11], true) ? "+55{$digits}" : "+{$digits}";
    }

    /**
     * Painel do salão: só administradores e profissionais do salão atual.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRoleInCurrentTenant(MembershipRole::Admin, MembershipRole::Professional);
    }

    public function hasRoleInCurrentTenant(MembershipRole ...$roles): bool
    {
        return $this->memberships()->whereIn('role', $roles)->exists();
    }

    /**
     * Vínculos do usuário no tenant atual (filtrado pelo TenantScope).
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Agendamentos do usuário como cliente, no salão atual (TenantScope).
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    /**
     * Todos os salões do usuário, em qualquer tenant.
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'memberships')
            ->withPivot('role')
            ->withTimestamps();
    }
}
