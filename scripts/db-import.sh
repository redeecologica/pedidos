#!/usr/bin/env bash
# Importa um dump (padrão: o mais recente em dumps/) no banco LOCAL 'pedidos'.
# Destrói e recria o banco local — nunca toca produção.
set -euo pipefail
cd "$(dirname "$0")/.."

DUMP="${1:-}"
if [[ -z "$DUMP" ]]; then
  # shellcheck disable=SC2012  # ls -t é suficiente para nomes controlados por nós
  DUMP="$(ls -t dumps/*.sql.gz 2>/dev/null | head -1 || true)"
fi
if [[ -z "$DUMP" || ! -f "$DUMP" ]]; then
  echo "ERRO: nenhum dump encontrado. Rode scripts/db-pull.sh antes (ou passe o arquivo: scripts/db-import.sh dumps/arquivo.sql.gz)." >&2
  exit 1
fi

echo ">> Subindo serviço de banco..."
docker compose up -d --wait db

echo ">> Recriando banco local 'pedidos' (charset latin1, igual ao default do banco de produção) e importando $DUMP ..."
docker compose exec -T -e MYSQL_PWD=root db mysql -uroot \
  -e "DROP DATABASE IF EXISTS pedidos; CREATE DATABASE pedidos CHARACTER SET latin1 COLLATE latin1_general_ci;"
gunzip -c "$DUMP" | docker compose exec -T -e MYSQL_PWD=root db mysql -uroot pedidos

echo ">> Tabelas importadas:"
docker compose exec -T -e MYSQL_PWD=root db mysql -uroot \
  -e "SELECT COUNT(*) AS tabelas FROM information_schema.tables WHERE table_schema='pedidos';"