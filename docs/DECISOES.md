# DECISÕES — divergências em relação a `docs/modelagem-sgh.md`

Registro de toda decisão que se afasta do documento normativo, com a justificativa.
Ordem cronológica. `docs/PROMPT-CLAUDE-CODE.md` vence o documento em caso de conflito
(ele contém as adaptações para a stack real); as divergências que vêm de lá estão
marcadas como **prescrita pelo prompt**.

---

## D-01 · Tabela `usuario` substituída por `users` do starter kit

**Origem:** prescrita pelo prompt §3.1 · **Status:** aceita · **2026-08-18**

O documento modela uma tabela `usuario` própria. O projeto já tem a tabela `users` do
`laravel/vue-starter-kit`, com autenticação, reset de senha e verificação de e-mail
funcionando.

**Decisão.** Estender `users` em vez de criar `usuario`. Mapeamento:

| Documento | Projeto |
|---|---|
| `usuario` | `users` |
| `usuario.senha_hash` | `users.password` (o `Authenticatable` espera esse nome) |
| `usuario.login` | `users.login` (nova coluna, `unique`) |
| `usuario.tipo` | `users.tipo` (novo enum: `PACIENTE`, `PROFISSIONAL`, `ADMIN`) |
| `usuario.senha_provisoria` | `users.senha_provisoria` (RN-06) |
| `usuario.ativo`, `tentativas_falhas`, `bloqueado_ate`, `ultimo_login_em`, `senha_alterada_em` | colunas novas em `users` (RNF-08) |
| `paciente.usuario_id` | `paciente.user_id` → `users.id` |
| `profissional.usuario_id` | `profissional.user_id` → `users.id` |
| qualquer FK para `usuario.id` | FK para `users.id`, coluna renomeada para `user_id` |

**Motivo.** Recriar a identidade do zero jogaria fora auth testado e obrigaria a
reimplementar reset de senha e verificação de e-mail sem ganho nenhum.

---

## D-02 · `users.email` passa a ser `nullable`

**Origem:** prescrita pelo prompt §3.1 · **Status:** aceita · **2026-08-18**

Paciente de urgência frequentemente não tem e-mail, e o cadastro não pode ser bloqueado
por isso (RF-04). O índice `unique` é mantido — MySQL aceita múltiplos `NULL` em índice
único.

---

## D-03 · Duplicação intencional entre `users.name` e `nome_completo`

**Origem:** prescrita pelo prompt §3.1 · **Status:** aceita · **2026-08-18**

`users.name` permanece como **rótulo de exibição** do starter kit. O nome oficial do
registro é `paciente.nome_completo` / `profissional.nome_completo`.

**Consequência operacional:** a Action que cadastra é responsável por manter `users.name`
sincronizado. Qualquer renomeação de paciente precisa passar por ela — escrever direto no
model quebra a sincronia. Duplicação aceita conscientemente para não alterar o contrato do
starter kit.

---

## D-04 · Banco de dados: MySQL (resolvido — servidor 9.6.0)

**Origem:** decisão do usuário no checkpoint do Passo 0 · **Status:** ✅ **resolvida** ·
**2026-08-18**

> **Desfecho.** O usuário provisionou **MySQL Community 9.6.0** em `127.0.0.1:3306`.
> Bancos criados: `prsaude` (aplicação) e `prsaude_test` (suíte), ambos
> `utf8mb4 / utf8mb4_0900_ai_ci`, como o `schema.sql` especifica. O servidor é **mais
> novo que o 8.4 alvo** e suporta tudo que o schema exige: InnoDB, `ENUM`, colunas
> geradas `STORED`, `CHECK` (8.0.16+), window functions e `GRANT`/`REVOKE`.
>
> ⚠️ **Atenção operacional:** esse servidor hospeda outros bancos do usuário
> (`db-cartas` com 345 MB, `ams360`, `permuta`, `permuta_testing`, `netbench`,
> `5minutos`). Todo trabalho do SGH é estritamente escopado em `prsaude` e
> `prsaude_test` — `migrate:fresh` jamais pode ser executado com `DB_DATABASE`
> apontando para outro schema.
>
> Pendência remanescente: o CI ainda cria banco **SQLite** (ver a nota ao fim desta
> decisão).

O Passo 0 encontrou o projeto em **SQLite**, enquanto `docs/schema.sql` é MySQL 8.4. O
levantamento de disponibilidade: portas 3306 e 5432 fechadas, `herd services` exige Herd
Pro (não licenciado), Docker 29.5.2 instalado mas com o daemon parado.

