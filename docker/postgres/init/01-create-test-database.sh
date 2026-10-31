#!/bin/sh
# Crea il database usato dai test (phpunit.xml), separato da quello di sviluppo.
set -e
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-SQL
    CREATE DATABASE ${POSTGRES_DB}_test OWNER "$POSTGRES_USER";
SQL
