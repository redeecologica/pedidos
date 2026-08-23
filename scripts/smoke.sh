#!/usr/bin/env bash
# Rede de regressão: cria admin descartável no banco LOCAL, faz login via curl
# e varre todas as páginas PHP em :8084.
# Reprova se: a página responde 5xx, o HTML contém erro/aviso do PHP, a sessão
# se perde, ou um payload de XSS aparece cru na saída.
# Uso: scripts/smoke.sh
set -euo pipefail
cd "$(dirname "$0")/.."

EMAIL="smoke@dev.local"
SENHA="smoke-fase2"
COOKIES=$(mktemp)
trap 'rm -f "$COOKIES"' EXIT

echo ">> Garantindo usuário de smoke (admin) no banco LOCAL..."
HASH=$(docker compose exec -T web-modern php -r 'require "/var/www/html/settings.php"; echo crypt("'"$SENHA"'", PASSWORD_SALT);')
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

# injeta payload de XSS (marcador único) nos campos de texto do usuário de smoke;
# se alguma view exibir cru, o marcador aparece literal no HTML (escapado vira &lt;...)
XSSMARK='<script>SMOKEXSS</script>'
docker compose exec -T -e MYSQL_PWD=root db mysql -uroot pedidos -e "
  UPDATE usuarios SET
    usr_nome_completo='Smoke $XSSMARK', usr_contatos='$XSSMARK',
    usr_endereco='$XSSMARK', usr_atividades='$XSSMARK',
    usr_profissao='$XSSMARK', usr_habilidades='$XSSMARK'
  WHERE usr_email='$EMAIL';"

echo ">> Login..."
curl -s -c "$COOKIES" -o /dev/null "http://localhost:8084/login.php"
curl -s -b "$COOKIES" -c "$COOKIES" -o /dev/null -d "login_usr_email=$EMAIL" -d "login_usr_senha=$SENHA" \
  "http://localhost:8084/login.php"
# sessão válida? deslogado, a página responde só com o redirect javascript
if curl -s -b "$COOKIES" "http://localhost:8084/inicio.php" | grep -q "location.href='login.php'"; then
  echo "ERRO: login falhou" >&2; exit 1
fi

# páginas excluídas: geradoras de efeito colateral ou includes/fora do fluxo logado
EXCLUIR='script_gera_pedidos_associacao.php|bd_fora.php|settings|\.inc\.php'
PAGINAS=$(git ls-files public/ | grep -E '^public/[^/]+\.php$' | sed 's#^public/##' | grep -vE "$EXCLUIR")

FALHAS=0
printf '%-50s %s\n' "PÁGINA" "HTTP"
for p in $PAGINAS; do
  STATUS=$(curl -s -b "$COOKIES" -o /tmp/smoke.html -w '%{http_code}' "http://localhost:8084/$p")
  ERRO=$(grep -cE 'Fatal error|Parse error|Warning:|Deprecated:' /tmp/smoke.html || true)
  XSS=$(grep -cF "$XSSMARK" /tmp/smoke.html || true)
  MARCA=""
  # sem a comparação entre dois PHPs, o 5xx é o que sobra para pegar página quebrada
  [[ "$STATUS" =~ ^5 ]] && MARCA="$MARCA HTTP-$STATUS"
  grep -q "location.href='login.php'" /tmp/smoke.html && MARCA="$MARCA SESSAO-PERDIDA"
  [[ "$ERRO" -gt 0 ]] && MARCA="$MARCA ERRO-PHP($ERRO)"
  [[ "$XSS" -gt 0 ]] && MARCA="$MARCA XSS-CRU($XSS)"
  if [[ -n "$MARCA" ]]; then
    FALHAS=$((FALHAS+1))
    printf '%-50s %s  << %s\n' "$p" "$STATUS" "$MARCA"
  fi
done
echo
echo ">> Regressão: throttling (6 tentativas → bloqueio)..."
for i in 1 2 3 4 5; do curl -s -o /dev/null -d "login_usr_email=throttle@smoke.local" -d "login_usr_senha=x$i" "http://localhost:8084/login.php"; done
if curl -s -d "login_usr_email=throttle@smoke.local" -d "login_usr_senha=x6" "http://localhost:8084/login.php" | grep -q 'Aguarde 15 minutos'; then echo "   throttling OK"; else echo "   THROTTLING FALHOU"; FALHAS=$((FALHAS+1)); fi
docker compose exec -T -e MYSQL_PWD=root db mysql -uroot pedidos -e "DELETE FROM login_tentativas WHERE tent_email='throttle@smoke.local';" >/dev/null 2>&1

if (( FALHAS > 0 )); then echo "SMOKE: $FALHAS página(s) com problema."; exit 1; fi
echo "SMOKE: tudo verde."
