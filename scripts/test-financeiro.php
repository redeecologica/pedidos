<?php
// Testes do módulo financeiro.
// Não roda direto: use scripts/test-financeiro.sh.
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
require "/var/www/html/financeiro.inc.php";

echo "\nrazao\n";

mysqli_begin_transaction($conn_link);

// A transação é nossa: avisa o módulo, senão lanca_transacao abre um BEGIN
// aninhado — que no MySQL faz COMMIT implícito desta e o rollback do fim não
// desfaz nada.
$financeiro_em_transacao = true;

$con_a = insere("INSERT INTO contas (con_tipo, con_nome) VALUES ('rede','Teste A')");
$con_b = insere("INSERT INTO contas (con_tipo, con_nome) VALUES ('rede','Teste B')");

$tra = lanca_transacao('2026-08-01 10:00:00', 'ajuste', $con_a, $con_b, 100.00,
                       'teste de lancamento', array('obs' => 'unitario'));

verifica("lanca_transacao devolve o id da transacao",
    is_numeric($tra) && $tra > 0, var_export($tra, true));

verifica("a conta debitada fica negativa",
    saldo_da_conta($con_a) == -100.00, "saldo = " . saldo_da_conta($con_a));

verifica("a conta creditada fica positiva",
    saldo_da_conta($con_b) == 100.00, "saldo = " . saldo_da_conta($con_b));

verifica("a transacao gerou exatamente duas pernas",
    (int)valor_escalar("SELECT COUNT(*) FROM lancamentos WHERE lan_tra = $tra") === 2);

verifica("valor zero ou negativo é recusado",
    lanca_transacao('2026-08-01 10:00:00','ajuste',$con_a,$con_b,0,'x') === null
    && lanca_transacao('2026-08-01 10:00:00','ajuste',$con_a,$con_b,-5,'x') === null);

verifica("transferir de uma conta para ela mesma é recusado",
    lanca_transacao('2026-08-01 10:00:00','ajuste',$con_a,$con_a,10,'x') === null);

// O invariante roda sobre a base inteira, não só sobre o fixture: se qualquer
// transação já gravada estiver torta, este teste acusa.
$tortas = transacoes_desbalanceadas();
verifica("nenhuma transacao da base esta desbalanceada",
    count($tortas) === 0, "tra_id fora: " . implode(",", array_slice($tortas, 0, 5)));

