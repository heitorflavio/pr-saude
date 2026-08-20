# PROGRESSO — Sistema de Gestão Hospitalar

Estado real das 13 fases. Atualizado ao fim de cada fase, com o que foi entregue, os
testes que passam e as pendências.

**Legenda:** ✅ concluída · 🔄 em andamento · ⏳ bloqueada · ⬜ não iniciada

| # | Fase | Estado |
|---|---|---|
| 0 | Inspeção do projeto | ✅ |
| 1 | Fundação do banco | ✅ |
| 2 | Autenticação e autorização | ✅ |
| 3 | Cadastro de paciente | ✅ |
| 4 | Token, QR Code e pulseira | ✅ |
| 5 | Atendimento e máquina de estados | ✅ |
| 6 | Triagem e classificação de risco | ✅ |
| 7 | Fila e painel do profissional | ✅ |
| 8 | Prontuário e evolução | ✅ |
| 9 | Medicamentos | ✅ |
| 10 | Clínica e exames | ✅ |
| 11 | Portal do paciente | ✅ |
| 12 | Auditoria e indicadores | ✅ |
| 13 | Fechamento | ✅ |

**Testes:** `php artisan test` → **319 passando, 1753 asserções** (MySQL).
**Cobertura do núcleo:** Actions + Services + Enums → **93,0%**.

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
- `Gate::before` libera irrestritamente toda conta com `users.tipo = ADMIN`, inclusive
  nas abilities contextuais. A matriz configurável das roles permanece fiel à doc §2.3
  e as invariantes do domínio e do banco continuam obrigatórias (D-20, revisada).

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
| `Gate::before` irrestrito para contas do tipo `ADMIN` | D-20 |
| CI com service container MySQL | D-21 |
| Inertia compartilha subconjunto do usuário, não o model | D-22 |
| Equipe segue autenticando por e-mail, não por matrícula | D-23 |

### Pendências que a Fase 2 deixa em aberto

1. O alcance do `Gate::before` foi revisado em 2026-08-20: por decisão do usuário, toda
   conta do tipo `ADMIN` tem acesso irrestrito às funcionalidades (D-20).
2. **Login da equipe ainda é por e-mail** (D-23), não por `users.login`. Resolver quando
   existir a gestão de usuários da equipe.
3. **A suíte leva ~90 s**, dominada pelo `RbacSeeder` rodando a cada teste (42 permissions
   × 8 roles). Otimizável com um trait que semeie uma vez por classe.
4. `portal.login` e `portal.senha`, referenciados pelos middlewares, só existem na
   Fase 11. Hoje são inalcançáveis (nenhum paciente consegue autenticar).

---

## ✅ Fase 3 — Cadastro de paciente (2026-08-18)

UC-01 completo, com os fluxos alternativos A1, A2, A4 e A5 e as exceções E1 e E2. A3
ficou parcial — ver pendências.

### Entregue

**Domínio**

- `CadastrarPacienteAction` — em **uma transação**: `User` + `Paciente` + credencial
  (login = CPF, senha = `DDMMAAAA` da data de nascimento, `senha_provisoria = true`) +
  `token_pulseira` + alergias + condições + auditoria + evento (RN-04, RN-05, RN-06).
- `RegularizarIdentificacaoAction` (RN-30) — vincula o CPF real **preservando o
  histórico**: mesmo `user_id`, mesmo token, mesmo prontuário.
- `TokenPulseiraService` conforme doc §8.2.1, com o contrato `GeradorTokenPulseira`
  (D-25, D-26).
- `GeradorCodigoProvisorioService` — `NI-2026-0031`, sequencial por ano, com
  `lockForUpdate` (RF-04).
- Regra `App\Rules\Cpf` — dígito verificador por módulo 11, **não regex**, recusando
  também os CPFs de dígitos repetidos que passam no cálculo.
- Exceções nomeadas: `PacienteJaCadastradoException` (carrega o paciente existente),
  `TokenPulseiraIndisponivelException`, `RegularizacaoInvalidaException`.
- Eventos `PacienteCadastrado` e `IdentificacaoRegularizada`.

**Interface**

- `PacienteController` (index, create, store, show, regularizar) — nenhuma escrita fora
  de Action.
- `CadastrarPacienteRequest` e `RegularizarIdentificacaoRequest`, com A4 (dígito
  verificador), A5 (data futura / idade > 130) e A3 parcial (responsável para menor).
- Busca por nome, CPF, CNS, data de nascimento (dois formatos), código provisório e
  token de pulseira (RF-09) — o token é validado por checksum **antes** de virar
  `SELECT`.
- Páginas `Pacientes/Index`, `Pacientes/Create`, `Pacientes/Show`.
- `PainelAlergias` e `BadgeAlergia` (RF-11, RNF-15): cor + rótulo + ícone, nunca só a
  cor. A ausência de alergia também é exibida explicitamente — tela vazia poderia
  significar apenas que ninguém perguntou.

### Definition of done

| Critério | Estado |
|---|---|
| CPF inválido recusado | ✅ |
| CPF duplicado oferece o cadastro existente em vez de duplicar | ✅ |
| Cadastro sem CPF funciona | ✅ |
| A credencial é criada na mesma transação | ✅ |
| Falha na geração do token faz rollback e não deixa paciente órfão | ✅ |
| Idade: véspera, dia do aniversário, 29/02, recém-nascido | ✅ 9 testes |

### Testes

`php artisan test` → **151 passando, 819 asserções**.

