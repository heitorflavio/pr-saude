# Prompt mestre para o Claude Code — Sistema de Gestão Hospitalar

> **Projeto:** `C:\Users\heito\Herd\pr-saude`
> **Stack:** Laravel + Inertia + Vue 3 + TypeScript + Tailwind + shadcn-vue

---

## ⚠️ ANTES DE COLAR O PROMPT — faça isto primeiro

O prompt manda o Claude Code **ler a especificação do disco** em vez de carregar 32 mil
palavras no contexto. Sem esses dois arquivos no lugar, ele vai trabalhar por dedução e a
qualidade cai muito.

```powershell
cd C:\Users\heito\Herd\pr-saude
mkdir docs
# copie para docs\ os dois arquivos da etapa anterior:
#   docs\modelagem-sgh.md
#   docs\schema.sql
```

Depois disso, cole tudo o que está abaixo da linha divisória em uma sessão nova do Claude
Code, com o diretório de trabalho em `C:\Users\heito\Herd\pr-saude`.

---
---

# ═══════════════ COPIE DAQUI PARA BAIXO ═══════════════

Você vai implementar um **Sistema de Gestão Hospitalar (SGH)** neste projeto Laravel.
A especificação completa já existe e está no repositório. Leia-a antes de escrever
qualquer linha de código.

## 0. Convenção de referências neste prompt

- **`doc §X.Y`** aponta para a seção X.Y de `docs/modelagem-sgh.md`. Vá ler quando
  aparecer.
- **`seção N deste prompt`** aponta para uma seção deste texto.
- **`RF-nn`, `RNF-nn`, `RN-nn`, `D-nn`, `M-nn`** são requisitos, regras de negócio,
  decisões de modelagem e mitigações de segurança numerados no documento. Cite-os em
  comentário no código que os implementa.

## 1. Fontes da verdade

| Arquivo | O que contém | Como usar |
|---|---|---|
| `docs/modelagem-sgh.md` | Especificação completa: 82 requisitos funcionais (RF), 22 não funcionais (RNF), 30 regras de negócio (RN), 18 casos de uso, DER, dicionário de dados, máquina de estados, 4 módulos detalhados, análise de segurança | **Fonte normativa.** Toda dúvida de comportamento se resolve aqui |
| `docs/schema.sql` | DDL validado por execução: 34 tabelas, 3 views, 67 FKs, 18 restrições `CHECK` | **Fonte normativa do modelo de dados**, com as adaptações da seção 3 deste prompt |

Regras de uso dessas fontes:

- **Leia o índice do documento primeiro.** Depois leia sob demanda a seção da fase em que
  estiver. Não tente carregar o documento inteiro no contexto de uma vez.
- Quando implementar algo que o documento numera (`RF-24`, `RN-11`, `D-07`, `M-3`),
  **cite o identificador em comentário no código**. Isso torna a rastreabilidade
  verificável.
- Se encontrar contradição entre este prompt e o documento, **este prompt vence** (ele
  contém as adaptações para a stack real). Registre a divergência em
  `docs/DECISOES.md`.

## 2. Passo 0 — inspeção obrigatória, antes de qualquer código

Não instale nada nem gere arquivo antes de completar e **relatar** este levantamento:

1. Leia `composer.json` e `package.json`. Reporte: versão do Laravel, do PHP exigido, do
   Inertia, do Vue, se há shadcn-vue configurado (`components.json`), qual runner de
   testes (Pest ou PHPUnit).
2. Leia `.env` (sem expor segredos). Reporte: `DB_CONNECTION`, `DB_DATABASE`, `APP_URL`,
   `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE`.
3. Liste `database/migrations/`, `app/Models/`, `routes/`, `resources/js/pages/`,
   `resources/js/components/`. Reporte o que o starter kit já criou.
4. Verifique se o Argon2id está disponível no PHP:
   `php -r "print_r(password_algos());"` — precisa aparecer `argon2id`.
5. Confirme se o banco responde: `php artisan db:show`.

**Ao terminar o Passo 0, pare e me apresente o levantamento com sua recomendação de
ajustes.** Só siga depois do meu OK.

## 3. Adaptações obrigatórias do documento para esta stack

O documento foi escrito supondo Livewire e uma tabela `usuario` própria. Este projeto usa
Inertia + Vue e já tem a tabela `users` do starter kit. As adaptações abaixo **substituem**
o que está no documento.

### 3.1 Identidade: `users` estendida, não `usuario`

Não crie a tabela `usuario`. Estenda a `users` do starter kit, preservando o
funcionamento da autenticação já instalada (reset de senha, verificação de e-mail, 2FA).

| No documento | Neste projeto | Motivo |
|---|---|---|
| tabela `usuario` | tabela `users` (com colunas novas) | Preserva o auth do starter kit |
| `usuario.senha_hash` | `users.password` | `Authenticatable` espera `password` |
| `usuario.login` | nova coluna `users.login`, `unique` | CPF do paciente ou matrícula do profissional |
| `usuario.tipo` | nova coluna `users.tipo` (enum) | `PACIENTE`, `PROFISSIONAL`, `ADMIN` |
| `usuario.senha_provisoria` | nova coluna `users.senha_provisoria` | RN-06 |
| `usuario.ativo`, `tentativas_falhas`, `bloqueado_ate`, `ultimo_login_em`, `senha_alterada_em` | colunas novas em `users` | RNF-08 |
| `paciente.usuario_id` (PK/FK) | `paciente.user_id` (PK/FK → `users.id`) | Segue o rename |
| `profissional.usuario_id` (PK/FK) | `profissional.user_id` | Idem |
| toda FK que aponta para `usuario.id` | aponta para `users.id`, coluna renomeada para `user_id` quando o nome era `usuario_id` | Consistência |

