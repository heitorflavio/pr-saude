<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base de toda excecao de regra de negocio do SGH.
 *
 * Nenhuma Action lanca `\Exception` cru: em sistema clinico, uma excecao generica
 * impede distinguir "o banco caiu" de "a enfermeira tentou administrar a dose duas
 * vezes" -- e as duas exigem tratamento oposto na interface e no log.
 */
abstract class DominioException extends RuntimeException {}