- `CadastroPacienteTest` (23) — fluxo principal, A1, A2, A4, A5, A3 parcial, E1, E2,
  RN-30, RF-09 e autorização das rotas.
- `IdadeDerivadaTest` (9) — os quatro casos exigidos, mais as fronteiras de 30 dias e
  24 meses, e a idade em data de referência (a "idade congelada" da pulseira, doc §8.4).
- `TokenPulseiraTest` (9) — os quatro casos da DoD da Fase 4, mais opacidade e não
  exposição na serialização.

### Bugs corrigidos nesta fase

1. **`$paciente->idade` lançaria `TypeError`**: `diffInYears` devolve `float` no Carbon 3
   e o atributo declara `?int` num arquivo com `strict_types=1`. Corrigido com cast
   explícito, que também é a truncagem correta.
2. **`Carbon::createFromFormat` lança exceção** no Carbon 3 em vez de devolver `false` —
   a busca por data quebraria com termo não-data. Envolvido em `rescue()`.
3. **`vinculo` na ficha cadastral** impediria a recepcionista de ver a ficha que acabou
   de criar (D-27).
4. **`migrate:fresh --seed` estava quebrado** por um `User::create()` com `bcrypt()` e sem
   `login`/`tipo` no `DatabaseSeeder` — alteração vinda de fora desta sessão. Substituído
   por `UsuarioAdministradorSeeder`, que faz a mesma coisa corretamente (D-28). Os testes
   não pegaram porque nenhum deles chama o `DatabaseSeeder`.

### Decisões tomadas nesta fase

| Decisão | Registro |
|---|---|
| A3 (credencial de menor no CPF do responsável) **não** implementada | D-24 — **requer decisão** |
| `TokenPulseiraService` entregue aqui, não na Fase 4 | D-25 |
| Contrato `GeradorTokenPulseira` para tornar E1 testável | D-26 |
| Ficha cadastral não exige vínculo assistencial | D-27 |

### Pendências

1. **A3 do UC-01** (D-24): menores são cadastrados com o próprio CPF. Emitir a credencial
   no CPF do responsável exigiria coluna nova e uma política de desempate de `login`
   único. **Requer sua decisão.**
2. **A suíte passou de 130 s** — o `RbacSeeder` roda a cada teste. Vai piorar a cada fase.
3. `PULSEIRA_KEY` está no `.env` local; a advertência de não rotacionar está em
   `config/app.php` e no `.env.example`, mas ainda não há checklist de implantação
   (Fase 13).
4. **`migrate:fresh --seed` precisa entrar no checklist de todo checkpoint** (D-28), não
   só no da fase que o menciona — a suíte não o exercita.
5. **`php` do Herd deixou de auto-detectar a isolação** entre sessões: `php -v` na pasta
   devolve 8.2.31 enquanto o site segue em 8.4. Contorno: usar `herd php artisan ...`.

---

## ✅ Fase 4 — Token, QR Code e pulseira (2026-08-18)

O `TokenPulseiraService` já viera na Fase 3 (D-25); esta fase entregou o resto.

### Entregue

- `GerarPulseiraService` — serviço **puro**, sem escrita: renderiza o QR e o PDF. QR na
  **versão 5, correção Q**, 600 px de origem para 22 mm impressos (doc §8.5).
- `ImprimirPulseiraAction` — o write: registra `pulseira_impressao` com motivo (RF-15),
  audita e emite `PulseiraImpressa`. A reimpressão usa **o mesmo token** (RF-16, RN-03).
- Template `pulseira.termica` 25 × 280 mm com as quatro decisões de layout da doc §8.4:
  faixa de cor na borda superior inteira, nome na maior fonte, idade congelada ao lado da
  data de nascimento, e faixa de alergia na última linha com marcação redundante.
  **Não imprime CPF, CNS, CID nem endereço** — e há teste provando cada ausência.
- `PulseiraController::resolver` — o fluxograma da doc §8.3 **na ordem exata**. A ordem
  dos passos *é* o controle: validar o checksum antes do banco, e redirecionar antes da
  consulta, é o que impede a rota de virar oráculo de enumeração.
- `useQrScanner` com Barcode Detection API nativa + ponyfill `barcode-detector` 3.2.2, e
  `LeitorPulseira` com os dois modos de falha reais (permissão negada, sem câmera) e
  busca manual sempre disponível — o profissional não pode travar porque a câmera falhou.
- Confirmação de identidade em duas etapas (RF-44): o leitor **nunca** age direto; ele
  resolve o token e abre a tela de conferência.
- Rota `portal.login` como placeholder informativo até a Fase 11 (D-30).

### Definition of done

| Critério | Estado |
|---|---|
| 20.000 tokens sem colisão; caractere alterado, truncado e outra chave rejeitados | ✅ (Fase 3) |
| `GET /p/{token}` sem autenticação não vaza dado e redireciona | ✅ |
| Token existente e inexistente produzem resposta **idêntica** sem sessão | ✅ |
| Imprimir uma pulseira e ler o QR com um celular | ✅ artefatos gerados e rota validada ao vivo (D-32); falta só apontar a câmera |

### Testes

`PulseiraTest` (18): dimensionamento do QR (37 módulos = versão 5), RF-15/RF-16, os três
campos que a pulseira não imprime, faixa de alergia redundante, cor com rótulo textual, e
os seis caminhos do fluxograma da doc §8.3 — inclusive o mínimo vital sem vínculo e a
negativa a paciente lendo pulseira alheia.