**Decisão do usuário: MySQL, que ele próprio providenciaria** — cumprida no mesmo dia.

**Motivo.** Preserva `schema.sql` como fonte normativa literal. Sob SQLite seria preciso:

| Objeto | Problema no SQLite |
|---|---|
| Coluna gerada `ativo_key ... STORED` (D-07/RN-07) | SQLite não permite adicionar coluna gerada `STORED` via `ALTER TABLE` |
| As 18 `CHECK` | SQLite não tem `ADD CONSTRAINT`; só declaradas no `CREATE TABLE` |
| As 3 views | Escritas em dialeto MySQL (`TIMESTAMPDIFF`, `IFNULL`) |
| `docs/privilegios.sql` (`REVOKE UPDATE, DELETE`) | SQLite não tem `GRANT`/`REVOKE`; exigiria triggers `RAISE(ABORT)` |
| Teste de concorrência da Fase 5 | SQLite serializa escrita — o teste ficaria artificial |

**Pendência aberta:** o CI em `.github/workflows/tests.yml` cria um banco SQLite. Com o
MySQL em uso local, o workflow precisa de um *service container* MySQL — caso contrário o
CI testa um banco que não reproduz as 18 `CHECK` nem a coluna gerada, justamente as
garantias que a Fase 1 existe para estabelecer. Ver também D-13.

---

## D-05 · RBAC pelo spatie/laravel-permission; 4 tabelas do schema removidas

**Origem:** prescrita pelo prompt §3.2 · **Status:** aceita · **2026-08-18**

As tabelas `perfil`, `permissao`, `perfil_permissao` e `usuario_perfil` do `schema.sql`
são **removidas** — seriam um RBAC duplicado. O pacote traz `permissions`, `roles`,
`model_has_permissions`, `model_has_roles` e `role_has_permissions`.

**Resultado:** 30 tabelas de domínio + 5 do spatie.

**Restrições que isso impõe:**

- `User` recebe o trait `HasRoles` e implementa `Authorizable`.
- **Nunca** criar no `User` propriedade, relação ou método `role`, `roles`, `permission`
  ou `permissions` — conflita com o trait.
- Todos os `Role` e `Permission` são criados com `guard_name = 'web'`. O guard `paciente`
  fica **sem nenhuma role e sem nenhuma permission, de propósito**: assim qualquer `can()`
  nesse guard nega por construção. É a primeira camada de garantia da RN-27.
- `auditoria_log.perfis_no_momento` recebe o *snapshot* de
  `$user->roles->pluck('name')->implode(',')` no instante do evento — o log não muda quando
  as roles mudam.

---

## D-06 · PHP elevado de 8.2 para 8.4; o prompt §3.2 estava incorreto sobre o spatie

**Origem:** correção de erro técnico do prompt · **Status:** ✅ aplicada · **2026-08-18**

O prompt §3.2 manda instalar `spatie/laravel-permission` **v8**, descrevendo-o como
"compatível com Laravel 12/13 e PHP 8.3+". O projeto rodava **PHP 8.2.31**, e a v8.3.0
exige `php ^8.3`. Verificado com `composer require --dry-run`: o Composer resolveria para
**6.25.0**, não v8 — o prompt teria sido silenciosamente descumprido.

**Decisão.** Elevar o site para **PHP 8.4.22** (já instalado no Herd), via
`herd isolate 8.4`, e subir a constraint de `composer.json` de `^8.2` para `^8.4`.

**Motivo adicional.** O CI (`.github/workflows/lint.yml` e `tests.yml`) já roda **PHP
8.4**. Local e CI estavam divergindo desde o início. A constraint `^8.4` passa a refletir
exatamente os dois ambientes que existem de fato.

**Verificação.** `php -v` na pasta do projeto devolve 8.4.22; `composer --version` também;
Argon2id presente (`2y, argon2i, argon2id`); `php artisan test` com 27 testes passando.

---

## D-07 · Hook quebrado do Laravel Boost removido do `composer.json`

**Origem:** defeito herdado do repositório · **Status:** ✅ aplicada · **2026-08-18**

O commit `fa40609` ("Install Laravel Boost") adicionou `@php artisan boost:update --ansi`
ao `post-update-cmd`, mas **`laravel/boost` não está instalado** — a única menção em
`composer.lock` é uma cláusula `conflict` de terceiro. Qualquer `composer update` falhava
com "command not found".

