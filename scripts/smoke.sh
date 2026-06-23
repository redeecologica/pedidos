#!/usr/bin/env bash
# Rede de regressão: cria admin descartável no banco LOCAL, faz login via curl
# e varre todas as páginas PHP em :8056 (referência) e :8084 (alvo).
# Reprova se: status difere entre os PHPs, ou o HTML contém erro/aviso PHP.
# Uso: scripts/smoke.sh
set -euo pipefail
cd "$(dirname "$0")/.."

EMAIL="smoke@dev.local"
SENHA="smoke-fase2"
COOKIES56=$(mktemp); COOKIES84=$(mktemp)
trap 'rm -f "$COOKIES56" "$COOKIES84"' EXIT

echo ">> Garantindo usuário de smoke (admin) no banco LOCAL..."
HASH=$(docker compose exec -T web-legacy php -r 'require "/var/www/html/settings.php"; echo crypt("'"$SENHA"'", PASSWORD_SALT);')
docker compose exec -T -e MYSQL_PWD=root db mysql -uroot pedidos -e "
  INSERT INTO usuarios (usr_nome_completo, usr_nome_curto, usr_email, usr_senha, usr_archive, usr_nuc)
  SELECT 'Usuário Smoke', 'smoke', '$EMAIL', '$HASH', '0',
         (SELECT nuc_id FROM nucleos ORDER BY nuc_id LIMIT 1) FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usr_email='$EMAIL');
  UPDATE usuarios SET usr_senha='$HASH', usr_archive='0' WHERE usr_email='$EMAIL';
  INSERT INTO usuariopapeis (usrp_usr, usrp_pap, usrp_por_usr)
  SELECT u.usr_id, p.pap_id, u.usr_id FROM usuarios u, papeis p
  WHERE u.usr_email='$EMAIL' AND p.pap_nome='Administrador'
    AND NOT EXISTS (SELECT 1 FROM usuariopapeis x WHERE x.usrp_usr=u.usr_id AND x.usrp_pap=p.pap_id);"

login() { # $1=porta $2=cookiejar
  curl -s -c "$2" -o /dev/null "http://localhost:$1/login.php"
  curl -s -b "$2" -c "$2" -o /dev/null -d "login_usr_email=$EMAIL" -d "login_usr_senha=$SENHA" \
    "http://localhost:$1/login.php"
  # sessão válida? deslogado, a página responde só com o redirect javascript
  if curl -s -b "$2" "http://localhost:$1/inicio.php" | grep -q "location.href='login.php'"; then
    echo "ERRO: login falhou na porta $1" >&2; exit 1
  fi
}
echo ">> Login nas duas portas..."
login 8056 "$COOKIES56"
login 8084 "$COOKIES84"

# páginas excluídas: geradoras de efeito colateral ou includes/fora do fluxo logado
EXCLUIR='script_gera_pedidos_associacao.php|bd_fora.php|settings|\.inc\.php'
PAGINAS=$(git ls-files '*.php' | grep -vE "$EXCLUIR" | grep -v '/')

FALHAS=0
printf '%-50s %s  %s\n' "PÁGINA" "5.6" "8.4"
for p in $PAGINAS; do
  S56=$(curl -s -b "$COOKIES56" -o /tmp/smoke56.html -w '%{http_code}' "http://localhost:8056/$p")
  S84=$(curl -s -b "$COOKIES84" -o /tmp/smoke84.html -w '%{http_code}' "http://localhost:8084/$p")
  ERRO56=$(grep -cE 'Fatal error|Parse error|Warning:|Deprecated:' /tmp/smoke56.html || true)
  ERRO84=$(grep -cE 'Fatal error|Parse error|Warning:|Deprecated:' /tmp/smoke84.html || true)
  MARCA=""
  grep -q "location.href='login.php'" /tmp/smoke84.html && MARCA="SESSAO-PERDIDA-8.4"
  [[ "$S56" != "$S84" ]] && MARCA="$MARCA STATUS-DIFERE"
  [[ "$ERRO56" -gt 0 ]] && MARCA="$MARCA ERRO-PHP-5.6($ERRO56)"
  [[ "$ERRO84" -gt 0 ]] && MARCA="$MARCA ERRO-PHP-8.4($ERRO84)"
  if [[ -n "$MARCA" ]]; then
    FALHAS=$((FALHAS+1))
    printf '%-50s %s  %s  << %s\n' "$p" "$S56" "$S84" "$MARCA"
  fi
done
echo
if (( FALHAS > 0 )); then echo "SMOKE: $FALHAS página(s) com problema."; exit 1; fi
echo "SMOKE: tudo verde nas duas versões de PHP."
