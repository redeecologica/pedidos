#!/usr/bin/env bash
# Deploy da branch atual para produção (Locaweb) — base reutilizável da Fase 3.
# Estágio via git archive (só arquivos rastreados — settings/dumps/docs não EXISTEM no estágio),
# poda artefatos de repositório, backup tar no servidor, rsync com dry-run + confirmação.
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
# poda: artefatos do repositório, não do servidor
rm -rf "$STAGE"/bd "$STAGE"/scripts "$STAGE"/docker "$STAGE"/docker-compose.yml \
       "$STAGE"/settings.php.docker "$STAGE"/composer.json "$STAGE"/composer.lock \
       "$STAGE"/DESENVOLVIMENTO.md "$STAGE"/README.md "$STAGE"/LICENSE.txt \
       "$STAGE"/.gitignore "$STAGE"/.gitattributes

echo ">> Backup no servidor (tar local ao servidor, segundos)..."
# shellcheck disable=SC2029  # expansão local de PROD_WEB_ROOT é intencional
ssh "${PROD_SSH_USER}@${PROD_SSH_HOST}" \
  "tar czf ~/backup-pre-fase2-\$(date +%F-%H%M).tgz -C '$(dirname "${PROD_WEB_ROOT}")' '$(basename "${PROD_WEB_ROOT}")' && ls -lh ~/backup-pre-fase2-*.tgz | tail -1"

# --chmod=D755,F644 força permissões corretas independente da origem.
# Sem isto, o diretório do mktemp (modo 700) era copiado para o web root,
# deixando o public_html 700 → servidor não consegue entrar → 403 em tudo.
RSYNC_PERMS="--chmod=D755,F644"

echo ">> DRY-RUN do rsync (nada é alterado ainda):"
rsync -avzn $RSYNC_PERMS --itemize-changes "$STAGE"/ "${PROD_SSH_USER}@${PROD_SSH_HOST}:${PROD_WEB_ROOT}/" | tail -40
echo
read -r -p ">> Confirma o deploy? Digite SIM para prosseguir: " CONFIRMA
[[ "$CONFIRMA" == "SIM" ]] || { echo "Abortado."; exit 1; }

rsync -avz $RSYNC_PERMS "$STAGE"/ "${PROD_SSH_USER}@${PROD_SSH_HOST}:${PROD_WEB_ROOT}/" | tail -5

echo ">> Removendo diretórios substituídos no servidor (ckeditor, phpmailer)..."
# shellcheck disable=SC2029  # expansão local de PROD_WEB_ROOT é intencional
ssh "${PROD_SSH_USER}@${PROD_SSH_HOST}" "cd '${PROD_WEB_ROOT}' && rm -rf ckeditor phpmailer && echo removidos"

echo ">> Deploy concluído. Smoke de produção:"
curl -s -o /dev/null -w 'login.php: %{http_code}\n' https://pedidos.redeecologicario.org/login.php
echo ">> Lembrete: a virada de versão do PHP é manual, no painel da Locaweb (5.3 → 8.4)."
echo ">> Rollback completo = painel de volta a 5.3 + restaurar o backup:"
echo "   ssh ${PROD_SSH_USER}@${PROD_SSH_HOST} 'tar xzf ~/backup-pre-fase2-DATA.tgz -C $(dirname "${PROD_WEB_ROOT}")'"
