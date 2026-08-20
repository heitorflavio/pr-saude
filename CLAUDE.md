# CLAUDE.md — Sistema de Gestão Hospitalar (SGH)

Contexto permanente do projeto. Leia este arquivo antes de qualquer trabalho: ele
substitui a necessidade de recarregar a especificação inteira a cada sessão.

## 1. Fontes da verdade

| Arquivo | Papel |
|---|---|
| `docs/modelagem-sgh.md` | **Fonte normativa de comportamento.** 82 RF, 22 RNF, 30 RN, 18 casos de uso, DER, dicionário de dados, máquina de estados, análise de segurança |
| `docs/schema.sql` | **Fonte normativa do modelo de dados.** MySQL 8.4: 34 tabelas, 3 views, 67 FKs, 18 `CHECK` |
| `docs/PROMPT-CLAUDE-CODE.md` | Plano de execução e adaptações para esta stack. **Vence o documento em caso de conflito** |
| `docs/DECISOES.md` | Toda divergência do documento, com justificativa |
| `docs/PROGRESSO.md` | Estado real das 13 fases |

Leia sob demanda a seção da fase em curso — não carregue o documento inteiro de uma vez.
Ao implementar algo que o documento numera (`RF-24`, `RN-11`, `D-07`, `M-3`), **cite o
identificador em comentário no código**.

## 2. Stack detectada (Passo 0, 2026-08-18)

| Item | Valor |
|---|---|
| Laravel | 12.67.0 |
| PHP | **8.4.22** (site isolado via `herd isolate 8.4`; `composer.json` exige `^8.4`) |
| Banco | **MySQL Community 9.6.0** em `127.0.0.1:3306`, schemas `prsaude` e `prsaude_test` (`utf8mb4_0900_ai_ci`). ⚠️ O servidor hospeda outros bancos do usuário — nunca rodar `migrate:fresh` fora desses dois schemas. Ver `docs/DECISOES.md` D-04 |
| RBAC | `spatie/laravel-permission` **8.3.0** |
| Inertia | `inertiajs/inertia-laravel` 2.0.25 + `@inertiajs/vue3` 2.0.3 |
| Vue / TS / Vite | 3.5.13 / 5.7.3 / 6.1.1 |
| Tailwind | 3.4.17 (**v3**, não v4) — config em `tailwind.config.js` |
| shadcn-vue | configurado (`components.json`), componentes em `resources/js/components/ui/` |
| Testes | **Pest 3.8.7** (+ plugin-laravel, plugin-arch, plugin-mutate) |
| Hashing | Argon2id disponível (`password_algos()` devolve `2y, argon2i, argon2id`) |
| Sessão / fila / cache | driver `database` |
| Starter kit | `laravel/vue-starter-kit` — auth com controllers próprios, **sem Fortify, sem 2FA** |

CI (`.github/workflows/`) roda em ubuntu-latest com PHP 8.4.

## 3. As 15 invariantes invioláveis

Se uma implementação violar qualquer uma, ela está errada — mesmo que compile e passe nos
testes. Cada uma precisa estar garantida por **teste automatizado**.

1. **A idade nunca é armazenada.** Atributo derivado de `data_nascimento` (D-01, RN-02).
   Nenhuma coluna `idade` em nenhuma tabela.
2. **`registro_clinico` não aceita `UPDATE` nem `DELETE`.** Correção é adendo novo
   apontando para o original via `registro_retificado_id` (RN-16, RN-17, D-05).
3. **Nenhuma exclusão física de dado clínico.** Sempre `SoftDeletes` (D-08).
4. **Um único atendimento não finalizado por paciente por unidade**, garantido no banco —
   não por verificação em PHP (RN-07, D-07).
5. **A fila ordena por prioridade clínica, depois por horário de entrada.** A posição é
   **calculada na leitura**, nunca persistida (RN-10).
6. **Reclassificação preserva `entrou_em`.** O paciente não volta ao fim da fila (§7.5).
7. **Transição de status só pelo enum**, via `AlterarStatusAction`, validada contra
   `StatusAtendimento::podeTransitarPara()` e gravada em `atendimento_status_historico`
   sem sobrescrever nada (RN-13, RN-15).