### Erros corrigidos

1. **O código de QR da doc §8.5 não compila** na versão instalada: `endroid/qr-code` 6.1.3
   substituiu `Builder::create()` fluente por construtor de argumentos nomeados (D-29).
   Os parâmetros de dimensionamento foram preservados integralmente.
2. **A `PacienteFactory` gerava token sem checksum válido** (`Str::random(26)`, marcador
   deixado na Fase 1). Seis testes falharam por essa causa única — a rota rejeitava todo
   paciente de teste antes de consultar o banco. Corrigido para usar o serviço real
   (D-31).
3. O exemplo de código da própria doc §8.3 usa `authorize('verContexto')`, que daria 403
   — mas o fluxograma logo acima manda cair no **mínimo vital** nesse caso. Seguido o
   fluxograma.

### Validação manual executada (D-32)

`herd secure pr-saude` habilitado, `APP_URL` em `https://pr-saude.test`. Com o sistema
rodando de verdade:

| Verificação | Resultado |
|---|---|
| Site em HTTPS | 200 |
| Pulseira gerada (paciente LARANJA, com alergia) | PDF de 884 KB |
| QR | versão 5, 37 × 37 módulos |
| `GET /p/{token}` sem sessão | redireciona para `/portal/entrar` |
| Vazamento na resposta | nenhum — sem nome, CPF nem token |
| Checksum inválido | 404 |

**Encontrou um defeito que a suíte não encontraria (D-33):** imprimir com o admin do
seeder estourava violação de constraint, porque `impressa_por` é `NOT NULL` com FK para
`profissional` e o admin é conta de TI sem registro profissional. Todos os testes usavam
`Profissional::factory()`, que sempre cria o registro. Corrigido com
`OperadorSemRegistroProfissionalException` e coberto por teste novo.

### Pendências

1. **Apontar a câmera de um celular** para o QR impresso — não automatizável. Os
   artefatos estão gerados e o HTTPS no ar.
2. `portal.login` é placeholder (D-30) — substituído na Fase 11.

---


## ✅ Fase 5 — Atendimento e máquina de estados (2026-08-18)

### Entregue

**Domínio**

- `AbrirAtendimentoAction` — numeração sequencial (RF-21), guarda de RN-07 e a primeira
  linha do histórico. A verificação em PHP **não é o controle**: quando a corrida escapa
  dela, a Action traduz a violação de `uk_atendimento_ativo` em
  `AtendimentoAtivoExistenteException`.
- `AlterarStatusAction` (doc §6.3) — a **única** porta de escrita de `atendimento.status`.
  Valida RN-13, acrescenta ao histórico sem sobrescrever (RN-15), calcula
  `permanencia_segundos` (RF-39), grava `primeiro_atendimento_em` uma única vez, encerra
  os itens de fila ao terminalizar e emite `StatusAtendimentoAlterado` (RF-38).
- `FinalizarAtendimentoAction` — RN-14, delegando à `AlterarStatusAction` de propósito:
  caminho próprio de escrita escaparia das garantias de RN-13 e RN-15 justamente no
  momento mais importante do episódio.
- `GeradorNumeroAtendimentoService` com `lockForUpdate` + retentativa no índice único.
- Exceções nomeadas: `TransicaoInvalidaException` (que lista as transições legais na
  mensagem), `AtendimentoAtivoExistenteException` (que carrega o atendimento existente) e
  `DesfechoObrigatorioException`.

**Interface**

- `AtendimentoController` com index (RF-18), show (RF-22), store, alterarStatus e
  finalizar.
- `Atendimentos/Index` separando em andamento de finalizados, com o formulário de
  abertura visível só quando não há episódio aberto.
- `Atendimentos/Show` com a linha do tempo lida direto do histórico append-only, e
  **apenas as transições legais oferecidas** — a máquina de estados é a fonte, e a tela
  não oferece o que a Action recusaria.
- `BadgePrioridade` (RNF-15): cor + rótulo + ícone.

### Definition of done

| Critério | Estado |
|---|---|
| Todas as transições da doc §6.2: permitidas passam, demais lançam | ✅ 81 combinações, 26 legais e 55 recusadas |
| Alcançabilidade: todo estado não terminal alcança `FINALIZADO` | ✅ busca em largura |
| Concorrência: duas aberturas simultâneas | ⚠️ ver ressalva abaixo |

**Ressalva honesta sobre o teste de concorrência.** Sob `RefreshDatabase` tudo roda numa
transação, então execução paralela real não é reproduzível. O que os testes provam é:
(a) a Action **traduz** a violação de `uk_atendimento_ativo` para a exceção de domínio
quando a corrida escapa da verificação — simulado com um concorrente inserido entre a
verificação e a escrita; e (b) que o **índice recusa** o segundo ativo, provado em
`EsquemaTest` escrevendo direto no banco. Juntos cobrem o requisito; separadamente,
nenhum dos dois cobriria.

### Testes

- `MaquinaEstadosTest` (7, 173 asserções) — sem banco, em milissegundos. Confere o enum
  contra a tabela da doc §6.2 linha por linha, percorre as 81 combinações, prova
  terminalidade, ausência de deadlock, cancelamento a partir de qualquer estado, e que a
  auto-transição de reclassificação é a única do sistema.
- `AtendimentoTest` (21, 213 asserções) — abertura, numeração, RN-07 nas três variações,
  as 26 transições legais e as 55 ilegais pela Action, histórico, permanência,
  finalização, `ativo_key`, auditoria, RN-12 e as duas telas.

