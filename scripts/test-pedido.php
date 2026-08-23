<?php
// Testes do envio de pedido e do lembrete de pedidos não enviados.
// Não roda direto: use scripts/test-pedido.sh (canaliza este arquivo para o
// PHP do container, que é a mesma versão 8.4 da produção).
//
// Nada pode ser impresso antes do require de common.inc.php: é assim que o cron
// carrega o sistema, e qualquer saída anterior mudaria o comportamento da sessão.
//
// Os dados de teste são criados dentro de uma transação e desfeitos no final:
// o banco local carrega uma cópia real de produção e não pode ser sujado.

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

function valor_escalar($sql)
{
    $res = executa_sql($sql);
    if (!$res) return null;
    $row = mysqli_fetch_array($res, MYSQLI_NUM);
    return $row ? $row[0] : null;
}

function insere($sql)
{
    $res = executa_sql($sql);
    if (!$res) {
        echo "  ERRO ao inserir fixture: " . $sql . "\n";
        exit(2);
    }
    return id_inserido();
}

// --- carga do sistema, exatamente como o cron faz -------------------------
// O cron roda sob MAILTO: qualquer aviso do PHP vira e-mail para a Aline toda
// noite. O bootstrap precisa ser silencioso em CLI, não só em HTTP.
$avisos = array();
set_error_handler(function ($no, $str, $file, $line) use (&$avisos) {
    $avisos[] = "$str ($file:$line)";
    return true;
});
require "/var/www/html/common.inc.php";
require "/var/www/html/lembrete.inc.php";
require "/var/www/html/pedido.inc.php";
restore_error_handler();

echo "\nbootstrap em CLI\n";

verifica("require de common.inc.php não emite avisos",
    count($avisos) === 0,
    implode(" | ", $avisos));

verifica("URL_ABSOLUTA tem host (o link do e-mail depende dela)",
    defined('URL_ABSOLUTA') && preg_match('~^https?://[^/\s]+~', URL_ABSOLUTA) === 1,
    "URL_ABSOLUTA = " . (defined('URL_ABSOLUTA') ? var_export(URL_ABSOLUTA, true) : "(indefinida)"));

// ---------------------------------------------------------------------------
echo "\nseleção de pedidos a lembrar (janela de 30h)\n";
// ---------------------------------------------------------------------------

mysqli_begin_transaction($conn_link);

$NUC = 1; $PRODT = 1; $PROD = 1;

// três chamadas: uma dentro da janela, uma longe demais, uma já encerrada
$cha_dentro = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max)
    VALUES ($PRODT, NOW() + INTERVAL 3 DAY, NOW() - INTERVAL 5 DAY, NOW() + INTERVAL 10 HOUR)");
$cha_fora = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max)
    VALUES ($PRODT, NOW() + INTERVAL 9 DAY, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 100 HOUR)");
$cha_passou = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max)
    VALUES ($PRODT, NOW() + INTERVAL 1 DAY, NOW() - INTERVAL 9 DAY, NOW() - INTERVAL 5 HOUR)");

