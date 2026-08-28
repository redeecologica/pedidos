<?php
// Testes do prazo contábil padrão da chamada.
// Não roda direto: use scripts/test-chamada.sh (canaliza este arquivo para o
// PHP do container, que é a mesma versão 8.4 da produção).
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

require "/var/www/html/common.inc.php";
require "/var/www/html/chamada.inc.php";

mysqli_begin_transaction($conn_link);

// Os tipos vêm por NOME, não por id fixo: é assim que a função os reconhece, e
// um teste que fixasse o id passaria mesmo se a função parasse de casar o nome.
$PRODT_FRESCOS   = valor_escalar("SELECT prodt_id FROM produtotipos WHERE prodt_nome = 'Frescos'");
$PRODT_SECOS     = valor_escalar("SELECT prodt_id FROM produtotipos WHERE prodt_nome = 'Secos'");
$PRODT_BIMESTRAL = valor_escalar("SELECT prodt_id FROM produtotipos WHERE prodt_nome = 'Secos Bimestral'");
$PRODT_ASSOC     = valor_escalar("SELECT prodt_id FROM produtotipos WHERE prodt_nome = 'Associação'");

verifica("os quatro tipos com regra própria existem na base",
    $PRODT_FRESCOS && $PRODT_SECOS && $PRODT_BIMESTRAL && $PRODT_ASSOC,
    "frescos=$PRODT_FRESCOS secos=$PRODT_SECOS bimestral=$PRODT_BIMESTRAL assoc=$PRODT_ASSOC");

// ---------------------------------------------------------------------------
echo "\nprazo padrão por tipo\n";
// ---------------------------------------------------------------------------

// Data fixa e distante de qualquer chamada real: se ela casasse por acaso com
// uma chamada de Secos da base, o teste da Associação mediria dado de produção
// em vez do fixture.
$ENTREGA = '2027-03-10 23:59:59';

verifica("Frescos fecha 4 dias depois da entrega",
    prazo_contabil_padrao($PRODT_FRESCOS, $ENTREGA) === '2027-03-14 23:59:59',
    var_export(prazo_contabil_padrao($PRODT_FRESCOS, $ENTREGA), true));

verifica("Secos fecha 6 dias depois da entrega",
    prazo_contabil_padrao($PRODT_SECOS, $ENTREGA) === '2027-03-16 23:59:59',
    var_export(prazo_contabil_padrao($PRODT_SECOS, $ENTREGA), true));

verifica("Secos Bimestral fecha 6 dias depois da entrega",
    prazo_contabil_padrao($PRODT_BIMESTRAL, $ENTREGA) === '2027-03-16 23:59:59',
    var_export(prazo_contabil_padrao($PRODT_BIMESTRAL, $ENTREGA), true));

// Tipo sem regra própria. Campanha, Frescos Pontual e Secos Pontual caem aqui, e
// é o que impede o buraco de voltar por uma porta que ninguém listou.
$PRODT_CAMPANHA = valor_escalar("SELECT prodt_id FROM produtotipos WHERE prodt_nome = 'Campanha'");

verifica("tipo sem regra própria cai no padrão de 6 dias",
    prazo_contabil_padrao($PRODT_CAMPANHA, $ENTREGA) === '2027-03-16 23:59:59',
    var_export(prazo_contabil_padrao($PRODT_CAMPANHA, $ENTREGA), true));

// Tipo que não existe: não pode explodir nem inventar data.
verifica("tipo inexistente cai no padrão de 6 dias",
    prazo_contabil_padrao(999999, $ENTREGA) === '2027-03-16 23:59:59',
    var_export(prazo_contabil_padrao(999999, $ENTREGA), true));

verifica("entrega vazia devolve null em vez de inventar data",
    prazo_contabil_padrao($PRODT_FRESCOS, '') === null,
    var_export(prazo_contabil_padrao($PRODT_FRESCOS, ''), true));

