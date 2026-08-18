# PROGRESSO — Sistema de Gestão Hospitalar

Estado real das 13 fases. Atualizado ao fim de cada fase, com o que foi entregue, os
testes que passam e as pendências.

**Legenda:** ✅ concluída · 🔄 em andamento · ⏳ bloqueada · ⬜ não iniciada

| # | Fase | Estado |
|---|---|---|
| 0 | Inspeção do projeto | ✅ |
| 1 | Fundação do banco | ✅ |
| 2 | Autenticação e autorização | ⬜ próxima |
| 3 | Cadastro de paciente | ⬜ |
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

**Testes:** `php artisan test` → **48 passando, 102 asserções** (~13 s, MySQL).

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

1. **CI ainda usa SQLite** (`.github/workflows/tests.yml`). Precisa de *service
   container* MySQL, senão o CI não exercita `CHECK` nem coluna gerada — exatamente o
   que esta fase construiu. **Requer decisão.**
2. **`users.login` e `users.tipo` nullable** (D-14). Aperta na Fase 2, junto com a
   remoção da rota pública de cadastro.
3. **Rota de auto-exclusão de conta** do starter kit (D-15). Revisitar na Fase 2.
4. **`docs/privilegios.sql`** — `REVOKE UPDATE, DELETE` nas tabelas append-only. O
   prompt aloca na Fase 13; as tabelas alvo já existem desde agora.

---

## ⬜ Fase 2 — Autenticação e autorização

Escopo previsto:

- [ ] Guards `web` (equipe) e `paciente` em `config/auth.php`, providers distintos sobre
      o mesmo model `User`
- [ ] Expiração de sessão de 30 min (equipe) e 15 min (paciente) — exige middleware
      próprio, o Laravel tem um único `session.lifetime` (D-12)
- [ ] `config/hashing.php` com Argon2id (RNF-07)
- [ ] Seeder das 8 roles e de todas as permissions, reproduzindo a matriz da doc §2.3
- [ ] As 6 Policies, registradas
- [ ] `Gate::before` liberando `admin`, exceto `prontuario.quebra_sigilo`
- [ ] Middlewares `SenhaProvisoria`, `RegistrarAuditoria`, `ExigirVinculoAssistencial`
- [ ] `AuditoriaService` com mascaramento de `password`, `token_pulseira`, `cpf` e `cns`
- [ ] Layout base Inertia com navegação por perfil
- [ ] Remover cadastro público e tornar `login`/`tipo` `NOT NULL` (D-14)
- [ ] Teste percorrendo a matriz RBAC — **os testes negativos são os que importam**
- [ ] Teste provando que o guard `paciente` recebe `false` em qualquer `can()`
