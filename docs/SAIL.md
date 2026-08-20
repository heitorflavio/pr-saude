# Ambiente local com Laravel Sail

Este projeto usa o Laravel Sail para manter PHP, Composer, Node.js, npm e MySQL dentro
de contêineres. No Windows, instale somente Docker Desktop e WSL2 e habilite a
integração do Docker Desktop com a distribuição Ubuntu.

Execute os comandos desta página no terminal WSL, não no PowerShell.

## Primeiro uso

Entre no diretório do projeto e execute:

```bash
bash scripts/setup-sail.sh
```

Em um clone novo, `vendor/bin/sail` ainda não existe. O script primeiro executa
`composer install` na imagem descartável `laravelsail/php84-composer`, sem instalar
Composer ou PHP no computador. Depois que `vendor` existe, os demais comandos passam a
ser executados pelo Sail.

O script realiza somente estas ações:

1. verifica se o Docker está acessível pelo WSL;
2. cria `.env` a partir de `.env.example`, se necessário;
3. cria `vendor` com um contêiner temporário, se necessário;
4. cria a imagem e preenche os volumes Linux de dependências;
5. inicia os serviços definidos em `compose.yaml`;
6. gera `APP_KEY` e `PULSEIRA_KEY` quando estiverem vazias;
7. instala as dependências do frontend com `npm ci`.

As migrações não são automáticas para evitar alterações inesperadas em um banco já
existente. Para preparar um banco novo com os dados de demonstração:

```bash
./vendor/bin/sail artisan migrate --seed
```

## Desenvolvimento diário

Inicie Laravel, MySQL e Vite em segundo plano. Este é o único comando necessário no
uso normal:

```bash
./vendor/bin/sail up -d
```

O Vite é gerenciado como um serviço do Compose, incluindo reinício automático. Não
execute `npm run dev` manualmente.

Comandos frequentes:

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail composer require fornecedor/pacote
./vendor/bin/sail npm install pacote
./vendor/bin/sail test
./vendor/bin/sail shell
./vendor/bin/sail logs -f
```

No PowerShell, use os equivalentes abaixo (sem precisar abrir um terminal WSL):

```powershell
docker compose up -d
docker compose exec laravel.test composer install
docker compose exec laravel.test npm ci
docker compose exec laravel.test php artisan migrate --seed
docker compose restart laravel.test vite
docker compose logs -f laravel.test
```

As pastas `vendor` e `node_modules` usadas pela aplicação ficam em volumes Linux do
Docker. Isso reduz bastante a lentidão do Docker Desktop no Windows; o restante do
código continua no diretório do projeto e pode ser editado normalmente.

Pare os contêineres sem apagar o banco:

```bash
./vendor/bin/sail stop
```

Evite `./vendor/bin/sail down -v`: a opção `-v` remove o volume persistente e apaga o
banco MySQL local.

## Endereços e portas

| Serviço | Endereço no Windows | Endereço entre contêineres |
|---|---|---|
| Aplicação | `http://localhost:8080` | `laravel.test:80` |
| Vite | `http://localhost:5173` | `vite:5173` |
| MySQL | `localhost:3307` | `mysql:3306` |

O Laravel deve usar `DB_HOST=mysql` e `DB_PORT=3306`. A porta `3307` só é necessária
para conectar um cliente externo, como DBeaver ou TablePlus. Na primeira criação do
volume, o contêiner também cria `prsaude_test`, usado exclusivamente pela suíte de
testes.

## Problemas comuns

### `vendor/bin/sail` não existe

Execute novamente o bootstrap:

```bash
bash scripts/setup-sail.sh
```

### Docker não está acessível no WSL

Abra Docker Desktop, acesse **Settings > Resources > WSL Integration** e habilite a
distribuição Ubuntu. Depois valide no WSL:

```bash
docker info
docker compose version
```

### Porta já está em uso

Altere apenas a porta publicada no `.env`, por exemplo:

```dotenv
APP_PORT=8081
FORWARD_DB_PORT=3308
```

Mantenha `DB_HOST=mysql` e `DB_PORT=3306`, pois esses valores pertencem à rede interna
do Docker.

### Dependências ou imagem desatualizadas

```bash
./vendor/bin/sail composer install
./vendor/bin/sail npm ci
./vendor/bin/sail build --no-cache
```

O rebuild não apaga o banco. A remoção do volume ocorre somente quando `down -v` é
executado explicitamente.

Se estiver usando apenas o PowerShell:

```powershell
docker compose run --rm --no-deps laravel.test composer install
docker compose run --rm --no-deps laravel.test npm ci
docker compose up -d --force-recreate laravel.test
```
