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
> Pendência remanescente **resolvida na Fase 2**: o CI passou a usar service container
> MySQL (D-21).

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

**Pendência que era aberta (resolvida em D-21):** o CI criava banco SQLite, o que faria
os testes de esquema rodarem contra um banco sem as 18 `CHECK` nem a coluna gerada.

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

**Origem:** decisão do usuário no checkpoint do Passo 0 · **Status:** ✅ **resolvida na
Fase 4** (ver D-32) · **2026-08-18**

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

**Pendência resolvida na Fase 2 (D-21):** `.github/workflows/tests.yml` passou a subir um
service container `mysql:9`.

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

---

## D-18 · Removidos o cadastro público e a auto-exclusão de conta

**Origem:** decisão do usuário no checkpoint da Fase 1 · **Status:** ✅ aplicada ·
**2026-08-18**

O starter kit expunha `GET/POST /register` (autocadastro) e
`DELETE /settings/profile` (auto-exclusão de conta). Ambas foram removidas, junto com
`RegisteredUserController`, `Register.vue`, `DeleteUser.vue` e `ProfileController::destroy`.

**Motivo do cadastro.** Num SGH não existe autocadastro. Usuários são criados pela
recepção (pacientes, RN-04, com login = CPF e senha provisória) ou pelo administrador
(equipe, sob `usuario.gerenciar`). A rota aberta permitia a qualquer pessoa na internet
criar conta autenticada no sistema.

**Motivo da exclusão.** D-08 proíbe exclusão física de dado clínico, e um usuário
removido deixaria registros clínicos órfãos de autor — `registro_clinico.autor_id` é
`NOT NULL` e a trilha de auditoria precisa continuar podendo dizer quem agiu.
Desativação de conta (`users.ativo = false`) é ato administrativo, não autoatendimento.

**Consequência:** fechou a dívida D-14 — `users.login` e `users.tipo` passaram a
`NOT NULL` na migration `2026_08_18_210000`.

---

## D-19 · `prontuario.criar` dividida em duas permissões

**Origem:** a matriz da doc §2.3 é mais granular que a lista do prompt §5 ·
**Status:** ✅ aplicada · **2026-08-18**

O prompt §5 lista `prontuario.criar` entre as permissões nomeadas. A matriz da doc §2.3,
porém, separa **"Prontuário — nota médica"** de **"Prontuário — evolução de enfermagem"**
com acessos diferentes: o técnico de enfermagem escreve evolução, mas não nota médica.
Uma permissão única não expressa isso.

**Decisão.** Seguir a convenção `recurso.acao` do prompt, mas com duas permissões:
`prontuario.criar_nota_medica` e `prontuario.criar_evolucao_enfermagem`. As outras 12
permissões nomeadas no prompt entraram com o nome exato.

Não é contradição com o prompt: ele manda "semear a matriz completa da doc §2.3", e a
matriz completa exige a separação.

---

## D-20 · `Gate::before` restrito ao domínio administrativo

**Origem:** prescrito pelo prompt §5, **corrigido por decisão do usuário** ·
**Status:** ✅ aplicada · **2026-08-18**

O prompt §5 manda: "`Gate::before` libera o `admin` — mas **não** para
`prontuario.quebra_sigilo`". Implementado assim primeiro.

**A tensão detectada.** A matriz da doc §2.3 dá ao administrador apenas **R** (leitura)
nas linhas clínicas — prontuário, prescrição, administração de medicamento. A intenção do
documento é clara: administrador configura o sistema, não pratica medicina. O
`Gate::before` irrestrito concedia escrita clínica a ele.

**Decisão do usuário: restringir o atalho.** Ele agora vale apenas para:

| Domínio | Cobertura |
|---|---|
| Gestão de usuários | prefixo `usuario.` |
| Catálogos | prefixo `catalogo_` |
| Auditoria | prefixo `auditoria.` |
| Cadastro de paciente | prefixo `paciente.` |
| Ficha cadastral | ability de Policy `verContexto` (doc §13.5: o admin não tem vínculo assistencial com ninguém) |

`prontuario.quebra_sigilo` e `quebrarSigilo` continuam **fora do atalho mesmo dentro do
domínio administrativo** — é o ponto inteiro do controle da RN-28.

Todo o resto cai na verificação normal, e lá o admin tem exatamente o que a matriz
semeou: **leitura** nas linhas clínicas, **nenhuma escrita**.

**Motivo.** Um administrador de TI capaz de assinar evolução médica em nome próprio é um
risco de integridade do prontuário que nenhuma auditoria posterior desfaz. O prompt vence
o documento em caso de conflito, mas aqui o conflito era com uma regra de segurança
assistencial, e o usuário optou por seguir o documento.