8. **`FINALIZADO` é terminal e exige desfecho** (RN-14).
9. **Alergia é verificada por princípio ativo, nunca por nome comercial** (RN-21).
10. **A mesma dose aprazada não é administrada duas vezes** — `UNIQUE KEY
    uk_adm_aprazamento` (RN-20).
11. **Medicamento de alta vigilância exige um segundo profissional**, distinto do
    executor (RN-22).
12. **Resultado de exame só é visível ao paciente após liberação explícita** (RN-24).
13. **O paciente não executa nenhuma escrita**, exceto trocar a própria senha — garantido
    por **ausência de rota**, não por verificação em controller (RN-27).
14. **Data e hora de evento clínico vêm do servidor**, nunca do cliente (RN-29).
15. **O token da pulseira é permanente.** Gerado uma vez, nunca alterado, nunca
    reaproveitado; o QR Code **não codifica id nem CPF** (RN-03, §8.2).

## 4. Arquitetura de autorização — duas camadas

| Camada | Ferramenta | Pergunta que responde |
|---|---|---|
| **Estática** | spatie: role → permission | "Este *papel* pode, em princípio, fazer isso?" |
| **Contextual** | Policies do Laravel | "Este *usuário* pode fazer isso **neste registro**?" |

**Regra de composição: a Policy checa a permission do spatie *e* o vínculo contextual.
Permission sozinha nunca basta para dado clínico.**

- **Permissions** (`recurso.acao`): `paciente.criar`, `pulseira.imprimir`,
  `triagem.classificar`, `fila.atribuir`, `atendimento.alterar_status`,
  `prontuario.criar`, `prontuario.quebra_sigilo`, `prescricao.criar`,
  `medicamento.administrar`, `exame.solicitar`, `exame.liberar_resultado`,
  `auditoria.ler`, `usuario.gerenciar`. Matriz completa em §2.3, semeada por seeder.
- **Roles** (8): `recepcao`, `enfermeiro_triagem`, `enfermeiro_assistencial`,
  `tecnico_enfermagem`, `medico`, `laboratorio`, `admin`, `auditor`.
- **Policies** (6): `PacientePolicy` (`verContexto`, `verMinimoVital`, `quebrarSigilo`),
  `AtendimentoPolicy`, `RegistroClinicoPolicy`, `PrescricaoPolicy`,
  `AdministracaoPolicy`, `ExameResultadoPolicy`.
- **`Gate::before`** libera irrestritamente toda conta com `users.tipo = ADMIN`; as
  invariantes clínicas e constraints do banco continuam obrigatórias (D-20).
- **Global scope** `DoPacienteAutenticadoScope` nas entidades clínicas, para que um
  `where` esquecido não vaze dado de outro paciente (§12.1).
- Regras contextuais que **não** são expressáveis como permission estática: RN-12
  (só o responsável altera o status), RN-28 (*break the glass* com justificativa),
  §13.5 (mínimo vital — nome + **alergias** — liberado a qualquer profissional em
  plantão), RN-26 (paciente só vê o próprio dado).
- Guards: `web` (equipe, sessão de 30 min) e `paciente` (sessão de 15 min), providers
  distintos sobre o mesmo model `User`. **O guard `paciente` não tem nenhuma role e
  nenhuma permission, de propósito** — qualquer `can()` nele nega por construção (RN-27).
- **Nunca** criar no `User` propriedade, relação ou método chamado `role`, `roles`,
  `permission` ou `permissions` — conflita com o trait `HasRoles`.

## 5. Convenções de código

**Idioma.** Domínio em português, seguindo o `schema.sql`: `Paciente`, `Atendimento`,
`PrescricaoItem`, `administracao_medicamento`. Framework em inglês: `index`, `store`,
`AtendimentoController`. **Interface e mensagens de erro em pt-BR** — nenhuma string em
inglês visível ao usuário. Nomes de colunas exatamente como no DDL.

**Camadas — a regra que sustenta tudo: nenhuma escrita de dado clínico acontece fora de
uma Action.** Controllers e componentes Vue não chamam `Model::create()` para dado
clínico. Cada Action:

- fica em `app/Actions/<Contexto>/<Verbo><Objeto>Action.php`, com um único método público
  `execute()`;
- é `final`, com dependências injetadas pelo construtor;
- envolve a escrita em `DB::transaction()`;
- valida as regras de negócio e lança **exceção de domínio nomeada** (nunca `\Exception`);
- registra em auditoria;
- emite um evento de domínio, **mesmo que ninguém o escute ainda** (§7.7).

