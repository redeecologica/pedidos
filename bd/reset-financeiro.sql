-- APAGA TUDO QUE O MÓDULO FINANCEIRO GRAVOU, como se ele nunca tivesse rodado.
-- Passada à mão. Nenhum script lê este arquivo.
--
-- PARA QUE SERVE
-- Permitir um teste de verdade em produção — uma força-tarefa registrando um mês inteiro
-- a partir das planilhas — e depois voltar ao estado anterior antes de o sistema passar
-- a valer para todos.
--
-- POR QUE ISSO É SEGURO: O MÓDULO É UMA ILHA
-- Conferido no schema, e não por memória. Tudo que ele grava está em quatro tabelas que
-- só ele usa — `contas`, `transacoes`, `lancamentos`, `rateios` — mais UMA coluna numa
-- tabela antiga: `nucleos.nuc_quota_rateio`, escrita pela tela de quotas.
--
-- Nenhuma tabela de fora aponta para as quatro (information_schema.KEY_COLUMN_USAGE
-- devolve só as FK internas, de lancamentos para contas e transacoes). Então apagá-las
-- não deixa referência órfã em lugar nenhum.
--
-- O QUE ESTE SCRIPT NÃO TOCA — e é o que importa
--   pedidos · pedidoprodutos · chamadas · chamadaprodutos · distribuicao · estoque
--   usuarios · nucleos (fora da coluna de quota) · fornecedores · produtos
-- O módulo LÊ tudo isso e nunca escreve. O trabalho de entrega, mutirão e pedido segue
-- exatamente como estava.
--
-- ORDEM DO APAGAMENTO
-- `lancamentos` tem chave estrangeira para `contas` e `transacoes`, então ela sai
-- primeiro. Trocar a ordem faz o banco recusar — o que é bom: a recusa é a prova de que
-- as chaves estão fazendo o trabalho delas.
--
-- COMO RODAR (faça backup antes)
--   1. bloco ANTES — guarde a saída: é o retrato do que está sendo apagado
--   2. os DELETE
--   3. decida sobre as QUOTAS (bloco opcional, leia a explicação)
--   4. bloco DEPOIS — as quatro contagens têm de voltar 0


-- ---------------------------------------------------------------- ANTES ------
SELECT 'o que sera apagado' AS bloco;
SELECT (SELECT COUNT(*) FROM contas)      AS contas,
       (SELECT COUNT(*) FROM transacoes)  AS transacoes,
       (SELECT COUNT(*) FROM lancamentos) AS lancamentos,
       (SELECT COUNT(*) FROM rateios)     AS rateios;

-- por tipo, para conferir depois se o número bate com o que a força-tarefa registrou
SELECT tra_tipo, COUNT(*) transacoes, ROUND(SUM(ABS(l.lan_valor))/2,2) valor
  FROM transacoes t JOIN lancamentos l ON l.lan_tra = t.tra_id
 GROUP BY tra_tipo ORDER BY tra_tipo;

-- e o que NÃO será tocado, para comparar depois
SELECT 'intocado — confira que nao muda' AS bloco;
SELECT (SELECT COUNT(*) FROM pedidos)         AS pedidos,
       (SELECT COUNT(*) FROM pedidoprodutos)  AS pedidoprodutos,
       (SELECT COUNT(*) FROM distribuicao)    AS distribuicao,
       (SELECT COUNT(*) FROM estoque)         AS estoque,
       (SELECT COUNT(*) FROM usuarios)        AS usuarios;


-- ---------------------------------------------------------------- APAGA ------
DELETE FROM lancamentos;
DELETE FROM rateios;
DELETE FROM transacoes;
DELETE FROM contas;

-- Os contadores voltam ao começo. Não é obrigatório para a correção — id não significa
-- nada aqui —, mas é o que faz o segundo teste parecer o primeiro, em vez de começar na
-- transação 4.312 e deixar quem lê achando que sobrou coisa.
ALTER TABLE transacoes  AUTO_INCREMENT = 1;
ALTER TABLE lancamentos AUTO_INCREMENT = 1;
ALTER TABLE contas      AUTO_INCREMENT = 1;


-- ------------------------------------------------------- QUOTAS (opcional) ---
-- As quotas de rateio são CONFIGURAÇÃO, não dado de teste: dizem quanto cada núcleo pesa
-- na divisão dos custos, e a Rede decidiu isso uma vez. Apagá-las obrigaria a redecidir.
--
-- Rode a linha abaixo SÓ se quiser voltar também a configuração ao ponto zero. Na dúvida,
-- não rode: uma quota errada é visível na tela de Quotas, e uma quota apagada não é.
--
-- UPDATE nucleos SET nuc_quota_rateio = NULL;


-- ---------------------------------------------------------------- DEPOIS -----
SELECT 'tem de voltar tudo zero' AS bloco;
SELECT (SELECT COUNT(*) FROM contas)      AS contas,
       (SELECT COUNT(*) FROM transacoes)  AS transacoes,
       (SELECT COUNT(*) FROM lancamentos) AS lancamentos,
       (SELECT COUNT(*) FROM rateios)     AS rateios;

SELECT 'tem de bater com o bloco ANTES' AS bloco;
SELECT (SELECT COUNT(*) FROM pedidos)         AS pedidos,
       (SELECT COUNT(*) FROM pedidoprodutos)  AS pedidoprodutos,
       (SELECT COUNT(*) FROM distribuicao)    AS distribuicao,
       (SELECT COUNT(*) FROM estoque)         AS estoque,
       (SELECT COUNT(*) FROM usuarios)        AS usuarios;
