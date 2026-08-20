#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
BOOTSTRAP_IMAGE="${SAIL_BOOTSTRAP_IMAGE:-laravelsail/php84-composer:latest}"

cd "$PROJECT_ROOT"

if ! command -v docker >/dev/null 2>&1; then
    echo "Erro: Docker não foi encontrado. Execute este script no WSL com o Docker Desktop ativo." >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Erro: o Docker não está em execução ou não está integrado a esta distribuição WSL." >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    cp .env.example .env
    echo "Arquivo .env criado a partir de .env.example."
fi

# Em um clone novo, o próprio executável do Sail ainda não existe. Esta imagem
# descartável instala apenas o necessário para criar vendor/bin/sail.
if [[ ! -f vendor/bin/sail ]]; then
    echo "Instalando dependências PHP com o contêiner temporário ${BOOTSTRAP_IMAGE}..."
    docker run --rm \
        --user "$(id -u):$(id -g)" \
        --env COMPOSER_HOME=/tmp/composer \
        --volume "$PROJECT_ROOT:/var/www/html" \
        --workdir /var/www/html \
        "$BOOTSTRAP_IMAGE" \
        composer install --ignore-platform-reqs --no-interaction --no-scripts
fi

echo "Construindo a imagem do Sail..."
docker compose build laravel.test

# vendor e node_modules usam volumes Linux para evitar o custo de milhares de
# acessos ao NTFS pelo Docker Desktop. Preenche os volumes antes de iniciar o app.
docker compose run --rm --no-deps \
    --env SUPERVISOR_PHP_USER=root \
    laravel.test \
    chown -R "${WWWUSER:-1000}:${WWWGROUP:-1000}" \
    /var/www/html/vendor /var/www/html/node_modules

echo "Instalando dependências PHP no volume do Docker..."
docker compose run --rm --no-deps laravel.test composer install --no-interaction

echo "Instalando dependências do frontend no volume do Docker..."
docker compose run --rm --no-deps laravel.test npm ci

echo "Compilando os assets do frontend..."
docker compose run --rm --no-deps laravel.test npm run build

echo "Iniciando os serviços do Sail..."
bash ./vendor/bin/sail up -d

if ! grep -Eq '^APP_KEY=.+$' .env; then
    bash ./vendor/bin/sail artisan key:generate --force
fi

if ! grep -Eq '^PULSEIRA_KEY=.+$' .env; then
    pulseira_key="$(bash ./vendor/bin/sail php -r 'echo "base64:".base64_encode(random_bytes(32));')"

    if grep -q '^PULSEIRA_KEY=' .env; then
        sed -i "s|^PULSEIRA_KEY=.*$|PULSEIRA_KEY=${pulseira_key}|" .env
    else
        printf '\nPULSEIRA_KEY=%s\n' "$pulseira_key" >> .env
    fi

    echo "PULSEIRA_KEY gerada. Não a altere depois de emitir pulseiras."
fi

cat <<'EOF'

Ambiente preparado.

Próximos comandos:
  ./vendor/bin/sail artisan migrate --seed

Aplicação: http://localhost:8080
EOF
