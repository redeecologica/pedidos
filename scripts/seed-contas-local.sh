#!/usr/bin/env bash
# Cria contas de destino no banco LOCAL, para dar para registrar pagamento na tela.
#
# A tela de pagamentos esconde o botão de gravar sem conta de destino — de propósito,
# porque contas_de_destino() É a validação de registra_pagamento(). Em produção essas
# contas nascem no plano seguinte; aqui são criadas à mão só para exercitar a tela.
#
# SÓ LOCAL: roda dentro do container de desenvolvimento, que não alcança produção.
# Idempotente — pode rodar de novo depois de reimportar um dump.
#
# Uso: scripts/seed-contas-local.sh
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

# guarda contra rodar isto apontando para outro lugar: o container local usa o banco
# 'pedidos' em 'db', e é a única coisa que este script alcança
ALVO=$(docker compose exec -T web-modern php -r 'require "/var/www/html/settings.php"; echo DB_HOST . "/" . DB_NAME;' 2>/dev/null)
if [ "$ALVO" != "db/pedidos" ]; then
  echo "ERRO: o container aponta para '$ALVO', e este script so roda contra 'db/pedidos'." >&2
  exit 2
fi

# ATENÇÃO: com contas cadastradas, scripts/test-financeiro.sh FALHA — a suíte
# pressupõe `contas` vazia e vários testes batem na UNIQUE de con_nuc. Rode
# scripts/seed-contas-local.sh --limpar antes de rodar os testes.
docker compose exec -T web-modern php -- "$@" < scripts/seed-contas-local.php
