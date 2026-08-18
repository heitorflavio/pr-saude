# PROGRESSO — Sistema de Gestão Hospitalar

Estado real das 13 fases. Atualizado ao fim de cada fase, com o que foi entregue, os
testes que passam e as pendências.

**Legenda:** ✅ concluída · 🔄 em andamento · ⏳ bloqueada · ⬜ não iniciada

| # | Fase | Estado |
|---|---|---|
| 0 | Inspeção do projeto | ✅ |
| 1 | Fundação do banco | ⏳ bloqueada — aguarda MySQL |
| 2 | Autenticação e autorização | ⬜ |
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

---

## 🚧 Bloqueio ativo

**A Fase 1 não pode começar sem MySQL 8.4.** Portas 3306 e 5432 estão fechadas; `herd
services` exige Herd Pro (não licenciado); Docker 29.5.2 está instalado mas com o daemon
parado. O usuário assumiu o provisionamento (`docs/DECISOES.md` D-04).

Para destravar, é preciso que a porta 3306 responda e que o `.env` receba:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sgh
DB_USERNAME=<usuário>
DB_PASSWORD=<senha>
```

Verificação de destravamento: `php artisan db:show` deve reportar a conexão `mysql`.

---

## ✅ Fase 0 — Inspeção do projeto (2026-08-18)

### Levantamento

| Item | Valor |
|---|---|
| Laravel | 12.67.0 |
| PHP | 8.2.31 no levantamento → **elevado para 8.4.22** (D-06) |
| Banco encontrado | SQLite 3.51.3, 9 tabelas do starter |
| Inertia | `inertiajs/inertia-laravel` 2.0.25 + `@inertiajs/vue3` 2.0.3 |
| Vue / TS / Vite | 3.5.13 / 5.7.3 / 6.1.1 |
| Tailwind | 3.4.17 (v3) |
| shadcn-vue | configurado; 16 componentes em `resources/js/components/ui/` |
| Runner de testes | Pest 3.8.7 |
| Argon2id (RNF-07) | ✅ disponível |
| Starter kit | `laravel/vue-starter-kit`, auth com controllers próprios |

Estado inicial: 3 migrations, 1 model (`User`), 4 arquivos de rota, 9 páginas Inertia,
20 componentes + 16 do shadcn, 12 arquivos de teste.

### Entregue

- `CLAUDE.md` — contexto permanente: stack, as 15 invariantes, o desenho de autorização em
  duas camadas, as convenções de código e os anti-padrões.
- `docs/DECISOES.md` — 12 decisões registradas (D-01 a D-12).
- `docs/PROGRESSO.md` — este arquivo.
- **PHP elevado para 8.4.22** via `herd isolate 8.4`; `composer.json` com `"php": "^8.4"`
  (D-06). Alinha o ambiente local ao CI, que já usava 8.4, e viabiliza o
  `spatie/laravel-permission` v8 que o prompt exige.
- **Hook quebrado do Laravel Boost removido** do `post-update-cmd` (D-07).

### Testes

`php artisan test` → **27 passando, 64 asserções** (baseline do starter kit em PHP 8.4).

### Decisões tomadas neste checkpoint

| Decisão | Quem decidiu |
|---|---|
| Banco: MySQL 8.4, provisionado pelo usuário | usuário |
| PHP 8.4.22 | usuário (recomendação aceita) |
| Corrigir só o hook do Boost; adiar HTTPS e o case do `components.json` | usuário |
| Derivar os 16 testes de esquema da doc §5.4 | usuário |

### Erros encontrados no prompt e no repositório

1. **§3.2 sobre o spatie v8** — descrito como compatível, mas exige `php ^8.3` contra os
   8.2.31 do projeto. Resolvido elevando o PHP (D-06).
2. **§3.1 menciona 2FA** a preservar — não existe neste starter kit (D-11).
3. **`verificacao/` ausente** — `testes_schema.sh`, `verifica_algoritmos.php` e
   `fixtures_schema.sql` não vieram com os docs (D-08).
4. **`post-update-cmd` chamava `boost:update`** sem o pacote instalado (D-07).

### Riscos aceitos

- **D-09** — sem HTTPS, a leitura de QR das Fases 4 e 7 não é testável no navegador.
- **D-10** — case divergente no `components.json` pode quebrar o build no CI Ubuntu.
- **D-04** — o CI cria banco SQLite; quando o MySQL entrar, o workflow precisará de um
  *service container*, senão local e CI divergem.

---

## ⏳ Fase 1 — Fundação do banco

**Não iniciada.** Escopo previsto quando o MySQL estiver disponível:

- [ ] Migration estendendo `users` (D-01, D-02)
- [ ] Migrations das 30 tabelas de domínio, na ordem de dependência das FKs
- [ ] As 18 restrições `CHECK`
- [ ] Coluna gerada `ativo_key` + `UNIQUE KEY uk_atendimento_ativo` (RN-07, D-07 do doc)
- [ ] Migrations das 3 views
- [ ] Instalação do `spatie/laravel-permission` v8 + migrations
- [ ] Models com `$fillable`, `casts`, relações e `SoftDeletes`
- [ ] `RegistroClinico` com `save()`/`delete()` lançando `RegistroImutavelException`
- [ ] Enums: `StatusAtendimento`, `CorPrioridade`, `ViaAdministracao`,
      `TipoRegistroClinico`, `SituacaoEspera`
- [ ] Seeder de `classificacao_risco` (5 níveis Manchester, alvos 0/10/60/120/240 min)
- [ ] Seeders de catálogo: `medicamento` (≥ 20, com ≥ 4 de alta vigilância), `exame`
      (≥ 15), `queixa` (≥ 20), `cid10` (≥ 50)
- [ ] Factories com states (`comAlergia()`, `comAtendimentoAtivo()`,
      `comSenhaProvisoria()`)
- [ ] Teste Pest com os 14 negativos + 2 positivos da doc §5.4 (D-08)
- [ ] `php artisan migrate:fresh --seed` limpo
- [ ] `php artisan db:show --counts` com 30 tabelas de domínio + 5 do spatie
