#!/usr/bin/env bash
# Testes do slice de lembrete de pedidos não enviados.
# Roda o arquivo PHP dentro do container web-modern (PHP 8.4, mesma versão da
# produção) canalizando por stdin — assim o teste não precisa morar em public/,
# que é o docroot e vai para o servidor no deploy.
#
# Os dados de teste vivem numa transação com rollback: o banco local carrega uma
# cópia real de produção e não é alterado.
#
# Uso: scripts/test-pedido.sh
set -euo pipefail
cd "$(dirname "$0")/.."

docker compose exec -T web-modern php < scripts/test-pedido.php