**Provado por teste:** `AutorizacaoTest` verifica que o admin é negado em 14 escritas
clínicas (`prontuario.criar_*`, `prescricao.criar`, `medicamento.administrar`,
`triagem.classificar`, `atendimento.alterar_status`, `exame.executar`,
`exame.liberar_resultado`, entre outras) e mantém a leitura que a matriz prevê.

---

## D-21 · CI passa a rodar com service container MySQL

**Origem:** decisão do usuário no checkpoint da Fase 1 · **Status:** ✅ aplicada ·
**2026-08-18**

`.github/workflows/tests.yml` criava um banco SQLite. Substituído por um service
container `mysql:9` com healthcheck, `prsaude_test` como schema e as variáveis `DB_*` no
nível do job. `.env.example` também passou a apontar para MySQL.

**Motivo.** Fecha a divergência aberta em D-04/D-13: sem isso, o CI executaria os testes
de esquema contra um banco que não reproduz as 18 `CHECK`, a coluna gerada `ativo_key`
nem os `UNIQUE` da RN-20 — os testes falhariam por dialeto ou, pior, passariam por
vacuidade.

---

## D-22 · O Inertia compartilha um subconjunto do usuário, não o model

**Origem:** decisão de segurança tomada na Fase 2 · **Status:** ✅ aplicada ·
**2026-08-18**

`HandleInertiaRequests::share()` vinha do starter kit com `'user' => $request->user()`,
que serializa o model inteiro.

**Decisão.** Compartilhar apenas `id`, `name`, `email`, `tipo` e `senha_provisoria`,
mais `roles` e `permissoes` para a navegação por perfil.

**Motivo.** `users.login` é o **CPF** quando o usuário é paciente (RN-04). Serializar o
model completo colocaria CPF em texto claro no HTML inicial de toda página e em cada
resposta do Inertia — exatamente o vazamento silencioso que a doc §14.2 pede para evitar.

---

## D-23 · A equipe continua autenticando por e-mail, não por matrícula

**Origem:** escopo · **Status:** ⚠️ dívida registrada · **2026-08-18**

O `schema.sql` define `usuario.login` como "CPF do paciente, matrícula do profissional ou
código provisório". A tela de login da equipe, herdada do starter kit, continua usando
**e-mail + senha**.

**Motivo de não mudar agora.** A Fase 2 entrega a infraestrutura de autorização; o
cadastro de profissionais (que atribui matrícula) ainda não existe. Trocar o campo de
login antes de existir quem preencha matrícula deixaria o sistema sem forma de entrar.

**A resolver:** quando a gestão de usuários da equipe for construída, migrar o login para
`users.login`. O portal do paciente (Fase 11) já nasce usando `login` = CPF, conforme
RN-04 — não há dívida do lado do paciente.

---

## D-24 · A3 do UC-01: menor de idade mantém login no próprio CPF

**Origem:** lacuna do documento · **Status:** ✅ **decidida pelo usuário** ·
**2026-08-18**

> **Decisão do usuário: manter o login no próprio CPF do menor.** O responsável legal
> permanece registrado como contato (nome e telefone obrigatórios para menores de 18).
> Esta é uma divergência consciente do fluxo A3, registrada abaixo com o raciocínio que
> a sustenta.

O fluxo alternativo A3 do UC-01 diz: paciente menor de idade "exige dados do responsável
legal. **A credencial de acesso é emitida no CPF do responsável**".

**O que foi implementado.** A parte inequívoca: o cadastro de menor de 18 anos exige nome
e telefone do responsável legal (`contato_emergencia_nome` e
`contato_emergencia_telefone`), e o formulário rotula os campos como "responsável legal"
quando a data de nascimento indica menoridade.

**O que não foi, e por quê.** Emitir a credencial no CPF do responsável esbarra em dois
problemas que o documento não resolve:

1. **`users.login` é `unique`.** Se o responsável já é paciente da mesma unidade — o que
   é comum, uma mãe atendida junto com o filho — o login colidiria e o cadastro do menor
   falharia. O documento não define o desempate.
2. **Não há coluna para o CPF do responsável** no `schema.sql`. `paciente` tem
   `nome_mae`, `contato_emergencia_nome` e `contato_emergencia_telefone`, nenhum deles
   com CPF. Implementar exigiria acrescentar coluna, o que é divergência de esquema.

Há ainda uma terceira questão, de projeto: uma credencial única compartilhada entre mãe e
filho significa que o portal não consegue distinguir de quem é o dado exibido — e RN-26
exige que o paciente acesse exclusivamente os próprios dados.

**Opções para sua decisão:**

