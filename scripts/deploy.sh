#!/usr/bin/env bash
# Deploy da branch atual para produção (Locaweb) — base reutilizável da Fase 3.
# Estágio via git archive (só arquivos rastreados — settings/dumps/docs não EXISTEM no estágio);
# envia apenas public/ (o app), backup tar no servidor, rsync com dry-run + confirmação.
set -euo pipefail
cd "$(dirname "$0")/.."

# shellcheck source=/dev/null
source scripts/prod.env

echo ">> Sondando canal SSH (Locaweb desliga sozinho após ~3h)..."
SONDA=$(ssh -o BatchMode=yes -o ConnectTimeout=10 "${PROD_SSH_USER}@${PROD_SSH_HOST}" 'echo VIVO' 2>/dev/null || true)
[[ "$SONDA" == "VIVO" ]] || { echo "ERRO: canal SSH morto. Reabilite no painel e repita." >&2; exit 1; }

BRANCH=$(git branch --show-current)
STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT
echo ">> Estágio limpo da branch '$BRANCH'..."
git archive "$BRANCH" | tar -x -C "$STAGE"
# o app servido vive em public/; ferramentas (bd, scripts, docker, docs) ficam fora do estágio servido
SRC="$STAGE/public"
# itens que vivem em public/ mas NÃO são servidos:
rm -f "$SRC/composer.json" "$SRC/composer.lock" "$SRC/settings.php.docker" "$SRC/settings.php.sample"
# normaliza permissões na ORIGEM (dirs 755, files 644). NÃO usar rsync --chmod: o openrsync
# do macOS o rejeita. Sem isto, o modo 700 do diretório do mktemp iria parar no web root → 403.
chmod 755 "$SRC"
find "$SRC" -type d -exec chmod 755 {} +
find "$SRC" -type f -exec chmod 644 {} +

PAI="$(dirname "${PROD_WEB_ROOT}")"; BASE="$(basename "${PROD_WEB_ROOT}")"
echo ">> Backup no servidor (exclui tutorial/intranet — rápido)..."
# shellcheck disable=SC2029  # expansão local intencional
ssh "${PROD_SSH_USER}@${PROD_SSH_HOST}" \
  "cd '${PAI}' && tar czf ~/backup-pre-deploy-\$(date +%F-%H%M).tgz --exclude='${BASE}/tutorial' --exclude='${BASE}/intranet' '${BASE}' && ls -lh ~/backup-pre-deploy-*.tgz | tail -1"

echo ">> DRY-RUN do rsync (nada é alterado ainda):"
rsync -avzn --itemize-changes "$SRC"/ "${PROD_SSH_USER}@${PROD_SSH_HOST}:${PROD_WEB_ROOT}/" | tail -40
echo
read -r -p ">> Confirma o deploy? Digite SIM para prosseguir: " CONFIRMA
[[ "$CONFIRMA" == "SIM" ]] || { echo "Abortado."; exit 1; }

rsync -avz "$SRC"/ "${PROD_SSH_USER}@${PROD_SSH_HOST}:${PROD_WEB_ROOT}/" | tail -5

echo ">> Deploy concluído. Smoke de produção:"
curl -s -o /dev/null -w 'login.php: %{http_code}\n' https://pedidos.redeecologicario.org/login.php
echo ">> Rollback (deploy code-only no 8.4) = restaurar o backup:"
echo "   ssh ${PROD_SSH_USER}@${PROD_SSH_HOST} 'tar xzf ~/backup-pre-deploy-DATA.tgz -C $(dirname "${PROD_WEB_ROOT}")'"
