<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExameAnexoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `caminho` aponta para fora do document root: anexo de exame nao e servido por URL
 * direta. `hash_sha256` garante que o arquivo entregue e o arquivo gravado.
 */
class ExameAnexo extends Model
{
    /** @use HasFactory<ExameAnexoFactory> */
    use HasFactory;

    protected $table = 'exame_anexo';

    public $timestamps = false;

    protected $fillable = [
        'exame_resultado_id', 'nome_original', 'caminho', 'mime',
        'tamanho_bytes', 'hash_sha256', 'enviado_por', 'criado_em',
    ];

    protected function casts(): array
    {
        return [
            'tamanho_bytes' => 'integer',
            'criado_em' => 'datetime',
        ];
    }

    public function resultado(): BelongsTo
    {
        return $this->belongsTo(ExameResultado::class, 'exame_resultado_id');
    }

    public function remetente(): BelongsTo
    {
        return $this->belongsTo(Profissional::class, 'enviado_por', 'user_id');
    }
}
