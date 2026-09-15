-- ============================================================================
-- SUPERADA POR bd/migracao-cria-razao.sql — NÃO RODE ESTE ARQUIVO EM PRODUÇÃO.
--
-- Produção nunca teve a forma antiga da tabela: ela não tinha transacoes nenhuma.
-- migracao-cria-razao.sql cria a tabela já com tra_categoria, então este
-- ADD COLUMN encontraria a coluna existente e o banco recusaria a passada inteira.
--
-- O arquivo fica no repositório porque serve a uma base que JÁ tenha a forma antiga —
-- uma cópia local velha, por exemplo. Para produção, o roteiro é de dois passos:
-- migracao-cria-razao.sql e depois migracao-rateio.sql, e mais nada.
-- ============================================================================

-- Acrescenta a CATEGORIA da transação, para o Caixa do núcleo (spec §4).
-- Passada ÚNICA, à mão. Nenhum script lê este arquivo.
--
-- POR QUE UMA COLUNA, E NÃO TEXTO NO HISTÓRICO
-- Despesa e repasse à Rede têm as MESMAS duas pernas de propósito (núcleo +X ·
-- rede −X): a diferença entre "o núcleo gastou" e "o núcleo entregou o dinheiro"
-- não está no movimento, está na intenção. O que os separa é tra_tipo, e o que
-- separa uma despesa de outra é a categoria — que é justamente por onde o fluxo
-- de caixa mensal (spec §5) agrupa. Dentro do histórico, texto livre, não daria
-- para somar por categoria sem adivinhar grafia.
--
-- NULLABLE de propósito: só despesa tem categoria. Repasse, pagamento a produtor
-- e outra receita não têm o que categorizar, e gravar 'outros' neles afirmaria
-- uma classificação que ninguém fez.
--
-- Percona 5.6 não tem ADD COLUMN IF NOT EXISTS: rodar duas vezes dá erro 1060
-- (Duplicate column name), que é ruído, não estrago. Confira antes com o SELECT.

-- ANTES: tem de voltar 0
SELECT COUNT(*) AS ja_existe FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transacoes'
   AND COLUMN_NAME = 'tra_categoria';

ALTER TABLE transacoes ADD COLUMN tra_categoria varchar(30) DEFAULT NULL;

-- DEPOIS: tem de voltar 1, e todas as transações já existentes ficam com NULL,
-- que é a verdade — elas são de pagamento, e pagamento não tem categoria.
SELECT COUNT(*) AS existe_agora FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transacoes'
   AND COLUMN_NAME = 'tra_categoria';
SELECT tra_tipo, COUNT(*) linhas, SUM(tra_categoria IS NULL) sem_categoria
  FROM transacoes GROUP BY tra_tipo;
