<?php
// Testes do Balanço da chamada.
// Não roda direto: use scripts/test-balanco.sh.
//
// Os dados de teste são criados dentro de uma transação e desfeitos no final: o banco
// local carrega uma cópia real de produção e não pode ser sujado. O runner confere as
// contagens antes e depois e falha se sobrou qualquer linha — porque um rollback é um
// combinado, não uma garantia, e uma suíte que passa verde deixando lixo no banco é
// pior do que uma que falha.
//
// O Balanço NÃO GRAVA NADA. Estes testes só criam fixture para poder perguntar, e a
// única escrita que existe aqui é a do próprio fixture.

$falhas = 0;
$total  = 0;

function verifica($descricao, $condicao, $detalhe = '')
{
    global $falhas, $total;
    $total++;
    if ($condicao) {
        echo "  ok    $descricao\n";
        return;
    }
    $falhas++;
    echo "  FALHA $descricao" . ($detalhe !== '' ? "\n         -> $detalhe" : "") . "\n";
}

function insere($sql)
{
    $res = executa_sql($sql);
    if (!$res) { echo "  ERRO no fixture: $sql\n"; exit(2); }
    return id_inserido();
}

function valor_escalar($sql)
{
    $res = executa_sql($sql);
    if (!$res) return null;
    $row = mysqli_fetch_array($res, MYSQLI_NUM);
    return $row ? $row[0] : null;
}

require "/var/www/html/common.inc.php";
require "/var/www/html/balanco.inc.php";

mysqli_begin_transaction($conn_link);

// ---------------------------------------------------------------------------
// FIXTURE COMUM. Núcleo, produtor e produto descartáveis, criados dentro da transação.
// Ids de produto na faixa 9000xx para não colidirem com os da cópia de produção.
// ---------------------------------------------------------------------------
$tipo_id = array();
foreach (array('Mensal') as $nome_tipo)
    $tipo_id[$nome_tipo] = valor_escalar("SELECT nuct_id FROM nucleotipos WHERE nuct_nome = '$nome_tipo'");

verifica("os tipos de nucleo estao cadastrados", (bool)$tipo_id['Mensal'],
    json_encode($tipo_id));

