#!/usr/bin/env bash
# Prepara o ambiente local antes de uma suíte. É PARA SER SOURCED, não executado:
#
#   . "$(dirname "$0")/ambiente.sh"
#   prepara_ambiente
#
# Existe porque duas armadilhas do ambiente local já produziram resultado falso,
# e as duas são invisíveis para quem lê a saída da suíte.
#
# 1. O CACHE DO BIND MOUNT. No macOS o Docker serve arquivo velho por alguns
#    segundos depois da escrita, então a suíte testa a versão anterior do código.
#    Isso já deu vermelho em código correto e verde em código quebrado. Só o
#    restart do container limpa.
#
# 2. O MYSQLD CRESCE ATÉ A VM MORRER. Não existe imagem arm64 do percona:5.6,
#    então num Mac ARM ele roda emulado sob Rosetta, e o processo emulado cresce
#    a cada consulta sem nunca devolver: umas poucas centenas de MiB por rodada
#    de suíte, muito além do que a configuração explica (todos os buffers estão
#    no padrão, somando menos de meio giga no total).
#
#    Quando encosta no teto da VM, o OOM killer do kernel escolhe o mysqld por
#    ser o maior processo e manda SIGKILL — sinal que nenhum processo consegue
#    registrar. Daí o sintoma confuso: o log não mostra causa nenhuma, só um
#    "Database was not shutdown normally!" na partida seguinte, e o
#    `docker inspect` diz OOMKilled=false porque essa flag só marca estouro de
#    cgroup, e o estouro aqui é da VM inteira.
#
#    O vazamento é da camada de emulação: não dá para corrigir daqui, e o 5.6 não
#    tem imagem nativa. O que dá é reciclar antes de encostar no teto.
#
# Nada disto vale para produção, que roda x86 de verdade.

# Acima deste RSS o mysqld é reciclado. O teto da VM fica na casa de 7 GiB e cada
# rodada de suíte acrescenta bem menos que um giga, então este limite deixa folga
# de sobra e ainda assim recicla só de vez em quando, não a cada corrida.
: "${LIMITE_RSS_MYSQLD_KB:=2097152}"   # 2 GiB

_rss_mysqld_kb() {
  docker exec "$(docker compose ps -q db 2>/dev/null)" \
    sh -c "ps -eo rss,comm | awk '/mysqld/{print \$1; exit}'" 2>/dev/null
}

_espera_db() {
  local _
  for _ in $(seq 1 60); do
    docker compose exec -T -e MYSQL_PWD=root db \
      mysqladmin ping -uroot --silent >/dev/null 2>&1 && return 0
    sleep 1
  done
  return 1
}

_espera_web() {
  local _
  for _ in $(seq 1 30); do
    docker compose exec -T web-modern php -r 'exit(0);' >/dev/null 2>&1 && return 0
    sleep 1
  done
  return 1
}

# Reciclagem LIMPA. `docker compose restart db` não serve: medido, ele derruba o
# mysqld sem que o desligamento se complete, e a partida seguinte cai em
# recuperação de crash. `docker stop` com folga generosa desliga limpo em poucos
# segundos — o buffer pool é pequeno, não há o que esperar.
_recicla_db() {
  echo ">> Reciclando o banco (mysqld emulado crescendo; ver ambiente.sh)..."
  docker stop -t 60 "$(docker compose ps -q db)" >/dev/null 2>&1
  docker compose up -d db >/dev/null 2>&1
}

prepara_ambiente() {
  # O banco pode estar morto de OOM desde a corrida anterior. Contar com ele no ar
  # é justamente o que faz a suíte mentir.
  if ! _espera_db; then
    echo ">> Banco fora do ar; subindo..."
    docker compose up -d db >/dev/null 2>&1
    if ! _espera_db; then
      echo "ERRO o banco nao subiu. Sem ele a suite nao afirma nada."
      return 2
    fi
  fi

  local rss
  rss=$(_rss_mysqld_kb)
  if [ -n "$rss" ] && [ "$rss" -gt "$LIMITE_RSS_MYSQLD_KB" ] 2>/dev/null; then
    _recicla_db
    if ! _espera_db; then
      echo "ERRO o banco nao voltou depois da reciclagem."
      return 2
    fi
  fi

  echo ">> Limpando o cache do bind mount (macOS serve arquivo velho)..."
  docker compose restart web-modern >/dev/null 2>&1
  if ! _espera_web; then
    echo "ERRO o web-modern nao respondeu depois do restart."
    echo "     Rodar assim testaria a versao velha do codigo."
    return 2
  fi

  return 0
}
