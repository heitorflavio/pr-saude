<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\GeradorTokenPulseira;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\PacienteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * D-02: especializacao de `users` com FK-PK compartilhada.
 *
 * NAO EXISTE coluna `idade` -- D-01, RN-02. Armazenar a idade viola a 2FN por
 * dependencia funcional derivada e produz um dado que comeca correto e apodrece
 * silenciosamente: no dia seguinte ao aniversario, o prontuario mente.
 */
class Paciente extends Model
{
    /** @use HasFactory<PacienteFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'paciente';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id', 'uuid', 'token_pulseira', 'nome_completo', 'nome_social',
        'cpf', 'cns', 'data_nascimento', 'sexo', 'nome_mae', 'telefone',
        'contato_emergencia_nome', 'contato_emergencia_telefone',
        'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'uf', 'cep',
        'identificacao_provisoria', 'codigo_provisorio', 'observacoes',
    ];

    /**
     * RN-03: o token da pulseira e permanente e nunca aparece em serializacao casual.
     */
    protected $hidden = ['token_pulseira'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'identificacao_provisoria' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function atendimentos(): HasMany
    {
        return $this->hasMany(Atendimento::class, 'paciente_id', 'user_id');
    }

    public function alergias(): HasMany
    {
        return $this->hasMany(PacienteAlergia::class, 'paciente_id', 'user_id');
    }

    public function condicoes(): HasMany
    {
        return $this->hasMany(PacienteCondicao::class, 'paciente_id', 'user_id');
    }

    public function pulseiraImpressoes(): HasMany
    {
        return $this->hasMany(PulseiraImpressao::class, 'paciente_id', 'user_id');
    }

    /** Nome de exibicao: o nome social precede o civil sempre que existir. */
    public function nomeExibicao(): string
    {
        return $this->nome_social ?: $this->nome_completo;
    }

    /**
     * D-01: idade em anos, sempre derivada. Para exibicao clinica use
     * `idadeDescritiva()` -- idade em anos e inutil para um neonato.
     */
    protected function idade(): Attribute
    {
        return Attribute::get(function (): ?int {
            // O cast e obrigatorio: `diffInYears` devolve float no Carbon 3, e este
            // arquivo esta em strict_types -- sem ele, ler $paciente->idade lancaria
            // TypeError. A truncagem para baixo tambem e o comportamento correto:
            // na vespera do aniversario a pessoa ainda tem a idade anterior.
            return $this->data_nascimento === null
                ? null
                : (int) $this->data_nascimento->diffInYears(now());
        });
    }

    /**
     * D-01: granularidade adaptativa -- dias ate 30 dias, meses ate 24 meses, anos a
     * partir dai. E a pratica assistencial corrente: "3 dias" e "2 meses" sao
     * clinicamente decisivos; "0 anos" nao informa nada.
     */
    public function idadeDescritiva(?CarbonInterface $referencia = null): ?string
    {
        if ($this->data_nascimento === null) {
            return null;
        }

        $referencia ??= now();
        $nascimento = $this->data_nascimento;

        $dias = (int) $nascimento->diffInDays($referencia);
        if ($dias <= 30) {
            return $dias === 1 ? '1 dia' : "{$dias} dias";
        }

        $meses = (int) $nascimento->diffInMonths($referencia);
        if ($meses < 24) {
            return $meses === 1 ? '1 mes' : "{$meses} meses";
        }

        $anos = (int) $nascimento->diffInYears($referencia);

        return $anos === 1 ? '1 ano' : "{$anos} anos";
    }

    /**
     * RF-09: busca por nome, CPF, CNS, data de nascimento ou token de pulseira.
     *
     * O token e comparado por igualdade exata e so depois de passar pela validacao de
     * formato (doc 8.2.1): um token malformado e descartado por comparacao de string,
     * antes de qualquer SELECT. Uma varredura de 100.000 tentativas nao vira 100.000
     * consultas ao banco.
     */
    public function scopeBusca(Builder $query, ?string $termo): Builder
    {
        $termo = trim((string) $termo);

        if ($termo === '') {
            return $query;
        }

        $digitos = preg_replace('/\D/', '', $termo) ?? '';

        return $query->where(function (Builder $q) use ($termo, $digitos) {
            $q->where('nome_completo', 'like', "%{$termo}%")
                ->orWhere('nome_social', 'like', "%{$termo}%");

            if (strlen($digitos) === 11) {
                $q->orWhere('cpf', $digitos);
            }

            if (strlen($digitos) === 15) {
                $q->orWhere('cns', $digitos);
            }

            // Codigo provisorio do paciente nao identificado (RF-04).
            if (str_starts_with(mb_strtoupper($termo), 'NI-')) {
                $q->orWhere('codigo_provisorio', mb_strtoupper($termo));
            }

            if (app(GeradorTokenPulseira::class)->valido($termo)) {
                $q->orWhere('token_pulseira', $termo);
            }

            // Data de nascimento aceita tanto 1985-03-14 quanto 14/03/1985.
            // `createFromFormat` lanca InvalidFormatException no Carbon 3 em vez de
            // devolver false, entao a tentativa vai dentro de rescue().
            foreach (['Y-m-d', 'd/m/Y'] as $formato) {
                $data = rescue(
                    fn () => Carbon::createFromFormat($formato, $termo),
                    null,
                    report: false
                );

                if ($data instanceof Carbon && $data->format($formato) === $termo) {
                    $q->orWhereDate('data_nascimento', $data->toDateString());
                    break;
                }
            }
        });
    }

    /** RN-07: no maximo um atendimento nao finalizado por unidade. */
    public function atendimentoAtivo(?int $unidadeId = null): ?Atendimento
    {
        return $this->atendimentos()
            ->whereNotIn('status', ['FINALIZADO', 'CANCELADO'])
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId))
            ->first();
    }

    /** M-9: portal durante o episódio e por 30 dias após o encerramento. */
    public function possuiAcessoVigente(): bool
    {
        return $this->atendimentos()
            ->where(function (Builder $q) {
                $q->whereNotIn('status', ['FINALIZADO', 'CANCELADO'])
                    ->orWhere('finalizado_em', '>=', now()->subDays((int) config('portal.acesso_apos_alta_dias', 30)));
            })
            ->exists();
    }

    /** M-2: a credencial de nascimento só vale 72 h e nunca após o episódio. */
    public function senhaProvisoriaVigente(): bool
    {
        return $this->user->created_at->addHours((int) config('portal.senha_provisoria_horas', 72))->isFuture()
            && $this->atendimentoAtivo() !== null;
    }
}
