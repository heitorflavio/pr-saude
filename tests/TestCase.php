<?php

namespace Tests;

use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Semeia a matriz RBAC e os catálogos de domínio.
     *
     * O `RefreshDatabase` roda `migrate:fresh --seed` **uma única vez por execução** e
     * depois isola cada teste numa transação. Como o seed acontece antes da primeira
     * transação, os catálogos ficam visíveis a todos os testes sem serem recriados —
     * eram ~340 inserts por teste antes disto.
     *
     * `CatalogoSeeder` e não `DatabaseSeeder`: o segundo cria a conta administrativa, e
     * um teste que conta usuários não deve depender de quem o seeder de ambiente criou.
     */
    protected bool $seed = true;

    protected string $seeder = CatalogoSeeder::class;
}