### Erros corrigidos

1. **`permanencia_segundos` negativo derrubava a transição** (D-35). A coluna é
   `INT UNSIGNED` e o MySQL recusa o `INSERT` inteiro. Acontece com desvio de relógio ou
   registro retroativo. Truncado em zero: perder a transição seria muito pior — o
   paciente ficaria preso no estado anterior.
2. **A numeração "por ano e unidade" do prompt é incompatível com o schema** (D-34):
   `uk_atendimento_numero` é global e o formato `2026-000148` não tem componente de
   unidade. Passou a ser sequencial por ano, global entre unidades. **Ver pendências.**

### Pendências

1. ~~A numeração diverge da letra do prompt~~ — **decidido pelo usuário**: mantida
   global por ano (D-34).
2. `profissional_responsavel_id` ainda não é preenchido por nenhum fluxo — quem atribui é
   a `AtribuirProfissionalAction` da Fase 7. Até lá, a supervisão do RN-12 é o que
   sustenta a alteração de status.

---

## ✅ Fase 6 — Triagem e classificação de risco (2026-08-18)

### Entregue

- `RealizarTriagemAction` — classifica, registra sinais vitais (D-06) e **bifurca**:
  vermelho vai direto a `EM_ATENDIMENTO` sem `fila_item` (RN-11); as demais cores entram
  na fila geral com `entrou_em` carimbado.
- `ReclassificarRiscoAction` (doc §7.5) — nova triagem **encadeada**, a anterior intacta,
  `entrou_em` preservado, reimpressão de pulseira disparada (RN-09) e promoção a
  atendimento imediato quando o novo nível é vermelho.
- `AvaliadorEsperaService` (doc §7.3.1) — classifica a criticidade da espera **sem
  reordenar nada**. Envelhecimento automático de prioridade é proibido: um azul que
  espera três horas não se torna mais grave que um laranja que acabou de chegar.
- Eventos `TriagemRealizada`, `ReimpressaoPulseiraNecessaria` e `EmergenciaDetectada`.
- `TriagemController` + `Triagem/Edit.vue` com a cadeia de classificações visível e o
  aviso de espera crítica que **sugere** reavaliação em vez de promover.

### Definition of done

| Critério | Estado |
|---|---|
| Reclassificar de verde para laranja preserva `entrou_em` e reordena a fila | ✅ |
| Vermelho vai direto a `EM_ATENDIMENTO`, sem fila | ✅ |
| A triagem anterior continua legível após a reclassificação | ✅ |

### Testes

`TriagemTest` (18) — os três casos da DoD, mais: recusa de segunda triagem, série
temporal de sinais vitais, os `CHECK` de faixa recusando pela Action com rollback
integral, auditoria com justificativa, as fronteiras exatas do `AvaliadorEsperaService`
(0,75 / 1,00 / 2,00) e a prova de que **um paciente azul esperando 600 min contra um alvo
de 240 continua azul**.

### Bug corrigido — e este é sério

**O Eloquent truncava os microssegundos** de todas as nove colunas `DATETIME(6)`
(D-36). O `$dateFormat` padrão é `'Y-m-d H:i:s'`, e a precisão dessas colunas não é
decorativa: é o que desempata registros criados no mesmo segundo.

O sintoma foi pequeno — a ordem do histórico de triagens saía errada. A consequência real
é maior: **numa fila com chegadas simultâneas, que é a regra num pronto-socorro, o
desempate por ordem de chegada da RN-10 deixaria de ser determinístico.** Dois pacientes
verdes com o mesmo `entrou_em` seriam ordenados pelo que o MySQL devolvesse primeiro, e a
ordem poderia mudar entre duas leituras da mesma fila.

Corrigido com `$dateFormat = 'Y-m-d H:i:s.u'` nos nove models.

### Pendências

1. `fila_item.profissional_id` nasce nulo (fila geral). A atribuição é a Fase 7 (UC-05).
2. `ReimpressaoPulseiraNecessaria` é emitido mas ainda não tem ouvinte — por projeto
   (doc §7.7). A impressão continua sendo ato de um profissional (RF-15), não automação
   silenciosa.

---

## ✅ Fase 7 — Fila e painel do profissional (2026-08-18)

### Entregue

**Leitura direto das views.** `PainelFilaService` consome `vw_fila_ordenada` e
`vw_carga_profissional`: a ordenação da RN-10, a posição por `ROW_NUMBER()` e a carga
ponderada já estão resolvidas lá. Reimplementá-las em PHP criaria uma segunda fonte de
verdade, e a divergência apareceria como dois profissionais vendo ordens diferentes da
mesma fila.

- `AtribuirProfissionalAction` — **reaproveita** o item da fila geral, só trocando o
  dono. Criar item novo reiniciaria `entrou_em` e o paciente perderia o tempo esperado.
- `TransferirFilaAction` — cria item novo encadeado por `transferido_de_id`, mas
  **copia** `entrou_em`. O histórico mostra o remanejamento; o paciente não paga por ele.
- `EstimadorEsperaService` — média móvel de 30 dias do próprio profissional por cor, com
  cascata de fallback explícita (D-39).
- `Fila/Index.vue` com `usePoll(10000, { only: ['fila'] })` (RF-34, RNF-03) — o `only` é
  o que torna o polling barato e o que a doc §7.7 aponta como substituível por WebSocket.
- `Fila/Atribuir.vue` reproduzindo o mockup da doc §7.4: disponibilidade, quantidade,
  composição por cor, carga ponderada, espera estimada e a marca de sugerido.

