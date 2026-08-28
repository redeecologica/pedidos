#!/usr/bin/env bash
# Testes do retorno para a página pretendida depois do login.
#
# Duas partes, e a segunda existe por causa de um defeito real: a primeira versão
# deste recurso funcionava por curl com a query na URL do POST, e NÃO funcionava
# no navegador — o action do formulário é "login.php" sem query, então o volta
# sumia no envio. Teste de unidade não alcança isso; só pedir a página e olhar o
# HTML alcança.
#
# Uso: scripts/test-login.sh
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

FALHOU=0
BASE=http://localhost:8084

docker compose exec -T web-modern php < scripts/test-login.php || FALHOU=1

echo
echo "o volta atravessa o formulário"

ALVO='pedido.php%3Faction%3D0%26ped_id%3D100701'
HTML=$(curl -s "$BASE/login.php?volta=$ALVO")

if grep -q '<input type="hidden" name="volta" value="pedido.php?action=0&amp;ped_id=100701" />' <<< "$HTML"; then
  echo "  ok    a tela de login carrega o destino num campo escondido, escapado"
else
  echo "  FALHA o destino nao chegou ao formulario — o POST vai perde-lo"; FALHOU=1
fi

# valor hostil não pode nem ser renderizado
HOSTIL=$(curl -s "$BASE/login.php?volta=%22%3E%3Cscript%3Ealert(1)%3C/script%3E" | grep -c 'name="volta"')
if [ "$HOSTIL" -eq 0 ]; then
  echo "  ok    destino hostil nao vira campo escondido"
else
  echo "  FALHA destino hostil foi renderizado ($HOSTIL campo(s))"; FALHOU=1
fi

echo
if [ "$FALHOU" -ne 0 ]; then echo "TESTES FALHARAM"; exit 1; fi
exit 0
