#!/usr/bin/env bash
# Testes do retorno para a página pretendida depois do login.
# Roda o arquivo PHP dentro do container web-modern (PHP 8.4, mesma versão da
# produção) canalizando por stdin.
#
# destino_para_voltar() só lê $_SERVER: não há fixture, não há escrita no banco,
# e por isso esta rede não precisa contar tabela nenhuma.
#
# Uso: scripts/test-login.sh
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

docker compose exec -T web-modern php < scripts/test-login.php
