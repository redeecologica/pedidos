#!/usr/bin/env bash
# Gera dumps/prod-AAAA-MM-DD.sql.gz a partir do banco de PRODUÇÃO (somente leitura).
#
# Estratégia (nesta ordem):
#   A1) dump completo via conexão direta (cliente percona:5.6 do container)
#   A2) se A1 falhar/estourar tempo: dump TABELA POR TABELA via conexão direta
#       (experiência anterior: dump completo pode dar timeout na Locaweb)
#   B)  mysqldump no servidor via SSH
#
# Atenção (aprendido 2026-06-11): o SSH da Locaweb auto-desliga ~3h após habilitado
# no painel, e o canal "morto" autentica, executa NADA e retorna sucesso com saída
# vazia. Por isso o plano B exige uma sonda com marcador antes de confiar no canal.
set -euo pipefail
cd "$(dirname "$0")/.."

ENV_FILE="scripts/prod.env"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERRO: $ENV_FILE não existe. Copie scripts/prod.env.sample e preencha." >&2
  exit 1
fi
# shellcheck source=/dev/null
source "$ENV_FILE"

mkdir -p dumps
OUT="dumps/prod-$(date +%F).sql.gz"
TMP="dumps/.parcial-$$.sql"
trap 'rm -f "$TMP"' EXIT
DUMP_FLAGS=(--single-transaction --quick --default-character-set=utf8)
TAMANHO_MINIMO=102400   # 100 KB comprimidos; dump real tem MBs — menos que isso é falha

# executa cliente mysql/mysqldump dentro do container (mesma versão 5.6 do servidor);
# 'timeout' roda DENTRO do container linux (o mac não tem GNU timeout por padrão)
no_container() {
  docker compose run --rm -T -e MYSQL_PWD="$PROD_DB_PASS" db "$@"
}

testa_conexao() {
  no_container mysql -h "$PROD_DB_HOST" -u "$PROD_DB_USER" --connect-timeout=8 \
    -e "SELECT 1" "$PROD_DB_NAME" >/dev/null 2>&1
}

dump_completo() {
  echo ">> A1: tentando dump completo (limite 10 min)..."
  no_container timeout 600 mysqldump "${DUMP_FLAGS[@]}" \
    -h "$PROD_DB_HOST" -u "$PROD_DB_USER" "$PROD_DB_NAME" > "$TMP"
}

dump_por_tabela() {
  echo ">> A2: dump tabela por tabela (limite 3 min cada)..."
  : > "$TMP"
  local tabelas
  tabelas=$(no_container mysql -N -h "$PROD_DB_HOST" -u "$PROD_DB_USER" \
    -e "SHOW TABLES" "$PROD_DB_NAME")
  local total=0
  for t in $tabelas; do total=$((total+1)); done
  local i=0
  for t in $tabelas; do
    i=$((i+1))
    printf '   [%2d/%2d] %s ... ' "$i" "$total" "$t"
    no_container timeout 180 mysqldump "${DUMP_FLAGS[@]}" \
      -h "$PROD_DB_HOST" -u "$PROD_DB_USER" "$PROD_DB_NAME" "$t" >> "$TMP"
    echo "ok"
  done
  echo ">> Aviso: dumps por tabela são consistentes POR TABELA (não há transação global entre elas)."
}

if testa_conexao; then
  if dump_completo; then
    echo ">> OK (A1 — dump completo, conexão direta)."
  else
    echo ">> A1 falhou/estourou o tempo (esperado em alguns casos na Locaweb)."
    dump_por_tabela
    echo ">> OK (A2 — tabela por tabela, conexão direta)."
  fi
  gzip -c "$TMP" > "$OUT"
else
  echo ">> Conexão direta indisponível. Plano B: mysqldump no servidor via SSH..."
  echo ">> Sondando canal SSH (Locaweb desliga o SSH sozinho após ~3h)..."
  SONDA=$(ssh -o BatchMode=yes -o ConnectTimeout=10 \
    "${PROD_SSH_USER}@${PROD_SSH_HOST}" 'echo VIVO' 2>/dev/null || true)
  if [[ "$SONDA" != "VIVO" ]]; then
    echo "ERRO: canal SSH morto ou desabilitado. Reabilite o SSH no painel da Locaweb e tente de novo." >&2
    exit 1
  fi
  # Senha vai num arquivo temporário 0600 no servidor (não aparece em 'ps').
  # shellcheck disable=SC2087  # expansão local das credenciais é intencional; o lado remoto usa \$
  ssh "${PROD_SSH_USER}@${PROD_SSH_HOST}" bash -s <<EOF > "$OUT"
set -euo pipefail
umask 077
CNF=\$(mktemp)
printf '[client]\nhost=%s\nuser=%s\npassword=%s\n' '$PROD_DB_HOST' '$PROD_DB_USER' '$PROD_DB_PASS' > "\$CNF"
trap 'rm -f "\$CNF"' EXIT
mysqldump --defaults-extra-file="\$CNF" ${DUMP_FLAGS[*]} '$PROD_DB_NAME' | gzip
EOF
  echo ">> OK (plano B — via SSH)."
fi

BYTES=$(stat -f%z "$OUT" 2>/dev/null || stat -c%s "$OUT")
if (( BYTES < TAMANHO_MINIMO )); then
  echo "ERRO: dump suspeito de truncado/vazio ($BYTES bytes). Arquivo mantido para inspeção: $OUT" >&2
  exit 1
fi
ls -lh "$OUT"