#!/usr/bin/env bash
# Testes do envio de pedido e do lembrete de pedidos não enviados.
# Roda o arquivo PHP dentro do container web-modern (PHP 8.4, mesma versão da
# produção) canalizando por stdin — assim o teste não precisa morar em public/,
# que é o docroot e vai para o servidor no deploy.
#
# Os dados de teste vivem numa transação com rollback: o banco local carrega uma
# cópia real de produção e não é alterado.
#
# Uso: scripts/test-pedido.sh
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

FALHOU=0

docker compose exec -T web-modern php < scripts/test-pedido.php || FALHOU=1

# O batimento é do ponto de entrada, não de uma função — por isso é testado aqui
# no shell. A regra que mais importa: simulação NÃO escreve, senão uma conferência
# manual deixaria o arquivo com cara de execução agendada e esconderia um cron
# que não está disparando.
echo
echo "batimento do cron"
BATIMENTO=/var/www/cron_lembrete.ultima

docker compose exec -T web-modern rm -f "$BATIMENTO"
docker compose exec -T web-modern php /var/www/html/cron_lembrete.php --simulacao >/dev/null
if docker compose exec -T web-modern test -f "$BATIMENTO"; then
  echo "  FALHA simulação escreveu batimento"; FALHOU=1
else
  echo "  ok    simulação não escreve batimento"
fi

docker compose exec -T web-modern php /var/www/html/cron_lembrete.php >/dev/null
if docker compose exec -T web-modern test -f "$BATIMENTO"; then
  echo "  ok    execução real escreve batimento"
else
  echo "  FALHA execução real não escreveu batimento"; FALHOU=1
fi
docker compose exec -T web-modern rm -f "$BATIMENTO"

echo
if [ "$FALHOU" -ne 0 ]; then echo "TESTES FALHARAM"; exit 1; fi
exit 0
