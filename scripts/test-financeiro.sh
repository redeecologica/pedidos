#!/usr/bin/env bash
# Testes do módulo financeiro.
# Roda o arquivo PHP dentro do container web-modern (PHP 8.4, mesma versão da
# produção) canalizando por stdin — assim o teste não precisa morar em public/,
# que é o docroot e vai para o servidor no deploy.
#
# Os dados de teste vivem numa transação com rollback: o banco local carrega uma
# cópia real de produção e não é alterado.
#
# Só que esse rollback é um combinado, não uma garantia: depende de o PHP avisar
# o módulo de que a transação é dele (a flag $financeiro_em_transacao). Quando o
# combinado quebra, a suíte passa VERDE e o banco fica sujo — já aconteceu duas
# vezes. Por isso o runner conta as três tabelas antes e depois e falha se sobrou
# qualquer linha. É a rede que não depende de ninguém lembrar de nada, e vale
# também para os testes que as próximas tarefas acrescentarem.
#
# Uso: scripts/test-financeiro.sh
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

# as três contagens numa linha só, separadas por tab
contagens() {
  docker compose exec -T -e MYSQL_PWD=root db \
    mysql -uroot -N -B pedidos -e \
    "SELECT (SELECT COUNT(*) FROM contas), (SELECT COUNT(*) FROM transacoes), (SELECT COUNT(*) FROM lancamentos);" 2>/dev/null
}

ANTES=$(contagens)
if [ -z "$ANTES" ]; then
  echo "ERRO nao consegui contar contas/transacoes/lancamentos antes do teste."
  echo "     Banco fora do ar, ou as tabelas do financeiro ainda nao existem."
  exit 2
fi

docker compose exec -T web-modern php < scripts/test-financeiro.php
TESTES=$?

DEPOIS=$(contagens)
if [ -z "$DEPOIS" ]; then
  echo "ERRO nao consegui contar contas/transacoes/lancamentos depois do teste."
  echo "     Sem essa contagem nao da para afirmar que o banco ficou intacto."
  exit 2
fi

if [ "$ANTES" != "$DEPOIS" ]; then
  echo
  echo "FALHA o teste deixou rastro no banco (contas / transacoes / lancamentos)"
  echo "         antes:  $ANTES"
  echo "         depois: $DEPOIS"
  echo "         O banco local carrega copia real de producao. Confira se o teste"
  echo "         novo roda dentro da transacao com rollback (e avisa o modulo pela"
  echo "         flag), ou se limpa o que grava de proposito."
  exit 1
fi

exit $TESTES
