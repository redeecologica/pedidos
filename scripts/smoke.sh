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
#
# usr_nome_curto é o campo que vira $_SESSION['usr.nome'] e sai na navbar de TODA
# página logada. Ele estava fora desta lista, e era justamente onde faltava escape:
# com o echo cru que o menu.inc.php tinha, o marcador saía literal em 68 das 76
# páginas e esta rede não via nada. Injetar aqui não quebra tela nenhuma — as 76
# rodaram com 0 erro de PHP e 0 5xx.
XSSMARK='<script>SMOKEXSS</script>'
docker compose exec -T -e MYSQL_PWD=root db mysql -uroot pedidos -e "
  UPDATE usuarios SET
    usr_nome_completo='Smoke $XSSMARK', usr_nome_curto='$XSSMARK',
    usr_contatos='$XSSMARK',
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

# AVISOS JÁ CONHECIDOS, por página. Esta lista é uma CATRACA, não um perdão: a página
# reprova se o número mudar para mais OU para menos. Para mais é regressão; para menos
# é conserto que precisa baixar o número aqui, senão a folga volta a esconder o
# próximo aviso.
#
# Todos são da mesma família — o PHP 8 reclamando de $row indexado depois de consulta
# que não devolveu linha, e de variável que só existe em um dos ramos. São anteriores a
# esta rede enxergar qualquer aviso, e estão anotados para serem tratados, não para
# ficarem assim.
CONHECIDOS="
distribuicao_consolidado.php 5
distribuicao_consolidado_por_produtor.php 5
entrega_cestantes_consolidado.php 6
entrega_divergencias.php 3
entrega_nucleos_consolidado.php 5
estoque.php 1
pedido_entregue.php 12
recebimento.php 2
rel_distribuicao.php 2
rel_entrega_cestantes_nucleo.php 4
rel_pedido_contato_cestantes.php 2
rel_pedido_por_cestante.php 4
rel_pedido_por_cestante_nucleo.php 4
rel_pedido_por_produtor.php 2
rel_pedido_por_produtor_considera_estoque.php 2
rel_pedido_pre_mutirao.php 2
rel_previsao_pagamento.php 2
rel_previsao_pagamento_detalhado.php 2
"

esperado() { awk -v p="$1" '$1==p {print $2; found=1} END {if (!found) print 0}' <<< "$CONHECIDOS"; }

FALHAS=0
printf '%-50s %s\n' "PÁGINA" "HTTP"
for p in $PAGINAS; do
  STATUS=$(curl -s -b "$COOKIES" -o /tmp/smoke.html -w '%{http_code}' "http://localhost:8084/$p")
  # SEM dois-pontos depois de Warning e Deprecated: o PHP emite "<b>Warning</b>: ", e o
  # padrão antigo exigia "Warning:" colado — nunca casava. A rede vinha reprovando fatal
  # e erro de parse e deixando passar TODO aviso, em 18 das 88 páginas.
  VISTOS=$(grep -cE 'Fatal error|Parse error|Warning|Deprecated' /tmp/smoke.html || true)
  ESPERADO=$(esperado "$p")
  # ERRO fica NUMÉRICO — a comparação adiante é aritmética. O texto da catraca vai
  # separado, em NOTA, para a linha dizer se subiu ou se caiu.
  ERRO=0; NOTA=""
  if [[ "$VISTOS" -ne "$ESPERADO" ]]; then
    ERRO=1
    if [[ "$VISTOS" -gt "$ESPERADO" ]]; then NOTA="PHP($VISTOS, eram $ESPERADO)"
    else                                     NOTA="PHP($VISTOS, eram $ESPERADO — baixe a linha em CONHECIDOS)"
    fi
  fi
  XSS=$(grep -cF "$XSSMARK" /tmp/smoke.html || true)
  MARCA=""
  # sem a comparação entre dois PHPs, o 5xx é o que sobra para pegar página quebrada
  [[ "$STATUS" =~ ^5 ]] && MARCA="$MARCA HTTP-$STATUS"
  grep -q "location.href='login.php'" /tmp/smoke.html && MARCA="$MARCA SESSAO-PERDIDA"
  [[ "$ERRO" -gt 0 ]] && MARCA="$MARCA $NOTA"
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
