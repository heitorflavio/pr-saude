#!/usr/bin/env bash

set -e

TEST_DATABASE="${TEST_DB_DATABASE:-prsaude_test}"

mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`${TEST_DATABASE}\`
        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOSQL

if [[ -n "${MYSQL_USER:-}" ]]; then
    mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
        GRANT ALL PRIVILEGES ON \`${TEST_DATABASE}\`.* TO '${MYSQL_USER}'@'%';
EOSQL
fi
