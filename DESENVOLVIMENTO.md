# Ambiente de desenvolvimento local

Ambiente completo via Docker Compose: o mesmo código servido por **dois PHPs**
(5.6 “referência”, espelhando produção; 8.4 “alvo” da migração), banco
**Percona/MySQL 5.6** igual ao de produção, **Mailpit** capturando todo e-mail
e **phpMyAdmin**.

## Pré-requisitos
- Docker Desktop para Mac (em Apple Silicon, habilite
  Settings → General → “Use Rosetta for x86_64/amd64 emulation” — as imagens
  PHP 5.6 e Percona 5.6 são Intel).

## Subindo pela primeira vez
1. `cp settings.php.docker settings.php` e preencha `PASSWORD_SALT`
   (mesmo valor da produção, senão nenhuma senha do banco copiado funciona).
2. `docker compose up -d --build --wait`
3. Acesse:

   | Serviço | URL |
   |---|---|
   | App (PHP 5.6, referência) | http://localhost:8056 |
   | App (PHP 8.4, alvo)       | http://localhost:8084 |
   | Mailpit (e-mails)         | http://localhost:8025 |
   | phpMyAdmin                | http://localhost:8089 (root/root ou pedidos/pedidos) |

O `php.ini` dos containers (`docker/php-dev.ini`) espelha os valores efetivos de
produção (`max_input_vars=9000`, `memory_limit=256M`, etc.) — para que um
formulário ou relatório que funcione aqui funcione lá.

## Banco com dados reais (mantenedores autorizados)
1. `cp scripts/prod.env.sample scripts/prod.env` e preencha (arquivo ignorado pelo git).
2. `scripts/db-pull.sh` — gera `dumps/prod-AAAA-MM-DD.sql.gz` sem travar produção
   (`--single-transaction`). Se o dump completo estourar o tempo, o script cai
   automaticamente para o modo tabela-por-tabela.
3. `scripts/db-import.sh` — recria o banco local e importa o dump mais recente.

**Privacidade (LGPD):** `dumps/`, `prod-snapshot/`, `settings.php` e
`scripts/prod.env` são ignorados pelo git e **jamais** devem ser commitados —
contêm dados pessoais reais e segredos.

## E-mails
Todo e-mail enviado pelo app cai no Mailpit (http://localhost:8025).
Nenhuma mensagem sai para a internet — pode testar fluxos com dados reais
sem risco de notificar pessoas.

## Problemas comuns
- **Build do PHP 5.6 falha em Mac M1/M2/M3:** habilite Rosetta no Docker Desktop
  (pré-requisitos) e repita.
- **Porta em uso (3306/8056/8084/8089/8025):** pare o serviço conflitante ou
  ajuste a porta no `docker-compose.yml`.
- **Login recusa senha que funciona em produção:** `PASSWORD_SALT` do
  `settings.php` local difere do de produção.
- **SSH da Locaweb “para de funcionar”:** o painel desabilita o SSH
  automaticamente ~3h depois de habilitado — reabilite e rode de novo
  (os scripts detectam o canal morto e avisam).
- **Erros na importação citando DEFINER:** remova as cláusulas DEFINER do dump
  (`gunzip -c dump.sql.gz | sed 's/DEFINER=[^*]*\*/\*/g' | gzip > dump2.sql.gz`).
- **Página avisa “banco de dados fora do ar”:** o serviço `db` ainda está
  inicializando — aguarde o healthcheck (`docker compose ps`).

## Por que dois PHPs?
Produção está em PHP 5.3 (fim de vida em 2014). A migração para 8.x é testada
no :8084 contra o MESMO banco e código do :8056 — qualquer diferença observada
tem uma única variável: a versão do PHP.
