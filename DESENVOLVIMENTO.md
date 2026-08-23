# Ambiente de desenvolvimento local

Ambiente completo via Docker Compose: **PHP 8.4**, a mesma versão de produção,
banco **Percona/MySQL 5.6** igual ao de produção, **Mailpit** capturando todo
e-mail e **phpMyAdmin**.

> A aplicação (tudo que o servidor web serve) fica no diretório **`public/`**.
> Os diretórios `docker/`, `scripts/`, `bd/` e `docs/` são de apoio e ficam fora dele.
> No deploy, só o conteúdo de `public/` vai para o servidor.

## Pré-requisitos
- Docker Desktop para Mac (em Apple Silicon, habilite
  Settings → General → “Use Rosetta for x86_64/amd64 emulation” — a imagem do
  Percona 5.6 é Intel).

## Subindo pela primeira vez
1. `cp public/settings.php.docker public/settings.php` e preencha `PASSWORD_SALT`
   (mesmo valor da produção, senão nenhuma senha do banco copiado funciona).
2. `docker compose up -d --build --wait`
3. Acesse:

   | Serviço | URL |
   |---|---|
   | App (PHP 8.4)     | http://localhost:8084 |
   | Mailpit (e-mails) | http://localhost:8025 |
   | phpMyAdmin        | http://localhost:8089 (root/root ou pedidos/pedidos) |

O `php.ini` dos containers (`docker/php-dev.ini`) espelha os valores efetivos de
produção (`max_input_vars=9000`, `memory_limit=256M`, etc.) — para que um
formulário ou relatório que funcione aqui funcione lá.

## Banco com dados reais (mantenedores autorizados)
1. `cp scripts/prod.env.sample scripts/prod.env` e preencha (arquivo ignorado pelo git).
2. `scripts/db-pull.sh` — gera `dumps/prod-AAAA-MM-DD.sql.gz` sem travar produção
   (`--single-transaction`). Se o dump completo estourar o tempo, o script cai
   automaticamente para o modo tabela-por-tabela.
3. `scripts/db-import.sh` — recria o banco local e importa o dump mais recente.

**Privacidade (LGPD):** `dumps/`, `prod-snapshot/`, `public/settings.php` e
`scripts/prod.env` são ignorados pelo git e **jamais** devem ser commitados —
contêm dados pessoais reais e segredos.

## E-mails
Todo e-mail enviado pelo app cai no Mailpit (http://localhost:8025).
Nenhuma mensagem sai para a internet — pode testar fluxos com dados reais
sem risco de notificar pessoas.

## Problemas comuns
- **Porta em uso (3306/8084/8089/8025):** pare o serviço conflitante ou
  ajuste a porta no `docker-compose.yml`.
- **Login recusa senha que funciona em produção:** `PASSWORD_SALT` do
  `public/settings.php` local difere do de produção.
- **SSH da Locaweb “para de funcionar”:** o painel desabilita o SSH
  automaticamente ~3h depois de habilitado — reabilite e rode de novo
  (os scripts detectam o canal morto e avisam).
- **Erros na importação citando DEFINER:** remova as cláusulas DEFINER do dump
  (`gunzip -c dump.sql.gz | sed 's/DEFINER=[^*]*\*/\*/g' | gzip > dump2.sql.gz`).
- **Página avisa “banco de dados fora do ar”:** o serviço `db` ainda está
  inicializando — aguarde o healthcheck (`docker compose ps`).

## Testes
- `scripts/smoke.sh` — varre todas as páginas logado e reprova em 5xx, aviso do
  PHP, sessão perdida ou XSS cru.
- `scripts/test-pedido.sh` — envio do pedido e lembrete de pedidos não enviados.
  Cria os dados dentro de uma transação e desfaz no fim: não altera o banco local.