| Opção | Consequência |
|---|---|
| Menor mantém login no **próprio CPF** (comportamento atual), com o responsável apenas registrado como contato | Não requer mudança de esquema. O acesso ao portal, quando existir, é feito com o CPF do menor |
| Adicionar `paciente.responsavel_cpf` e um vínculo responsável → dependentes, com o portal permitindo alternar entre eles | Fiel à intenção do A3, resolve RN-26, mas é divergência de esquema e trabalho de Fase 11 |
| Menor sem CPF entra como identificação provisória vinculada ao responsável | Reaproveita RF-04, mas distorce o significado de "não identificado" |

**Opção escolhida: a primeira.** Além de não exigir mudança de esquema, ela é a única
que preserva RN-26 sem trabalho adicional — o portal sempre sabe de quem é o dado que
está exibindo, porque cada credencial pertence a uma única pessoa. A intenção do A3
(garantir que exista um adulto responsável identificado) fica atendida pela
obrigatoriedade do contato; o que não se implementa é o compartilhamento de credencial,
que criaria mais risco de sigilo do que resolve.

Se o portal do paciente (Fase 11) precisar de acesso do responsável aos dados do menor,
o caminho correto será um vínculo explícito responsável → dependentes, não um login
compartilhado.

---

## D-25 · `TokenPulseiraService` entregue na Fase 3, não na Fase 4

**Origem:** ordem de dependência · **Status:** ✅ aplicada · **2026-08-18**

O prompt aloca o `TokenPulseiraService` na Fase 4. Mas o passo 10c do UC-01 — escopo da
Fase 3 — exige que o cadastro "gere o token de pulseira" **dentro da mesma transação**.
Não havia como entregar a Fase 3 sem ele.

**Decisão.** Implementar o serviço completo agora, conforme a doc §8.2.1 (22 caracteres
base62 via `random_int` + 4 de HMAC-SHA256, validação com `hash_equals`, chave em
`config('app.pulseira_key')`), com os quatro testes da *definition of done* da Fase 4 já
escritos: 20.000 gerados sem colisão, caractere alterado rejeitado, token truncado
rejeitado, token de outra chave rejeitado.

**O que resta para a Fase 4:** QR Code (`endroid/qr-code`, versão 5, correção Q, 22 mm), o
template da pulseira 25 × 280 mm, o registro em `pulseira_impressao`, a rota
`GET /p/{token}` e o composable de leitura por câmera.

⚠️ **`PULSEIRA_KEY` nunca pode ser rotacionada em produção.** O corpo do token está
gravado em `paciente.token_pulseira`, mas o checksum é recalculado a cada validação: se a
chave mudar, todas as pulseiras já impressas passam a ser recusadas de uma vez. RN-03
exige que o token seja permanente, e uma pulseira que deixa de funcionar é risco
assistencial. A advertência está em `config/app.php` e no `.env.example`.

---

## D-26 · Contrato `GeradorTokenPulseira` extraído para tornar E1 testável

**Origem:** necessidade de teste · **Status:** ✅ aplicada · **2026-08-18**

E1 do UC-01 exige que a falha na geração do token produza **rollback integral** do
cadastro. Provar isso por teste requer injetar a falha — e `TokenPulseiraService` é
`final`, deliberadamente: ninguém deve poder sobrescrever a geração de um identificador
de segurança por herança.

**Decisão.** Extrair a interface `App\Contracts\GeradorTokenPulseira` (dois métodos),
implementada pelo serviço e ligada no `AppServiceProvider`. A implementação continua
fechada; a invariante fica testável.

É a única indireção desse tipo no projeto, e existe por uma razão nomeada — não por
princípio genérico de "programar para interfaces".

---

## D-27 · A ficha cadastral não exige vínculo assistencial

**Origem:** correção de erro de projeto detectado na Fase 3 · **Status:** ✅ aplicada ·
**2026-08-18**

A primeira versão da rota `pacientes.show` recebeu o middleware `vinculo` (RN-28). Estava
errado, e o erro só apareceu ao escrever o teste do fluxo da recepção.

**O problema.** RN-28 diz que "nenhum profissional acessa **prontuário** de paciente ao
qual não esteja vinculado". A ficha cadastral não é prontuário: é nome, documentos,
contato e alergias. E o passo 11 do UC-01 manda exibir exatamente essa ficha logo após o
cadastro, com as ações "Imprimir Pulseira" e "Novo Atendimento" — instante em que a
recepcionista não tem vínculo assistencial nenhum, porque o atendimento ainda não existe.
Exigir vínculo ali tornaria o próprio fluxo de cadastro impossível de concluir.

**Decisão.** `PacientePolicy::verFichaCadastral` exige apenas a permission
`paciente.ler`, e a rota mantém `auditar:paciente.ler`. O acesso é amplo e integralmente
auditado: o controle é a trilha, não a porta.

O middleware `vinculo` e a Policy `verContexto` continuam existindo, e entram nas rotas
de **prontuário**, na Fase 8 — que é onde RN-28 se aplica.