Ajustes adicionais em `users`, por migration:

- **`email` passa a ser nullable.** Paciente de urgência frequentemente não tem e-mail, e
  o cadastro não pode ser bloqueado por isso (RF-04). Mantenha o índice `unique` — MySQL
  aceita múltiplos `NULL` em índice único.
- `name` permanece como **rótulo de exibição** do starter kit. O nome oficial do registro
  é `paciente.nome_completo` / `profissional.nome_completo`; a Action que cadastra mantém
  `users.name` sincronizado. Documente essa duplicação intencional em `docs/DECISOES.md`.
- O modelo continua se chamando `App\Models\User`. Não renomeie para `Usuario`.

### 3.2 RBAC: spatie substitui as quatro tabelas de perfil

Instale `spatie/laravel-permission` (v8 — compatível com Laravel 12/13 e PHP 8.3+) e
**remova do `schema.sql` as tabelas `perfil`, `permissao`, `perfil_permissao` e
`usuario_perfil`**. Elas seriam um RBAC duplicado.

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan optimize:clear
```

O pacote cria `permissions`, `roles`, `model_has_permissions`, `model_has_roles` e
`role_has_permissions`. Resultado: **30 tabelas de domínio + 5 do spatie**.

Cuidados obrigatórios:

- Adicione o trait `HasRoles` ao modelo `User` e garanta que ele implementa
  `Illuminate\Contracts\Auth\Access\Authorizable`.
- **Nunca** crie no `User` propriedade, relação ou método chamado `role`, `roles`,
  `permission` ou `permissions` — conflita com o trait.
- Todos os `Role` e `Permission` são criados com **`guard_name = 'web'`**. O guard
  `paciente` fica **sem nenhuma role e sem nenhuma permission**, de propósito: assim
  qualquer `can()` avaliado nesse guard nega por construção. Isso é a primeira camada de
  garantia da RN-27.
- `auditoria_log.perfis_no_momento` recebe o *snapshot* de
  `$user->roles->pluck('name')->implode(',')` no instante do evento. O log não muda quando
  as roles mudam.

### 3.3 Hashing Argon2id

RNF-07 exige Argon2id. Em `config/hashing.php` defina `'driver' => 'argon2id'` e
parametrize memória/tempo/threads. Se o Passo 0 mostrar que o PHP do Herd não tem
`argon2id` em `password_algos()`, **pare e me avise** — não troque silenciosamente para
bcrypt.

### 3.4 Frontend: Inertia + Vue 3 + shadcn-vue

Onde o documento diz "componente Livewire", leia "página Inertia + componente Vue".

| Necessidade | Implementação nesta stack |
|---|---|
| Formulários | `useForm` do `@inertiajs/vue3`. Validação **só no servidor**, via `FormRequest`; os erros voltam por `errors` do Inertia |
| Atualização da fila (RF-34, RNF-03) | `usePoll` do `@inertiajs/vue3`: `usePoll(10000, { only: ['fila'] })`. Confirme a assinatura na doc da versão instalada antes de usar |
| Tabelas, badges, diálogos, selects | Componentes shadcn-vue. Se `components.json` não existir, configure o shadcn-vue antes da Fase 7 |
| Cor de prioridade na tela | **Nunca** só a cor (RNF-15). Sempre cor + rótulo textual + ícone, com contraste AA |
| Idioma da interface | pt-BR em toda a UI. Sem string em inglês visível ao usuário |

Não duplique validação no cliente. Em sistema clínico, validação no cliente que divirja
da do servidor é passivo de segurança. Máscara de entrada e feedback visual são
permitidos; regra de negócio, não.

### 3.5 Leitura do QR Code no navegador

Use a **Barcode Detection API nativa** quando disponível, com *ponyfill* como fallback —
a API nativa não existe em Firefox nem Safari:

```bash
npm i barcode-detector
```

```ts
// resources/js/composables/useQrScanner.ts
// Usa a API nativa quando existe; cai no ponyfill (ZXing-C++ em WASM) quando não.
// O import dinâmico evita baixar o .wasm em navegadores que já têm a API.
async function resolverDetector(): Promise<typeof BarcodeDetector> {
  if ('BarcodeDetector' in globalThis) {
    return (globalThis as unknown as { BarcodeDetector: typeof BarcodeDetector }).BarcodeDetector
  }
  const { BarcodeDetector: Ponyfill } = await import('barcode-detector/pure')
  return Ponyfill as unknown as typeof BarcodeDetector
}

