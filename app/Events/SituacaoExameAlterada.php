<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\SituacaoExame;
use App\Models\ExameSolicitacao;
use Illuminate\Foundation\Events\Dispatchable;

final class SituacaoExameAlterada
{
    use Dispatchable;

    public function __construct(
        public readonly ExameSolicitacao $solicitacao,
        public readonly SituacaoExame $anterior,
        public readonly SituacaoExame $nova,
    ) {}
}