// ---------------------------------------------------------------------------
echo "\nAssociação: copia o prazo da chamada de Secos\n";
// ---------------------------------------------------------------------------

// A Associação entrega um dia ANTES do Secos — conferido na base: 15 de 17
// chamadas desde 2025 seguem isso, e a de Secos sempre tem cha_id menor, ou
// seja, já existe quando a Associação é criada.
$ENTREGA_ASSOC = '2027-06-09 23:59:59';
$ENTREGA_SECOS = '2027-06-10 23:59:59';

$cha_secos = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_prazo_contabil)
    VALUES ($PRODT_SECOS, '$ENTREGA_SECOS', '2027-06-19 14:00:00')");

verifica("Associação copia o prazo do Secos do dia seguinte",
    prazo_contabil_padrao($PRODT_ASSOC, $ENTREGA_ASSOC) === '2027-06-19 14:00:00',
    var_export(prazo_contabil_padrao($PRODT_ASSOC, $ENTREGA_ASSOC), true));

// O caso que separa "achei a chamada" de "achei prazo utilizável". Sem esta
// distinção, a função que existe para acabar com o nulo gravaria nulo.
executa_sql("UPDATE chamadas SET cha_dt_prazo_contabil = NULL WHERE cha_id = $cha_secos");

verifica("Secos pareado com prazo NULO cai no padrão, não devolve null",
    prazo_contabil_padrao($PRODT_ASSOC, $ENTREGA_ASSOC) === '2027-06-15 23:59:59',
    var_export(prazo_contabil_padrao($PRODT_ASSOC, $ENTREGA_ASSOC), true));

// Sem chamada de Secos nenhuma por perto. Aconteceu de verdade em 2 das 17
// Associações desde 2025 (12%), então não é hipótese.
$ENTREGA_ORFA = '2027-09-14 23:59:59';

verifica("Associação sem Secos pareado cai no padrão de 6 dias",
    prazo_contabil_padrao($PRODT_ASSOC, $ENTREGA_ORFA) === '2027-09-20 23:59:59',
    var_export(prazo_contabil_padrao($PRODT_ASSOC, $ENTREGA_ORFA), true));

// O prazo do Secos é dado arbitrário da base, e a base TEM prazo inválido: cha
// 1177 fechou um ano antes da entrega (typo de ano) e cha 963, 26 dias antes.
// Copiar um desses daria à Associação um prazo anterior à própria entrega, ou
// seja, uma chamada nascida trancada para registro de entrega.
$ENTREGA_ASSOC_RUIM = '2027-07-14 23:59:59';
insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_prazo_contabil)
    VALUES ($PRODT_SECOS, '2027-07-15 23:59:59', '2026-07-20 10:00:00')");

verifica("prazo de Secos anterior à entrega da Associação não é copiado",
    prazo_contabil_padrao($PRODT_ASSOC, $ENTREGA_ASSOC_RUIM) === '2027-07-20 23:59:59',
    var_export(prazo_contabil_padrao($PRODT_ASSOC, $ENTREGA_ASSOC_RUIM), true));

// Chamada de Secos longe demais não é par: a regra é o dia seguinte, não
// "o próximo Secos que aparecer".
$ENTREGA_LONGE = '2027-12-01 23:59:59';
insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_prazo_contabil)
    VALUES ($PRODT_SECOS, '2027-12-20 23:59:59', '2027-12-28 10:00:00')");

verifica("Secos a 19 dias de distância não é considerado par",
    prazo_contabil_padrao($PRODT_ASSOC, $ENTREGA_LONGE) === '2027-12-07 23:59:59',
    var_export(prazo_contabil_padrao($PRODT_ASSOC, $ENTREGA_LONGE), true));

// ---------------------------------------------------------------------------
echo "\nvalidação do prazo contra a data de entrega\n";
// ---------------------------------------------------------------------------

