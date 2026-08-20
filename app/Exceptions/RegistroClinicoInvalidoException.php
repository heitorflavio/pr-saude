<?php

declare(strict_types=1);

namespace App\Exceptions;

final class RegistroClinicoInvalidoException extends DominioException
{
    public static function conteudoVazio(): self
    {
        return new self(
            'O registro clínico precisa de conteúdo. Uma nota em branco ocupa lugar na '
            .'linha do tempo e não informa nada a quem assumir o paciente.'
        );
    }

    public static function atendimentoEncerrado(): self
    {
        return new self(
            'Este atendimento está encerrado. Um registro posterior ao desfecho precisa '
            .'ser feito no atendimento em que o fato ocorreu.'
        );
    }

    /** RN-16: adendo é criado pela RetificarRegistroAction, não como nota avulsa. */
    public static function adendoForaDaRetificacao(): self
    {
        return new self(
            'Adendo não é um tipo de nota livre: ele existe para retificar um registro '
            .'específico. Use a retificação a partir do registro original (RN-16).'
        );
    }

    public static function retificacaoDeOutroAtendimento(): self
    {
        return new self(
            'O adendo pertence ao mesmo atendimento do registro que retifica: a cadeia '
            .'de hash é por atendimento e um elo cruzado a romperia (doc §9.4).'
        );
    }

    public static function motivoObrigatorio(): self
    {
        return new self(
            'A retificação exige o motivo. Em sindicância, o que se avalia é o raciocínio '
            .'na época — e ele só é reconstituível se o porquê da correção estiver escrito.'
        );
    }

    public static function adendoDeAdendo(): self
    {
        return new self(
            'Retifique o registro original, não o adendo. Encadear correção sobre correção '
            .'torna a versão vigente ambígua exatamente quando ela mais importa.'
        );
    }

    public static function tipoNaoUsaSoap(): self
    {
        return new self(
            'Este tipo de registro não usa a estrutura SOAP. Utilize o conteúdo livre '
            .'(doc §9.2).'
        );
    }
}