// As duas quebras abaixo vão à mão: lanca_transacao nunca produz transação sem
// par de pernas, e é justamente esse estado que o invariante tem de pegar. Vêm
// DEPOIS da varredura acima, que exige a base coerente.
$tra_sem_perna = insere("INSERT INTO transacoes (tra_dt, tra_tipo, tra_historico, tra_usr_registro)
    VALUES ('2026-08-01 10:00:00','ajuste','teste sem perna',0)");

verifica("transacao sem perna nenhuma é acusada",
    in_array($tra_sem_perna, transacoes_desbalanceadas()),
    "tra_id $tra_sem_perna nao apareceu na varredura");

$tra_uma_perna = insere("INSERT INTO transacoes (tra_dt, tra_tipo, tra_historico, tra_usr_registro)
    VALUES ('2026-08-01 10:00:00','ajuste','teste uma perna',0)");
insere("INSERT INTO lancamentos (lan_tra, lan_con, lan_valor) VALUES ($tra_uma_perna, $con_a, 10.00)");

verifica("transacao com uma perna só é acusada",
    in_array($tra_uma_perna, transacoes_desbalanceadas()),
    "tra_id $tra_uma_perna nao apareceu na varredura");

// Três pernas que somam zero: o total fecha e mesmo assim não é partida dobrada.
// É o único caso em que a soma está certa e só a contagem denuncia.
$tra_tres_pernas = insere("INSERT INTO transacoes (tra_dt, tra_tipo, tra_historico, tra_usr_registro)
    VALUES ('2026-08-01 10:00:00','ajuste','teste tres pernas',0)");
insere("INSERT INTO lancamentos (lan_tra, lan_con, lan_valor) VALUES ($tra_tres_pernas, $con_a, -10.00)");
insere("INSERT INTO lancamentos (lan_tra, lan_con, lan_valor) VALUES ($tra_tres_pernas, $con_b,   4.00)");
insere("INSERT INTO lancamentos (lan_tra, lan_con, lan_valor) VALUES ($tra_tres_pernas, $con_b,   6.00)");

verifica("transacao com três pernas, mesmo somando zero, é acusada",
    in_array($tra_tres_pernas, transacoes_desbalanceadas()),
    "tra_id $tra_tres_pernas nao apareceu na varredura");

// Desfaz as quebras deliberadas para o resto do arquivo — e as tarefas que vierem
// acrescentar teste aqui — voltar a ver uma base coerente.
executa_sql("DELETE FROM lancamentos WHERE lan_tra IN ($tra_uma_perna, $tra_tres_pernas)");
executa_sql("DELETE FROM transacoes WHERE tra_id IN ($tra_sem_perna, $tra_uma_perna, $tra_tres_pernas)");

// Confere só as três quebras deste fixture, não a base inteira: quebra alheia já
// é assunto da varredura lá de cima, e contar duas vezes daria duas falhas para
// uma causa só.
$ainda_tortas = array_intersect(transacoes_desbalanceadas(),
    array($tra_sem_perna, $tra_uma_perna, $tra_tres_pernas));

verifica("desfeitas as quebras, nenhuma delas sobra na varredura",
    count($ainda_tortas) === 0, "sobrou: " . implode(",", $ainda_tortas));

echo "\ncontas\n";

$usr_t = insere("INSERT INTO usuarios (usr_nome_completo, usr_nome_curto, usr_email, usr_senha, usr_archive, usr_nuc)
    VALUES ('Teste Conta','tconta','teste-conta@dev.local','x','0',1)");

verifica("cestante sem movimento nao tem conta criada a toa",
    conta_do_cestante($usr_t) === null);

$con_t = conta_do_cestante($usr_t, true);
verifica("conta do cestante e criada quando pedida",
    is_numeric($con_t) && $con_t > 0, var_export($con_t, true));

// O runner conta linhas antes e depois, mas este bloco roda dentro da transação:
// nada do que ele grava chega lá. Conferir a linha que nasceu é o único jeito de
// pegar uma conta criada com o tipo certo e o vínculo errado — ou o contrário.
verifica("a conta nasce com tipo cestante e vinculada ao usuario",
    valor_escalar("SELECT COUNT(*) FROM contas WHERE con_id = " . (int)$con_t
        . " AND con_tipo = 'cestante' AND con_usr = " . (int)$usr_t) == 1);

verifica("chamar de novo devolve a mesma conta, nao cria outra",
    conta_do_cestante($usr_t, true) == $con_t);

verifica("conta nova nasce zerada",
    saldo_da_conta($con_t) == 0.0);

// Duas chamadas, os dois valores guardados: a segunda tem de ACHAR a primeira.
// Se o acento de 'Rede Ecológica' se perdesse na gravação, a busca por nome
// erraria e viria um id novo — é este teste que prova o ida-e-volta em utf8.
$con_rede  = conta_da_rede();
$con_rede2 = conta_da_rede();

verifica("a conta da Rede existe ou e criada",
    is_numeric($con_rede) && $con_rede > 0, var_export($con_rede, true));

verifica("a segunda chamada acha a mesma conta da Rede, nao cria outra",
    $con_rede2 == $con_rede, "primeira = " . var_export($con_rede, true)
        . " · segunda = " . var_export($con_rede2, true));

verifica("a conta da Rede nasce com tipo, nome e chave estavel",
    valor_escalar("SELECT COUNT(*) FROM contas WHERE con_id = " . (int)$con_rede
        . " AND con_tipo = 'rede' AND con_nome = 'Rede Ecológica'"
        . " AND con_chave = 'rede_principal'") == 1);

// Identidade não é rótulo. Renomear a conta principal é coisa que a administração
// pode fazer pela tela; com a busca por con_nome, como era antes, a chamada
// seguinte não acharia mais a conta e cairia no INSERT — nasceria uma SEGUNDA
// conta principal e os débitos de entrega passariam a ter duas contrapartes.
//
// O que a asserção observa é que a busca continua achando a MESMA conta. Sob a
// volta para busca por nome ela falha de todo jeito: com a UNIQUE KEY no lugar,
// o INSERT da segunda conta é recusado e conta_da_rede() devolve null; sem a
// chave única, devolveria um id diferente. Nos dois casos, diferente de
// $con_rede.
executa_sql("UPDATE contas SET con_nome = 'Rede Ecologica (renomeada na mao)'
             WHERE con_id = " . (int)$con_rede);

verifica("renomear a conta da Rede nao faz a busca perder a conta",
    conta_da_rede() == $con_rede,
    "antes = " . var_export($con_rede, true)
        . " · depois = " . var_export(conta_da_rede(), true));

// A UNIQUE KEY conta_chave é o que impede duas contas principais de coexistir.
// Sem ela — um ALTER que tivesse acrescentado só a coluna — todo o resto da
// suíte seguiria verde: este é o único teste que acusa a chave faltando.
verifica("chave repetida e recusada pelo banco",
    cria_conta('nucleo', array('con_nuc' => 2, 'con_chave' => 'rede_principal')) === null);

// Usuários novos, ainda sem conta: as recusas abaixo têm de vir da validação, e
// não da UNIQUE KEY conta_usuario. Reusando $usr_t, que já ganhou conta lá em
// cima, o INSERT falharia por chave duplicada e o teste passaria verde sem
// provar nada — foi assim que a primeira versão destes dois testes passou
// mesmo com a validação removida.
$usr_t2 = insere("INSERT INTO usuarios (usr_nome_completo, usr_nome_curto, usr_email, usr_senha, usr_archive, usr_nuc)
    VALUES ('Teste Conta 2','tconta2','teste-conta-2@dev.local','x','0',1)");
$usr_t3 = insere("INSERT INTO usuarios (usr_nome_completo, usr_nome_curto, usr_email, usr_senha, usr_archive, usr_nuc)
    VALUES ('Teste Conta 3','tconta3','teste-conta-3@dev.local','x','0',1)");

// Coerência entre con_tipo e o campo de vínculo: o MySQL 5.6 aceita CHECK e o
// ignora em silêncio, então quem barra é cria_conta. A contagem em volta das
// recusas garante que elas acontecem ANTES do INSERT — sem ela, um refactor que
// movesse a validação para depois passaria despercebido.
$contas_antes = (int)valor_escalar("SELECT COUNT(*) FROM contas");

verifica("tipo de conta fora da lista e recusado",
    cria_conta('carteira', array('con_nome' => 'Tipo Inventado')) === null);

verifica("conta da rede sem nome e recusada",
    cria_conta('rede') === null
    && cria_conta('rede', array('con_nome' => '   ')) === null);

verifica("cestante sem usuario e recusado",
    cria_conta('cestante') === null
    && cria_conta('cestante', array('con_nome' => 'sem usuario')) === null);

// O crivo vale para TODO campo informado, não só para o exigido. Sem estes dois,
// a conta sairia coerente na coluna que o tipo pede e torta em outra — e, como
// con_nuc e con_forn têm UNIQUE KEY, o '' virado 0 só estouraria mais tarde, num
// INSERT sem relação nenhuma com a causa.
verifica("vinculo de outro tipo na mesma conta e recusado",
    cria_conta('cestante', array('con_usr' => $usr_t2, 'con_forn' => 3)) === null
    && cria_conta('rede', array('con_nome' => 'Rede com nucleo', 'con_nuc' => 1)) === null);

// Com os vínculos mutuamente exclusivos, o único campo que pode acompanhar outro
// é con_nome — e ele passa pelo mesmo crivo do exigido, não por um mais frouxo.
verifica("rotulo vazio e recusado mesmo num tipo que nao o exige",
    cria_conta('cestante', array('con_usr' => $usr_t3, 'con_nome' => '   ')) === null);

verifica("nenhuma conta foi gravada pelas recusas",
    (int)valor_escalar("SELECT COUNT(*) FROM contas") === $contas_antes,
    "antes = $contas_antes · depois = " . valor_escalar("SELECT COUNT(*) FROM contas"));

// Fecham os quatro tipos: cestante e rede saíram exercitados lá em cima, núcleo e
// produtor só existiam no mapa. Confirmam que a montagem genérica das colunas
// serve a vínculos além do primeiro, e que o rótulo acompanha um tipo que não o
// exige. Vêm DEPOIS da contagem acima, que exige que nada tenha sido gravado.
$con_nuc_t = cria_conta('nucleo', array('con_nuc' => 1, 'con_nome' => 'Teste Nucleo'));
verifica("conta de nucleo nasce com o vinculo e o rotulo",
    valor_escalar("SELECT COUNT(*) FROM contas WHERE con_id = " . (int)$con_nuc_t
        . " AND con_tipo = 'nucleo' AND con_nuc = 1 AND con_nome = 'Teste Nucleo'") == 1,
    var_export($con_nuc_t, true));

$con_forn_t = cria_conta('produtor', array('con_forn' => 1));
verifica("conta de produtor nasce com o vinculo",
    valor_escalar("SELECT COUNT(*) FROM contas WHERE con_id = " . (int)$con_forn_t
        . " AND con_tipo = 'produtor' AND con_forn = 1") == 1,
    var_export($con_forn_t, true));

mysqli_rollback($conn_link);

// FIM DA REDE DE PROTEÇÃO. Daqui para baixo não há transação aberta, e a flag
// tem de voltar a false: se ficasse ligada, lanca_transacao acreditaria que
// alguém cuida do commit, não abriria a sua, e gravaria em autocommit — sem
// atomicidade e sem rollback, sujando a cópia de produção.
//
// Quem acrescentar teste DEPOIS desta linha grava DE VERDADE e é responsável por
// limpar o que criar. Teste novo que dependa de rollback vai ACIMA do
// mysqli_rollback, não aqui.
$financeiro_em_transacao = false;


echo "\ncaminho de producao\n";

// Nenhum teste acima exercita o ramo que roda em produção: com o fixture dentro
// de uma transação nossa, lanca_transacao sempre vê $nossa === false e nunca
// executa o próprio begin/commit. Aqui, sim — e por isso estes registros são
// gravados de verdade.
// A limpeza vai para o shutdown, e é registrada ANTES de existir o que limpar:
// tem de rodar mesmo se uma asserção falhar, se o próprio insere() abaixo abortar
// no meio ou se der erro fatal. É o mesmo motivo do `trap EXIT` nos scripts de
// shell. Por isso apaga por nome, e não por id — no pior caso o id nem chegou a
// existir.
register_shutdown_function(function () {
    executa_sql("DELETE l FROM lancamentos l JOIN transacoes t ON t.tra_id = l.lan_tra
                 WHERE t.tra_historico = 'teste caminho de producao'");
    executa_sql("DELETE FROM transacoes WHERE tra_historico = 'teste caminho de producao'");
    executa_sql("DELETE FROM contas WHERE con_nome IN ('Teste Producao A','Teste Producao B')");
});

$prod_a = insere("INSERT INTO contas (con_tipo, con_nome) VALUES ('rede','Teste Producao A')");
$prod_b = insere("INSERT INTO contas (con_tipo, con_nome) VALUES ('rede','Teste Producao B')");

$tra_prod = lanca_transacao('2026-08-01 10:00:00', 'ajuste', $prod_a, $prod_b, 42.50,
                            'teste caminho de producao');

// Se a função tivesse deixado a transação aberta em vez de comitar, este rollback
// apagaria as duas pernas. É o que separa "gravou" de "comitou".
mysqli_rollback($conn_link);

$pernas_prod = (int)valor_escalar("SELECT COUNT(*) FROM lancamentos WHERE lan_tra = " . (int)$tra_prod);
$soma_prod   = (float)valor_escalar("SELECT COALESCE(SUM(lan_valor),0) FROM lancamentos WHERE lan_tra = " . (int)$tra_prod);

verifica("o caminho de producao comita mesmo: as duas pernas sobrevivem a um rollback",
    is_numeric($tra_prod) && $tra_prod > 0 && $pernas_prod === 2 && $soma_prod == 0.0,
    "tra_id = " . var_export($tra_prod, true) . " · pernas = $pernas_prod · soma = $soma_prod");

verifica("o caminho de producao devolve a flag para false",
    $financeiro_em_transacao === false,
    "flag = " . var_export($financeiro_em_transacao, true));

echo "\n";
if ($falhas === 0) { echo "TODOS OS $total TESTES PASSARAM\n"; exit(0); }
echo "$falhas de $total TESTES FALHARAM\n";
exit(1);