### Definition of done

| Critério | Estado |
|---|---|
| Cenário da doc §5.4.1: cinco pacientes em ordem inversa, fila correta | ✅ |
| Carga ponderada de 1 laranja + 1 amarelo + 2 verdes = 11 | ✅ |
| Transferência entre filas não penaliza a posição | ✅ |

O teste da §5.4.1 é literal: Ana Paula, que chegou **por último**, sai em primeiro por
ser laranja; e entre os dois verdes o desempate é por chegada — 149 min antes de 39 min.

### Testes

`FilaTest` (18) — o cenário da doc, tempo-alvo excedido por item, carga e composição,
sugestão do RF-28, exclusão de quem está fora de plantão, contagem de preteridos do
UC-05 passo 6, atribuição sem recarimbar `entrou_em`, transferência preservando posição,
e a média móvel ignorando histórico fora da janela de 30 dias.

### Pendências

1. **Ponderação linear** (D-38): cinco azuis somam a carga de um vermelho. A própria doc
   §7.4 aponta o limite e sugere `2^(5−peso)`. Troca de uma linha na view — fica como
   ponto de calibração com a equipe do hospital.
2. `EmergenciaDetectada` e `ReimpressaoPulseiraNecessaria` seguem sem ouvinte, por
   projeto (doc §7.7).

---

## ✅ Fase 8 — Prontuário e evolução (2026-08-20)

**RF atendidos:** RF-45, RF-46, RF-47, RF-48, RF-49, RF-50, RF-51, RF-52 ·
**RN:** RN-16, RN-17, RN-28, RN-29 · **UC-08**

### Entregue

| Artefato | Papel |
|---|---|
| `HashEncadeadoService` | Forma canônica, `hash_anterior`, `verificarCadeia()` (doc §9.4) |
| `RegistrarNotaClinicaAction` | SOAP em quatro colunas, snapshot de autoria (doc §9.2) |
| `RetificarRegistroAction` | Adendo; o original não é tocado (doc §9.3) |
| `RegistrarDiagnosticoAction` | CID-10 com natureza e principal (RF-46) |
| `ProntuarioConsolidadoService` | Linha do tempo, episódios (RF-51), tipos por permissão |
| `VerificarIntegridadeProntuarioJob` | Varredura horária da cadeia, agendada em `routes/console.php` |
| `ProntuarioController` + `routes/prontuario.php` | 7 rotas, **nenhuma `PUT`/`PATCH`/`DELETE`** |
| `resources/views/prontuario/pdf.blade.php` | RF-52, com tarja, autoria de época e integridade |
| `Prontuario/Show.vue`, `Prontuario/Consolidado.vue`, `RegistroClinico.vue` | Telas |
| 3 exceções de domínio, 4 eventos | `RegistroClinicoInvalido`, `DiagnosticoInvalido`; `RegistroClinicoCriado`, `RegistroRetificado`, `DiagnosticoRegistrado`, `IntegridadeProntuarioViolada` |

### Definition of Done

| Critério | Como está garantido |
|---|---|
| `UPDATE` em `registro_clinico` lança | `RegistroClinico::save()` (Fase 1) + teste |
| `DELETE` lança | `delete()` e `forceDelete()` sobrescritos + teste |
| Adendo órfão é recusado | `CHECK ck_registro_adendo`, testado com `INSERT` cru |
| Após retificação o original permanece inalterado | Comparação byte a byte da linha via `DB::table()` |
| Alteração por fora vira `CONTEUDO_ALTERADO` | `UPDATE` cru real no teste, não simulado |
| Remoção do meio vira `ELO_ROMPIDO` | `DELETE` cru real no teste |
| Autoria é snapshot | Cadastro alterado depois; registro não muda |
| Registro sigiloso some no portal, sem indicar que existe | `linhaDoTempo()` filtra por Policy; teste espera lista **vazia** |
| Ausência de rota de edição | Teste varre `Route::getRoutes()` |

### O que foi decidido sozinho

D-40 a D-44 em `docs/DECISOES.md`. Os dois que mais importam:

- **D-40** — o `HashEncadeadoService` da doc §9.4, transcrito literalmente, acusaria
  adulteração em **todo registro íntegro**: `toArray()` aplica casts e `(string)` sobre
  um Carbon descarta os microssegundos que a coluna `DATETIME(6)` guarda. A forma
  canônica passou a normalizar data, enum e id explicitamente. Um detector que sempre
  alarma é um detector desligado.
- **D-42** — o middleware `vinculo` só resolvia `{paciente}`. A rota
  `atendimentos/{atendimento}/prontuario` — a que o plantão usa o dia inteiro — teria
  ficado fora do *break the glass* (RN-28).

### Pendências

1. **D-43 precisa de validação clínica**: "um diagnóstico principal por atendimento" e
   "suspeita não pode ser principal" são interpretação, não estão no documento.
2. Os quatro eventos são emitidos sem ouvinte, por desenho (doc §7.7).
3. O `REVOKE UPDATE, DELETE` sobre `registro_clinico` — terceira camada da imutabilidade
   — continua alocado à Fase 13 (`docs/privilegios.sql`).
4. O autocompletar de CID-10 (`GET /cid10`) existe na API mas a tela ainda pede o código
   digitado; a busca por descrição é melhoria de usabilidade, não de regra.

---

## ✅ Fase 9 — Medicamentos (2026-08-20)

