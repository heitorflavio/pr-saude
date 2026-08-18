# PROGRESSO — Sistema de Gestão Hospitalar

Estado real das 13 fases. Atualizado ao fim de cada fase, com o que foi entregue, os
testes que passam e as pendências.

**Legenda:** ✅ concluída · 🔄 em andamento · ⏳ bloqueada · ⬜ não iniciada

| # | Fase | Estado |
|---|---|---|
| 0 | Inspeção do projeto | ✅ |
| 1 | Fundação do banco | ✅ |
| 2 | Autenticação e autorização | ✅ |
| 3 | Cadastro de paciente | ⬜ próxima |
| 4 | Token, QR Code e pulseira | ⬜ |
| 5 | Atendimento e máquina de estados | ⬜ |
| 6 | Triagem e classificação de risco | ⬜ |
| 7 | Fila e painel do profissional | ⬜ |
| 8 | Prontuário e evolução | ⬜ |
| 9 | Medicamentos | ⬜ |
| 10 | Clínica e exames | ⬜ |
| 11 | Portal do paciente | ⬜ |
| 12 | Auditoria e indicadores | ⬜ |
| 13 | Fechamento | ⬜ |

**Testes:** `php artisan test` → **107 passando, 720 asserções** (~90 s, MySQL).

---

## ✅ Fase 0 — Inspeção do projeto (2026-08-18)

### Levantamento

| Item | Valor |
|---|---|
| Laravel | 12.67.0 |
| PHP | 8.2.31 no levantamento → **elevado para 8.4.22** (D-06) |
| Banco encontrado | SQLite 3.51.3 → **migrado para MySQL 9.6.0** (D-04) |
| Inertia | `inertiajs/inertia-laravel` 2.0.25 + `@inertiajs/vue3` 2.0.3 |
| Vue / TS / Vite | 3.5.13 / 5.7.3 / 6.1.1 |
| Tailwind | 3.4.17 (v3) |
| shadcn-vue | configurado; 16 componentes em `resources/js/components/ui/` |
| Runner de testes | Pest 3.8.7 |
| Argon2id (RNF-07) | ✅ disponível |

Estado inicial: 3 migrations, 1 model (`User`), 4 arquivos de rota, 9 páginas Inertia,
20 componentes + 16 do shadcn, 12 arquivos de teste.

### Entregue

- `CLAUDE.md`, `docs/DECISOES.md`, `docs/PROGRESSO.md`.
- **PHP elevado para 8.4.22** via `herd isolate 8.4`; `composer.json` com `"php": "^8.4"`.
- **Hook quebrado do Laravel Boost removido** do `post-update-cmd` (D-07).

### Erros encontrados no prompt e no repositório

1. **§3.2 sobre o spatie v8** — descrito como compatível, mas exige `php ^8.3` contra os
   8.2.31 do projeto. Resolvido elevando o PHP (D-06).
2. **§3.1 menciona 2FA** a preservar — não existe neste starter kit (D-11).
3. **`verificacao/` ausente** — os três scripts de verificação não vieram com os docs
   (D-08).
4. **`post-update-cmd` chamava `boost:update`** sem o pacote instalado (D-07).

---

## ✅ Fase 1 — Fundação do banco (2026-08-18)

### Banco

MySQL **Community 9.6.0** em `127.0.0.1:3306`. Schemas `prsaude` e `prsaude_test`, ambos
`utf8mb4 / utf8mb4_0900_ai_ci`.

> ⚠️ O servidor hospeda outros bancos do usuário. Todo trabalho é escopado em `prsaude`
> e `prsaude_test`; `migrate:fresh` nunca deve rodar apontado para outro schema (D-04).

### Entregue

**31 migrations**, na ordem de dependência das FKs:

- `extend_users_table_for_sgh` — 8 colunas novas, `email` nullable, `SoftDeletes`,
  índice `(tipo, ativo)` (D-01, D-02, D-14).
