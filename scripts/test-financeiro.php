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

mysqli_rollback($conn_link);

echo "\n";
if ($falhas === 0) { echo "TODOS OS $total TESTES PASSARAM\n"; exit(0); }
echo "$falhas de $total TESTES FALHARAM\n";
exit(1);