**RF atendidos:** RF-53 a RF-61 · **RN:** RN-18 a RN-23 · **UC-09/UC-10**

### Entregue

- `PrescreverAction`, `SuspenderPrescricaoAction` e `RegistrarAdministracaoAction`: ordem,
  agenda e fato permanecem separados, e toda escrita clínica ocorre em transação.
- `AprazamentoService`: grade operacional 6/12/18/00; SOS não gera horário (RF-56).
- RN-21 compara alergia pelo **princípio ativo**, inclusive texto sem acento e catálogo
  com nome comercial diferente. A sobreposição exige justificativa e emite
  `AlertaAlergiaSobreposto` para notificação do prescritor.
- RN-20 usa `lockForUpdate` para serializar confirmações e o índice
  `uk_adm_aprazamento` como garantia final contra corrida.
- RN-22 exige conferente distinto em alta vigilância; RN-23 aceita dose divergente, mas
  exige observação e emite `DoseDivergente`.
- Não administração com motivo (RF-58) e baixa do aprazamento na mesma transação.
- Checklist do turno lido diretamente de `vw_doses_pendentes`, com polling de 10 s.
- Tela dos nove certos: paciente, medicamento, dose, via, horário, validade, orientação,
  forma e registro, sempre com alerta por ícone + texto além da cor.
- Middleware de vínculo passou a resolver o paciente também por `prescricao` e
  `aprazamento`; sem isso, as novas rotas contornariam a RN-28.

### Definition of done

| Critério | Estado |
|---|---|
| Alergia bloqueia; justificativa marca a sobreposição | ✅ |
| Dose duplicada é recusada e resta um único fato | ✅ |
| Alta vigilância recusa ausência e o próprio executor | ✅ |
| Divergência é permitida com observação | ✅ |
| Não administração exige motivo | ✅ |
| Aprazamento muda na mesma transação | ✅ |

### Testes

`MedicamentoTest` acrescenta **12 testes e 54 asserções**. Suíte completa: **282 testes,
1549 asserções**, build Vite verde.

### Decisões e achados

- **D-45:** tolerância de um segundo no início da vigência por arredondamento de
  `DATETIME(0)` no MySQL; sem ela, uma ordem recém-criada pode parecer futura.
- **D-46:** como o schema não possui colunas próprias para lote, validade e orientação,
  a conferência é validada no servidor e anexada a `observacao`, sem criar migration que
  divergiria da fonte normativa.

### Pendências

1. Os eventos de alergia e divergência estão prontos para listeners de mensageria; o
   canal externo (push, SMS ou alerta interno) não é definido no documento.

---

## ✅ Fase 10 — Clínica e exames (2026-08-20)

**RF atendidos:** RF-62 a RF-69 · **RN:** RN-24 e RN-25 · **UC-11/UC-12/UC-13**

### Entregue

- `SituacaoExame` e `AlterarSituacaoExameAction`: a máquina completa impede saltos em
  `SOLICITADO → COLETADO → EM_EXECUCAO → CONCLUIDO → LIBERADO`; cancelado é terminal.
- `SolicitarExameAction`, `RegistrarResultadoAction` e `LiberarResultadoAction`, todas
  transacionais, auditadas e com eventos de domínio.
- `AvaliadorResultadoService` consulta `exame_faixa_critica`, nova tabela
  parametrizável sem limites clínicos em constante de código (D-47).
- Resultado estruturado por analito; unidade, referência mínima/máxima/textual e
  sinalização ficam congeladas na linha do resultado (doc §11.3).
- Valor crítico agrega em `possui_valor_critico`, emite `ValorCriticoDetectado` e só é
  liberado por médico; a liberação é o registro explícito de ciência (D-48, RN-25).
- Anexos PDF/imagem ficam em `storage/app/private`, com nome opaco e SHA-256. O arquivo
  só é entregue por rota autenticada, com vínculo, Policy e verificação de hash.
- Fila do laboratório usa o scope existente: urgente primeiro e depois ordem de
  solicitação, atualizada a cada 10 s.
- A solicitação pendente passou a constituir vínculo funcional do perfil laboratório
  com aquele paciente (D-49); sem isso a própria fila levaria a 403.

### Definition of done

| Critério | Estado |
|---|---|
| Banco recusa visibilidade sem autor/data de liberação | ✅ |
| Valor crítico não é liberado pelo laboratório | ✅ |
| Médico registra ciência e libera valor crítico | ✅ |
| Anexo fica fora do `public/` e sem URL direta | ✅ |
| SHA-256 é conferido antes do download | ✅ |
| Urgentes aparecem antes das rotinas | ✅ |

### Testes

`ExameTest`: **12 testes e 57 asserções**. Suíte completa: **294 testes, 1606
asserções**; build Vite e lint verdes.

### Pendências

1. `ValorCriticoDetectado` é o ponto de integração para a notificação prioritária; o
   canal externo não foi especificado.

---

## ✅ Fase 11 — Portal do paciente (2026-08-20)

**RF atendidos:** RF-70 a RF-77 · **RN:** RN-26 e RN-27 · **UC-11**

### Entregue

- Portal isolado no guard `paciente`; todas as rotas de dado são `GET`. Sob autenticação,
  as únicas escritas são a troca da própria senha e o logout.
- `DoPacienteAutenticadoScope` cobre atendimentos, registros, diagnósticos, prescrições,
  doses e exames pelos respectivos caminhos relacionais. UUID de outro paciente vira 404.