---

## D-28 · Conta administrativa inicial em seeder próprio

**Origem:** correção de um `User::create()` que quebrava o seed · **Status:** ✅ aplicada ·
**2026-08-18**

O `DatabaseSeeder` recebeu, fora desta sessão, um bloco que criava um usuário com
`bcrypt('password')` e sem `login` nem `tipo`. Ele **quebrava `migrate:fresh --seed`** —
que é justamente a *definition of done* da Fase 1 e da Fase 13.

**Os três defeitos, em ordem de quem estoura primeiro:**

1. **`bcrypt()` contra o driver Argon2id.** RNF-07 configura `hashing.driver = argon2id`,
   e o cast `hashed` do model chama `Hash::verifyConfiguration()`. Um hash `$2y$` pronto
   faz esse método lançar `RuntimeException`, e o seed inteiro morre.
2. **`login` e `tipo` são `NOT NULL`** desde a migration `2026_08_18_210000` (D-14).
   Mesmo que o hash passasse, o `INSERT` violaria a constraint.
3. **Senha fixa e permanente.** Uma credencial padrão conhecida que nunca expira é a
   porta dos fundos clássica de sistema hospitalar.

**Decisão.** A intenção era legítima e necessária — sem cadastro público (D-18), não há
como entrar no sistema após `migrate:fresh`. Em vez de remover, foi implementada
corretamente em `UsuarioAdministradorSeeder`: senha em texto claro (o cast aplica
Argon2id), `login` e `tipo` preenchidos, role `admin` sincronizada, e
**`senha_provisoria = true`** — o middleware `SenhaProvisoria` obriga a troca no primeiro
acesso (RN-06).

Credenciais parametrizadas por `ADMIN_LOGIN`, `ADMIN_EMAIL` e `ADMIN_SENHA`, documentadas
no `.env.example`. O seeder é idempotente (`updateOrCreate` por `login`).

**Lição operacional:** os testes não pegaram isso porque nenhum deles chama o
`DatabaseSeeder` — eles semeiam `RbacSeeder` e `ClassificacaoRiscoSeeder` diretamente, por
velocidade. `migrate:fresh --seed` precisa entrar no checklist de cada checkpoint, não só
no da fase que o menciona.

---

## D-29 · `endroid/qr-code` v6 mudou a API — o exemplo da doc §8.5 não compila

**Origem:** erro técnico do documento · **Status:** ✅ contornada · **2026-08-18**

A doc §8.5 traz o código de geração do QR Code na API fluente da v4/v5:

```php
Builder::create()->writer(new PngWriter())->data(...)->errorCorrectionLevel(...)->build();
```

A versão instalada é a **6.1.3**, onde `Builder` é `final readonly` com **construtor de
argumentos nomeados** — `Builder::create()` não existe mais. O código do documento daria
*fatal error*.

**Decisão.** Usar a API real da v6:

```php
new Builder(
    writer: new PngWriter,
    data: route('pulseira.resolver', $token),
    errorCorrectionLevel: ErrorCorrectionLevel::Quartile,
    size: 600,
    margin: 0,
    roundBlockSizeMode: RoundBlockSizeMode::Margin,
)->build();
```

Os **parâmetros de dimensionamento da doc §8.5 foram preservados integralmente**: ECC
nível Q (25%), margem controlada no template, 600 px de origem para 22 mm impressos.

A versão do QR não é fixada por parâmetro — o `endroid` escolhe a menor que acomode o
conteúdo. Com a URL de 48 caracteres e ECC Q isso dá a **versão 5 (37 × 37 módulos)**,
como o cálculo da doc previu. Há teste conferindo os 37 módulos: se o formato do token
ou a URL base crescerem, ele quebra e avisa **antes** de a pulseira sair errada da
impressora.

---

## D-30 · `portal.login` é placeholder até a Fase 11

**Origem:** ordem de dependência entre fases · **Status:** ⚠️ placeholder consciente ·
**2026-08-18**

O fluxograma da doc §8.3 redireciona para `portal.login` quando não há sessão, e os
middlewares `ExpirarSessao` e `SenhaProvisoria` também referenciam essa rota. Mas o
portal do paciente é a **Fase 11**, e o que o torna defensável são as mitigações M-1 a
M-12 da doc §12.2.3 — em especial **M-3**, que transforma a data de nascimento (15,3
bits) em dois fatores usando o token da pulseira (131 bits).

**Decisão.** A rota existe e renderiza uma página informativa que **não autentica
ninguém**. Preferiu-se isso a um formulário de login sem as mitigações, que seria pior
que não ter portal: criaria a expectativa de acesso protegido sobre um esquema
reconhecidamente fraco.