**Estrutura de diretórios:** siga §13.3 do documento.

**Migrations.** Uma por tabela, nomeada pela tabela. Reproduza fielmente do `schema.sql`:
tipos, `NOT NULL`, `ENUM`, todas as FKs, todos os índices e **todas as 18 `CHECK`**. As
`CHECK` não são decorativas: são a garantia que sobrevive a bug de aplicação e a condição
de corrida.

**Views.** As 3 views (`vw_fila_ordenada`, `vw_carga_profissional`, `vw_doses_pendentes`)
são criadas por migration com `DB::statement`. Elas resolvem ordenação da fila, carga
ponderada e checklist de doses — **não reimplemente essa lógica em PHP**.

**Frontend.**

| Necessidade | Implementação |
|---|---|
| Formulários | `useForm` do `@inertiajs/vue3`; validação **só no servidor** via `FormRequest` |
| Atualização da fila | `usePoll(10000, { only: ['fila'] })` (RF-34, RNF-03) |
| Tabelas, badges, diálogos | shadcn-vue |
| Cor de prioridade | **Nunca só a cor** (RNF-15): cor + rótulo textual + ícone, contraste AA |

Não duplique validação no cliente. Máscara de entrada e feedback visual são permitidos;
regra de negócio, não.

## 6. Anti-padrões proibidos

| Não faça | Faça |
|---|---|
| Coluna `idade` | Atributo derivado da data de nascimento |
| Tabela única misturando prescrição e administração | `prescricao_item`, `aprazamento`, `administracao_medicamento` (D-04) |
| Coluna `posicao` persistida em `fila_item` | Posição por `ROW_NUMBER()` na leitura |
| `UPDATE` em `registro_clinico` | Adendo novo referenciando o original |
| `Model::create()` de dado clínico em controller | Sempre via Action, em transação |
| Verificar "é paciente?" no controller do portal | Ausência de rota de escrita |
| Codificar id ou CPF no QR Code | Token opaco de 131 bits |
| Imprimir CPF na pulseira | Nome + data de nascimento já são dois identificadores |
| Envelhecer prioridade automaticamente | Sinalizar a espera e sugerir reclassificação |
| Bloquear toda divergência de dose | Bloquear alergia; sinalizar divergência (fadiga de alerta) |
| Validar regra de negócio no cliente | Só no servidor |
| Cor como único indicador | Cor + rótulo + ícone |
| `rand()` / `mt_rand()` para o token | `random_int()` |
| `==` para comparar HMAC | `hash_equals()` |
| Data/hora vinda do cliente | `now()` no servidor |
| Migration sem as `CHECK` | Todas as 18 reproduzidas |
| `throw new \Exception` | Exceção de domínio nomeada |
| Comentário explicando *o que* o código faz | Comentário explicando *por quê*, citando RF/RN |

## 7. Protocolo de trabalho

1. Mantenha `docs/PROGRESSO.md` e `docs/DECISOES.md` atualizados — eles precisam refletir
   o estado real, não a intenção.
2. **Um commit por fase**, no mínimo, com mensagem citando os RF/RN atendidos.
3. **Checkpoint ao fim de cada fase.** Reportar o que foi feito, quais testes passam,
   quais decisões foram tomadas sozinho, o que precisa de decisão do usuário.
   **Não iniciar a fase seguinte sem OK.**
4. **Nunca inventar regra de negócio.** Se o documento não cobre um caso, perguntar. Em
   sistema clínico, adivinhar regra é criar risco assistencial.
5. **Teste junto com o código, nunca ao final.** §15.2 já traz os testes críticos
   escritos — portá-los, não reinventá-los.
6. **Se algo no prompt estiver tecnicamente errado** (API que mudou, pacote incompatível),
   avisar em vez de contornar silenciosamente. Verificar a documentação da versão
   realmente instalada antes de assumir uma assinatura de método.

## 8. Comandos

```powershell
php artisan test                    # Pest — precisa ficar verde
php artisan migrate:fresh --seed    # ambiente navegável de ponta a ponta
php artisan db:show --counts
composer run dev                    # serve + queue:listen + vite
vendor/bin/pint                     # formatação PHP (o CI verifica)
npm run lint ; npm run format       # ESLint + Prettier (o CI verifica)
```