- Primeiro acesso exige CPF, senha provisória dentro de 72 horas, atendimento ativo e o
  token opaco lido da pulseira. O fator permanece na sessão somente até a troca de senha.
- Erro uniforme e verificação Argon2id de custo uniforme evitam enumeração de CPF;
  limitação por IP e bloqueio progressivo da conta aplicam 1 min, 15 min, 1 h e bloqueio
  até intervenção da recepção.
- Troca obrigatória recusa senha curta, comum, igual à atual, CPF e variações da data de
  nascimento; o acesso definitivo expira 30 dias após a alta.
- Toda tentativa é auditada com CPF mascarado e o sucesso emite `AcessoPortalRealizado`
  como ponto de integração da notificação M-8.
- Telas acessíveis de acompanhamento, atendimento, medicamentos, exames e histórico usam
  rótulos para pacientes, mostram apenas tempo decorrido e nunca estimativa.
- Registro sigiloso é omitido sem vestígio; resultado não liberado aparece em análise sem
  laudo, conclusão ou analitos.

### Definition of done

| Critério | Estado |
|---|---|
| Fator da pulseira obrigatório no primeiro acesso | ✅ |
| CPF inexistente e senha errada são indistinguíveis | ✅ |
| Paciente A não acessa atendimento do paciente B | ✅ |
| Resultado não liberado e registro sigiloso não vazam conteúdo | ✅ |
| Rotas de dado são estruturalmente somente leitura | ✅ |

### Testes

`PortalPacienteTest`: **19 testes e 76 asserções**. Suíte completa: **313 testes, 1682
asserções**; build Vite e lint verdes.

### Pendências

1. O segundo fator alternativo por SMS (M-10) é opcional e depende da contratação de um
   provedor; `AcessoPortalRealizado` também aguarda esse canal para a notificação M-8.

---

## ✅ Fase 12 — Auditoria e indicadores (2026-08-20)

**RF atendidos:** RF-80 a RF-82 · **RN:** RN-28 · **RNF-11**

### Entregue

- Auditoria de leitura nas rotas e serviços clínicos, com usuário, snapshot de perfil,
  paciente, atendimento, IP, user-agent e horário do servidor.
- Tela pesquisável da trilha dos últimos 90 dias responde quem acessou cada paciente e
  usa a coluna inicial do índice `ix_audit_paciente (paciente_id, criado_em)`.
- Mascaramento recursivo remove `password`, `senha`, `token_pulseira`, `cpf` e `cns` antes
  da persistência; o log mantém apenas o contexto necessário à investigação.
- Fluxo completo de quebra de sigilo: tela de alerta, justificativa entre 10 e 1000
  caracteres, autorização de uso único vinculada ao paciente e destino, e auditoria.
- `IndicadoresService` calcula os nove indicadores da doc §7.6 por período e unidade:
  porta–triagem, porta–atendimento, aderência ao alvo, permanência, distribuição por cor,
  reclassificação, evasão, produtividade por profissional e tempo em cada status.
- Tela gerencial apresenta cartões e tabelas acessíveis, amostras e estados “Sem dados”.
  Não foi produzido gráfico porque a skill `dataviz` solicitada pelo plano não estava
  disponível nesta sessão.

### Definition of done

| Critério | Estado |
|---|---|
| Leitura de prontuário gera auditoria | ✅ |
| Acesso sem vínculo e sem justificativa é recusado | ✅ |
| Quebra justificada é de uso único e auditada | ✅ |
| Credenciais e identificadores sensíveis são mascarados | ✅ |
| Nove indicadores usam uma coorte temporal consistente | ✅ |

### Testes

`IndicadoresTest` acrescenta **4 testes e 45 asserções**. Suíte completa: **317 testes,
1727 asserções**; build Vite e lint verdes.

---

## ✅ Fase 13 — Fechamento (2026-08-20)

### Entregue

- Cobertura configurada em `phpunit.xml` exatamente para `app/Actions`, `app/Services` e
  `app/Enums`; execução integral com Xdebug 3.5.3 mediu **93,0%**, acima dos 80% da RNF-19.
- Auditoria estática de acessibilidade: prioridade sempre traz rótulo e ícone além da cor;
  alertas têm texto; foco visível global, salto para o conteúdo e preferência por movimento
  reduzido foram acrescentados aos layouts da equipe e do paciente.
- `docs/privilegios.sql` define usuários MySQL de privilégio mínimo e remove UPDATE/DELETE
  das tabelas append-only; `docs/IMPLANTACAO.md` cobre infraestrutura, LGPD, publicação,
  restauração e validação final.
- `README.md` documenta requisitos, setup, schemas permitidos, testes, seeds, controles e
  as oito contas de demonstração.
- `DemonstracaoSeeder` cria deterministicamente 1 unidade, 8 profissionais cobrindo todas
  as roles, 30 pacientes, 15 atendimentos em 8 estados e filas povoadas. As escritas
  clínicas passam pelas Actions e o seeder é idempotente.
- `migrate:fresh --seed` foi executado do zero no schema `prsaude` e concluiu com sucesso.
- Dependências revisadas: `composer audit` e `npm audit --omit=dev` encerram com zero
  vulnerabilidades; o lockfile npm foi atualizado para remover 18 alertas transitivos.

### Definition of done

| Critério | Estado |
|---|---|
| Cobertura do núcleo ≥ 80% | ✅ 93,0% |
| Nenhuma prioridade depende apenas de cor | ✅ |
| Foco visível, skip link e controles nativos de teclado | ✅ |
| Privilégios mínimos e checklist de implantação | ✅ |
| Ambiente limpo reproduzível com seed | ✅ |
| Suíte, lint e build | ✅ |

