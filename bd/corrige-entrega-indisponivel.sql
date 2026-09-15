-- Corrige as linhas com produto ENTREGUE marcado como INDISPONÍVEL na chamada.
-- Passada ÚNICA, para rodar à mão. Não é lida por script nenhum.
--
-- O QUE SÃO
-- Poucas dezenas de linhas em toda a história da base, concentradas em duas chamadas
-- antigas: uma de Frescos, com quase todas, e uma de Secos Bimestral, com um punhado.
-- Os ids saem do bloco ANTES, abaixo.
--
-- POR QUE SÃO ERRO DE DADO, E NÃO ENTREGA
-- Três sinais, todos na mesma direção:
--
--   1. chaprod_disponibilidade = '0' — o produto estava marcado como indisponível.
--      Na chamada de Frescos, quase todos os produtos de um mesmo produtor estavam
--      assim: o produtor inteiro ficou fora daquela chamada.
--   2. chaprod_recebido_confirmado é NULL — ninguém confirmou que a Rede recebeu
--      esses produtos do produtor. É a coluna que rel_previsao_pagamento.php usa
--      para calcular quanto pagar a ele.
--   3. Quase todas as linhas têm pedprod_entregue IDÊNTICO a pedprod_quantidade. Onde
--      alguém confere entrega de verdade os números divergem com frequência — é o
--      que acontece nas linhas de produto disponível dessas mesmas chamadas.
--
-- Linhas iguais ao pedido, sem recebimento confirmado e com o produto marcado fora,
-- são quantidade que sobrou do pedido e nunca foi corrigida — não entrega que
-- aconteceu.
--
-- POR QUE NULL E NÃO 0
-- A base usa NULL para "não registrado" (milhões de linhas) e 0 para "alguém
-- registrou zero" (uma fração pequena disso). Nas próprias chamadas corrigidas as
-- linhas irmãs são NULL. Gravar 0 afirmaria que alguém conferiu e viu zero, o que não
-- aconteceu. NULL diz o que de fato se sabe: não há registro de entrega.
--
-- O QUE NÃO MUDA
-- Nenhum saldo. As duas telas — Quadro de Cestantes (cestantes_quadro.php:368) e
-- débito derivado (financeiro.inc.php) — já filtram chaprod_disponibilidade <> '0',
-- então essas linhas nunca entraram em conta nenhuma. O que muda é o dado deixar de
-- se contradizer: hoje ele diz ao mesmo tempo que o produto não estava disponível e
-- que foi entregue.
--
-- COMO RODAR (faça backup antes)
--   1. bloco ANTES — ele IMPRIME as linhas afetadas com o valor atual. Guarde essa saída:
--      é a única cópia do que está sendo apagado, porque pedidoprodutos não tem
--      coluna de histórico.
--   2. UPDATE
--   3. bloco DEPOIS: "ainda_contraditorias" tem de ser 0


-- ---------------------------------------------------------------- ANTES ------
SELECT COUNT(*) AS linhas, COUNT(DISTINCT p.ped_cha) AS chamadas,
       COUNT(DISTINCT p.ped_usr) AS cestantes
FROM pedidos p
JOIN pedidoprodutos pp  ON pp.pedprod_ped = p.ped_id
JOIN chamadaprodutos cp ON cp.chaprod_cha = p.ped_cha AND cp.chaprod_prod = pp.pedprod_prod
WHERE p.ped_fechado = 1 AND pp.pedprod_entregue > 0 AND cp.chaprod_disponibilidade = '0';

-- GUARDE ESTA SAÍDA: é a única cópia dos valores que o UPDATE apaga.
SELECT p.ped_cha AS chamada, p.ped_id, p.ped_usr AS cestante, pp.pedprod_prod AS produto,
       pp.pedprod_quantidade AS pediu, pp.pedprod_entregue AS entregue_hoje
FROM pedidos p
JOIN pedidoprodutos pp  ON pp.pedprod_ped = p.ped_id
JOIN chamadaprodutos cp ON cp.chaprod_cha = p.ped_cha AND cp.chaprod_prod = pp.pedprod_prod
WHERE p.ped_fechado = 1 AND pp.pedprod_entregue > 0 AND cp.chaprod_disponibilidade = '0'
ORDER BY chamada, cestante, produto;


-- ---------------------------------------------------------------- UPDATE -----
UPDATE pedidoprodutos pp
JOIN pedidos p          ON p.ped_id = pp.pedprod_ped
JOIN chamadaprodutos cp ON cp.chaprod_cha = p.ped_cha AND cp.chaprod_prod = pp.pedprod_prod
   SET pp.pedprod_entregue = NULL
 WHERE p.ped_fechado = 1
   AND pp.pedprod_entregue > 0
   AND cp.chaprod_disponibilidade = '0';


-- ---------------------------------------------------------------- DEPOIS -----
-- tem de voltar 0
SELECT COUNT(*) AS ainda_contraditorias
FROM pedidos p
JOIN pedidoprodutos pp  ON pp.pedprod_ped = p.ped_id
JOIN chamadaprodutos cp ON cp.chaprod_cha = p.ped_cha AND cp.chaprod_prod = pp.pedprod_prod
WHERE p.ped_fechado = 1 AND pp.pedprod_entregue > 0 AND cp.chaprod_disponibilidade = '0';

-- e as duas chamadas continuam com as entregas legítimas intactas. Ensaiado em
-- transação com rollback na cópia local: cada chamada perdeu exatamente as linhas
-- corrigidas, e nenhuma outra linha foi tocada — o chaprod_disponibilidade = '0' do
-- WHERE é o que garante isso.
SELECT p.ped_cha AS chamada, COUNT(*) AS linhas_entregues_que_ficaram
FROM pedidos p
JOIN pedidoprodutos pp ON pp.pedprod_ped = p.ped_id
WHERE p.ped_cha IN (450,1015) AND pp.pedprod_entregue > 0
GROUP BY chamada;