const Detector = await resolverDetector()
const detector = new Detector({ formats: ['qr_code'] })
```

Trate os dois casos de falha que **vão** acontecer em uso real: permissão de câmera negada
e nenhuma câmera disponível. Em ambos, ofereça a busca manual por nome ou CPF como
alternativa — o profissional não pode ficar travado porque a câmera falhou.

Verifique no npm a versão corrente do pacote antes de fixar.

### 3.6 HTTPS é obrigatório para o leitor funcionar

`getUserMedia` (acesso à câmera) só funciona em contexto seguro. Rode uma vez:

```powershell
herd secure pr-saude
```

e ajuste `APP_URL` para `https://pr-saude.test`. Sem isso, todo o fluxo de leitura de
pulseira (UC-07, UC-10) fica intestável.

## 4. Invariantes invioláveis

Estas quinze regras não são preferências. Se uma implementação sua violar qualquer uma,
ela está errada — independentemente de compilar e passar nos testes.

1. **A idade nunca é armazenada.** É atributo derivado de `data_nascimento`, calculado na
   aplicação (D-01, RN-02). Nenhuma coluna `idade` em nenhuma tabela.
2. **`registro_clinico` não aceita `UPDATE` nem `DELETE`.** Correção é adendo novo
   apontando para o original via `registro_retificado_id` (RN-16, RN-17, D-05).
3. **Nenhuma exclusão física de dado clínico.** Sempre `SoftDeletes` (D-08).
4. **Um único atendimento não finalizado por paciente por unidade**, garantido pela
   coluna gerada `ativo_key` + índice único — não por verificação em PHP (RN-07, D-07).
5. **A fila ordena por prioridade clínica, depois por horário de entrada.** Nunca só por
   ordem de chegada. A posição é **calculada na leitura**, nunca persistida (RN-10).
6. **Reclassificação preserva `entrou_em`.** O paciente não volta ao fim da fila (doc §7.5).
7. **Transição de status só pelo enum.** Toda mudança passa por
   `AlterarStatusAction`, valida contra `StatusAtendimento::podeTransitarPara()` e grava
   em `atendimento_status_historico` sem sobrescrever nada (RN-13, RN-15).
8. **`FINALIZADO` é terminal e exige desfecho** (RN-14).
9. **Alergia é verificada por princípio ativo, nunca por nome comercial** (RN-21).
10. **A mesma dose aprazada não é administrada duas vezes**, garantido pela
    `UNIQUE KEY uk_adm_aprazamento` (RN-20).
11. **Medicamento de alta vigilância exige um segundo profissional**, distinto do
    executor (RN-22).
12. **Resultado de exame só é visível ao paciente após liberação explícita** (RN-24).
13. **O paciente não executa nenhuma escrita**, exceto trocar a própria senha. Isso é
    garantido por **ausência de rota**, não por verificação em controller (RN-27).
14. **Data e hora de evento clínico vêm do servidor**, nunca do cliente (RN-29).
15. **O token da pulseira é permanente.** Gerado uma vez, nunca alterado, nunca
    reaproveitado, e o QR Code **não codifica id nem CPF** (RN-03, doc §8.2).

## 5. Arquitetura de autorização — o desenho exato

Você perguntou "gates ou spatie". A resposta é **os dois, em camadas diferentes**, porque
resolvem problemas diferentes:

| Camada | Ferramenta | Pergunta que responde | Exemplo |
|---|---|---|---|
| **Estática** | spatie: role → permission | "Este *papel* pode, em princípio, fazer isso?" | Recepcionista pode `paciente.criar`; não pode `prescricao.criar` |
| **Contextual** | Policies do Laravel | "Este *usuário* pode fazer isso **neste registro**?" | Só o profissional responsável altera o status **deste** atendimento |

A matriz da doc §2.3 do documento é a camada estática — é dado, e o administrador precisa
poder ajustá-la sem deploy. Mas as regras que realmente protegem o paciente são
contextuais e **não são expressáveis como permissão estática**:

- RN-12 — somente o profissional responsável (ou supervisor) altera o status do
  atendimento;
- RN-28 — acesso a paciente sem vínculo assistencial exige justificativa (*break the
  glass*);
- doc §13.5 — o "mínimo vital" (nome + **alergias**) é liberado a qualquer profissional em
  plantão, mesmo sem vínculo, porque negar lista de alergias a quem atende uma parada no
  corredor seria decisão de projeto com potencial letal;
- RN-26 — o paciente acessa exclusivamente os próprios dados.

Implementação obrigatória:

- **Permissions** nomeadas em `recurso.acao`: `paciente.criar`, `pulseira.imprimir`,
  `triagem.classificar`, `fila.atribuir`, `atendimento.alterar_status`,
  `prontuario.criar`, `prontuario.quebra_sigilo`, `prescricao.criar`,
  `medicamento.administrar`, `exame.solicitar`, `exame.liberar_resultado`,
  `auditoria.ler`, `usuario.gerenciar`. Semeie a matriz completa da doc §2.3 em um seeder.
- **Roles**: `recepcao`, `enfermeiro_triagem`, `enfermeiro_assistencial`,
  `tecnico_enfermagem`, `medico`, `laboratorio`, `admin`, `auditor`.
- **Policies**: `PacientePolicy` (com `verContexto`, `verMinimoVital`, `quebrarSigilo`),
  `AtendimentoPolicy`, `RegistroClinicoPolicy`, `PrescricaoPolicy`,
  `AdministracaoPolicy`, `ExameResultadoPolicy`.