### Testes e verificação

`FechamentoTest` acrescenta **2 testes e 26 asserções**. Suíte completa: **319 testes,
1753 asserções**. O teste visual interativo no navegador integrado não pôde ser executado
porque nenhum navegador estava conectado à sessão; a validação manual nos temas claro e
escuro permanece explicitada no checklist de implantação.

---

## ✅ Complemento — Painel inicial da equipe (2026-08-20)

Fora das 13 fases: a rota `/dashboard` ainda era o placeholder do starter kit. Registrado
em `docs/DECISOES.md` como **D-58**.

### Entregue

- `DashboardController` + `PainelInicialService` substituem o closure de `routes/web.php`.
  Só leitura: fila, atraso de dose e ordenação vêm de `vw_fila_ordenada` e
  `vw_doses_pendentes`, e a criticidade da espera vem do `AvaliadorEsperaService` — nada
  é recontado em PHP.
- **Cada bloco é montado no servidor conforme a permission**: fila (`fila.ler`),
  atendimentos (`atendimento.ler_status`), doses (`medicamento.ler_administracao` ou
  `prescricao.ler`), exames (`exame.ler_solicitacao`). Quem não pode ver não recebe o
  dado no payload.
- `Dashboard.vue`: cartões de fila, além do tempo-alvo (RF-33), minha fila, atendimentos
  abertos, doses pendentes e exames em curso; composição da fila por prioridade; lista de
  quem aguarda profissional; próximas doses com marca de alta vigilância (RN-22).
  Prioridade sempre com cor + rótulo + ícone via `BadgePrioridade` (RNF-15).
- `usePoll(15000, { only: ['painel'] })` — mesmo padrão da fila (RF-34/RNF-03), com ciclo
  mais folgado porque o painel é panorâmico.

### Definition of done

| Critério | Estado |
|---|---|
| Nenhum número reimplementa lógica das views | ✅ |
| Bloco sem permissão não chega ao cliente | ✅ |
| Prioridade nunca depende apenas da cor | ✅ |
| A leitura do painel não escreve nada | ✅ |
| Paciente do portal não alcança o painel da equipe | ✅ |

### Testes

`PainelInicialTest` acrescenta **12 testes e 166 asserções**. Suíte completa: **335
testes, 1932 asserções**; Pint, ESLint e Prettier verdes.

---

## ✅ Complemento — Landing pública (2026-08-20)

Fora das 13 fases: a raiz `/` ainda servia a página do starter kit. Registrado em
`docs/DECISOES.md` como **D-59**.

### Entregue

- `Welcome.vue` reescrita em pt-BR: identificação do sistema, jornada do paciente em
  quatro etapas, quatro garantias do domínio (adendo em vez de sobrescrita, alergia por
  princípio ativo, acesso vinculado e auditado, paciente lê e não escreve) e rodapé com
  aviso de que o sistema não substitui atendimento.
- **Duas portas de entrada**: `login` (equipe, guard `web`) e `portal.login` (paciente,
  guard `paciente`). Quem já tem sessão vê só o destino que lhe pertence.
- Sem nenhuma prop de dado — nem agregado. Salto para o conteúdo principal, marcos
  semânticos (`header`/`main`/`footer`), `aria-labelledby` nas seções e ícones marcados
  como decorativos.

### Testes

`LandingPageTest` acrescenta **3 testes e 17 asserções**: a raiz é pública, o payload não
contém nome de paciente / número de atendimento / token de pulseira, e toda rota citada na
página existe — o `route()` do Ziggy derruba a tela inteira quando o nome não existe.
Suíte completa: **338 testes, 1949 asserções**; ESLint e build verdes.

---

## ✅ Complemento — Superacesso do tipo ADMIN (2026-08-20)

- `Gate::before` agora libera toda permission e ability de Policy para contas com
  `users.tipo = ADMIN`, mesmo sem role associada.
- A matriz das roles continua fiel à doc §2.3; possuir apenas a role `admin` em uma conta
  profissional não concede superacesso.
- A navegação já espelhava essa regra pelo campo `tipo`, portanto não exigiu alteração.
- Invariantes clínicas, autoria profissional, máquinas de estado e constraints do banco
  permanecem obrigatórias.

### Testes

`AutorizacaoTest` percorre as 42 permissions e abilities contextuais para o tipo ADMIN.
Suíte completa: **337 testes, 1972 asserções**; Pint verde.

---

## ✅ Complemento — Fluxo direto de atendimentos (2026-08-20)

- Item **Atendimentos** próprio na sidebar, visível por `atendimento.ler_status`.
- Nova visão `GET /atendimentos` com casos em andamento e os 20 encerramentos mais
  recentes, sem exigir busca prévia pela ficha do paciente.
- Ações contextuais levam direto ao caso, à triagem ou à atribuição; a fila ganhou um
  link explícito para o atendimento correspondente.
- A tela do caso agora retorna à seção Atendimentos pelo breadcrumb.
- A fila continua responsável pela ordem clínica da RN-10; a visão global não replica
  nem altera esse cálculo.

### Testes

`AtendimentoTest` cobre conteúdo e ordenação da visão global; `NavegacaoTest` garante a
rota da sidebar e o elo fila → atendimento. Suíte completa: **339 testes, 1998
asserções**; Pint, ESLint e build Vite verdes.