- **29 tabelas de domínio** reproduzidas do `schema.sql`, com tipos, `NOT NULL`, `ENUM`,
  FKs, índices e comentários de coluna preservados.
- **3 views** (`vw_fila_ordenada`, `vw_carga_profissional`, `vw_doses_pendentes`).
- `spatie/laravel-permission` **8.3.0** instalado e publicado.

**5 enums** em `app/Enums/`: `StatusAtendimento` (com `transicoesPermitidas()`,
`podeTransitarPara()`, `ehTerminal()`, `rotulo()`, `rotuloPaciente()`), `CorPrioridade`,
`ViaAdministracao`, `TipoRegistroClinico`, `SituacaoEspera`.

**2 exceções de domínio**: `DominioException` (base abstrata) e
`RegistroImutavelException`.

**30 models** (29 de domínio + `User`), com `$fillable`, `casts` de enum/data/decimal,
relações completas e `SoftDeletes` onde há `deleted_at`. Destaques:

- `RegistroClinico` com `save()`, `delete()` e `forceDelete()` sobrescritos.
- `Paciente` com `idade` derivada e `idadeDescritiva()` de granularidade adaptativa
  (dias → meses → anos), sem nenhuma coluna `idade`.
- `Atendimento` com `ativo_key` deliberadamente fora de `$fillable`.

**5 seeders de catálogo**: 5 níveis de Manchester, **59 códigos CID-10**, **32 queixas**
com fluxograma, **33 medicamentos** (8 de alta vigilância: insulina, heparina, morfina,
cloreto de potássio, fentanila, midazolam, noradrenalina, adrenalina) e **24 exames**.

**26 factories**, com os *states* pedidos — `Paciente::factory()->comAlergia()`,
`->comAtendimentoAtivo()`, `->comSenhaProvisoria()` — além de `naoIdentificado()`,
`recemNascido()`, `Atendimento::finalizado()`, `Profissional::enfermeiro()`,
`Medicamento::altaVigilancia()`, `RegistroClinico::adendoDe()` e outros. Inclui
`Support\GeradorCpf`, que produz CPF com dígito verificador válido.

### Definition of done

| Critério | Estado |
|---|---|
| `php artisan migrate:fresh --seed` roda limpo | ✅ |
| Os 14 testes negativos + 2 positivos da doc §5.4 portados para Pest | ✅ 16/16 |
| 30 tabelas de domínio + 5 do spatie | ✅ verificado |

Verificação do esquema contra a especificação:

| Objeto | Esperado | Encontrado |
|---|---|---|
| Tabelas de domínio | 30 | **30** |
| Tabelas do spatie | 5 | **5** |
| Restrições `CHECK` | 18 | **18** |
| Views | 3 | **3** |
| Coluna gerada `STORED` | 1 | **1** (`atendimento.ativo_key`) |
| Chaves estrangeiras | 63 de domínio + spatie | **67** |
| `uk_atendimento_ativo` | presente | **presente** |

### Testes

`php artisan test` → **48 passando, 102 asserções**.

`tests/Feature/Sgh/EsquemaTest.php` cobre, além dos 16 casos da doc §5.4, cinco
verificações de invariante:

- `ativo_key` vale o `paciente_id` com o atendimento aberto e `NULL` após
  `FINALIZADO`/`CANCELADO` (invariante 4);
- nenhuma tabela do schema possui coluna `idade` (invariante 1);
- `UPDATE` e `DELETE` em `registro_clinico` lançam `RegistroImutavelException` e o
  conteúdo permanece intacto no banco (invariante 2);
- o adendo legítimo é aceito e o original continua marcado como retificado;
- `FINALIZADO` e `CANCELADO` são terminais no enum (invariante 8).

### Decisões tomadas nesta fase