A Fase 11 substitui a página. O nome da rota já está correto, então nada além dela
precisa mudar.

---

## D-31 · A `PacienteFactory` passou a gerar token real

**Origem:** dívida da Fase 1 quitada · **Status:** ✅ aplicada · **2026-08-18**

A factory usava `Str::random(26)` como marcador, com o comentário "Fase 4 substitui por
TokenPulseiraService". Isso produzia tokens do tamanho certo mas **sem checksum HMAC
válido** — e a rota `/p/{token}` devolvia 404 para todo paciente de teste, porque o
passo 1 do fluxograma rejeita antes de consultar o banco.

Seis testes da Fase 4 falharam por essa causa única. A factory agora chama
`app(GeradorTokenPulseira::class)->gerar()`.

**O que isso ensina sobre o marcador:** um placeholder que produz dado com o *formato*
certo mas o *conteúdo* errado é pior que um que falha na hora — ele atravessa três fases
sem ser notado e só aparece quando alguém escreve o teste que depende do conteúdo.

---

## D-32 · HTTPS habilitado; a validação manual da Fase 4 destravou

**Origem:** decisão do usuário no checkpoint da Fase 4 · **Status:** ✅ aplicada ·
**2026-08-18**

`herd secure pr-saude` executado; `APP_URL` passou a `https://pr-saude.test` no `.env` e
no `.env.example`. Isso **fecha o D-09**, que registrava o risco assumido de não ter
contexto seguro.

**Verificado ao vivo, não só em teste:**

| Verificação | Resultado |
|---|---|
| Site responde em HTTPS | 200 em `/login` |
| Pulseira gerada com paciente, atendimento LARANJA e alergia | PDF de 884 KB |
| QR renderizado | versão 5, 37 × 37 módulos, três marcadores de posição |
| `GET /p/{token}` sem sessão | redireciona para `/portal/entrar` |
| Vazamento na resposta | **nenhum** — sem nome, sem CPF, sem o token |
| Token com checksum inválido | 404 |

**O que ainda depende de você:** apontar a câmera de um celular para o QR impresso. Isso
não é automatizável daqui. Os artefatos estão gerados e o HTTPS está no ar, então basta
abrir o PDF e escanear.

---

## D-33 · Imprimir pulseira exige registro profissional

**Origem:** defeito encontrado na validação manual · **Status:** ✅ corrigida ·
**2026-08-18**

A geração da pulseira de demonstração falhou com
`Column 'impressa_por' cannot be null` — violação de constraint do MySQL, não erro de
domínio.

**A causa.** `pulseira_impressao.impressa_por` é `NOT NULL` com FK para
`profissional (user_id)`, e a Action passava `$operador->profissional?->user_id`. O
administrador criado pelo `UsuarioAdministradorSeeder` é uma conta de TI **sem registro
profissional** — mas tem a permission `pulseira.imprimir` pela matriz da doc §2.3. Ou
seja: passava na autorização e morria no banco.

**Decisão.** O esquema está certo — quem imprime a pulseira precisa ser um profissional
identificável, porque "quem imprimiu" é parte da rastreabilidade (RF-15). A Action passou
a recusar antes, com `OperadorSemRegistroProfissionalException`, que diz o que fazer em
vez de expor uma mensagem do MySQL a quem está na recepção.

**O que isso revela sobre o método:** a suíte de 169 testes não pegou isso porque todos
os testes de impressão usam `Profissional::factory()`, que sempre cria o registro. O
caminho que quebra é o do usuário que o *seeder* cria. Rodar o sistema de verdade
encontrou em um minuto o que a suíte não encontraria — é exatamente para isso que a
*definition of done* da Fase 4 pede validação manual.

---

## D-34 · A numeração do atendimento é sequencial por ano, **global entre unidades**

**Origem:** contradição entre o prompt e o `schema.sql` · **Status:** ✅ **confirmada
pelo usuário** · **2026-08-18**

> **Decisão do usuário: manter a numeração global por ano.** Divergência consciente da
> letra do prompt, para preservar o `UNIQUE (numero)` do schema, o formato documentado e
> a não ambiguidade do número ditado entre setores.

O prompt (Fase 5) pede numeração "sequencial por ano **e unidade** (`2026-000148`)". Isso
é impossível de cumprir junto com o `schema.sql`, que declara:

```sql
UNIQUE KEY uk_atendimento_numero (numero)
```

O índice é **global**, não por unidade, e o formato documentado — `2026-000148` — não tem
nenhum componente que identifique a unidade. Contar por unidade faria a segunda UPA
colidir em `2026-000001` no primeiro atendimento do ano. O teste falhou exatamente assim
antes da correção.

**Decisão: sequencial por ano, global entre unidades.**

As três saídas possíveis, e por que esta:

