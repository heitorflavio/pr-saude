<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * D-02 / DECISOES.md D-01: identidade unica da pessoa. `paciente` e `profissional` sao
 * especializacoes com FK-PK compartilhada -- quando a enfermeira Ana e atendida na
 * propria unidade, ela tem UM usuario e dois papeis, e a auditoria mostra corretamente
 * "Ana acessou o prontuario de Ana".
 *
 * ATENCAO: nunca criar aqui propriedade, relacao ou metodo chamado `role`, `roles`,
 * `permission` ou `permissions` -- conflita com o trait HasRoles do spatie.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'login',
        'tipo',
        'senha_provisoria',
        'senha_alterada_em',
        'ativo',
        'ultimo_login_em',
        'tentativas_falhas',
        'bloqueado_ate',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'senha_provisoria' => 'boolean',
            'senha_alterada_em' => 'datetime',
            'ativo' => 'boolean',
            'ultimo_login_em' => 'datetime',
            'tentativas_falhas' => 'integer',
            'bloqueado_ate' => 'datetime',
        ];
    }

    public function paciente(): HasOne
    {
        return $this->hasOne(Paciente::class, 'user_id');
    }

    public function profissional(): HasOne
    {
        return $this->hasOne(Profissional::class, 'user_id');
    }

    public function ehAdmin(): bool
    {
        return $this->tipo === 'ADMIN';
    }

    public function ehPaciente(): bool
    {
        return $this->tipo === 'PACIENTE';
    }

    /**
     * doc 13.5: o "minimo vital" e a quebra de sigilo exigem profissional EM PLANTAO,
     * nao apenas profissional cadastrado. Quem nao esta de plantao nao tem motivo
     * assistencial para abrir prontuario nenhum.
     */
    public function emPlantao(): bool
    {
        return $this->profissional?->emPlantao() ?? false;
    }
}
