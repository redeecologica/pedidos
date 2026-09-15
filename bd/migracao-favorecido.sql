-- ============================================================================
-- SUPERADA POR bd/migracao-cria-razao.sql — NÃO RODE ESTE ARQUIVO EM PRODUÇÃO.
--
-- Produção nunca teve a forma antiga da tabela: ela não tinha transacoes nenhuma.
-- migracao-cria-razao.sql cria a tabela já com tra_favorecido, então este
-- ADD COLUMN encontraria a coluna existente e o banco recusaria a passada inteira.
--
-- O arquivo fica no repositório porque serve a uma base que JÁ tenha a forma antiga —
-- uma cópia local velha, por exemplo. Para produção, o roteiro é de dois passos:
-- migracao-cria-razao.sql e depois migracao-rateio.sql, e mais nada.
-- ============================================================================

-- Quem recebeu o dinheiro. Passada ÚNICA, à mão. Nenhum script lê este arquivo.
--
-- POR QUE UMA COLUNA, E NÃO O HISTÓRICO
-- Numa despesa do núcleo o dinheiro sai para uma pessoa — o motorista, quem levou as
-- embalagens — que NÃO tem conta no sistema e nunca vai ter: não é cestante, não é
-- produtor, não é núcleo. Hoje esse nome não fica registrado em lugar nenhum.
--
-- Dentro do histórico ele até caberia, mas em texto livre misturado com a descrição:
-- não daria para somar quanto foi pago a cada um no ano sem adivinhar grafia. Em coluna
-- própria, "quanto pagamos de motorista para Fulano" vira consulta.
--
-- NULLABLE de propósito: repasse e pagamento a produtor têm conta do outro lado, e ali
-- o favorecido é a própria conta. Preencher os dois seria dizer a mesma coisa duas
-- vezes, e as duas poderiam divergir.
--
-- Percona 5.6 não tem ADD COLUMN IF NOT EXISTS: rodar duas vezes dá erro 1060, que é
-- ruído e não estrago. Confira antes com o SELECT.

-- ANTES: tem de voltar 0
SELECT COUNT(*) AS ja_existe FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transacoes'
   AND COLUMN_NAME = 'tra_favorecido';

ALTER TABLE transacoes ADD COLUMN tra_favorecido varchar(120) DEFAULT NULL;

-- DEPOIS: tem de voltar 1, e tudo que já existe fica NULL — que é a verdade, porque
-- ninguém informou favorecido antes desta coluna existir.
SELECT COUNT(*) AS existe_agora FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transacoes'
   AND COLUMN_NAME = 'tra_favorecido';
SELECT COUNT(*) AS linhas, SUM(tra_favorecido IS NULL) AS sem_favorecido FROM transacoes;