- **Regra de composição:** a Policy checa a permission do spatie **e** o vínculo
  contextual. Permission sozinha nunca basta para dado clínico.
- **`Gate::before`** libera o `admin` — mas **não** para `prontuario.quebra_sigilo`, que
  deve permanecer auditada mesmo para administrador.
- **Global scope** `DoPacienteAutenticadoScope` nas entidades clínicas, para que um
  `where` esquecido em controller não vaze dado de outro paciente (doc §12.1).

## 6. Convenções de código

**Idioma.** Vocabulário de domínio em português, seguindo o `schema.sql`: `Paciente`,
`Atendimento`, `PrescricaoItem`, `administracao_medicamento`. Vocabulário de framework em
inglês: `index`, `store`, `update`, `AtendimentoController`. Interface e mensagens de erro
em pt-BR. Nomes de colunas exatamente como no DDL.

**Camadas.** A regra que sustenta tudo: **nenhuma escrita de dado clínico acontece fora de
uma Action.** Controllers e componentes Vue não chamam `Model::create()` para dado
clínico. Cada Action:

- fica em `app/Actions/<Contexto>/<Verbo><Objeto>Action.php`, com um único método público
  `execute()`;
- é `final`, com dependências injetadas pelo construtor;
- envolve a escrita em `DB::transaction()`;
- valida as regras de negócio e lança exceção de domínio nomeada (nunca `\Exception`);
- registra em auditoria;
- emite um evento de domínio, **mesmo que ninguém o escute ainda** — é isso que torna
  barata a migração futura para WebSocket (doc §7.7).

**Estrutura de diretórios.** Siga a doc §13.3 do documento. Ela já lista as Actions,
Services, Enums, Events, Exceptions e Policies esperados, com o requisito que cada uma
atende.

**Migrations.** Uma migration por tabela, nomeada pela tabela. Reproduza fielmente do
`schema.sql`: tipos, `NOT NULL`, `ENUM`, todas as FKs, todos os índices e **todas as 18
restrições `CHECK`**. As `CHECK` não são decorativas: são a garantia que sobrevive a bug
de aplicação e a condição de corrida.

Para a coluna gerada da RN-07, em MySQL:

```php
DB::statement("
    ALTER TABLE atendimento
    ADD COLUMN ativo_key BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE WHEN status IN ('FINALIZADO','CANCELADO') THEN NULL ELSE paciente_id END
        ) STORED,
    ADD UNIQUE KEY uk_atendimento_ativo (unidade_id, ativo_key)
");
```

Se o Passo 0 revelar que o projeto está em **SQLite**, use a solução nativa e mais limpa —
índice único parcial — e registre a troca em `docs/DECISOES.md`:

```sql
CREATE UNIQUE INDEX uk_atendimento_ativo ON atendimento (unidade_id, paciente_id)
WHERE status NOT IN ('FINALIZADO','CANCELADO');
```

**Views.** As três views do `schema.sql` (`vw_fila_ordenada`, `vw_carga_profissional`,
`vw_doses_pendentes`) são criadas por migration com `DB::statement`. Elas já resolvem a
ordenação da fila, a carga ponderada e o checklist de doses — não reimplemente essa
lógica em PHP.

**Privilégios de banco.** Gere `docs/privilegios.sql` com os `REVOKE UPDATE, DELETE` nas
tabelas append-only (`registro_clinico`, `auditoria_log`,
`atendimento_status_historico`, `administracao_medicamento`), conforme doc §9.1. Não execute
em desenvolvimento; documente como passo de implantação.

## 7. Plano de execução — 13 fases

Execute **na ordem**. Ao fim de cada fase, pare no checkpoint da seção 9 deste prompt.

---

### Fase 1 — Fundação do banco

**Objetivo.** Todo o modelo de dados no lugar, com as restrições ativas.

**Entregáveis**

- Migration que estende `users` conforme a seção 3.1 deste prompt.
- Migrations das 30 tabelas de domínio (34 do `schema.sql` menos as 4 de RBAC), na ordem
  de dependência das FKs.
- Migrations das 3 views.
- Instalação e migrations do spatie.
- Models Eloquent com `$fillable`, `casts` (enums, datas, decimais), relações completas e
  `SoftDeletes` onde houver `deleted_at`.
- `RegistroClinico` com `save()` e `delete()` sobrescritos para lançar
  `RegistroImutavelException`.
- Enums: `StatusAtendimento` (com `transicoesPermitidas()`, `podeTransitarPara()`,
  `ehTerminal()`, `rotuloPaciente()`), `CorPrioridade`, `ViaAdministracao`,
  `TipoRegistroClinico`, `SituacaoEspera`.
- Seeder de `classificacao_risco` com os cinco níveis de Manchester e os tempos-alvo
  0/10/60/120/240 min.
- Seeders de catálogo: `medicamento` (≥ 20, incluindo ≥ 4 de alta vigilância — insulina,
  heparina, opioide, cloreto de potássio), `exame` (≥ 15), `queixa` (≥ 20), `cid10`
  (≥ 50 códigos comuns de urgência).