| Saída | Problema |
|---|---|
| Contador por unidade, formato atual | Viola `uk_atendimento_numero`. Impossível |
| Contador por unidade, número prefixado com a unidade | Muda o formato documentado e alonga o número lido em voz alta |
| **Contador global por ano** (escolhida) | Diverge da letra do prompt, mas respeita o schema e o formato |

Há também um argumento de uso real a favor da escolha: o número é o identificador de
recuperação **quando o QR falha** (doc §8.4) e é ditado por telefone entre setores. Dois
episódios diferentes com `2026-000001` em unidades distintas — situação corriqueira para
um paciente transferido — seria pior que um contador por unidade.

**A alternativa descartada:** trocar `UNIQUE (numero)` por `UNIQUE (unidade_id, numero)`
tornaria o número ambíguo fora do contexto da unidade — e a pulseira imprime só o número
(doc §8.4), sem a unidade ao lado.

---

## D-35 · `permanencia_segundos` é truncada em zero quando o relógio anda para trás

**Origem:** defeito encontrado por teste · **Status:** ✅ corrigida · **2026-08-18**

`atendimento_status_historico.permanencia_segundos` é `INT UNSIGNED`. Um valor negativo
não é apenas errado: o MySQL recusa o `INSERT` inteiro com
`Out of range value for column`.

A referência do cálculo é a transição anterior. Ela pode ficar **no futuro** em relação
ao `now()` em três situações reais: desvio de relógio entre servidores de aplicação,
importação de registro com data retroativa, e correção manual de horário no servidor.

**Decisão.** `max(0, ...)`. Perder a transição de status por causa de um relógio seria
muito pior que gravar zero — o paciente ficaria preso no estado anterior, e a fila
pararia de refletir a realidade. O zero é visivelmente errado num indicador; o
atendimento travado é invisível até alguém reclamar.

---

## D-36 · `$dateFormat` com microssegundos nos models de coluna `DATETIME(6)`

**Origem:** defeito encontrado por teste · **Status:** ✅ corrigida · **2026-08-18**

O `schema.sql` declara nove colunas como `DATETIME(6)`, e a precisão de microssegundo
**não é decorativa**. Ela é o que desempata registros criados dentro do mesmo segundo:

| Coluna | O que a precisão sustenta |
|---|---|
| `fila_item.entrou_em` | RN-10 — desempate por ordem de chegada entre pacientes da mesma cor |
| `triagem.criado_em` | a sequência de reclassificações (doc §7.5) |
| `atendimento_status_historico.criado_em` | a ordem da linha do tempo (RF-22) |
| `registro_clinico.criado_em` | a ordem do encadeamento de hash (doc §9.4) |
| `administracao_medicamento.administrado_em` | a ordem das doses |

**O defeito.** O `$dateFormat` padrão do Eloquent é `'Y-m-d H:i:s'` — ele **trunca os
microssegundos na escrita**. Duas triagens criadas no mesmo segundo ficavam com
`criado_em` idêntico, e `sortByDesc('criado_em')` passava a devolver ordem indefinida.

O teste pegou isso pela cadeia de reclassificação: a triagem original aparecia antes da
reclassificação no histórico.

**A gravidade real é maior que o sintoma.** Numa fila com chegadas simultâneas — que é a
regra em pronto-socorro, não a exceção — o desempate da RN-10 deixaria de ser
determinístico. Dois pacientes verdes com o mesmo `entrou_em` seriam ordenados pelo que o
MySQL devolvesse primeiro, e a ordem poderia mudar entre duas leituras da mesma fila.

**Decisão.** `protected $dateFormat = 'Y-m-d H:i:s.u';` nos nove models. Todos têm
`$timestamps = false`, então a mudança não afeta `created_at`/`updated_at` de mais
ninguém. Colunas `DATETIME(0)` do mesmo model recebem o valor com fração e o MySQL
arredonda, sem erro.

---

## D-37 · Os catálogos são semeados uma vez por execução, não por teste

**Origem:** custo crescente da suíte · **Status:** ✅ aplicada · **2026-08-18**

Cada teste chamava `$this->seed(RbacSeeder::class)` no `beforeEach` — 8 roles, 42
permissions e as associações entre elas, cerca de **340 inserts por teste**. Com 216
testes, a suíte passou de 13 s (Fase 1) para 220 s (Fase 6), e faltavam sete fases.

**Decisão.** `CatalogoSeeder` — RBAC mais os cinco catálogos de domínio — declarado na
`Tests\TestCase` via `$seed` e `$seeder`.

**Por que isso funciona sem vazar estado entre testes.** O `RefreshDatabase` roda
`migrate:fresh --seed` **uma única vez por execução** (guardado por
`RefreshDatabaseState::$migrated`) e só depois abre a transação de cada teste. Os
catálogos ficam abaixo do ponto de rollback: visíveis a todos, recriados por nenhum. O
que cada teste escreve continua sendo revertido normalmente.