function cria_usuario($sufixo, $arquivado = 0)
{
    global $NUC;
    return insere("INSERT INTO usuarios (usr_nome_completo, usr_nome_curto, usr_email, usr_senha, usr_archive, usr_nuc)
        VALUES ('Teste Lembrete $sufixo', 'lembrete$sufixo', 'testlembrete-$sufixo@dev.local', 'x', '$arquivado', $NUC)");
}

// cenário => [chamada, enviado?, quantidade do item (null = sem linha), lembrete]
$cenarios = array(
    'A_deve_lembrar'          => array($cha_dentro, 0, 2,    "NULL"),
    'B_sem_itens'             => array($cha_dentro, 0, null, "NULL"),
    'C_itens_zerados'         => array($cha_dentro, 0, 0,    "NULL"),
    'D_ja_enviado'            => array($cha_dentro, 1, 2,    "NULL"),
    'E_fora_da_janela'        => array($cha_fora,   0, 2,    "NULL"),
    'F_ja_lembrado'           => array($cha_dentro, 0, 2,    "NOW()"),
    'G_usuario_arquivado'     => array($cha_dentro, 0, 2,    "NULL"),
    'H_chamada_encerrada'     => array($cha_passou, 0, 2,    "NULL"),
    'I_lembrete_antigo'       => array($cha_dentro, 0, 2,    "NOW() - INTERVAL 40 HOUR"),
);

$ped_ids = array();
foreach ($cenarios as $nome => $c) {
    list($cha, $enviado, $qtde, $lembrete) = $c;
    $usr = cria_usuario($nome, $nome === 'G_usuario_arquivado' ? 1 : 0);
    // data de atualização propositalmente antiga: se algo disparar o
    // ON UPDATE CURRENT_TIMESTAMP da coluna, o salto fica visível
    $ped = insere("INSERT INTO pedidos (ped_usr, ped_usr_associado, ped_nuc, ped_cha, ped_fechado, ped_dt_lembrete, ped_dt_atualizacao)
        VALUES ($usr, 1, $NUC, $cha, '$enviado', $lembrete, NOW() - INTERVAL 3 DAY)");
    if ($qtde !== null) {
        insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade)
            VALUES ($ped, $PROD, $qtde)");
    }
    $ped_ids[$nome] = $ped;
}

$encontrados = array();
foreach (pedidos_a_lembrar(30) as $linha) {
    $encontrados[] = $linha['ped_id'];
}

foreach ($cenarios as $nome => $c) {
    $esperado = ($nome === 'A_deve_lembrar' || $nome === 'I_lembrete_antigo');
    $achou    = in_array($ped_ids[$nome], $encontrados);
    verifica(($esperado ? "inclui " : "exclui ") . $nome,
        $achou === $esperado,
        $achou ? "veio na seleção mas não deveria" : "deveria estar na seleção e não veio");
}

// a seleção não pode arrastar pedidos reais da base junto
$fixture_ids = array_values($ped_ids);
$intrusos = array_diff($encontrados, $fixture_ids);
verifica("não seleciona pedidos fora do fixture",
    count($intrusos) === 0,
    count($intrusos) . " pedido(s) reais vieram junto: " . implode(",", array_slice($intrusos, 0, 5)));

// a linha precisa carregar o que o e-mail usa
$linha_a = null;
foreach (pedidos_a_lembrar(30) as $linha) {
    if ($linha['ped_id'] == $ped_ids['A_deve_lembrar']) $linha_a = $linha;
}
verifica("a linha traz e-mail, prazo, tipo e valor para montar a mensagem",
    $linha_a
        && !empty($linha_a['usr_email'])
        && !empty($linha_a['cha_dt_max'])
        && !empty($linha_a['prodt_nome'])
        && isset($linha_a['valor']) && $linha_a['valor'] > 0,
    $linha_a ? var_export($linha_a, true) : "(pedido A não veio na seleção)");

// ---------------------------------------------------------------------------
echo "\nmensagem e marcação\n";
// ---------------------------------------------------------------------------

$msg = monta_mensagem_lembrete($linha_a);

verifica("a mensagem leva o link do sistema",
    strpos($msg, URL_ABSOLUTA) !== false, $msg);

verifica("a mensagem leva o prazo da chamada",
    strpos($msg, $linha_a['cha_dt_max']) !== false, $msg);

verifica("a mensagem diz que o pedido não foi enviado",
    stripos($msg, 'não foi enviado') !== false, $msg);

// O aviso de cancelamento cobre o buraco que o cron não alcança: quem cancela
// às 10h de um prazo que vence às 14h já passou da execução da madrugada.
$msg_cancel = monta_mensagem_cancelamento('Fulano', 'Frescos', '25/08/2026', '22/08/2026 19:47', 123);

verifica("o aviso de cancelamento diz que o pedido não será considerado",
    stripos($msg_cancel, 'não será considerado') !== false, $msg_cancel);

verifica("o aviso de cancelamento leva o prazo e o link do pedido",
    strpos($msg_cancel, '22/08/2026 19:47') !== false
        && strpos($msg_cancel, URL_ABSOLUTA . "/pedido.php") !== false,
    $msg_cancel);

// Marcar é o que torna seguro rodar duas vezes na mesma madrugada: a segunda
// passada precisa encontrar a lista vazia.
$atualizacao_antes = valor_escalar("SELECT ped_dt_atualizacao FROM pedidos WHERE ped_id = " . $ped_ids['A_deve_lembrar']);

foreach (pedidos_a_lembrar(30) as $linha) {
    marca_lembrete_enviado($linha['ped_id']);
}

// ped_dt_atualizacao tem ON UPDATE CURRENT_TIMESTAMP: sem cuidado, registrar o
// lembrete faria a tela do cestante dizer que o pedido mudou hoje de madrugada
$atualizacao_depois = valor_escalar("SELECT ped_dt_atualizacao FROM pedidos WHERE ped_id = " . $ped_ids['A_deve_lembrar']);
verifica("marcar o lembrete não mexe na última atualização do pedido",
    $atualizacao_antes === $atualizacao_depois,
    "antes=$atualizacao_antes depois=$atualizacao_depois");
$segunda_passada = pedidos_a_lembrar(30);
verifica("depois de marcados, a segunda passada não seleciona ninguém",
    count($segunda_passada) === 0,
    count($segunda_passada) . " pedido(s) seriam lembrados de novo");

// ---------------------------------------------------------------------------
echo "\nenvio do pedido\n";
// ---------------------------------------------------------------------------

// Com o botão "somente salvar" removido, todo salvamento passa pelo envio. O
// e-mail de confirmação só pode sair na transição não-enviado -> enviado, senão
// quem edita um pedido enviado recebe uma confirmação a cada gravação.
$cobaia = $ped_ids['B_sem_itens'];

verifica("enviar um pedido ainda não enviado sinaliza a transição",
    marca_pedido_enviado($cobaia) === true);

verifica("o pedido passa a constar como enviado",
    valor_escalar("SELECT ped_fechado FROM pedidos WHERE ped_id = $cobaia") == 1);

verifica("a data do primeiro envio fica registrada",
    valor_escalar("SELECT ped_dt_envio FROM pedidos WHERE ped_id = $cobaia") !== null);

// envelhece a marca para que "preservar" signifique alguma coisa
executa_sql("UPDATE pedidos SET ped_dt_envio = NOW() - INTERVAL 2 DAY WHERE ped_id = $cobaia");
$envio_original = valor_escalar("SELECT ped_dt_envio FROM pedidos WHERE ped_id = $cobaia");

verifica("gravar de novo um pedido já enviado não sinaliza transição",
    marca_pedido_enviado($cobaia) === false);

verifica("a data do primeiro envio não é sobrescrita por gravações seguintes",
    valor_escalar("SELECT ped_dt_envio FROM pedidos WHERE ped_id = $cobaia") === $envio_original,
    "original=$envio_original agora=" . valor_escalar("SELECT ped_dt_envio FROM pedidos WHERE ped_id = $cobaia"));

// ---------------------------------------------------------------------------
echo "\nhistórico do cestante (últimos 4 pedidos do mesmo tipo)\n";
// ---------------------------------------------------------------------------

// Cada pedido que DEVE ser ignorado carrega uma quantidade própria e absurda:
// se alguma regra de exclusão quebrar, o número dela aparece no resultado e
// aponta qual regra falhou.
$PROD_X = 1; $PROD_Y = 53; $PROD_Z = 340;
$PRODT_A = 1; $PRODT_B = 2;

$usr_h = insere("INSERT INTO usuarios (usr_nome_completo, usr_nome_curto, usr_email, usr_senha, usr_archive, usr_nuc)
    VALUES ('Teste Historico', 'histor', 'testhistorico@dev.local', 'x', '0', $NUC)");

// cria chamada + pedido; devolve o cha_id
function cria_chamada_com_pedido($prodt, $dias_atras, $usr, $prod, $qtde, $fechado)
{
    global $NUC;
    $sinal = $dias_atras >= 0 ? "- INTERVAL $dias_atras DAY" : "+ INTERVAL " . abs($dias_atras) . " DAY";
    $cha = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max)
        VALUES ($prodt, NOW() $sinal, NOW() $sinal - INTERVAL 10 DAY, NOW() $sinal - INTERVAL 2 DAY)");
    insere("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) VALUES ($cha, $prod, 2)");
    $ped = insere("INSERT INTO pedidos (ped_usr, ped_usr_associado, ped_nuc, ped_cha, ped_fechado)
        VALUES ($usr, 1, $NUC, $cha, '$fechado')");
    insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade) VALUES ($ped, $prod, $qtde)");
    return $cha;
}

// os quatro que contam (do mais recente para o mais antigo)
$cha_h1 = cria_chamada_com_pedido($PRODT_A, 10, $usr_h, $PROD_X, 5,  1); // mais recente -> "ultimo"
$cha_h2 = cria_chamada_com_pedido($PRODT_A, 20, $usr_h, $PROD_X, 9,  1); // o maior     -> "max"
$cha_h3 = cria_chamada_com_pedido($PRODT_A, 30, $usr_h, $PROD_X, 2,  1);
$cha_h4 = cria_chamada_com_pedido($PRODT_A, 40, $usr_h, $PROD_X, 1,  1);

// os que NÃO podem entrar
$cha_h5 = cria_chamada_com_pedido($PRODT_A, 50, $usr_h, $PROD_X, 100, 1); // 5º: fora da janela de 4
cria_chamada_com_pedido($PRODT_A, 15, $usr_h, $PROD_X, 999, 0); // rascunho, não enviado
cria_chamada_com_pedido($PRODT_B,  5, $usr_h, $PROD_X, 777, 1); // outro tipo de chamada
cria_chamada_com_pedido($PRODT_A, -10, $usr_h, $PROD_X, 888, 1); // entrega depois da atual

// PROD_Y foi ofertado numa das 4 que contam -> não é novidade
insere("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) VALUES ($cha_h1, $PROD_Y, 2)");
// ...mas só foi PEDIDO na 2ª chamada, não na mais recente: assim 'ultimo' vem
// nulo e o caminho "máx sem últ" da tela fica coberto
$ped_h2 = valor_escalar("SELECT ped_id FROM pedidos WHERE ped_cha = $cha_h2 AND ped_usr = $usr_h");
insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade) VALUES ($ped_h2, $PROD_Y, 3)");
// PROD_Z foi ofertado SÓ na 5ª chamada, fora da janela -> tem que contar como
// novidade. Sem esta linha o teste passaria à toa, com um produto que nunca
// esteve em chamada nenhuma.
insere("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) VALUES ($cha_h5, $PROD_Z, 2)");

