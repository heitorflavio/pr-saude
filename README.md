# PR Saúde — Sistema de Gestão Hospitalar

Aplicação de pronto atendimento construída com Laravel 12, PHP 8.4, Vue 3, Inertia e
MySQL 8. O sistema cobre cadastro, pulseira com QR Code, triagem Manchester, fila,
prontuário imutável, medicamentos, exames, portal do paciente, auditoria e indicadores.

## Requisitos

- PHP 8.4 com `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` e `gd`
- Composer 2
- MySQL 8.0.16 ou superior
- Node.js 20 ou superior e npm

SQLite não é suportado: as invariantes usam `CHECK`, coluna gerada, índices funcionais e
views com `ROW_NUMBER()` do MySQL.

## Instalação local

```powershell
Copy-Item .env.example .env
composer install
npm install
herd php artisan key:generate
herd php -r "echo 'PULSEIRA_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Copie a linha `PULSEIRA_KEY` gerada para o `.env`. Essa chave não deve ser rotacionada
após a emissão de pulseiras: fazê-lo invalida todos os QR Codes existentes.

Crie apenas os schemas autorizados e configure as credenciais no `.env`:

```sql
CREATE DATABASE prsaude CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE prsaude_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Depois prepare o banco e os assets:

```powershell
herd php artisan migrate:fresh --seed
npm run build
```

Com Laravel Herd, a aplicação fica em `https://pr-saude.test`. Sem Herd, execute
`composer run dev` e use a URL informada pelo servidor.

## Ambiente de demonstração

`DatabaseSeeder` cria uma unidade, oito profissionais, 30 pacientes e 15 atendimentos em
estados variados. A senha das contas abaixo é `Demo@2026`:

| Perfil | Login |
|---|---|
| Recepção | `recepcao.demo` |
| Enfermeiro de triagem | `triagem.demo` |
| Enfermeiro assistencial | `enfermagem.demo` |
| Técnico de enfermagem | `tecnico.demo` |
| Médico | `medico.demo` |
| Laboratório | `laboratorio.demo` |
| Administrador | `admin` |
| Auditor | `auditor.demo` |

As contas são exclusivas para ambiente local. Altere ou remova todas antes de publicar.
O primeiro paciente de demonstração usa CPF `31000000057` e senha inicial `15011950`;
o primeiro acesso ao portal também exige ler o QR Code da pulseira na ficha do paciente.

## Testes e qualidade

```powershell
herd php artisan test
herd php vendor/bin/pint --test
npm run lint
npm run build
```

A cobertura normativa inclui somente `app/Actions`, `app/Services` e `app/Enums`, como
configurado em `phpunit.xml`, e deve permanecer em 80% ou mais:

```powershell
$env:XDEBUG_MODE='coverage'
herd php vendor/bin/pest --coverage --min=80
```

Os testes usam exclusivamente `prsaude_test`. Consulte [DECISOES.md](docs/DECISOES.md)
para conflitos resolvidos e [IMPLANTACAO.md](docs/IMPLANTACAO.md) para produção.

## Controles essenciais

- Dado clínico é escrito somente por Actions transacionais.
- Registro clínico e auditoria são append-only; retificação cria adendo.
- O portal do paciente não possui rota de escrita clínica.
- Acesso sem vínculo requer quebra de sigilo justificada e auditada.
- Anexos clínicos ficam em armazenamento privado e são conferidos por SHA-256.
- Posição de fila é calculada na leitura; nunca é persistida.