verifica("prazo em dia posterior à entrega é aceito",
    prazo_contabil_valido('2027-03-14 12:00:00', $ENTREGA) === true);

verifica("prazo no mesmo dia da entrega é recusado",
    prazo_contabil_valido('2027-03-10 22:30:00', $ENTREGA) === false,
    "a entrega é gravada às 23:59:59, então mesmo dia é sempre 'antes'");

verifica("prazo anterior à entrega é recusado",
    prazo_contabil_valido('2027-02-05 23:00:00', $ENTREGA) === false);

verifica("prazo vazio é recusado",
    prazo_contabil_valido('', $ENTREGA) === false);

// ---------------------------------------------------------------------------
echo "\npadrão nunca sobrescreve prazo já existente\n";
// ---------------------------------------------------------------------------

// A regra: prazo contábil já definido é decisão de quem definiu, e o padrão da
// criação não encosta nele. Quem garante isso é o COALESCE do chamada.php — a
// função aqui do lado não sabe se existe prazo, ela só calcula o sugerido.
//
// Estes dois testes são de naturezas diferentes, e vale dizer qual é qual em vez
// de fingir que são o mesmo:

// 1. COMPORTAMENTAL: a semântica do COALESCE, exercitada de verdade no banco.
$cha_com_prazo = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_prazo_contabil)
    VALUES ($PRODT_FRESCOS, '2027-10-05 23:59:59', '2027-10-30 09:00:00')");

executa_sql("UPDATE chamadas SET cha_dt_prazo_contabil = COALESCE(cha_dt_prazo_contabil, '2027-10-09 23:59:59')
    WHERE cha_id = $cha_com_prazo");

verifica("gravar de novo NÃO troca o prazo que já existia",
    valor_escalar("SELECT cha_dt_prazo_contabil FROM chamadas WHERE cha_id = $cha_com_prazo") === '2027-10-30 09:00:00',
    var_export(valor_escalar("SELECT cha_dt_prazo_contabil FROM chamadas WHERE cha_id = $cha_com_prazo"), true));

$cha_sem_prazo = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega)
    VALUES ($PRODT_FRESCOS, '2027-10-05 23:59:59')");

executa_sql("UPDATE chamadas SET cha_dt_prazo_contabil = COALESCE(cha_dt_prazo_contabil, '2027-10-09 23:59:59')
    WHERE cha_id = $cha_sem_prazo");

verifica("a mesma gravação PREENCHE quando o prazo estava nulo",
    valor_escalar("SELECT cha_dt_prazo_contabil FROM chamadas WHERE cha_id = $cha_sem_prazo") === '2027-10-09 23:59:59',
    var_export(valor_escalar("SELECT cha_dt_prazo_contabil FROM chamadas WHERE cha_id = $cha_sem_prazo"), true));

// 2. ESTRUTURAL: o teste acima prova o COALESCE, não prova que chamada.php usa
// COALESCE. Trocar aquela linha por um SET direto deixaria os dois testes verdes
// e passaria a sobrescrever prazo de finanças a cada edição de chamada. Como a
// gravação mora inline numa página, e página não se chama de teste, o que dá
// para travar é a forma do SQL. É guarda de forma, não de comportamento — o
// comportamento está verificado por HTTP e registrado no PR.
$fonte_chamada = file_get_contents('/var/www/html/chamada.php');

verifica("chamada.php grava o prazo por COALESCE, e não por atribuição direta",
    strpos($fonte_chamada, 'cha_dt_prazo_contabil = COALESCE(cha_dt_prazo_contabil,') !== false,
    "a linha que grava o prazo em chamada.php mudou de forma");

mysqli_rollback($conn_link);

// ---------------------------------------------------------------------------
echo "\n";
if ($falhas === 0) {
    echo "TODOS OS $total TESTES PASSARAM\n";
    exit(0);
}
echo "$falhas de $total TESTES FALHARAM\n";
exit(1);