$hist = historico_do_cestante($usr_h, $PRODT_A, date('Y-m-d H:i:s', strtotime('+5 days')));

verifica("considera exatamente os 4 pedidos anteriores",
    $hist['pedidos'] === 4, "considerou " . var_export($hist['pedidos'], true));

verifica("a quantidade máxima vem do maior dos 4",
    isset($hist['quantidades'][$PROD_X]) && (float)$hist['quantidades'][$PROD_X]['max'] == 9.0,
    "max = " . (isset($hist['quantidades'][$PROD_X]) ? $hist['quantidades'][$PROD_X]['max'] : '(ausente)'));

verifica("a última quantidade vem do pedido mais recente",
    isset($hist['quantidades'][$PROD_X]) && (float)$hist['quantidades'][$PROD_X]['ultimo'] == 5.0,
    "ultimo = " . (isset($hist['quantidades'][$PROD_X]) ? $hist['quantidades'][$PROD_X]['ultimo'] : '(ausente)'));

verifica("produto ofertado nas 4 anteriores não é novidade",
    isset($hist['ofertados'][$PROD_Y]));

// PROD_Y só foi pedido numa chamada mais antiga, não na mais recente: tem máximo
// mas não tem "último". É o caso que faz a tela mostrar "máx N" sem "· últ".
verifica("produto ausente do pedido mais recente tem máximo",
    isset($hist['quantidades'][$PROD_Y]) && (float)$hist['quantidades'][$PROD_Y]['max'] == 3.0,
    "max = " . (isset($hist['quantidades'][$PROD_Y]) ? var_export($hist['quantidades'][$PROD_Y]['max'], true) : '(ausente)'));

