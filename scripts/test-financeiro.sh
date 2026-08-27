#!/usr/bin/env bash
# Testes do módulo financeiro.
# Roda o arquivo PHP dentro do container web-modern (PHP 8.4, mesma versão da
# produção) canalizando por stdin — assim o teste não precisa morar em public/,
# que é o docroot e vai para o servidor no deploy.
#
# Os dados de teste vivem numa transação com rollback: o banco local carrega uma
# cópia real de produção e não é alterado.
#
# Uso: scripts/test-financeiro.sh
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

docker compose exec -T web-modern php < scripts/test-financeiro.php
