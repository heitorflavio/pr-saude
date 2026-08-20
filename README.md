# PR Saúde — Sistema de Gestão Hospitalar

Aplicação de pronto atendimento construída com Laravel 12, PHP 8.4, Vue 3, Inertia e
MySQL 8. O sistema cobre cadastro, pulseira com QR Code, triagem Manchester, fila,
prontuário imutável, medicamentos, exames, portal do paciente, auditoria e indicadores.

## Requisitos

- Docker Desktop com integração WSL2 habilitada
- Uma distribuição Linux no WSL2, como Ubuntu

PHP, Composer, MySQL, Node.js e npm são fornecidos pelos contêineres do Laravel Sail e
não precisam ser instalados no Windows ou no WSL.

SQLite não é suportado: as invariantes usam `CHECK`, coluna gerada, índices funcionais e
views com `ROW_NUMBER()` do MySQL.

## Instalação no Windows com Laravel Sail

1. Baixe e instale o [Docker Desktop](https://www.docker.com/products/docker-desktop/).
2. No Docker Desktop, habilite a integração com sua distribuição WSL2.
3. Abra o terminal da distribuição Linux, acesse a pasta do projeto e execute:

```bash
bash scripts/setup-sail.sh
```

O script resolve o primeiro `composer install` com uma imagem Docker temporária, cria o
`vendor/bin/sail`, inicia PHP e MySQL, gera as chaves locais e executa `npm ci`. Ele não
apaga nem recria bancos existentes.

Na primeira execução, prepare o banco de demonstração:

```bash
./vendor/bin/sail artisan migrate --seed
```

Windows:

```bash
docker compose up -d
docker compose exec laravel.test composer install
docker compose exec laravel.test npm ci
docker compose exec laravel.test php artisan migrate --seed
docker compose restart laravel.test
docker compose exec -d laravel.test npm run dev
```

A aplicação fica em `http://localhost:8080`. O MySQL é publicado em `localhost:3307`
apenas para clientes externos; o Laravel usa `mysql:3306` dentro da rede Docker.

Consulte [Sail](docs/SAIL.md) para os comandos diários, solução de problemas e o fluxo
de bootstrap de um clone sem a pasta `vendor`.

### Alternativa com Laravel Herd

Se optar pelo Herd, instale PHP, Composer, Node.js e MySQL localmente, troque
`DB_HOST` para `127.0.0.1` e `APP_URL` para `https://pr-saude.test`. Não execute Herd e
Sail nas mesmas portas ao mesmo tempo.

## Ambiente de demonstração

`DatabaseSeeder` cria uma unidade, oito profissionais, 30 pacientes e 15 atendimentos em
estados variados. A senha das contas abaixo é `password`:

| Perfil                  | Login              |
| ----------------------- | ------------------ |
| Recepção                | `recepcao.demo`    |
| Enfermeiro de triagem   | `triagem.demo`     |
| Enfermeiro assistencial | `enfermagem.demo`  |
| Técnico de enfermagem   | `tecnico.demo`     |
| Médico                  | `medico.demo`      |
| Laboratório             | `laboratorio.demo` |
| Administrador           | `admin`            |
| Auditor                 | `auditor.demo`     |

As contas são exclusivas para ambiente local. Altere ou remova todas antes de publicar.
O primeiro paciente de demonstração usa CPF `31000000057` e senha inicial `15011950`;
o primeiro acesso ao portal também exige ler o QR Code da pulseira na ficha do paciente.

## Testes e qualidade

```bash
./vendor/bin/sail test
./vendor/bin/sail pint --test
./vendor/bin/sail npm run lint
./vendor/bin/sail npm run build
```

A cobertura normativa inclui somente `app/Actions`, `app/Services` e `app/Enums`, como
configurado em `phpunit.xml`, e deve permanecer em 80% ou mais:

```bash
SAIL_XDEBUG_MODE=coverage ./vendor/bin/sail test --coverage --min=80
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