- Factories de todos os models, com *states* úteis:
  `Paciente::factory()->comAlergia()`, `->comAtendimentoAtivo()`, `->comSenhaProvisoria()`.

**Definition of done**

- `php artisan migrate:fresh --seed` roda limpo.
- **Porte `verificacao/testes_schema.sh` para um teste Pest** que reproduza os 14 testes
  negativos e os 2 positivos, e prove que o banco recusa cada violação. Todos passando.
- `php artisan db:show --counts` exibe as 30 tabelas de domínio + 5 do spatie.

---

### Fase 2 — Autenticação e autorização

**Objetivo.** Dois guards isolados e a matriz de permissões da doc §2.3 semeada.

**Entregáveis**

- Guards `web` (equipe) e `paciente` em `config/auth.php`, com providers distintos sobre o
  mesmo model `User`.
- Expiração de sessão: 30 min equipe, 15 min paciente (RNF-09, RNF-10).
- `config/hashing.php` com Argon2id.
- Seeder das 8 roles e de todas as permissions, reproduzindo a matriz da doc §2.3 célula por
  célula.
- As 6 Policies da seção 5 deste prompt, registradas.
- `Gate::before` liberando `admin`, exceto `prontuario.quebra_sigilo`.
- Middlewares `SenhaProvisoria`, `RegistrarAuditoria`, `ExigirVinculoAssistencial`.
- `AuditoriaService` completo, com mascaramento de `password`, `token_pulseira`, `cpf` e
  `cns` (doc §14.3).
- Layout base Inertia com navegação por perfil: cada usuário vê só o que pode acessar.

**Definition of done**

- Teste Pest percorrendo a matriz RBAC: para cada role, o que ela pode e **o que ela não
  pode**. Os testes negativos são os que importam.
- Teste provando que um usuário autenticado no guard `paciente` recebe `false` em
  qualquer `can()`.

---

### Fase 3 — Cadastro de paciente

**Objetivo.** UC-01 completo, incluindo os fluxos alternativos A1 a A5.

**Entregáveis**

- `CadastrarPacienteAction`: em **uma transação** cria o `User`, o `Paciente`, a credencial
  (login = CPF, senha = `DDMMAAAA` da data de nascimento, `senha_provisoria = true`), gera
  o `token_pulseira` e registra auditoria (RN-04, RN-05).
- Validação de CPF com dígito verificador — regra customizada, não regex.
- Cadastro sem CPF: gera `codigo_provisorio` no formato `NI-2026-0031` e marca
  `identificacao_provisoria` (RF-04). Esse código é o login.
- `RegularizarIdentificacaoAction`: vincula o CPF real depois, preservando todo o histórico
  (RN-30).
- Atributo derivado `idade`, com **granularidade adaptativa**: dias até 30 dias, meses até
  24 meses, anos a partir daí (D-01).
- Registro de alergias e condições crônicas, exibidas em destaque em **toda** tela do
  atendimento (RF-11).
- Busca por nome, CPF, CNS, data de nascimento e token de pulseira (RF-09).
- Páginas Inertia: `Pacientes/Index`, `Pacientes/Create`, `Pacientes/Show`.

**Definition of done**

- Testes: CPF inválido recusado; CPF duplicado oferece o cadastro existente em vez de
  duplicar; cadastro sem CPF funciona; a credencial é criada na mesma transação; falha na
  geração do token faz rollback e **não deixa paciente órfão**.
- Teste da idade em: véspera do aniversário, dia do aniversário, 29/02, recém-nascido.

---

### Fase 4 — Token, QR Code e pulseira

**Objetivo.** UC-07 completo e a pulseira imprimível.

**Entregáveis**

- `TokenPulseiraService`: corpo de 22 caracteres base62 via `random_int` (**nunca**
  `rand`/`mt_rand`) + sufixo HMAC-SHA256 de 4 caracteres. Validação com `hash_equals`.
  Chave em `config/app.php`, lida do `.env` (doc §8.2.1).
- `GerarPulseiraService`: QR Code **versão 5, correção de erro nível Q, lado de 22 mm**
  (doc §8.5). Instale `endroid/qr-code` e `barryvdh/laravel-dompdf`.
- Template da pulseira 25 mm × 280 mm reproduzindo o layout da doc §8.4: faixa de prioridade
  na borda superior inteira, nome na maior fonte, data de nascimento, idade congelada,
  sexo, número do atendimento, admissão, QR Code e **faixa de alergia na última linha com
  marcação redundante**.
- **Não imprima CPF, CNS, CID nem endereço.** A doc §8.4 justifica cada exclusão.
- Registro de toda impressão em `pulseira_impressao`, com motivo (RF-15). Reimpressão usa
  **o mesmo token** (RF-16).
- Rota `GET /p/{token}` implementando o fluxograma da doc §8.3 **exatamente**, incluindo: token
  inválido e token válido sem sessão produzem respostas **indistinguíveis**, para que a
  rota não sirva de oráculo de enumeração.
- Composable `useQrScanner` conforme seção 3.5 deste prompt, e componente de leitura com confirmação de
  identidade em duas etapas antes de ação crítica (RF-44).

**Definition of done**

- Testes do token: 20.000 gerados sem colisão; 1 caractere alterado é rejeitado; token
  truncado é rejeitado; token assinado com outra chave é rejeitado.
