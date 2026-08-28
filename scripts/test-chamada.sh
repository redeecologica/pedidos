#!/usr/bin/env bash
# Testes do prazo contábil padrão da chamada.
# Roda o arquivo PHP dentro do container web-modern (PHP 8.4, mesma versão da
# produção) canalizando por stdin — assim o teste não precisa morar em public/,
# que é o docroot e vai para o servidor no deploy.
#
# Os dados de teste vivem numa transação com rollback: o banco local carrega uma
# cópia real de produção e não é alterado.
#
# A rede conta só `chamadas` e `produtotipos`, que são as duas tabelas em que
# este teste cria fixture. De propósito NÃO conta pedidoprodutos: são 7 milhões
# de linhas contra um buffer pool de 128 MB, e esse COUNT já derrubou o container
# do banco quatro vezes noutra suíte. Rede que mata o ambiente não é rede.
#
# Uso: scripts/test-chamada.sh
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

TABELAS="chamadas produtotipos"

conta() {
  local saida=""
  for t in $TABELAS; do
    local n
    n=$(docker compose exec -T db mysql -uroot -proot pedidos -N -e "SELECT COUNT(*) FROM $t;" 2>/dev/null) || return 1
    [ -z "$n" ] && return 1
    saida="$saida $n"
  done
  echo "$saida"
}

ANTES=$(conta) || { echo "ERRO nao consegui contar as tabelas antes do teste: $TABELAS"; exit 2; }

FALHOU=0
docker compose exec -T web-modern php < scripts/test-chamada.php || FALHOU=1

DEPOIS=$(conta) || { echo "ERRO nao consegui contar as tabelas depois do teste: $TABELAS"; exit 2; }

echo
if [ "$ANTES" != "$DEPOIS" ]; then
  echo "FALHA o teste deixou rastro no banco"
  echo "   tabelas: $TABELAS"
  echo "   antes: $ANTES"
  echo "   depois:$DEPOIS"
  exit 1
fi

if [ "$FALHOU" -ne 0 ]; then echo "TESTES FALHARAM"; exit 1; fi
exit 0