| Decisão | Registro |
|---|---|
| Suíte de testes movida de SQLite in-memory para MySQL `prsaude_test` | D-13 |
| `users.login` e `users.tipo` nascem `nullable`, a apertar na Fase 2 | D-14 |
| `users` com `SoftDeletes`; teste do starter kit ajustado para exclusão lógica | D-15 |
| `auditoria_log.usuario_id` renomeada para `user_id` | D-16 |
| Testes de esquema escrevem direto no banco, não via Action | D-17 |

### Pendências que a Fase 1 deixa em aberto

1. ~~CI ainda usa SQLite~~ — **resolvido na Fase 2** (D-21).
2. ~~`users.login` e `users.tipo` nullable~~ — **resolvido na Fase 2** (D-14 fechada).
3. ~~Rota de auto-exclusão de conta~~ — **removida na Fase 2** (D-18).
4. **`docs/privilegios.sql`** (ainda aberto) — `REVOKE UPDATE, DELETE` nas tabelas append-only. O
   prompt aloca na Fase 13; as tabelas alvo já existem desde agora.

---

## ✅ Fase 2 — Autenticação e autorização (2026-08-18)

### Entregue

**Guards e sessão**

- `config/auth.php`: guards `web` (equipe) e `paciente`, com providers `profissionais` e
  `pacientes` sobre o mesmo model `User` (D-02). Brokers de senha separados, 60 min para
  a equipe e 30 para o paciente.
- Middleware `ExpirarSessao`: 30 min para a equipe, 15 para o paciente (RNF-09/RNF-10).
  O Laravel tem um único `session.lifetime` — a janela por guard é carimbada na sessão e
  verificada a cada request (D-12).

**Hashing**

- `config/hashing.php` com `driver = argon2id` (RNF-07), 64 MiB / 4 iterações — acima do
  piso do PHP e dentro da faixa recomendada pelo OWASP. A suíte usa o mínimo do PHP para
  não pagar ~50 ms por usuário criado.

**Autorização em duas camadas**

- `RbacSeeder`: **8 roles** e **42 permissions**, transcrevendo a matriz da doc §2.3
  célula por célula, todas com `guard_name = 'web'`.
- **6 Policies** registradas explicitamente em `AuthServiceProvider`: `PacientePolicy`
  (`verContexto`, `verMinimoVital`, `quebrarSigilo`, `imprimirPulseira`),
  `AtendimentoPolicy` (RN-12 + a nota ¹ do laboratório), `RegistroClinicoPolicy`,
  `PrescricaoPolicy`, `AdministracaoPolicy` (RN-22), `ExameResultadoPolicy` (RN-24/RN-25).
- `Gate::before` libera o `admin` — **exceto** `prontuario.quebra_sigilo`, coberta pelos
  dois nomes por que é consultada (permission e método da Policy). Ver a ressalva em D-20.

**Middlewares**

- `SenhaProvisoria` (RN-06), `ExpirarSessao` (RNF-09/10) — globais no grupo `web`.
- `auditar` → `RegistrarAuditoria` e `vinculo` → `ExigirVinculoAssistencial` (RN-28),
  como aliases para uso por rota.

**Auditoria**

- `AuditoriaService` (doc §14.3): registra leitura e escrita, grava o snapshot das roles
  no instante do evento e mascara `password`, `token_pulseira`, `cpf` e `cns` — inclusive
  em estruturas aninhadas.

**Interface**

- `HandleInertiaRequests` compartilha `roles` e `permissoes`, e um **subconjunto** do
  usuário em vez do model inteiro — `users.login` é o CPF do paciente (D-22).
- Composable `usePermissoes` e `AppSidebar` com navegação filtrada por perfil: cada
  usuário vê só o que pode acessar.
- Tela `auth/SenhaProvisoria.vue`.

**Fechamento de dívidas**

- `users.login` e `users.tipo` passaram a **`NOT NULL`** (fecha D-14).
- Removidos o cadastro público e a auto-exclusão de conta (D-18).
- CI passou a rodar com service container MySQL (D-21, fecha a pendência de D-04/D-13).

