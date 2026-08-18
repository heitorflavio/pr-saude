<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoRegistroClinico;
use App\Exceptions\RegistroImutavelException;
use Database\Factories\RegistroClinicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * D-05: prontuario APPEND-ONLY. Nao ha `deleted_at` aqui de proposito -- este registro
 * nao e nem excluivel logicamente. A correcao cria um ADENDO novo (RN-16, RN-17).
 *
 * A imutabilidade tem tres camadas independentes, porque cada uma falha de um jeito:
 *  1. `save()` e `delete()` sobrescritos aqui        -> pega o erro de programacao;
 *  2. CHECK `ck_registro_adendo` no banco            -> pega o adendo orfao;
 *  3. REVOKE UPDATE, DELETE (docs/privilegios.sql)   -> pega qualquer coisa que passe
 *     pelas duas primeiras, inclusive script de importacao e query crua.
 *
 * A adulteracao feita por fora da aplicacao (DB::table()->update()) escapa das camadas
 * 1 e 2 -- e detectada pelo hash encadeado da Fase 8 (doc 9.4).
 */
class RegistroClinico extends Model
{
    /** @use HasFactory<RegistroClinicoFactory> */
    use HasFactory;

    protected $table = 'registro_clinico';

    public $timestamps = false;

    protected $fillable = [
        'uuid', 'atendimento_id', 'tipo', 'subjetivo', 'objetivo', 'avaliacao',
        'plano', 'conteudo_livre', 'sigiloso', 'registro_retificado_id',
        'motivo_retificacao', 'autor_id', 'autor_nome', 'autor_conselho',
        'hash_conteudo', 'hash_anterior', 'criado_em',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoRegistroClinico::class,
            'sigiloso' => 'boolean',
            'criado_em' => 'datetime',
        ];
    }

    /**
     * RN-16: um registro ja persistido nunca e reescrito. A primeira gravacao passa;
     * qualquer save() posterior falha.
     *
     * Isso tambem cobre `update()`, que internamente e fill() + save().
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            throw RegistroImutavelException::aoAtualizar();
        }

        return parent::save($options);
    }

    /** RN-17 / D-05: nenhum dado clinico e removido do prontuario. */
    public function delete()
    {
        throw RegistroImutavelException::aoExcluir();
    }

    /** Fecha a porta de tras do SoftDeletes/forceDelete, caso alguem a adicione depois. */
    public function forceDelete()
    {
        throw RegistroImutavelException::aoExcluir();
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'autor_id', 'user_id');
    }

    /** O registro original que este adendo retifica (RN-16). */
    public function registroRetificado(): BelongsTo
    {
        return $this->belongsTo(self::class, 'registro_retificado_id');
    }

    /** Os adendos que retificam este registro. O original continua visivel. */
    public function adendos(): HasMany
    {
        return $this->hasMany(self::class, 'registro_retificado_id');
    }

    public function foiRetificado(): bool
    {
        return $this->adendos()->exists();
    }
}