- Teste provando que `GET /p/{token}` sem autenticação **não vaza nenhum dado** e redireciona.
- Validação manual: imprima uma pulseira e **leia o QR com um celular**. Reporte o resultado.

---

### Fase 5 — Atendimento e máquina de estados

**Objetivo.** Ciclo de vida do atendimento sob controle da máquina de estados.

**Entregáveis**

- `AbrirAtendimentoAction` com numeração sequencial por ano e unidade (`2026-000148`),
  gerada de forma segura sob concorrência.
- `AlterarStatusAction` conforme doc §6.3, calculando `permanencia_segundos` e emitindo
  `StatusAtendimentoAlterado`.
- `FinalizarAtendimentoAction` exigindo desfecho.
- Área de Atendimentos do paciente, separando finalizados e em andamento (RF-18).
- Linha do tempo consolidada do atendimento (RF-22).

**Definition of done**

- Teste de **todas** as transições da tabela da doc §6.2: as permitidas passam, as demais
  lançam `TransicaoInvalidaException`.
- Teste de alcançabilidade: todo estado não terminal alcança `FINALIZADO` — sem *deadlock*.
- Teste de concorrência: duas tentativas simultâneas de abrir atendimento para o mesmo
  paciente — uma passa, a outra recebe violação de índice único.

---

### Fase 6 — Triagem e classificação de risco

**Entregáveis**

- `RealizarTriagemAction` e `ReclassificarRiscoAction` conforme doc §7.5.
- Sinais vitais em `sinal_vital` (tabela própria, série temporal — D-06), com os `CHECK` de
  faixa ativos.
- Reclassificação **encadeada** por `triagem_anterior_id`; a triagem anterior permanece
  intacta.
- `AvaliadorEsperaService` (doc §7.3.1): classifica a criticidade da espera **sem reordenar a
  fila**. Envelhecimento automático de prioridade é proibido — a justificativa é clínica e
  está no doc §7.3.1.
- Disparo de reimpressão de pulseira na reclassificação (RN-09) e do fluxo de emergência
  quando a classificação é vermelho (RN-11).

**Definition of done**

- Teste: reclassificar de verde para laranja **preserva `entrou_em`** e reordena a fila.
- Teste: classificação vermelho move o atendimento direto para `EM_ATENDIMENTO`, sem fila.
- Teste: a triagem anterior continua legível depois da reclassificação.

---

### Fase 7 — Fila e painel do profissional

**Objetivo.** O módulo mais visível do sistema.

**Entregáveis**

- `AtribuirProfissionalAction` e `TransferirFilaAction` — a transferência **preserva
  `entrou_em`** e cria novo `fila_item` com `transferido_de_id`.
- Tela de atribuição (UC-05) mostrando, por profissional: disponibilidade, quantidade
  aguardando, **composição da fila por cor**, carga ponderada e espera estimada. Reproduza
  o mockup do doc §7.4.
- Sugestão automática do profissional de menor carga ponderada (RF-28).
- Painel do profissional: nome, cor da pulseira **com rótulo**, horário de entrada,
  posição e status (RF-29).
- Sinalização de tempo-alvo excedido, com sugestão de reavaliação de risco (RF-33).
- Polling com `usePoll(10000, { only: ['fila'] })`.
- Estimativa de espera pela **média móvel de 30 dias** do próprio profissional por cor —
  não constante fixa.

**Definition of done**

- Teste reproduzindo o cenário da doc §5.4.1: cinco pacientes inseridos em ordem inversa à
  prioridade, e a fila sai correta — laranja primeiro, verdes desempatados por chegada.
- Teste: carga ponderada de 1 laranja + 1 amarelo + 2 verdes = 11.
- Teste: transferência entre filas não penaliza a posição.

---

### Fase 8 — Prontuário e evolução

**Entregáveis**

- `RegistrarNotaClinicaAction` com SOAP em quatro colunas (doc §9.2).
- `RetificarRegistroAction` criando `ADENDO` e mantendo o original visível e marcado
  (doc §9.3).
- `HashEncadeadoService` com forma canônica de JSON, `hash_anterior` e `verificarCadeia()`
  (doc §9.4).
- *Snapshot* de `autor_nome` e `autor_conselho` em cada registro.
- Diagnósticos com CID-10 e natureza (`SUSPEITA`, `DEFINITIVO`, `DIFERENCIAL`).
- Campo `sigiloso`, com auditoria de quem marcou (doc §9.6).
- Prontuário consolidado atravessando todos os atendimentos do paciente (RF-51).
- Exportação em PDF (RF-52).
- Job agendado de verificação de integridade da cadeia.

**Definition of done**

- Teste: `UPDATE` em registro persistido lança `RegistroImutavelException`.
- Teste: `delete()` lança exceção.
- Teste: após retificação, o conteúdo original permanece inalterado.
- Teste: alteração feita **por fora da aplicação** (via `DB::table()->update()`) é detectada
  como `CONTEUDO_ALTERADO`; remoção de registro do meio é detectada como `ELO_ROMPIDO`.

---

### Fase 9 — Medicamentos

**Entregáveis**

- `PrescreverAction`, `SuspenderPrescricaoAction`, `RegistrarAdministracaoAction` conforme
  doc §10.4.