**`CatalogoSeeder` e não `DatabaseSeeder`:** o segundo cria a conta administrativa, e um
teste que conta usuários não deve depender de quem o seeder de ambiente criou. A
separação também deixa explícito o que é "carga sem a qual o sistema não funciona" e o
que é "conta para conseguir entrar".

---

## D-38 · A ponderação de carga é linear, e a própria doc reconhece o limite

**Origem:** limitação apontada na doc §7.4 · **Status:** ⚠️ registrada para calibração ·
**2026-08-18**

A carga ponderada usa `Σ (6 − peso_ordenacao)`: vermelho 5, laranja 4, amarelo 3,
verde 2, azul 1. A implementação segue a view `vw_carga_profissional` do `schema.sql`,
sem alteração — o teste confirma os 11 do exemplo da doc (1 laranja + 1 amarelo +
2 verdes).

**O limite, que a própria doc §7.4 explicita:** cinco pacientes azuis somam carga 5 —
exatamente a carga de **um** vermelho. Isso subestima o esforço de um caso crítico, que
na prática consome mais que cinco atendimentos leves.

Uma ponderação exponencial (`2^(5−peso)`: azul 1, verde 2, amarelo 4, laranja 8,
vermelho 16) refletiria melhor a realidade assistencial. A escala linear foi mantida por
ser legível ao usuário — a carga aparece na tela e precisa ser interpretável por quem
distribui os pacientes.

**A troca é de uma linha na view.** Fica como ponto de calibração para validação com a
equipe do hospital, não como dívida técnica.

---

## D-39 · A estimativa de espera tem uma cascata de fallback explícita

**Origem:** decisão de implementação · **Status:** ✅ aplicada · **2026-08-18**

A doc §7.4 pede "média histórica de duração de atendimento do próprio profissional por
cor — uma média móvel dos últimos 30 dias, não uma constante", mas não diz o que fazer
quando não há histórico. Numa instalação nova, isso é o caso de **todos** os
profissionais.

**Decisão.** Cascata em três níveis: (1) média do próprio profissional para aquela cor
nos últimos 30 dias; (2) média da instituição para aquela cor; (3) **20 minutos**, um
número redondo e visivelmente aproximado.

O padrão é deliberadamente grosseiro. Uma constante com aparência de precisão — "17,4
min" — esconderia a ausência de dado atrás de uma falsa exatidão, e a doc é explícita
sobre a estimativa precisar ser honesta.

**A duração vem de `fila_item`** (`chamado_em` → `saiu_em`), não do atendimento inteiro:
é o tempo que aquele profissional gastou com aquele paciente, e não o tempo de
permanência que inclui exame, medicação e observação.

---

## D-40 · A forma canônica do hash normaliza data e enum antes de calcular

**Origem:** correção de erro no código da doc §9.4 · **Status:** ✅ aplicada ·
**2026-08-20**

O `HashEncadeadoService` da doc §9.4 calcula a verificação com
`$this->calcular($r->toArray())`. Isso não funciona, e o modo como falha é o pior
possível: **acusa adulteração em todo registro íntegro**.

Duas causas:

1. `toArray()` aplica os casts. `criado_em` sai em ISO-8601 (`2026-08-20T13:04:11.000000Z`)
   e `tipo` sai como valor do enum — formas diferentes das usadas na criação, quando
   `criado_em` ainda é um Carbon.
2. `(string) $dados['criado_em']` sobre um Carbon devolve `Y-m-d H:i:s`, **sem
   microssegundos** — enquanto a coluna é `DATETIME(6)` e o valor lido do banco os traz.

**Decisão.** A forma canônica normaliza explicitamente: data sempre reformatada como
`Y-m-d H:i:s.u`, enum sempre reduzido a `->value`, ids sempre convertidos a `int`. E a
verificação lê `getAttributes()` — os atributos crus — em vez de `toArray()` ou
`getRawOriginal()`; o primeiro aplica casts, o segundo está vazio em model ainda não
persistido, o que impediria calcular o hash antes de gravar.

**Por que isso importa mais do que parece.** Um detector que alarma sempre é um detector
desligado: depois da terceira falsa quebra, ninguém mais olha o relatório — e a
adulteração real passa junto com o ruído. A `RegistroClinicoFactory` também passou a
fechar a cadeia de verdade, pelo mesmo motivo.

---

## D-41 · O `hash_anterior` esperado é o gravado, nunca o recalculado

**Origem:** decisão de implementação · **Status:** ✅ aplicada · **2026-08-20**