### Definition of done

| Critério | Estado |
|---|---|
| Teste percorrendo a matriz RBAC, com os negativos | ✅ 8 roles × 42 permissions |
| Teste provando que o guard `paciente` recebe `false` em qualquer `can()` | ✅ |

### Testes

`php artisan test` → **107 passando, 720 asserções**.

- `AutorizacaoTest` (20 testes, 537 asserções) — cada role é verificada contra a matriz
  **inteira**: o que ela tem e, principalmente, tudo o que ela não tem. Inclui as
  negativas nomeadas (recepção sem escrita clínica, técnico sem prescrever, laboratório
  sem prontuário, auditor só com a trilha) e as três garantias de RN-27.
- `PolicyTest` (19 testes) — vínculo assistencial pelas quatro origens, mínimo vital
  liberado sem vínculo mas negado fora de plantão, RN-12, a restrição do laboratório às
  transições de exame, dupla checagem recusando o próprio executor, RN-25.
- `AuditoriaTest` (8 testes) — mascaramento raso e aninhado, snapshot de perfis, log de
  leitura, e a consulta "quem acessou os dados deste paciente nos últimos 90 dias?".
- `AutenticacaoTest` (14 testes) — Argon2id no hash real, expiração de sessão com
  `travel()`, RN-06 em quatro cenários, ausência da rota de cadastro, `login`/`tipo`
  obrigatórios e únicos.

### Decisões tomadas nesta fase

| Decisão | Registro |
|---|---|
| Removidos cadastro público e auto-exclusão de conta | D-18 |
| `prontuario.criar` dividida em nota médica / evolução de enfermagem | D-19 |
| `Gate::before` dá ao admin mais poder que a matriz — **ressalva registrada** | D-20 |
| CI com service container MySQL | D-21 |
| Inertia compartilha subconjunto do usuário, não o model | D-22 |
| Equipe segue autenticando por e-mail, não por matrícula | D-23 |

### Pendências que a Fase 2 deixa em aberto

1. **`Gate::before` permite ao admin escrever no prontuário** (D-20). Implementado como o
   prompt §5 pede, mas contraria a intenção da matriz da doc §2.3, que dá ao admin apenas
   leitura nas linhas clínicas. **Recomendo restringir o atalho a permissões
   administrativas — requer sua decisão.**
2. **Login da equipe ainda é por e-mail** (D-23), não por `users.login`. Resolver quando
   existir a gestão de usuários da equipe.
3. **A suíte leva ~90 s**, dominada pelo `RbacSeeder` rodando a cada teste (42 permissions
   × 8 roles). Otimizável com um trait que semeie uma vez por classe.
4. `portal.login` e `portal.senha`, referenciados pelos middlewares, só existem na
   Fase 11. Hoje são inalcançáveis (nenhum paciente consegue autenticar).

---

## ⬜ Fase 3 — Cadastro de paciente

Escopo previsto:

- [ ] `CadastrarPacienteAction` — em uma transação: `User` + `Paciente` + credencial
      (login = CPF, senha = `DDMMAAAA`, `senha_provisoria = true`) + `token_pulseira` +
      auditoria (RN-04, RN-05)
- [ ] Validação de CPF com dígito verificador (regra customizada, não regex)
- [ ] Cadastro sem CPF: `codigo_provisorio` no formato `NI-2026-0031` (RF-04)
- [ ] `RegularizarIdentificacaoAction` preservando o histórico (RN-30)
- [ ] Idade derivada com granularidade adaptativa (D-01) — já implementada em `Paciente`,
      falta o teste das quatro datas-limite
- [ ] Alergias e condições crônicas em destaque em toda tela (RF-11)
- [ ] Busca por nome, CPF, CNS, data de nascimento e token (RF-09)
- [ ] Páginas `Pacientes/Index`, `Pacientes/Create`, `Pacientes/Show`