- `AprazamentoService` com **ancoragem em horários redondos** (doc §10.5) — 6/12/18/00 em vez
  do minuto do clique. Medicação "se necessário" não é aprazada.
- Verificação de alergia **por princípio ativo**, com bloqueio liberável só por
  justificativa registrada e notificação ao prescritor (RN-21).
- Dupla checagem para alta vigilância, recusando o próprio executor como conferente
  (RN-22).
- Divergência de dose: **permitida**, sinalizada, com observação obrigatória (RN-23). Note
  a assimetria deliberada com a RN-21 e o motivo — fadiga de alerta (doc §10.4).
- Registro de não-administração com motivo (RF-58).
- Tela de conferência dos nove certos, reproduzindo o mockup do doc §10.3.
- Checklist de doses do turno a partir de `vw_doses_pendentes` (RF-60).
- O aprazamento sai de `PENDENTE` **na mesma transação** da administração.

**Definition of done**

- Testes de todos os casos da doc §15.2: alergia bloqueia; alergia com justificativa marca
  `alerta_alergia_sobreposto`; dose duplicada é recusada; alta vigilância sem conferente é
  recusada; executor como próprio conferente é recusado; o aprazamento vira `ADMINISTRADA`.

---

### Fase 10 — Clínica e exames

**Entregáveis**

- `SolicitarExameAction`, `RegistrarResultadoAction`, `LiberarResultadoAction`.
- Ciclo de vida completo: `SOLICITADO → COLETADO → EM_EXECUCAO → CONCLUIDO → LIBERADO`.
- Resultado estruturado por analito, com faixa de referência **gravada no resultado** —
  não apenas no catálogo (doc §11.3).
- `AvaliadorResultadoService` com faixas críticas **em tabela parametrizável**, não em
  constante de código (doc §11.2).
- Valor crítico: notificação prioritária ao solicitante e bloqueio de liberação ao
  paciente antes da ciência médica (RN-25).
- Anexos com `hash_sha256`, armazenados **fora do document root**.
- Fila do laboratório, com urgentes primeiro.

**Definition of done**

- Teste: `visivel_ao_paciente = true` sem `liberado_por` é recusado pelo banco.
- Teste: resultado com valor crítico não pode ser liberado antes da ciência médica.
- Teste: anexo fora do `public/` e não acessível por URL direta.

---

### Fase 11 — Portal do paciente

**Objetivo.** Somente leitura, garantido por arquitetura e não por verificação.

**Entregáveis**

- `routes/portal.php` exatamente como a doc §12.1: **todas as rotas de dado são `GET`**. As
  únicas rotas `POST` são troca de senha e logout.
- Global scope `DoPacienteAutenticadoScope` nas entidades clínicas.
- Login implementando as mitigações M-1 a M-12 do doc §12.2.3. As três indispensáveis:
  - **M-3** — no primeiro acesso, exigir que a sessão carregue o `pulseira_token` válido.
    Isso transforma o esquema fraco (data de nascimento, 15,3 bits) em dois fatores, porque
    o token da pulseira vale 131 bits. **É a mitigação mais importante do sistema; leia a
    doc §12.2.2 antes de implementar.**
  - **M-6/M-7** — mensagem de erro idêntica para CPF inexistente e senha errada, com custo
    computacional uniforme para não criar oráculo por tempo de resposta.
  - **M-12** — na troca, recusar a própria data de nascimento, o CPF e senha com menos de
    8 caracteres.
- Rate limit por conta **e** por IP, com bloqueio progressivo (M-4, M-5).
- Telas conforme o mockup da doc §12.3, com **linguagem acessível**: "na veia", não "IV";
  "Aguardando realização de exame", não `AGUARDANDO_EXAME` — use `rotuloPaciente()`.
- Exibir tempo decorrido, **nunca previsão de atendimento** (doc §12.3, decisão 3).
- Resultado não liberado aparece como "resultado em análise médica"; registro sigiloso é
  omitido **sem indicar que existe**.

**Definition of done**

- Todos os testes de `PortalPacienteTest` da doc §15.2, incluindo o **teste estrutural** que
  varre a tabela de rotas e prova que as únicas rotas de escrita sob `auth:paciente` são
  `portal/senha` e `portal/sair`.
- Teste: paciente A recebe 404 ao tentar o atendimento do paciente B.

---

### Fase 12 — Auditoria e indicadores

**Entregáveis**

- Auditoria de **leitura**, não só de escrita — é o controle que de fato protege dado de
  saúde, porque o dano típico é bisbilhotagem, não alteração (doc §14.3).
- Fluxo de quebra de sigilo com justificativa obrigatória (RN-28).
- Tela de auditoria respondendo "quem acessou os dados deste paciente nos últimos 90
  dias?", usando o índice `ix_audit_paciente`.
- Os 9 indicadores da doc §7.6, incluindo a **taxa de reclassificação** — o melhor termômetro
  da qualidade da própria triagem.
- Ao gerar qualquer gráfico, leia antes a skill `dataviz`.

**Definition of done**

- Teste: leitura de prontuário gera registro em `auditoria_log`.
- Teste: acesso sem vínculo assistencial sem justificativa é recusado.
- Teste: `password`, `token_pulseira`, `cpf` e `cns` aparecem mascarados no log.