Em `verificarCadeia()`, depois de conferir um registro, o elo esperado do próximo é o
`hash_conteudo` **gravado** naquele registro — não o que acabou de ser recalculado.

A alternativa mascararia exatamente o ataque que a cadeia existe para detectar: um
registro adulterado viraria o novo "esperado", e a cadeia pareceria íntegra de lá para a
frente. A adulteração apareceria como uma quebra isolada em vez de um ponto a partir do
qual tudo é suspeito.

O teste `a adulteração não é mascarada nos registros seguintes` fixa esse comportamento.

---

## D-42 · `ExigirVinculoAssistencial` passou a resolver o paciente também pelo atendimento

**Origem:** lacuna encontrada ao implementar a Fase 8 · **Status:** ✅ aplicada ·
**2026-08-20**

O middleware `vinculo` (RN-28, *break the glass*) resolvia apenas `{paciente}` na rota.
Até a Fase 7 isso bastava, porque as rotas clínicas eram todas do paciente.

A Fase 8 introduz `atendimentos/{atendimento}/prontuario` — **a rota que o plantão usa o
dia inteiro**. Sem a mudança, ela ficaria inteiramente fora do break the glass: qualquer
profissional autenticado leria o prontuário de qualquer paciente sem justificativa e sem
o registro de quebra de sigilo, bastando conhecer o id do atendimento.

**Decisão.** `pacienteDaRota()` resolve `{paciente}` diretamente ou `{atendimento}` pela
relação. Uma única implementação de RN-28, em vez de uma segunda verificação no
controller que a próxima rota esqueceria.

---

## D-43 · O diagnóstico não é retificado por adendo

**Origem:** lacuna do documento · **Status:** ✅ aplicada · **2026-08-20**

RN-16 e o mecanismo de adendo valem para `registro_clinico`. O documento não diz o que
acontece quando um diagnóstico registrado se mostra errado.

**Decisão.** `diagnostico` não é registro clínico e não tem cadeia de hash. A revisão de
hipótese é um **diagnóstico novo**, com a natureza correspondente; o anterior permanece,
porque é ele que explica as condutas tomadas enquanto valia.

Duas regras de consistência foram acrescentadas, e ambas são interpretação — não estão
no documento:

- **Uma suspeita não pode ser o diagnóstico principal.** Marcar como principal uma
  hipótese ainda em aberto transformaria dúvida em afirmação na estatística e no
  faturamento.
- **Um principal por atendimento.** É ele que responde "por que este paciente esteve
  aqui"; dois tornam a resposta ambígua.

⚠️ **Precisa de validação clínica.** Se o hospital exigir que o principal possa ser
transferido de um CID para outro sem novo registro, a segunda regra muda.

---

## D-44 · O sigilo do original acompanha o adendo

**Origem:** lacuna do documento · **Status:** ✅ aplicada · **2026-08-20**

A doc §9.6 define `sigiloso` e a §9.3 define o adendo, mas não diz o que acontece quando
se retifica um registro sigiloso.

**Decisão.** O adendo herda o `sigiloso` do original. A retificação não é o caminho para
tornar visível no portal um registro que o médico decidiu não exibir — se fosse, o campo
seria contornável por qualquer um que pudesse retificar, e a decisão clínica de não
expor uma suspeita grave ainda não comunicada seria desfeita por acidente.

Tornar visível continua possível: é uma decisão explícita, e não efeito colateral de
uma correção de texto.

---

## D-45 · Vigência tolera o arredondamento de `DATETIME(0)`

**Origem:** defeito encontrado na Fase 9 · **Status:** ✅ aplicada · **2026-08-20**

`prescricao.vigencia_inicio` é `DATETIME` sem precisão fracionária, mas `now()` traz
microssegundos. O MySQL pode arredondar a fração ao segundo seguinte; nesse intervalo,
`isFuture()` classificava como inválida uma prescrição que acabara de ser criada.

**Decisão.** A RN-19 considera futura somente a vigência mais de um segundo à frente do
relógio do servidor. Isso absorve apenas a perda de precisão do tipo e não amplia de modo
clinicamente relevante a janela da ordem.

---

## D-46 · Lote, validade e orientação ficam na observação estruturada

**Origem:** conflito entre mockup e schema · **Status:** ✅ aplicada · **2026-08-20**

O mockup dos nove certos (§10.3) manda registrar conferência de lote, validade e
orientação. A fonte normativa do banco não oferece colunas para esses três dados, e o
plano proíbe divergir do `schema.sql` silenciosamente.

**Decisão.** O FormRequest valida as conferências no servidor e o controller as compõe
em linhas identificadas de `administracao_medicamento.observacao`. Preserva a evidência
sem inventar colunas. Se o hospital precisar consultar lote/validade de forma agregada
(por exemplo, recall), isso exige evolução explícita do modelo, não texto livre.
