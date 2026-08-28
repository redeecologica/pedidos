-- Preenche cha_dt_prazo_contabil das chamadas antigas que ficaram sem prazo.
-- Passada ÚNICA, para rodar à mão em produção. Não é migração automática.
--
-- POR QUE ISTO EXISTE
-- entrega_cestante.php calcula "dentro do prazo" como
--   (cha_dt_prazo_contabil is null) OR (cha_dt_prazo_contabil > now())
-- ou seja, prazo NULO deixa a chamada aberta para registro de entrega PARA
-- SEMPRE. Medido em 2026-08-28 na cópia de produção: 148 chamadas com entrega
-- real continuam editáveis, a mais antiga de 2018. Dá para alterar quantidade
-- entregue de uma chamada de 2019 hoje.
--
-- A REGRA
-- Decisão da Rede: prazo = data da entrega + 10 dias, para toda chamada sem
-- prazo. Precisão não importa aqui — para uma chamada que todo mundo considera
-- encerrada, qualquer data passada faz as duas coisas que o campo precisa fazer:
-- parar a edição e permitir o congelamento contábil.
--
-- Onde os 10 dias caem, contra o medido nos últimos 12 meses:
--   Frescos   mediana 4  p80  6  -> folgado
--   Secos     mediana 8  p80 10  -> exatamente o p80
--   Bimestral mediana 38 p80 39  -> curto, e de propósito: é histórico encerrado
--
-- NÃO É O MESMO QUE O PADRÃO DE CHAMADA NOVA
-- public/chamada.inc.php usa 4/6/6 por tipo em chamada nova, e a Associação
-- copia o prazo do Secos do ciclo. Aqui é uma passada só sobre o passado, com um
-- número só: o objetivo é fechar o que ficou aberto, não reconstituir qual teria
-- sido o prazo certo de cada uma.
--
-- É IDEMPOTENTE: só toca em linha com prazo NULO. Rodar duas vezes não muda nada
-- na segunda.
--
-- O QUE ESTE ARQUIVO NÃO CONSERTA
-- As 4 chamadas cujo prazo foi gravado ANTES da entrega (cha 254, 963, 1130 e
-- 1177). São erro de digitação, não ausência, e pedem decisão caso a caso — a
-- 1177 é typo de ano (2025 onde devia ser 2026) e a 963 parece mês trocado.
--
-- COMO RODAR
--   1. rode o bloco ANTES e confira os números
--   2. rode o UPDATE
--   3. rode o bloco DEPOIS: "sem prazo" tem de ser 0
-- Faça backup antes; é UPDATE em massa numa tabela de produção.


-- ---------------------------------------------------------------- ANTES ------
SELECT COUNT(*)                AS chamadas_sem_prazo,
       MIN(cha_dt_entrega)     AS entrega_mais_antiga,
       MAX(cha_dt_entrega)     AS entrega_mais_nova,
       SUM(cha_dt_entrega + INTERVAL 10 DAY > NOW()) AS ficariam_com_prazo_futuro
FROM chamadas
WHERE cha_dt_prazo_contabil IS NULL
  AND cha_dt_entrega IS NOT NULL;

-- "ficariam_com_prazo_futuro" é esperado para chamada entregue nos últimos 10
-- dias: significa "ainda aberta, fecha em breve", não erro.

-- as 10 mais recentes que serão alteradas, para conferir a cara do resultado
SELECT cha_id,
       cha_dt_entrega,
       cha_dt_entrega + INTERVAL 10 DAY AS prazo_que_sera_gravado
FROM chamadas
WHERE cha_dt_prazo_contabil IS NULL
  AND cha_dt_entrega IS NOT NULL
ORDER BY cha_dt_entrega DESC
LIMIT 10;


-- ---------------------------------------------------------------- UPDATE -----
UPDATE chamadas
   SET cha_dt_prazo_contabil = cha_dt_entrega + INTERVAL 10 DAY
 WHERE cha_dt_prazo_contabil IS NULL
   AND cha_dt_entrega IS NOT NULL;


-- ---------------------------------------------------------------- DEPOIS -----
-- tem de voltar 0; se não voltar, sobrou chamada com cha_dt_entrega nula, que é
-- outro problema e não se resolve por aqui
SELECT COUNT(*) AS ainda_sem_prazo
FROM chamadas
WHERE cha_dt_prazo_contabil IS NULL
  AND cha_dt_entrega IS NOT NULL;

-- chamadas sem data de entrega nenhuma: ficam de fora por não haver de onde
-- contar. Se este número não for 0, vale olhar uma a uma.
SELECT COUNT(*) AS sem_data_de_entrega
FROM chamadas
WHERE cha_dt_entrega IS NULL;
