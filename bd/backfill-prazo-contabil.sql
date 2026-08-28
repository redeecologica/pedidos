-- Preenche cha_dt_prazo_contabil das chamadas antigas que ficaram sem prazo.
-- Passada ÚNICA, para rodar à mão em produção. Não é migração automática e não é
-- lida por script nenhum (scripts/db-import.sh só carrega dumps/*.sql.gz).
--
-- Prazo nulo deixa a chamada aberta para registro de entrega para sempre —
-- entrega_cestante.php lê "(cha_dt_prazo_contabil is null) OR (... > now())".
--
-- REGRA: prazo = entrega + 10 dias, só para entrega anterior a julho de 2026.
-- Chamada mais recente pode estar genuinamente aberta, e o prazo dela é decisão
-- do time de finanças; o último bloco lista essas como fila de trabalho.
--
-- Idempotente: só toca em linha com prazo NULO.
--
-- Não conserta as chamadas cujo prazo foi gravado ANTES da entrega — são erro de
-- digitação, não ausência, e pedem decisão caso a caso.
--
-- COMO RODAR (faça backup antes; é UPDATE em massa em produção)
--   1. bloco ANTES, para conferir os números
--   2. UPDATE
--   3. bloco DEPOIS: "ainda_sem_prazo_antigas" tem de ser 0
--   4. bloco LISTA, e passe o resultado para o time de finanças


-- ---------------------------------------------------------------- ANTES ------
SELECT COUNT(*)            AS serao_alteradas,
       MIN(cha_dt_entrega) AS entrega_mais_antiga,
       MAX(cha_dt_entrega) AS entrega_mais_nova
FROM chamadas
WHERE cha_dt_prazo_contabil IS NULL
  AND cha_dt_entrega IS NOT NULL
  AND cha_dt_entrega < '2026-07-01';

SELECT cha_id,
       cha_dt_entrega,
       cha_dt_entrega + INTERVAL 10 DAY AS prazo_que_sera_gravado
FROM chamadas
WHERE cha_dt_prazo_contabil IS NULL
  AND cha_dt_entrega IS NOT NULL
  AND cha_dt_entrega < '2026-07-01'
ORDER BY cha_dt_entrega DESC
LIMIT 10;


-- ---------------------------------------------------------------- UPDATE -----
UPDATE chamadas
   SET cha_dt_prazo_contabil = cha_dt_entrega + INTERVAL 10 DAY
 WHERE cha_dt_prazo_contabil IS NULL
   AND cha_dt_entrega IS NOT NULL
   AND cha_dt_entrega < '2026-07-01';


-- ---------------------------------------------------------------- DEPOIS -----
SELECT COUNT(*) AS ainda_sem_prazo_antigas
FROM chamadas
WHERE cha_dt_prazo_contabil IS NULL
  AND cha_dt_entrega IS NOT NULL
  AND cha_dt_entrega < '2026-07-01';

-- chamadas sem data de entrega ficam de fora por não haver de onde contar; se
-- este número não for 0, vale olhar uma a uma
SELECT COUNT(*) AS sem_data_de_entrega
FROM chamadas
WHERE cha_dt_entrega IS NULL;


-- ---------------------------------------------------------------- LISTA ------
-- O que o corte deixou de fora, de propósito. Não é sobra do UPDATE: é a fila do
-- time de finanças, e enquanto estiverem aqui seguem abertas para registro.
SELECT c.cha_id,
       pt.prodt_nome AS tipo,
       c.cha_dt_entrega,
       DATEDIFF(NOW(), c.cha_dt_entrega) AS dias_desde_a_entrega
FROM chamadas c
LEFT JOIN produtotipos pt ON pt.prodt_id = c.cha_prodt
WHERE c.cha_dt_prazo_contabil IS NULL
  AND c.cha_dt_entrega >= '2026-07-01'
ORDER BY c.cha_dt_entrega;
