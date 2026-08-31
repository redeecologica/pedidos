#!/usr/bin/env bash
# Testes do Balanço da chamada.
# Roda o arquivo PHP dentro do container web-modern (PHP 8.4, mesma versão da
# produção) canalizando por stdin — assim o teste não precisa morar em public/,
# que é o docroot e vai para o servidor no deploy.
#
# Os dados de teste vivem numa transação com rollback: o banco local carrega uma
# cópia real de produção e não é alterado.
#
# Só que esse rollback é um combinado, não uma garantia: depende de o PHP avisar
# o PHP de que a transação é dele. Quando o
# combinado quebra, a suíte passa VERDE e o banco fica sujo — já aconteceu duas
# vezes. Por isso o runner conta as tabelas antes e depois e falha se sobrou
# qualquer linha. É a rede que não depende de ninguém lembrar de nada, e vale
# também para os testes que as próximas tarefas acrescentarem.
#
# A lista abaixo cobre TODA tabela em que a suíte cria fixture, sem exceção. Ela
# cresce junto com os testes: o débito derivado passou a montar chamada, pedido e
# entrega, e uma tabela de fora desta lista é um vazamento que a rede deixa passar
# calada. Uma rede que se descreve de um jeito e se comporta de outro não é rede.
#
# usuarios entrou depois de medido: 1210 linhas, 0,14 s sozinha com o docker exec
# incluído, e dentro da consulta única abaixo o custo some no ruído dos ~2,4 s que
# a contagem inteira já leva (o grosso é pedidoprodutos, com 7 milhões de linhas).
# A alegação anterior — tabela grande demais para contar a cada corrida — era falsa.
#
# Uso: scripts/test-balanco.sh
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

TABELAS="chamadas chamadaprodutos distribuicao estoque pedidos pedidoprodutos produtos usuarios nucleos fornecedores"

# todas as contagens numa linha só, separadas por tab
contagens() {
  local sel=""
  for t in $TABELAS; do
    [ -n "$sel" ] && sel="$sel, "
    sel="$sel(SELECT COUNT(*) FROM $t)"
  done
  docker compose exec -T -e MYSQL_PWD=root db \
    mysql -uroot -N -B pedidos -e "SELECT $sel;" 2>/dev/null
}

ANTES=$(contagens)
if [ -z "$ANTES" ]; then
  echo "ERRO nao consegui contar as tabelas antes do teste: $TABELAS"
  echo "     Banco fora do ar."
  exit 2
fi

docker compose exec -T web-modern php < scripts/test-balanco.php
TESTES=$?

DEPOIS=$(contagens)
if [ -z "$DEPOIS" ]; then
  echo "ERRO nao consegui contar as tabelas depois do teste: $TABELAS"
  echo "     Sem essa contagem nao da para afirmar que o banco ficou intacto."
  exit 2
fi

if [ "$ANTES" != "$DEPOIS" ]; then
  echo
  echo "FALHA o teste deixou rastro no banco"
  echo "         tabelas: $TABELAS"
  echo "         antes:  $ANTES"
  echo "         depois: $DEPOIS"
  echo "         O banco local carrega copia real de producao. Confira se o teste"
  echo "         novo roda dentro da transacao com rollback (e avisa o modulo pela"
  echo "         flag), ou se limpa o que grava de proposito."
  exit 1
fi

exit $TESTES