**Decisão.** Remover a linha do `post-update-cmd`. Se o Boost for desejado depois, instalar
o pacote e só então reintroduzir o hook.

---

## D-08 · Testes de esquema derivados da doc §5.4 (arquivo original ausente)

**Origem:** lacuna no repositório · **Status:** aceita · **2026-08-18**

A Fase 1 exige portar `verificacao/testes_schema.sh` para Pest. **O diretório
`verificacao/` não veio no repositório** — faltam `testes_schema.sh`,
`verifica_algoritmos.php` e `fixtures_schema.sql`.

**Decisão do usuário: derivar os testes da tabela da doc §5.4**, que enumera os 14 testes
negativos e os 2 positivos com a regra (RN/RF) e a constraint esperada de cada um. Nenhum
caso de teste será inventado além dessa lista.

**Consequência:** as mensagens de erro esperadas serão as do MySQL (`ERROR 1062`,
`CONSTRAINT ... failed`); os testes Pest verificarão a **recusa** e a constraint violada,
não o texto literal da mensagem.

---

## D-09 · HTTPS não habilitado — decisão consciente, com risco conhecido

**Origem:** decisão do usuário no checkpoint do Passo 0 · **Status:** ⚠️ risco aceito ·
**2026-08-18**

O prompt §3.6 pede `herd secure pr-saude` e `APP_URL=https://pr-saude.test`, porque
`getUserMedia` (acesso à câmera) só funciona em contexto seguro. **O usuário optou por não
aplicar agora.** `APP_URL` permanece `http://pr-saude.test`.

**Risco assumido:** a leitura de QR Code das Fases 4 e 7 (UC-07, UC-10) **não será
testável no navegador** até que o HTTPS seja habilitado. O código será escrito e coberto
por teste automatizado, mas a validação manual — "imprima uma pulseira e leia o QR com um
celular", exigida na *definition of done* da Fase 4 — fica pendente.

---

## D-10 · `components.json` com case divergente — não corrigido

**Origem:** defeito herdado do starter kit · **Status:** ⚠️ risco aceito · **2026-08-18**

`components.json` aponta os aliases para `resources/js/Components` (C maiúsculo), mas o
diretório real é `resources/js/components` (minúsculo). Funciona no Windows, que tem
sistema de arquivos *case-insensitive*. **O usuário optou por não corrigir agora.**

**Risco assumido:** o CI roda em `ubuntu-latest`, onde o sistema de arquivos é
*case-sensitive*. Qualquer componente novo adicionado pela CLI do shadcn-vue pode ser
gerado no caminho errado e quebrar o build no CI. Reavaliar antes da Fase 7, que é a que
mais consome componentes novos.

---

## D-11 · Ausência de 2FA no starter kit

**Origem:** premissa incorreta do prompt §3.1 · **Status:** registrada · **2026-08-18**

O prompt §3.1 manda preservar "o funcionamento da autenticação já instalada (reset de
senha, verificação de e-mail, **2FA**)". O levantamento mostrou que este starter kit usa
controllers de autenticação próprios (`app/Http/Controllers/Auth/`), **sem Fortify e sem
nenhum 2FA**. Reset de senha e verificação de e-mail existem; 2FA não.

**Decisão.** Nada a preservar quanto a 2FA. Nenhum RNF do documento exige segundo fator
para a equipe, então **não será implementado** sem pedido explícito. Registrado para que a
ausência não seja lida depois como esquecimento.

---

## D-12 · Expiração de sessão por guard exige middleware próprio

**Origem:** limitação do framework · **Status:** registrada · **2026-08-18**

RNF-09 e RNF-10 exigem 30 min de sessão para a equipe e 15 min para o paciente. O Laravel
tem um único `config('session.lifetime')`, hoje em 120 min — **não há expiração por
guard** nativa.

**Decisão.** Implementar na Fase 2 um middleware que carimba o instante da última
atividade na sessão e invalida conforme o guard ativo, em vez de tentar configurar dois
lifetimes. Registrado aqui porque a leitura ingênua de `config/session.php` sugeriria que a
exigência está atendida quando não está.

---

## D-13 · A suíte de testes roda em MySQL, não em SQLite in-memory

**Origem:** consequência de D-04 · **Status:** ✅ aplicada · **2026-08-18**

`phpunit.xml` vinha do starter kit com `DB_CONNECTION=sqlite` e `DB_DATABASE=:memory:`.