$forn_res = insere("INSERT INTO fornecedores (forn_prodt, forn_nome_curto, forn_nome_completo, forn_archive)
    VALUES (1,'fornbal','Fornecedor do balanco',0)");

// compra 8 e venda 10: o Balanço é a PREÇO DE VENDA, que é por onde o cestante é
// cobrado, e os dois preços diferentes é o que faz esse teste significar alguma coisa.
insere("INSERT INTO produtos (prod_id, prod_prodt, prod_forn, prod_nome, prod_unidade,
    prod_valor_compra, prod_valor_venda, prod_valor_venda_margem, prod_ini_validade, prod_fim_validade,
    prod_multiplo_venda, prod_retornavel)
    VALUES (900003,1," . (int)$forn_res . ",'Secos do estoque','kg',8.00,10.00,13.00,
    '2020-01-01 00:00:00','2030-01-01 00:00:00',1,0)");
$prod_est_id = 900003;


echo "\nbalanco da chamada: onde a mercadoria entrou e saiu\n";
// ---------------------------------------------------------------------------

// Chamada propria: dois nucleos, um deles com entrega faltando de proposito.
$nuc_cf1 = insere("INSERT INTO nucleos (nuc_nome_curto, nuc_nome_completo, nuc_archive, nuc_nuct)
    VALUES ('nucconf1','Conferencia 1',0," . (int)$tipo_id['Mensal'] . ")");
$nuc_cf2 = insere("INSERT INTO nucleos (nuc_nome_curto, nuc_nome_completo, nuc_archive, nuc_nuct)
    VALUES ('nucconf2','Conferencia 2',0," . (int)$tipo_id['Mensal'] . ")");

$usr_cf1 = insere("INSERT INTO usuarios (usr_nome_completo,usr_nome_curto,usr_email,usr_senha,usr_archive,usr_nuc)
    VALUES ('Cf um','cf1','cf1@teste.local','x','0'," . (int)$nuc_cf1 . ")");
$usr_cf2 = insere("INSERT INTO usuarios (usr_nome_completo,usr_nome_curto,usr_email,usr_senha,usr_archive,usr_nuc)
    VALUES ('Cf dois','cf2','cf2@teste.local','x','0'," . (int)$nuc_cf2 . ")");

// produto: compra 8, venda 10 — o balanco e a preco de VENDA
//
// cha_prodt = 2 (Secos) e nao 1 (Frescos): so tipo com prodt_mutirao = 1 guarda estoque,
// e metade dos testes daqui para baixo mede justamente o estoque. Com Frescos a
// conferencia devolve estoque zerado de proposito — o produtor entrega direto no nucleo —
// e o teste do 'nao cobrado' passaria a medir outra coisa.
$cha_cf = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual)
    VALUES (2,'2026-07-18 23:59:59','2026-07-01 00:00:00','2026-07-14 23:59:59',0.00)");
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade, chaprod_recebido_confirmado)
    VALUES (" . (int)$cha_cf . "," . (int)$prod_est_id . ",1,90)");

// nucleo 1 confirmou 50, os cestantes dele receberam 50 — fecha
executa_sql("INSERT INTO distribuicao (dist_cha, dist_nuc, dist_prod, dist_quantidade, dist_quantidade_recebido)
    VALUES (" . (int)$cha_cf . "," . (int)$nuc_cf1 . "," . (int)$prod_est_id . ",50,50)");
$ped_cf1 = insere("INSERT INTO pedidos (ped_cha, ped_usr, ped_nuc, ped_fechado, ped_usr_associado)
    VALUES (" . (int)$cha_cf . "," . (int)$usr_cf1 . "," . (int)$nuc_cf1 . ",1,'1')");
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_cf1 . "," . (int)$prod_est_id . ",50,50)");

// nucleo 2 confirmou 40, entregou 30 e deixou 1 linha SEM entrega registrada.
//
// A linha faltante e de OUTRO produto, no MESMO pedido: `pedidos` tem UNIQUE
// (ped_usr, ped_cha) — um pedido por cestante por chamada —, entao dois pedidos para a
// mesma pessoa na mesma chamada nao existem. A primeira versao deste fixture tentou, e
// o banco recusou.
$prod_cf2 = insere("INSERT INTO produtos (prod_id, prod_prodt, prod_forn, prod_nome, prod_unidade,
    prod_valor_compra, prod_valor_venda, prod_valor_venda_margem, prod_ini_validade, prod_fim_validade,
    prod_multiplo_venda, prod_retornavel)
    VALUES (900004,1," . (int)$forn_res . ",'Segundo produto','kg',8.00,10.00,13.00,
    '2020-01-01 00:00:00','2030-01-01 00:00:00',1,0)");
$prod_cf2_id = 900004;
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade)
    VALUES (" . (int)$cha_cf . "," . (int)$prod_cf2_id . ",1)");

executa_sql("INSERT INTO distribuicao (dist_cha, dist_nuc, dist_prod, dist_quantidade, dist_quantidade_recebido)
    VALUES (" . (int)$cha_cf . "," . (int)$nuc_cf2 . "," . (int)$prod_est_id . ",40,40)");
$ped_cf2 = insere("INSERT INTO pedidos (ped_cha, ped_usr, ped_nuc, ped_fechado, ped_usr_associado)
    VALUES (" . (int)$cha_cf . "," . (int)$usr_cf2 . "," . (int)$nuc_cf2 . ",1,'1')");
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_cf2 . "," . (int)$prod_est_id . ",30,30)");
// Esta linha e de um produto que o nucleo NUNCA confirmou receber. Ela NAO deve entrar
// no aviso: contribui zero dos dois lados da conta, e contar essas dava a um nucleo com
// diferenca 0,00 um "29 sem entrega registrada" ao lado.
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_cf2 . "," . (int)$prod_cf2_id . ",10,NULL)");

// Ja ESTA outra e o caso que o aviso existe para pegar: outro cestante do mesmo nucleo
// pediu o produto que o nucleo recebeu, e a entrega dele nao foi anotada. E ela que pode
// explicar os 100 de diferenca. Precisa de outro cestante porque `pedidos` tem UNIQUE
// (ped_usr, ped_cha): um pedido por pessoa por chamada.
$usr_cf2b = insere("INSERT INTO usuarios (usr_nome_completo,usr_nome_curto,usr_email,usr_senha,usr_archive,usr_nuc)
    VALUES ('Cf dois b','cf2b','cf2b@teste.local','x','0'," . (int)$nuc_cf2 . ")");
$ped_cf2b = insere("INSERT INTO pedidos (ped_cha, ped_usr, ped_nuc, ped_fechado, ped_usr_associado)
    VALUES (" . (int)$cha_cf . "," . (int)$usr_cf2b . "," . (int)$nuc_cf2 . ",1,'1')");
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_cf2b . "," . (int)$prod_est_id . ",10,NULL)");

$cf = balanco_da_chamada($cha_cf);

function nuc_da_conf($cf, $nuc) {
    foreach ((array)$cf['nucleos'] as $n) if ($n['nuc_id'] === (int)$nuc) return $n;
    return null;
}

verifica("o balanco e a preco de VENDA, nao de compra",
    is_array($cf) && ($n = nuc_da_conf($cf, $nuc_cf1)) && round($n['recebeu'],2) == 500.00,
    is_array($cf) ? json_encode($cf['nucleos']) : var_export($cf, true));

verifica("nucleo que entregou tudo fecha em zero",
    ($n = nuc_da_conf($cf, $nuc_cf1)) && round($n['diferenca'],2) == 0.00,
    var_export($n, true));

verifica("nucleo que recebeu mais do que distribuiu mostra a diferenca",
    ($n = nuc_da_conf($cf, $nuc_cf2)) && round($n['recebeu'],2) == 400.00
                                      && round($n['distribuiu'],2) == 300.00
                                      && round($n['diferenca'],2) == 100.00,
    var_export($n, true));

// O AVISO que impede de cobrar do nucleo um erro de digitacao: sem ele, os 100 acima
// seriam lidos como perda, quando ha uma linha pedida sem entrega registrada.
//
// UMA, e nao duas: a linha do outro produto tambem esta em branco, mas o nucleo nunca
// confirmou ter recebido aquele produto — ela nao pode explicar diferenca nenhuma. Contar
// as duas era o defeito que fazia um nucleo com diferenca 0,00 aparecer com dezenas de
// avisos ao lado.
verifica("avisa so as linhas que PODEM explicar a diferenca",
    ($n = nuc_da_conf($cf, $nuc_cf2)) && $n['sem_registro'] === 1
 && ($n1 = nuc_da_conf($cf, $nuc_cf1)) && $n1['sem_registro'] === 0,
    var_export($n['sem_registro'], true));

// A DEMANDA CRUA, e o unico numero da tabela que nao depende de alguem conferir
// mercadoria. Fixture: nucleo 1 pediu 50 e levou 50; nucleo 2 pediu 30 + 10 do outro
// produto no mesmo pedido, mais 10 do cf2b. Tudo a preco de venda 10.
verifica("o balanco traz o que os cestantes PEDIRAM, e nao so o que levaram",
    is_array($cf) && ($n = nuc_da_conf($cf, $nuc_cf1)) && round($n['pediu'],2) == 500.00,
    var_export(isset($n) ? $n : null, true));

// "sem nada abatido": o pedido nao encolhe por causa de estoque, nem de produto que
// ficou indisponivel. E o que as pessoas pediram, antes de qualquer conferencia.
verifica("o pedido NAO desconta o produto que ficou indisponivel",
    is_array($cf) && ($n = nuc_da_conf($cf, $nuc_cf2)) && round($n['pediu'],2) == 500.00,
    var_export(isset($n) ? $n : null, true));

verifica("e o total soma o pedido de todos os nucleos",
    is_array($cf) && round($cf['total']['pediu'],2) == 1000.00,
    var_export(is_array($cf) ? $cf['total']['pediu'] : $cf, true));

verifica("o total soma os nucleos",
    round($cf['total']['recebeu'],2) == 900.00 && round($cf['total']['distribuiu'],2) == 800.00
 && round($cf['total']['diferenca'],2) == 100.00 && $cf['total']['sem_registro'] === 1,
    json_encode($cf['total']));

verifica("o abatido de Financas e a distancia entre os nucleos e a confirmacao dela",
    round($cf['confirmado'],2) == 900.00 && round($cf['abatido'],2) == 0.00,
    "confirmado=" . $cf['confirmado'] . " abatido=" . $cf['abatido']);

// B − C, ja descontado o estoque: o que a Rede pagou e ninguem foi cobrado.
verifica("o nao cobrado e o que a Rede pagou sem ninguem ser cobrado",
    round($cf['nao_cobrado'],2) == 100.00,
    var_export($cf['nao_cobrado'], true));

// ESTOQUE entra na conta: sem ele, mercadoria guardada seria lida como perda.
executa_sql("INSERT INTO estoque (est_cha, est_prod, est_prod_qtde_antes, est_prod_qtde_depois)
    VALUES (" . (int)$cha_cf . "," . (int)$prod_est_id . ",0,10)");
$cf2 = balanco_da_chamada($cha_cf);
verifica("mercadoria que ficou guardada sai do 'nao cobrado'",
    round($cf2['estoque']['depois'],2) == 100.00 && round($cf2['nao_cobrado'],2) == 0.00,
    "estoque=" . json_encode($cf2['estoque']) . " nao_cobrado=" . $cf2['nao_cobrado']);

// O NUMERO VIRA NEGATIVO, e isso nao e defeito: quer dizer que saiu mais mercadoria —
// entregue mais guardada — do que a Rede pagou. Visto na chamada 1123 da base, com
// -81,00. A TELA depende deste sinal para trocar o rotulo: com o negativo ela mostra
// "recebido e nao pago", porque "pago e nao cobrado -81,00" afirma o contrario do que
// aconteceu. Se a funcao parasse de devolver negativo, o rotulo nunca mais apareceria e
// nada reclamaria.
executa_sql("UPDATE estoque SET est_prod_qtde_depois = 30
    WHERE est_cha = " . (int)$cha_cf . " AND est_prod = " . (int)$prod_est_id);
$cf_neg = balanco_da_chamada($cha_cf);
verifica("guardar mais do que a Rede pagou deixa o 'nao cobrado' NEGATIVO",
    is_array($cf_neg) && round($cf_neg['nao_cobrado'],2) == -200.00,
    "estoque=" . json_encode(is_array($cf_neg) ? $cf_neg['estoque'] : null)
    . " nao_cobrado=" . (is_array($cf_neg) ? $cf_neg['nao_cobrado'] : '?'));

// volta ao estado que os testes seguintes pressupoem
executa_sql("UPDATE estoque SET est_prod_qtde_depois = 10
    WHERE est_cha = " . (int)$cha_cf . " AND est_prod = " . (int)$prod_est_id);

// Nucleo que entregou SEM ter confirmado recebimento tambem tem de aparecer: e
// justamente o caso em que a conta nao fecha, e some-lo esconderia o problema.
$nuc_cf3 = insere("INSERT INTO nucleos (nuc_nome_curto, nuc_nome_completo, nuc_archive, nuc_nuct)
    VALUES ('nucconf3','Conferencia 3',0," . (int)$tipo_id['Mensal'] . ")");
$usr_cf3 = insere("INSERT INTO usuarios (usr_nome_completo,usr_nome_curto,usr_email,usr_senha,usr_archive,usr_nuc)
    VALUES ('Cf tres','cf3','cf3@teste.local','x','0'," . (int)$nuc_cf3 . ")");
$ped_cf4 = insere("INSERT INTO pedidos (ped_cha, ped_usr, ped_nuc, ped_fechado, ped_usr_associado)
    VALUES (" . (int)$cha_cf . "," . (int)$usr_cf3 . "," . (int)$nuc_cf3 . ",1,'1')");
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_cf4 . "," . (int)$prod_est_id . ",5,5)");

$cf3 = balanco_da_chamada($cha_cf);
verifica("nucleo que entregou sem confirmar recebimento aparece, com diferenca negativa",
    ($n = nuc_da_conf($cf3, $nuc_cf3)) && round($n['recebeu'],2) == 0.00
                                       && round($n['distribuiu'],2) == 50.00
                                       && round($n['diferenca'],2) == -50.00,
    var_export($n, true));

// A ORDEM E A MESMA DA CAIXA DE SELECAO de nucleo — ORDER BY nuc_nome_curto, que e o que
// vinte telas deste sistema usam. Ordenava por diferenca, e quem confere volta a esta
// tabela chamada apos chamada procurando o SEU nucleo: ele mudava de lugar a cada vez.
$nomes_ordem = array_map(function ($n) { return $n['nome']; }, $cf3['nucleos']);
$esperado_ordem = $nomes_ordem;
usort($esperado_ordem, 'compara_nome_de_nucleo');

verifica("a lista vem em ordem alfabetica, como a caixa de selecao",
    $nomes_ordem === $esperado_ordem,
    json_encode($nomes_ordem) . " esperado " . json_encode($esperado_ordem));

// O comparador tem de casar com latin1_swedish_ci, a collation da base: la o u com acento
// vale u, e nao um caractere depois do z. Em ordem de BYTE "Grajau" acentuado cairia
// depois de "Santa", porque o acento chega em utf8 (common.inc.php:76) como 0xC3.
verifica("acento ordena como a letra sem acento, e nao depois do z",
    compara_nome_de_nucleo('Grajaú', 'Santa') < 0
 && compara_nome_de_nucleo('Niterói', 'Nova Iguaçu') < 0
 && compara_nome_de_nucleo('Ubá', 'Ubz') < 0,
    "Grajau/Santa=" . compara_nome_de_nucleo('Grajaú', 'Santa')
    . " Niteroi/Nova=" . compara_nome_de_nucleo('Niterói', 'Nova Iguaçu')
    . " Uba/Ubz=" . compara_nome_de_nucleo('Ubá', 'Ubz'));

// O que importa nao e devolver 0 para 'urca' e 'Urca' — e o nome cair no MESMO LUGAR da
// lista, seja como for digitado. A assercao e sobre o lugar, e nao sobre o zero: exigir
// zero brigaria com o desempate de que a estabilidade depende.
verifica("maiuscula e minuscula nao mudam onde o nome cai na lista",
    compara_nome_de_nucleo('urca', 'Santa')  > 0
 && compara_nome_de_nucleo('URCA', 'Santa')  > 0
 && compara_nome_de_nucleo('urca', 'Vargem') < 0
 && compara_nome_de_nucleo('URCA', 'Vargem') < 0,
    "urca/Santa=" . compara_nome_de_nucleo('urca', 'Santa')
    . " URCA/Vargem=" . compara_nome_de_nucleo('URCA', 'Vargem'));

// Empate depois de tirar o acento nao pode virar 0: uasort com 0 deixa a ordem por conta
// do algoritmo, e ela mudaria de uma chamada para outra na mesma tela.
verifica("nomes que so diferem no acento tem ordem estavel, e nao empate",
    compara_nome_de_nucleo('Sao', 'São') !== 0
 && (compara_nome_de_nucleo('Sao', 'São') < 0) !== (compara_nome_de_nucleo('São', 'Sao') < 0),
    "Sao/Sao_til=" . compara_nome_de_nucleo('Sao', 'São')
    . " invertido=" . compara_nome_de_nucleo('São', 'Sao'));

// O caso que a Rede explicou: repasse entre cestantes. O nucleo recebeu 50, um cestante
// levou 60 e outro nao levou nada — a conta do nucleo FECHA, e ainda assim ha linha em
// branco. Nao e erro, e o aviso nao pode acusar como se fosse.
executa_sql("UPDATE pedidoprodutos SET pedprod_entregue = 60
    WHERE pedprod_ped = " . (int)$ped_cf1 . " AND pedprod_prod = " . (int)$prod_est_id);
$usr_cf1b = insere("INSERT INTO usuarios (usr_nome_completo,usr_nome_curto,usr_email,usr_senha,usr_archive,usr_nuc)
    VALUES ('Cf um b','cf1b','cf1b@teste.local','x','0'," . (int)$nuc_cf1 . ")");
$ped_cf1b = insere("INSERT INTO pedidos (ped_cha, ped_usr, ped_nuc, ped_fechado, ped_usr_associado)
    VALUES (" . (int)$cha_cf . "," . (int)$usr_cf1b . "," . (int)$nuc_cf1 . ",1,'1')");
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_cf1b . "," . (int)$prod_est_id . ",10,NULL)");
executa_sql("UPDATE distribuicao SET dist_quantidade_recebido = 60
    WHERE dist_cha = " . (int)$cha_cf . " AND dist_nuc = " . (int)$nuc_cf1
        . " AND dist_prod = " . (int)$prod_est_id);

$cf_rep = balanco_da_chamada($cha_cf);
verifica("conta que FECHA e mesmo assim tem linha em branco: repasse entre cestantes",
    ($n = nuc_da_conf($cf_rep, $nuc_cf1)) && abs($n['diferenca']) < 0.005
                                          && $n['sem_registro'] === 1,
    var_export($n, true));

// A − B: o julgamento de Financas. Nucleos confirmaram 900, Financas confirmou 900 tambem
// (90 unidades x 10) — nada abatido.

// ---- o DETALHE, produto a produto ----
//
// Existe porque o numero sozinho manda procurar no lugar errado. Medido em Santa, Secos de
// 09/05/2026: "6 sem entrega registrada" ao lado de R$ 49,00 de diferenca. Das seis, UMA
// era a diferenca — o mel, que chegou quebrado — e as outras cinco eram produto
// inteiramente distribuido com a linha de alguem em branco.
executa_sql("UPDATE distribuicao SET dist_just_dif_entrega = 'chegou quebrado'
    WHERE dist_cha = " . (int)$cha_cf . " AND dist_nuc = " . (int)$nuc_cf2
        . " AND dist_prod = " . (int)$prod_est_id);

// O contrapeso do aviso de linhas em branco: uma coisa e o nucleo dever explicacao,
// outra e ele ja ter explicado. Sem este numero as duas apareciam iguais na tela.
$cf_just = balanco_da_chamada($cha_cf);
verifica("o balanco conta as divergencias que o nucleo ja explicou por escrito",
    is_array($cf_just) && ($n = nuc_da_conf($cf_just, $nuc_cf2)) && $n['justificativas'] === 1,
    json_encode(is_array($cf_just) ? $cf_just['nucleos'] : $cf_just));

verifica("nucleo que nao escreveu nenhuma justificativa conta zero",
    is_array($cf_just) && ($n = nuc_da_conf($cf_just, $nuc_cf1)) && $n['justificativas'] === 0,
    json_encode(is_array($cf_just) ? $cf_just['nucleos'] : $cf_just));

verifica("e o total soma as justificativas dos nucleos",
    is_array($cf_just) && $cf_just['total']['justificativas'] === 1,
    json_encode(is_array($cf_just) ? $cf_just['total'] : $cf_just));

// Justificativa em BRANCO nao conta: a coluna existe na linha desde que alguem salvou
// qualquer coisa nela, e string vazia diria que houve explicacao onde nao houve.
executa_sql("UPDATE distribuicao SET dist_just_dif_entrega = '   '
    WHERE dist_cha = " . (int)$cha_cf . " AND dist_nuc = " . (int)$nuc_cf1);
$cf_vazia = balanco_da_chamada($cha_cf);
verifica("justificativa so com espacos nao conta como explicada",
    is_array($cf_vazia) && ($n = nuc_da_conf($cf_vazia, $nuc_cf1)) && $n['justificativas'] === 0,
    json_encode(is_array($cf_vazia) ? $cf_vazia['nucleos'] : $cf_vazia));

$det = detalhe_do_nucleo_na_chamada($cha_cf, $nuc_cf2);

verifica("o detalhe traz o produto que diverge, com a justificativa ja escrita",
    is_array($det) && count($det) >= 1
    && $det[0]['nome'] === 'Secos do estoque'
    && round($det[0]['diferenca'], 2) == 100.00
    && $det[0]['justificativa'] === 'chegou quebrado',
    is_array($det) ? json_encode($det) : var_export($det, true));

// Os nomes sao o que torna a linha investigavel: "4 em branco" manda abrir outra tela.
verifica("e NOMEIA quem ficou em branco, com o que cada um pediu",
    is_array($det) && count($det[0]['em_branco']) === 1
    && $det[0]['em_branco'][0]['nome'] === 'cf2b'
    && round($det[0]['em_branco'][0]['pediu'], 2) == 10.00,
    json_encode($det[0]['em_branco']));

// Os REGISTROS que deram origem a nota. A justificativa sozinha e palavra sem lastro:
// "chegou quebrado, R$ 100,00" nao diz de quem era, quem pediu, quem levou. Sem isso,
// conferir obriga a abrir outra tela.
verifica("o detalhe traz TODA linha de cestante do produto, e nao so as em branco",
    is_array($det) && count($det[0]['cestantes']) === 2,
    json_encode($det[0]['cestantes']));

// null e 0 sao coisas diferentes na base — 6,6 milhoes de linhas NULL contra 41 mil
// zeros —, e a distincao e justamente o que o aviso "em branco" conta. Achatar as duas
// em 0,00 apagaria a diferenca entre "ninguem anotou" e "anotaram que nao levou".
verifica("e distingue quem nao teve entrega ANOTADA de quem levou zero",
    is_array($det)
    && $det[0]['cestantes'][0]['nome'] === 'cf2'
    && round($det[0]['cestantes'][0]['entregue'], 2) == 30.00
    && $det[0]['cestantes'][1]['nome'] === 'cf2b'
    && $det[0]['cestantes'][1]['entregue'] === null
    && round($det[0]['cestantes'][1]['pediu'], 2) == 10.00,
    json_encode($det[0]['cestantes']));

// A linha que existe SO pela justificativa. entrega_divergencia_justificativa.php:65
// cria assim quando ainda nao havia linha: grava o texto e deixa dist_quantidade_recebido
// NULL. Sao 93 na base, com texto como "fracao da saca de 20kg que foi entregue para
// Santa Teresa" — a justificativa existe JUSTAMENTE porque nao houve recebimento.
//
// Sem esta linha no detalhe, o aviso "1 justificada" da tabela de cima contava uma
// explicacao que o clique nao mostrava: o mesmo defeito do aviso de linhas em branco,
// um nivel abaixo.
executa_sql("INSERT INTO distribuicao (dist_cha, dist_nuc, dist_prod, dist_just_dif_entrega)
    VALUES (" . (int)$cha_cf . "," . (int)$nuc_cf2 . "," . (int)$prod_cf2_id
        . ",'fracao da saca que foi para outro nucleo')");

$det_just = detalhe_do_nucleo_na_chamada($cha_cf, $nuc_cf2);
$so_just = null;
foreach ((array)$det_just as $x) if ($x['nome'] === 'Segundo produto') $so_just = $x;

verifica("linha que existe so pela justificativa aparece no detalhe",
    $so_just !== null && $so_just['justificativa'] === 'fracao da saca que foi para outro nucleo',
    json_encode(array_map(function ($x) { return $x['nome']; }, (array)$det_just)));

// null e 0 de novo: o nucleo nao confirmou recebimento NENHUM, e 0,00 diria que
// confirmou zero. O que a tela nao pode e quebrar com o NULL.
verifica("e o recebido dela vem zerado, sem quebrar no NULL",
    $so_just !== null && round($so_just['recebeu'], 2) == 0.00,
    var_export($so_just, true));

// E o aviso de cima passa a bater com o que o clique mostra.
$cf_bate = balanco_da_chamada($cha_cf);
verifica("o numero de justificativas do aviso bate com o que o detalhe mostra",
    is_array($cf_bate) && ($n = nuc_da_conf($cf_bate, $nuc_cf2))
    && $n['justificativas'] === count(array_filter((array)$det_just,
           function ($x) { return $x['justificativa'] !== ''; })),
    "aviso=" . (isset($n) ? $n['justificativas'] : '?')
    . " detalhe=" . count(array_filter((array)$det_just, function ($x) { return $x['justificativa'] !== ''; })));

executa_sql("DELETE FROM distribuicao WHERE dist_cha = " . (int)$cha_cf
    . " AND dist_nuc = " . (int)$nuc_cf2 . " AND dist_prod = " . (int)$prod_cf2_id);

// Produto que o nucleo NAO recebeu nao entra: nao ha o que explicar nele.
verifica("produto que o nucleo nao recebeu fica de fora do detalhe",
    is_array($det) && count(array_filter($det, function ($x) { return $x['nome'] === 'Segundo produto'; })) === 0,
    json_encode(array_map(function ($x) { return $x['nome']; }, (array)$det)));

// Nucleo sem nada a explicar devolve lista vazia, e nao null.
verifica("nucleo sem divergencia e sem linha em branco devolve lista vazia",
    is_array($d0 = detalhe_do_nucleo_na_chamada($cha_cf, $nuc_cf3)) && count($d0) === 0,
    var_export($d0, true));

executa_sql("CREATE TEMPORARY TABLE distribuicao (
    dist_cha mediumint(6) unsigned NOT NULL, dist_nuc mediumint(6) unsigned NOT NULL) ENGINE=InnoDB");
$sombra_d = (executa_sql("SELECT dist_just_dif_entrega FROM distribuicao") === false);
$d_sem_bd = detalhe_do_nucleo_na_chamada($cha_cf, $nuc_cf2);
executa_sql("DROP TEMPORARY TABLE distribuicao");

verifica("a sombra faz o servidor recusar o detalhe", $sombra_d);
verifica("detalhe de consulta recusada e null, e nao 'nada a explicar'",
    $d_sem_bd === null, var_export($d_sem_bd, true));

// ---- chamada que NAO passa pelo mutirao ----
//
// Frescos e afins: o produtor entrega direto no nucleo. Nao ha contagem central, remessa
// a caminho nem estoque guardado entre chamadas — medido na base, as 839 chamadas com
// prodt_mutirao = 0 nao tem UMA linha de estoque. A tela le esta flag para nao mostrar
// linha, coluna nem explicacao de etapa que nunca aconteceu.
verifica("chamada de tipo com mutirao se declara como tal",
    is_array($cf) && $cf['tem_mutirao'] === true,
    var_export(is_array($cf) ? $cf['tem_mutirao'] : $cf, true));

$cha_sm = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual)
    VALUES (1,'2026-07-25 23:59:59','2026-07-01 00:00:00','2026-07-21 23:59:59',0.00)");
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade, chaprod_recebido_confirmado)
    VALUES (" . (int)$cha_sm . "," . (int)$prod_est_id . ",1,20)");
executa_sql("INSERT INTO distribuicao (dist_cha, dist_nuc, dist_prod, dist_quantidade, dist_quantidade_recebido)
    VALUES (" . (int)$cha_sm . "," . (int)$nuc_cf1 . "," . (int)$prod_est_id . ",20,20)");
// linha de estoque PLANTADA de proposito: em producao ela nunca existe para este tipo, e
// o teste prova que a conferencia nem pergunta — nao que o banco esteja vazio por sorte.
executa_sql("INSERT INTO estoque (est_cha, est_prod, est_prod_qtde_antes, est_prod_qtde_depois)
    VALUES (" . (int)$cha_sm . "," . (int)$prod_est_id . ",7,9)");

$cf_sm = balanco_da_chamada($cha_sm);

verifica("chamada de tipo SEM mutirao se declara como tal",
    is_array($cf_sm) && $cf_sm['tem_mutirao'] === false,
    var_export(is_array($cf_sm) ? $cf_sm['tem_mutirao'] : $cf_sm, true));

verifica("e o estoque dela vem zerado, mesmo havendo linha de estoque gravada",
    is_array($cf_sm) && round($cf_sm['estoque']['antes'],2) == 0.00
                     && round($cf_sm['estoque']['depois'],2) == 0.00,
    json_encode(is_array($cf_sm) ? $cf_sm['estoque'] : $cf_sm));

// O 'nao cobrado' dela e so B − C: sem estoque nos dois extremos, nao ha o que descontar.
verifica("o nao cobrado dela e a distancia entre Financas e o cestante, sem estoque",
    is_array($cf_sm) && round($cf_sm['nao_cobrado'],2) == 200.00,
    var_export(is_array($cf_sm) ? $cf_sm['nao_cobrado'] : $cf_sm, true));

// (a fila de fechamento e do modulo financeiro, e nao entra nesta suite)

verifica("chamada que nao existe devolve null",
    balanco_da_chamada(99999999) === null);

// CONTRATO da familia.
executa_sql("CREATE TEMPORARY TABLE distribuicao (
    dist_cha mediumint(6) unsigned NOT NULL, dist_nuc mediumint(6) unsigned NOT NULL) ENGINE=InnoDB");
$sombra_cf = (executa_sql("SELECT dist_quantidade_recebido FROM distribuicao") === false);
$cf_sem_bd = balanco_da_chamada($cha_cf);
executa_sql("DROP TEMPORARY TABLE distribuicao");

verifica("a sombra faz o servidor recusar o balanco", $sombra_cf);
verifica("balanco de consulta recusada e null, e nao chamada sem divergencia",
    $cf_sem_bd === null, var_export($cf_sem_bd, true));

mysqli_rollback($conn_link);

echo "\n";
if ($falhas === 0) { echo "TODOS OS $total TESTES PASSARAM\n"; exit(0); }
echo "$falhas de $total TESTES FALHARAM\n";
exit(1);