verifica("produto ausente do pedido mais recente vem com último nulo",
    isset($hist['quantidades'][$PROD_Y]) && $hist['quantidades'][$PROD_Y]['ultimo'] === null,
    "ultimo = " . (isset($hist['quantidades'][$PROD_Y]) ? var_export($hist['quantidades'][$PROD_Y]['ultimo'], true) : '(ausente)'));

verifica("produto ofertado só fora da janela conta como novidade",
    !isset($hist['ofertados'][$PROD_Z]));

// O texto do histórico aparece em até 151 linhas: "4,00" polui, "4" não.
verifica("quantidade do histórico sai sem decimal inútil",
    formata_qtde_curta("4.00") === "4" && formata_qtde_curta("10.00") === "10",
    "4.00 -> " . formata_qtde_curta("4.00") . " | 10.00 -> " . formata_qtde_curta("10.00"));

verifica("quantidade fracionada preserva a casa que importa",
    formata_qtde_curta("0.50") === "0,5",
    "0.50 -> " . formata_qtde_curta("0.50"));

// Histórico velho não serve: o catálogo vira e o MESMO produto ganha prod_id novo
// (ex.: "Aipo" já foi 3, 360, 645, 1303, 1449, 1542, 1626, 3017 ao longo dos anos).
// Comparar contra um pedido de anos atrás marcaria quase tudo como novidade e
// sugeriria quantidades de outra época.
$usr_antigo = insere("INSERT INTO usuarios (usr_nome_completo, usr_nome_curto, usr_email, usr_senha, usr_archive, usr_nuc)
    VALUES ('Teste Historico Antigo', 'antigo', 'testhistoricoantigo@dev.local', 'x', '0', $NUC)");
cria_chamada_com_pedido($PRODT_A, 1200, $usr_antigo, $PROD_X, 5, 1); // ~3 anos atrás
$hist_antigo = historico_do_cestante($usr_antigo, $PRODT_A, date('Y-m-d H:i:s', strtotime('+5 days')));

verifica("pedido antigo demais não vira histórico",
    $hist_antigo['pedidos'] === 0,
    "considerou " . var_export($hist_antigo['pedidos'], true) . " pedido(s)");

// sem histórico nenhum: a tela não pode mostrar botão nem marcar tudo como novidade
$usr_novo = insere("INSERT INTO usuarios (usr_nome_completo, usr_nome_curto, usr_email, usr_senha, usr_archive, usr_nuc)
    VALUES ('Teste Sem Historico', 'semhist', 'testsemhistorico@dev.local', 'x', '0', $NUC)");
$hist_vazio = historico_do_cestante($usr_novo, $PRODT_A, date('Y-m-d H:i:s', strtotime('+5 days')));

verifica("cestante sem histórico devolve zero pedidos",
    $hist_vazio['pedidos'] === 0 && count($hist_vazio['quantidades']) === 0,
    var_export($hist_vazio, true));

mysqli_rollback($conn_link);

// ---------------------------------------------------------------------------
echo "\n";
if ($falhas === 0) {
    echo "TODOS OS $total TESTES PASSARAM\n";
    exit(0);
}
echo "$falhas de $total TESTES FALHARAM\n";
exit(1);