**Decisão.** Apontar a suíte para `mysql` / `prsaude_test`.

**Motivo.** As invariantes críticas do SGH são garantidas pelo **banco**, não pela
aplicação: as 18 restrições `CHECK`, a coluna gerada `ativo_key` (RN-07/D-07) e as
`UNIQUE KEY` da RN-20. SQLite in-memory não reproduz nenhuma delas — a coluna gerada
sequer existiria. Um teste de esquema rodando em SQLite provaria algo sobre um banco que
não é o de produção, que é pior que não testar, porque dá falsa confiança.

**Custo aceito.** A suíte passou de ~1,7 s para ~13 s. É o preço de testar o banco real.

**Pendência:** `.github/workflows/tests.yml` ainda faz `Create SQLite Database`. Precisa
de um *service container* MySQL, senão o CI testa um banco diferente do local — e
justamente nas garantias que mais importam.

---

## D-14 · `users.login` e `users.tipo` nascem `nullable`, a apertar na Fase 2

**Origem:** conflito entre o schema.sql e o cadastro público do starter kit ·
**Status:** ⚠️ dívida assumida · **2026-08-18**

O `schema.sql` define `usuario.login` e `usuario.tipo` como `NOT NULL`. O starter kit
expõe uma rota **pública** de cadastro (`POST /register`) que cria usuário com apenas
nome, e-mail e senha — não há valor válido de `login` nem de `tipo` que ela pudesse
preencher, porque **autocadastro não existe neste domínio**: usuários são criados pela
recepção ou pelo administrador.

**Decisão.** Na Fase 1 as duas colunas ficam `nullable` (com `unique` em `login`), para
não quebrar a autenticação existente antes da fase que trata dela.

**A resolver na Fase 2:** remover a rota pública de cadastro e tornar as duas colunas
`NOT NULL`. Registrado aqui para que a folga não seja lida depois como esquecimento —
enquanto ela existir, o banco aceita um usuário sem identificação de login nem de tipo.

---

## D-15 · `users` ganhou `SoftDeletes`; o teste de exclusão de conta foi ajustado

**Origem:** consequência de D-08 (exclusão sempre lógica) · **Status:** ✅ aplicada ·
**2026-08-18**

A tabela `usuario` do `schema.sql` tem `deleted_at`, e D-08 proíbe exclusão física de
entidade clínica. Adicionar `SoftDeletes` ao model `User` quebrou o teste
`user can delete their account`, do starter kit, que afirmava `$user->fresh()` ser
`null` após a exclusão.

**Decisão.** Ajustar o teste para asseverar exclusão **lógica**
(`assertSoftDeleted`), que é o comportamento correto: a auditoria precisa continuar
podendo referenciar quem agiu, e um usuário apagado fisicamente deixa registros
clínicos órfãos de autor.

**A revisitar na Fase 2:** a própria existência da rota de auto-exclusão de conta.
Num SGH, conta de paciente ou de profissional não se apaga por autoatendimento.

---

## D-16 · `auditoria_log.usuario_id` renomeada para `user_id`

**Origem:** consistência com D-01 · **Status:** ✅ aplicada · **2026-08-18**

A regra do prompt §3.1 fala em renomear "toda FK que aponta para `usuario.id`".
`auditoria_log.usuario_id` **não tem FK** — de propósito, para que o log sobreviva à
remoção lógica de qualquer entidade que referencia e para não criar dependência de ordem
na política de retenção (doc §14.4).

**Decisão.** Renomear mesmo assim, para `user_id`. Manter uma única coluna chamada
`usuario_id` em todo o modelo seria uma pegadinha permanente para quem escreve query.

---

## D-17 · Testes de esquema escrevem direto no banco

**Origem:** método · **Status:** ✅ aplicada · **2026-08-18**

Os 16 casos de `tests/Feature/Sgh/EsquemaTest.php` usam `DB::table()->insert()` /
`->update()` sempre que possível, em vez de passar pelas Actions.

**Motivo.** O que está sob teste é o **banco**, não a aplicação. Se o teste passasse por
uma camada de validação em PHP, ele provaria que o PHP recusa — e a tese da doc §5.4 é
exatamente a oposta: as regras estão gravadas no esquema justamente para sobreviver a
bug de aplicação, condição de corrida e script de importação. Cada asserção verifica o
nome da constraint violada, não apenas que "deu erro".