---

### Fase 13 — Fechamento

**Entregáveis**

- Cobertura ≥ 80 % em `app/Actions`, `app/Services` e `app/Enums` (RNF-19).
- Auditoria de acessibilidade: contraste AA e **nenhuma informação transmitida só por cor**
  (RNF-15). Verifique cada uso de cor de prioridade.
- Navegação completa por teclado no fluxo assistencial (RNF-16).
- `docs/privilegios.sql` e checklist de implantação.
- `README.md` com setup, seeds e usuários de demonstração.
- Seeder de demonstração: 1 unidade, 8 profissionais cobrindo todas as roles, 30 pacientes,
  15 atendimentos em estados variados, filas povoadas.

**Definition of done**

- `php artisan test` verde.
- `php artisan migrate:fresh --seed` reproduz um ambiente navegável de ponta a ponta.

## 8. Anti-padrões proibidos

Se você se pegar fazendo qualquer um destes, pare e reconsidere:

| Não faça | Faça |
|---|---|
| Coluna `idade` em qualquer tabela | Atributo derivado da data de nascimento |
| Tabela única misturando prescrição e administração | Três tabelas: `prescricao_item`, `aprazamento`, `administracao_medicamento` (D-04) |
| Coluna `posicao` persistida em `fila_item` | Posição calculada por `ROW_NUMBER()` na leitura |
| `UPDATE` em `registro_clinico` | Adendo novo referenciando o original |
| `Model::create()` de dado clínico em controller | Sempre via Action, em transação |
| Verificar "é paciente?" dentro do controller do portal | Ausência de rota de escrita |
| Codificar id ou CPF no QR Code | Token opaco de 131 bits |
| Imprimir CPF na pulseira | Nome + data de nascimento já são dois identificadores |
| Envelhecer prioridade automaticamente na fila | Sinalizar a espera e sugerir reclassificação |
| Bloquear toda divergência de dose | Bloquear alergia; sinalizar divergência (fadiga de alerta) |
| Validar regra de negócio no cliente | Só no servidor; cliente apenas formata |
| Cor como único indicador de prioridade | Cor + rótulo + ícone |
| `rand()` ou `mt_rand()` para o token | `random_int()` |
| `==` para comparar HMAC | `hash_equals()` |
| Data/hora vinda do cliente | `now()` no servidor |
| Migration sem as restrições `CHECK` | Todas as 18 reproduzidas |
| `throw new \Exception` | Exceção de domínio nomeada |
| Comentário explicando *o que* o código faz | Comentário explicando *por quê*, citando RF/RN |

## 9. Protocolo de trabalho

1. **Comece criando dois arquivos** e mantenha-os atualizados durante todo o projeto:
   - `CLAUDE.md` — contexto permanente: stack detectada no Passo 0, as 15 invariantes da
     seção 4 deste prompt, as convenções da seção 6 deste prompt, o desenho de autorização da seção 5 deste prompt. É o que faz uma sessão nova
     retomar o trabalho sem perder as regras.
   - `docs/PROGRESSO.md` — checklist das 13 fases, com o que foi entregue, os testes que
     passam e as pendências. Atualize ao fim de cada fase.
   - `docs/DECISOES.md` — toda divergência do documento, com a justificativa.
2. **Um commit por fase**, no mínimo. Mensagem citando os RF/RN atendidos.
3. **Checkpoint ao fim de cada fase.** Pare e reporte: o que foi feito, quais testes
   passam, quais decisões você tomou por conta própria, o que precisa da minha decisão.
   **Não inicie a fase seguinte sem meu OK.**
4. **Nunca invente regra de negócio.** Se o documento não cobre um caso, pergunte. Em
   sistema clínico, adivinhar regra é criar risco assistencial.
5. **Teste junto com o código, nunca ao final.** A doc §15.2 do documento já traz os testes
   críticos escritos — porte-os, não os reinvente.
6. **Se algo neste prompt estiver tecnicamente errado** — API que mudou, pacote
   incompatível, comando que não existe na versão instalada — **me diga em vez de
   contornar silenciosamente**. Verifique a documentação da versão realmente instalada
   antes de assumir uma assinatura de método.

## 10. Definition of done global

O sistema está pronto quando:

- [ ] As 15 invariantes da seção 4 deste prompt estão garantidas por **teste automatizado**, não por
      inspeção visual.
- [ ] Nenhuma regra crítica depende exclusivamente de validação em PHP: as que podem
      estar no banco, estão.
- [ ] O portal do paciente não tem uma única rota de escrita além de senha e logout, e
      isso é provado por teste estrutural.
- [ ] Auditoria registra leitura e escrita, com dado sensível mascarado.
- [ ] Cobertura ≥ 80 % na camada de domínio.
- [ ] `php artisan migrate:fresh --seed` produz um ambiente navegável de ponta a ponta.
- [ ] `docs/PROGRESSO.md` e `docs/DECISOES.md` refletem o estado real.

**Comece agora pelo Passo 0 (seção 2 deste prompt) e pare para o meu OK antes de instalar qualquer coisa.**

# ═══════════════ FIM DO PROMPT ═══════════════
