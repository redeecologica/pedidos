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

// A busca da conta do cestante é só por con_usr, e estes dois testes é que
// seguram isso. Um filtro por coluna editável — con_tipo ou con_archive — faria
// a busca errar, o INSERT seguinte bater na UNIQUE conta_usuario, e
// conta_do_cestante devolver null: o cestante ficaria SEM CONTA para sempre, com
// lanca_transacao recebendo null e deixando de gravar calado. Falha silenciosa e
// permanente, que é a pior combinação num razão.
//
// Cada teste devolve a coluna ao valor original: o que vem depois tem de ver a
// conta como ela nasceu.
executa_sql("UPDATE contas SET con_tipo = 'rede' WHERE con_id = " . (int)$con_t);
verifica("con_tipo adulterado nao faz a conta do cestante sumir",
    conta_do_cestante($usr_t) == $con_t,
    "esperado $con_t · veio " . var_export(conta_do_cestante($usr_t), true));
executa_sql("UPDATE contas SET con_tipo = 'cestante' WHERE con_id = " . (int)$con_t);

// O comentário da função sempre afirmou que conta arquivada tem de ser achada,
// mas nada testava: um `AND con_archive = 0` acrescentado por engano passaria
// verde, com o mesmo desfecho do caso acima.
executa_sql("UPDATE contas SET con_archive = 1 WHERE con_id = " . (int)$con_t);
verifica("conta arquivada continua sendo encontrada",
    conta_do_cestante($usr_t) == $con_t,
    "esperado $con_t · veio " . var_export(conta_do_cestante($usr_t), true));
executa_sql("UPDATE contas SET con_archive = 0 WHERE con_id = " . (int)$con_t);

// Chave reservada em tipo que não é o dono dela. É a coerência que faltava:
// con_chave entrou como campo livre ("qualquer tipo pode ter uma chave") e a
// busca de conta_da_rede() de propósito não filtra con_tipo. Juntas, as duas
// decisões deixavam 'rede_principal' caber numa conta de núcleo — e aí
// conta_da_rede() devolveria essa linha de núcleo como a conta principal da Rede.
//
// Vem ANTES de a conta da Rede existir, e isso é essencial: com 'rede_principal'
// já gravada, a UNIQUE KEY conta_chave recusaria o INSERT sozinha e a asserção
// passaria verde mesmo com a regra removida — exatamente o falso-verde que a
// UNIQUE conta_usuario já produziu uma vez nesta suíte. A contagem entra na
// mesma asserção para o teste não depender de nenhum outro.
$contas_antes_reserva = (int)valor_escalar("SELECT COUNT(*) FROM contas");

verifica("chave reservada e recusada em tipo que nao e o dono",
    cria_conta('nucleo',      array('con_nuc'  => 3, 'con_chave' => 'rede_principal')) === null
    && cria_conta('produtor', array('con_forn' => 3, 'con_chave' => 'rede_principal')) === null
    && (int)valor_escalar("SELECT COUNT(*) FROM contas") === $contas_antes_reserva);


// A guarda de chave reservada compara em PHP, byte a byte; a busca de
// conta_da_rede() compara em MySQL. Enquanto con_chave era utf8_general_ci — que
// dobra caixa E acento — as duas discordavam sobre o que é "a mesma chave", e o
// ataque passava pela fresta: nenhuma das variantes abaixo é 'rede_principal'
// para o PHP, mas todas eram para o SQL. A conta de núcleo era aceita e
// conta_da_rede() devolvia ELA. Com con_chave em utf8_bin, guarda, `=` e UNIQUE
// passam a concordar por construção.
//
// Testar só o literal exato — como a primeira versão deste bloco fazia — deixava
// a rede de regressão com o mesmo furo da regra. Por isso as variantes.
//
// Vêm ANTES de a conta da Rede existir: é a única janela em que o ataque
// funciona, porque depois a UNIQUE recusaria a colisão.
$variantes_chave = array('REDE_PRINCIPAL', 'rede_principál', 'ReDe_PrInCiPaL');
$contas_variantes = array();
$nuc_variante = 101;
foreach ($variantes_chave as $variante)
{
    $contas_variantes[$variante] = cria_conta('nucleo',
        array('con_nuc' => $nuc_variante++, 'con_chave' => $variante));
}

// Sob utf8_general_ci as três colidem entre si e com a chave real, então a UNIQUE
// recusa da segunda em diante e vêm nulls — este teste já acusa a colação errada.
verifica("variantes da chave reservada sao chaves distintas, nao a mesma",
    count(array_filter($contas_variantes)) === count($variantes_chave),
    "criadas: " . json_encode($contas_variantes));


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

// O teste que fecha o buraco: com as variantes gravadas, conta_da_rede() tem de
// devolver a conta CERTA e nunca uma delas. Sob utf8_general_ci a busca por
// 'rede_principal' casava com a primeira variante e era ela que voltava — uma
// conta de núcleo servindo de contraparte dos débitos de entrega.
verifica("nenhuma variante da chave e devolvida como a conta da Rede",
    $con_rede > 0 && !in_array($con_rede, $contas_variantes, true),
    "conta_da_rede() = " . var_export($con_rede, true)
        . " · variantes = " . json_encode($contas_variantes));

// BINARY força a comparação byte a byte independente da colação da coluna: se a
// linha devolvida não tiver exatamente a chave, este teste acusa mesmo que a
// colação volte a dobrar caixa e acento.
verifica("a conta devolvida tem a chave byte a byte exata",
    valor_escalar("SELECT COUNT(*) FROM contas WHERE con_id = " . (int)$con_rede
        . " AND con_chave = BINARY 'rede_principal'") == 1);


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

// A UNIQUE KEY conta_chave é o que impede duas contas com a mesma identidade.
// Sem ela — um ALTER que tivesse acrescentado só a coluna — todo o resto da
// suíte seguiria verde: este é o único teste que acusa a chave faltando no banco.
//
// A chave aqui é NÃO reservada de propósito. Com 'rede_principal', a regra de
// chave reservada recusaria antes de o SQL sair, e o teste passaria verde mesmo
// sem índice nenhum — deixaria de falar do banco e passaria a falar da validação,
// que já tem teste próprio logo abaixo.
$con_chave_a = cria_conta('nucleo', array('con_nuc' => 2, 'con_chave' => 'teste_chave_unica'));

verifica("chave repetida e recusada pelo banco",
    is_numeric($con_chave_a) && $con_chave_a > 0
    && cria_conta('produtor', array('con_forn' => 2, 'con_chave' => 'teste_chave_unica')) === null,
    "primeira conta = " . var_export($con_chave_a, true));

// Usuários novos, ainda sem conta: as recusas abaixo têm de vir da validação, e
// não da UNIQUE KEY conta_usuario. Reusando $usr_t, que já ganhou conta lá em
// cima, o INSERT falha por chave duplicada e a asserção passa sem provar nada —
// foi o que aconteceu com "rotulo vazio", que ficou verde com o crivo removido.
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

echo "\ndebito derivado\n";

// O débito é derivado da entrega, e o preço é o da ÉPOCA dela: produtos guarda
// uma linha por versão do produto (prod_id NÃO é único) e é a janela de validade
// que casa a versão certa com cha_dt_entrega.
//
// Os valores esperados não são literais aqui: o banco é cópia de produção e o
// preço do produto 1 muda na próxima carga. Vêm de uma consulta DIFERENTE da que
// a implementação faz — "a última versão que começou até a data" —, para o teste
// não repetir a regra que deveria estar conferindo. Se a implementação perder a
// janela de validade, ela soma TODAS as versões do produto e o valor não bate.
$preco_hoje  = (float)valor_escalar("SELECT prod_valor_venda FROM produtos WHERE prod_id = 1
    AND prod_ini_validade <= NOW() - INTERVAL 3 DAY ORDER BY prod_ini_validade DESC LIMIT 1");
$margem_hoje = (float)valor_escalar("SELECT prod_valor_venda_margem FROM produtos WHERE prod_id = 1
    AND prod_ini_validade <= NOW() - INTERVAL 3 DAY ORDER BY prod_ini_validade DESC LIMIT 1");
$preco_2014  = (float)valor_escalar("SELECT prod_valor_venda FROM produtos WHERE prod_id = 1
    AND prod_ini_validade <= '2014-06-01 00:00:00' ORDER BY prod_ini_validade DESC LIMIT 1");

// Chamada com prazo contábil no futuro: ainda pode mudar, logo não é congelável.
$cha_t = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 5 DAY, 0.10, NOW() + INTERVAL 5 DAY)");

// Chamada velha, com o prazo contábil já vencido: congelável, e com preço de 2014.
$cha_velha = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1, '2014-06-01 10:00:00', '2014-05-20 10:00:00', '2014-05-28 10:00:00', 0.10, '2014-07-01 10:00:00')");

// Chamada com pedido em aberto: entrega registrada, mas o pedido não foi fechado.
$cha_aberta = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 5 DAY, 0.10, NOW() + INTERVAL 5 DAY)");

// Chamada fechada em que nada foi entregue. É o caso mais comum da base: na
// conferência contra o Quadro apareceram 576 dessas em 28 cestantes. O Quadro
// imprime a linha com 0,00 porque a tela é uma grade; o débito derivado omite,
// porque quem não recebeu nada não deve nada.
$cha_zero = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1, NOW() - INTERVAL 3 DAY, NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 5 DAY, 0.10, NOW() + INTERVAL 5 DAY)");

// O produto 2 entra na chamada marcado como INDISPONÍVEL e mesmo assim ganha
// entrega registrada. É o estado que existe na base de produção e que o Quadro
// de Cestantes ignora — se o débito derivado o contasse, divergiria da tela.
insere("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) VALUES ($cha_t, 1, 2)");
insere("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) VALUES ($cha_t, 2, 0)");
insere("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) VALUES ($cha_velha, 1, 2)");
insere("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) VALUES ($cha_aberta, 1, 2)");
insere("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) VALUES ($cha_zero, 1, 2)");
insere("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) VALUES ($cha_zero, 2, 2)");

// Associado: paga o preço de venda e a taxa por cima.
$ped_t = insere("INSERT INTO pedidos (ped_usr, ped_usr_associado, ped_nuc, ped_cha, ped_fechado)
    VALUES ($usr_t, 1, 1, $cha_t, '1')");
// Não associado, na MESMA chamada: paga o preço com margem e não paga taxa.
$ped_t2 = insere("INSERT INTO pedidos (ped_usr, ped_usr_associado, ped_nuc, ped_cha, ped_fechado)
    VALUES ($usr_t2, 0, 1, $cha_t, '1')");
$ped_velho = insere("INSERT INTO pedidos (ped_usr, ped_usr_associado, ped_nuc, ped_cha, ped_fechado)
    VALUES ($usr_t, 1, 1, $cha_velha, '1')");
$ped_aberto = insere("INSERT INTO pedidos (ped_usr, ped_usr_associado, ped_nuc, ped_cha, ped_fechado)
    VALUES ($usr_t, 1, 1, $cha_aberta, '0')");
$ped_zero = insere("INSERT INTO pedidos (ped_usr, ped_usr_associado, ped_nuc, ped_cha, ped_fechado)
    VALUES ($usr_t, 1, 1, $cha_zero, '1')");

// Pediu 5, recebeu 2: o débito é do que foi entregue.
insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES ($ped_t, 1, 5, 2)");
insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES ($ped_t, 2, 3, 3)");
insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES ($ped_t2, 1, 5, 2)");
insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES ($ped_velho, 1, 5, 2)");
insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES ($ped_aberto, 1, 5, 2)");
// Pedido fechado sem entrega: as duas formas que a base guarda — NULL (a tela de
// entrega nunca foi preenchida) e 0 (foi preenchida com zero).
insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES ($ped_zero, 1, 5, NULL)");
insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES ($ped_zero, 2, 3, 0)");

$deb = debitos_derivados($usr_t);
$linha = null;
$linha_velha = null;
$linha_aberta = null;
$linha_zero = null;
foreach ($deb as $d)
{
    if ($d['cha_id'] == $cha_t)      $linha        = $d;
    if ($d['cha_id'] == $cha_velha)  $linha_velha  = $d;
    if ($d['cha_id'] == $cha_aberta) $linha_aberta = $d;
    if ($d['cha_id'] == $cha_zero)   $linha_zero   = $d;
}

verifica("o debito derivado aparece para a chamada entregue",
    $linha !== null, "nenhuma linha para cha_id=$cha_t");

// Pediu 5, recebeu 2 — os dois números são diferentes de propósito: com
// pedprod_quantidade no lugar de pedprod_entregue o valor seria 5 * o preço.
verifica("o valor considera a quantidade entregue, nao a pedida",
    $linha && round($linha['valor_entregue'], 2) == round(2 * $preco_hoje, 2),
    $linha ? "valor_entregue={$linha['valor_entregue']} esperado=" . round(2 * $preco_hoje, 2)
           : var_export($linha, true));

verifica("associado paga taxa sobre o entregue, somada ao valor",
    $linha && round($linha['taxa'], 2) == round(2 * $preco_hoje * 0.10, 2)
    && round($linha['valor'], 2) == round($linha['valor_entregue'] + $linha['taxa'], 2),
    $linha ? "taxa={$linha['taxa']} entregue={$linha['valor_entregue']} valor={$linha['valor']}" : '');

verifica("com prazo contabil no futuro a chamada ainda nao e congelavel",
    $linha && !$linha['congelavel']);

verifica("com prazo contabil vencido a chamada e congelavel",
    $linha_velha && $linha_velha['congelavel'],
    var_export($linha_velha, true));

// O preço tem de ser o da data da entrega, não o de hoje. A guarda de desigualdade
// está na mesma asserção porque, se as duas versões do produto tivessem o mesmo
// preço, o teste passaria sem exercitar nada.
verifica("o preco e o vigente na data da entrega, nao o de hoje",
    $preco_2014 > 0 && $preco_2014 != $preco_hoje
    && $linha_velha && round($linha_velha['valor_entregue'], 2) == round(2 * $preco_2014, 2),
    "preco_2014=$preco_2014 preco_hoje=$preco_hoje entregue="
        . ($linha_velha ? $linha_velha['valor_entregue'] : 'sem linha'));

// O Quadro de Cestantes descarta o produto marcado indisponível na chamada. A
// primeira condição confere que o fixture criou mesmo esse estado — sem ela, o
// teste ficaria verde por não haver o que descartar.
$entregue_indisponivel = (float)valor_escalar("SELECT pedprod_entregue FROM pedidoprodutos
    WHERE pedprod_ped = " . (int)$ped_t . " AND pedprod_prod = 2");

verifica("produto indisponivel na chamada nao entra na conta",
    $entregue_indisponivel > 0
    && $linha && round($linha['valor_entregue'], 2) == round(2 * $preco_hoje, 2),
    "entregue do indisponivel=$entregue_indisponivel · valor_entregue="
        . ($linha ? $linha['valor_entregue'] : 'sem linha'));

verifica("pedido nao fechado nao gera debito",
    $linha_aberta === null, var_export($linha_aberta, true));

// Sem entrega não há débito, e a chamada nem aparece na lista. O Quadro imprime
// 0,00 porque é uma grade; aqui a lista é de dívida, e linha de valor zero só
// atrapalharia quem for lançar. A guarda confere que o fixture criou mesmo o
// pedido fechado sem entrega — senão o teste ficaria verde por não haver caso.
$linhas_sem_entrega = (int)valor_escalar("SELECT COUNT(*) FROM pedidoprodutos
    WHERE pedprod_ped = " . (int)$ped_zero . " AND COALESCE(pedprod_entregue, 0) = 0");

verifica("chamada fechada sem entrega nenhuma nao vira linha de debito",
    $linhas_sem_entrega === 2 && $linha_zero === null,
    "linhas sem entrega=$linhas_sem_entrega · linha=" . var_export($linha_zero, true));

// Não associado: o preço já vem com a margem embutida e não há taxa. Está na
// MESMA chamada do associado, então este teste também segura o filtro por
// cestante — sem ele, as duas chamadas somariam os dois pedidos e nenhum dos
// dois valores bateria.
$deb2 = debitos_derivados($usr_t2);
$linha2 = null;
foreach ($deb2 as $d) if ($d['cha_id'] == $cha_t) $linha2 = $d;

verifica("nao associado paga o preco com margem e nao paga taxa",
    $margem_hoje != $preco_hoje && $linha2
    && round($linha2['valor_entregue'], 2) == round(2 * $margem_hoje, 2)
    && round($linha2['taxa'], 2) == 0.0
    && round($linha2['valor'], 2) == round(2 * $margem_hoje, 2),
    $linha2 ? "entregue={$linha2['valor_entregue']} taxa={$linha2['taxa']} valor={$linha2['valor']}"
            : "nenhuma linha para cha_id=$cha_t no cestante $usr_t2");

// A consulta que NÃO RODA não pode virar "não deve nada". ONLY_FULL_GROUP_BY é o
// que quebra exatamente esta consulta e mais nada: o GROUP BY é por cha_id e a
// lista traz colunas não agregadas, então o servidor a recusa. A função tem de
// devolver null. O sql_mode volta ao valor original na linha seguinte — tudo o
// que roda daqui para baixo depende dele.
$sql_mode_antes = valor_escalar("SELECT @@SESSION.sql_mode");
executa_sql("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY'");
$deb_quebrado = debitos_derivados($usr_t);
executa_sql("SET SESSION sql_mode = " . prep_para_bd((string)$sql_mode_antes));

// O === é obrigatório: em PHP array() == null é VERDADEIRO, e com == este teste
// passaria também com o `return $linhas` que ele existe justamente para reprovar.
verifica("consulta recusada pelo servidor devolve null, nao lista vazia",
    $deb_quebrado === null, var_export($deb_quebrado, true));

// Fecha o teste acima por três lados: prova que o null veio do sql_mode e não de
// um cestante errado, que a restauração pegou, e que o erro do servidor não levou
// junto a transação de onde saem todos os fixtures.
$deb_restaurado = debitos_derivados($usr_t);
verifica("com o sql_mode restaurado a mesma chamada volta a listar",
    is_array($deb_restaurado) && count($deb_restaurado) > 0,
    "sql_mode = " . var_export(valor_escalar("SELECT @@SESSION.sql_mode"), true)
        . " · resultado = " . var_export($deb_restaurado, true));


// O SELECT que NÃO RODA não pode virar "não existe conta" e cair no cria_conta:
// criar por cima de uma pergunta sem resposta é pedir uma segunda conta para quem
// já tem uma. A alavanca é uma TEMPORARY TABLE `contas` SEM a coluna con_id — ela
// sombreia a tabela real só nesta sessão, faz o servidor recusar os dois SELECTs
// (ERROR 1054 Unknown column 'con_id') e deixa de pé o INSERT do cria_conta, que
// não menciona con_id. Uma sombra só cobre as duas funções.
//
// CREATE e DROP TEMPORARY TABLE são a exceção documentada que NÃO faz COMMIT
// implícito — ALTER numa temporária faz, então o bloco se limita a criar e
// derrubar. É o que torna esta alavanca usável aqui dentro: a transação que envolve todo
// o fixture sobrevive. Se não sobrevivesse, o rollback do fim não desfaria nada e
// a contagem de tabelas do runner reprovaria a corrida — a prova é automática.
//
// O bloco é apertado de propósito: enquanto a sombra existe, TODA consulta a
// `contas` cai nela. Por isso ele vem depois de tudo que usa a tabela de verdade,
// e a sombra é derrubada antes das asserções.
executa_sql("CREATE TEMPORARY TABLE contas (
    con_tipo  varchar(10) NOT NULL,
    con_usr   mediumint(6) unsigned DEFAULT NULL,
    con_nuc   mediumint(6) unsigned DEFAULT NULL,
    con_forn  mediumint(6) unsigned DEFAULT NULL,
    con_nome  varchar(120) DEFAULT NULL,
    con_chave varchar(30)  DEFAULT NULL) ENGINE=InnoDB");

$sombra_de_pe   = (executa_sql("SELECT con_tipo FROM contas") !== false);
$select_recusado= (executa_sql("SELECT con_id FROM contas WHERE con_usr = 1") === false);

$cestante_no_erro = conta_do_cestante($usr_t, true);
$rede_no_erro     = conta_da_rede();
$gravadas_na_sombra = (int)valor_escalar("SELECT COUNT(*) FROM contas");

executa_sql("DROP TEMPORARY TABLE contas");

// Guarda do fixture: sem ela o teste ficaria verde por não haver o que quebrar.
verifica("a sombra sem con_id faz o servidor recusar a busca de conta",
    $sombra_de_pe && $select_recusado,
    "sombra=" . var_export($sombra_de_pe, true)
        . " · select recusado=" . var_export($select_recusado, true));

// O === null é obrigatório, e não é preciosismo: com a guarda revertida o INSERT
// do cria_conta entra na sombra, que não tem AUTO_INCREMENT, id_inserido()
// devolve 0 e cria_conta devolve 0 — falsy. Escrito como `!$r`, este teste
// passaria também contra a versão defeituosa.
verifica("busca recusada nao vira conta nova: conta_do_cestante devolve null",
    $cestante_no_erro === null, var_export($cestante_no_erro, true));

verifica("busca recusada nao vira conta nova: conta_da_rede devolve null",
    $rede_no_erro === null, var_export($rede_no_erro, true));

// A prova direta de que o cria_conta nem foi chamado: o INSERT dele cairia na
// sombra, e é lá que se conta. Com a guarda revertida este número é 2.
verifica("com a busca recusada nenhum INSERT de conta chega a ser tentado",
    $gravadas_na_sombra === 0, "linhas gravadas na sombra = $gravadas_na_sombra");

// Derrubada a sombra, a tabela real tem de voltar a aparecer — senão tudo o que
// vier depois estaria olhando para a tabela errada sem avisar.
verifica("derrubada a sombra, a busca volta a enxergar a conta real do cestante",
    conta_do_cestante($usr_t) == $con_t,
    "esperado $con_t · veio " . var_export(conta_do_cestante($usr_t), true));

echo "\nextrato\n";

// O extrato é a junção das duas metades: o débito que ainda é DERIVADO da entrega
// e o lançamento que já está GRAVADO no razão. Tudo aqui roda ACIMA do rollback,
// em cima do fixture do débito derivado — é ele que cria $usr_t, $con_t, as
// chamadas e as entregas de que o extrato depende.
//
// $con_rede já foi obtido lá em cima e é o mesmo con_id que conta_da_rede()
// devolveria de novo.

// Datas vindas do BANCO, não do relógio do PHP: cha_dt_entrega foi gravada com
// NOW() do MySQL, e comparar com date() daqui misturaria dois relógios.
$agora_bd          = valor_escalar("SELECT NOW()");
$dt_entrega_t      = valor_escalar("SELECT cha_dt_entrega FROM chamadas WHERE cha_id = " . (int)$cha_t);
$dt_entrega_velha  = valor_escalar("SELECT cha_dt_entrega FROM chamadas WHERE cha_id = " . (int)$cha_velha);
$prodt_nome_1      = valor_escalar("SELECT prodt_nome FROM produtotipos WHERE prodt_id = 1");

// Paga uma parte do que deve, com data posterior às duas entregas.
$tra_pag = lanca_transacao($agora_bd, 'pagamento', $con_rede, $con_t, 10.00, 'pagamento teste');

$ext = extrato_do_cestante($usr_t);
$ext = is_array($ext) ? $ext : array();

$derivadas = array();
$gravadas  = array();
foreach ($ext as $l)
{
    if ($l['situacao'] === 'derivado') $derivadas[] = $l;
    if ($l['situacao'] === 'gravado')  $gravadas[]  = $l;
}

// $usr_t tem exatamente DUAS entregas que viram débito ($cha_t e $cha_velha) — a
// aberta e a sem entrega não contam — mais o pagamento recém-lançado. A contagem
// exata é o que segura as duas fontes: some qualquer uma delas e o número muda.
verifica("o extrato junta as duas entregas derivadas e o pagamento gravado",
    count($ext) === 3 && count($derivadas) === 2 && count($gravadas) === 1
    && $gravadas[0]['historico'] === 'pagamento teste'
    && $gravadas[0]['tra_id'] == $tra_pag && $derivadas[0]['tra_id'] === null,
    "linhas = " . count($ext) . " · derivadas = " . count($derivadas)
        . " · gravadas = " . count($gravadas));

// Regra de sinal do módulo: negativo deve, positivo tem a receber. A entrega
// recebida é dívida do cestante, e debitos_derivados() devolve o valor POSITIVO —
// quem inverte o sinal é o extrato. Sem essa inversão, entrega e pagamento
// somariam no mesmo sentido e o saldo cresceria a cada entrega.
verifica("entrega entra negativa e pagamento entra positivo",
    count($derivadas) === 2 && $derivadas[0]['valor'] < 0 && $derivadas[1]['valor'] < 0
    && count($gravadas) === 1 && round($gravadas[0]['valor'], 2) == 10.00,
    "derivadas = " . json_encode(array_map(function($l){ return $l['valor']; }, $derivadas))
        . " · gravadas = " . json_encode(array_map(function($l){ return $l['valor']; }, $gravadas)));

// O histórico da linha derivada nomeia o tipo de produto da chamada. O caso do
// nome em branco está no fim deste bloco.
verifica("a linha derivada e rotulada com o tipo de produto da chamada",
    count($derivadas) === 2 && $derivadas[0]['historico'] === 'entrega ' . $prodt_nome_1,
    count($derivadas) ? var_export($derivadas[0]['historico'], true) : 'sem linha derivada');

// O saldo é acumulado NA ORDEM EM QUE AS LINHAS SAEM, e conferido linha a linha —
// não só no fim. Somar antes de ordenar dá o mesmo total e saldos errados no meio,
// e é justamente o erro que uma checagem só do último valor deixaria passar.
$acumulado  = 0.0;
$saldo_bate = (count($ext) > 0);
foreach ($ext as $l)
{
    $acumulado = round($acumulado + $l['valor'], 2);
    if (abs($l['saldo'] - $acumulado) > 0.005) $saldo_bate = false;
}

verifica("o saldo de cada linha e a soma de tudo que veio ate ela",
    $saldo_bate, json_encode(array_map(function($l){
        return array('dt' => $l['dt'], 'valor' => $l['valor'], 'saldo' => $l['saldo']); }, $ext)));


// Lançamento com data ANTERIOR ao que já existe. É o caso que motivou não gravar
// saldo: o núcleo lança na segunda um pagamento feito na sexta. A data escolhida
// cai ENTRE a entrega de 2014 e a de três dias atrás, então a linha tem de se
// enfiar no meio — sem o usort ela ficaria no fim, que é a ordem em que a consulta
// a devolve.
$tra_retro = lanca_transacao('2020-01-01 08:00:00', 'ajuste', $con_rede, $con_t, 5.00, 'retroativo teste');
$ext2 = extrato_do_cestante($usr_t);
$ext2 = is_array($ext2) ? $ext2 : array();

$datas2 = array();
foreach ($ext2 as $l) $datas2[] = $l['dt'];
$ordenado2 = $datas2;
sort($ordenado2);

verifica("o extrato sai em ordem de data mesmo com lancamento retroativo",
    count($ext2) === 4 && $datas2 === $ordenado2, implode(" | ", $datas2));

$pos_retro = null;
foreach ($ext2 as $i => $l) if ($l['historico'] === 'retroativo teste') $pos_retro = $i;

verifica("o lancamento retroativo entra na posicao da data dele, nao no fim",
    $pos_retro === 1, "posicao = " . var_export($pos_retro, true) . " · " . implode(" | ", $datas2));

// O saldo de todas as linhas seguintes se reordenou sozinho, porque não estava
// gravado em lugar nenhum.
$acumulado2  = 0.0;
$saldo_bate2 = (count($ext2) > 0);
foreach ($ext2 as $l)
{
    $acumulado2 = round($acumulado2 + $l['valor'], 2);
    if (abs($l['saldo'] - $acumulado2) > 0.005) $saldo_bate2 = false;
}

verifica("depois do retroativo o saldo de cada linha continua batendo",
    $saldo_bate2, json_encode(array_map(function($l){
        return array('dt' => $l['dt'], 'valor' => $l['valor'], 'saldo' => $l['saldo']); }, $ext2)));


// A entrega que JÁ virou lançamento sai da lista de derivados. Sem isso o cestante
// veria a mesma entrega cobrada duas vezes — uma como débito derivado, outra como
// o lançamento que acabou de ser gravado — e o saldo dobraria a dívida.
//
// A segunda metade da asserção é tão necessária quanto a primeira: um descarte
// grosso demais, que jogasse fora TODA linha derivada, também deixaria a primeira
// verde. A entrega de 2014 não foi lançada e tem de continuar aparecendo.
//
// O valor do lançamento sai do PRÓPRIO extrato, e não do $linha do bloco do débito
// derivado, ~290 linhas acima: uma variável emprestada daquela distância continua
// funcionando hoje e cai calada num valor qualquer no dia em que alguém renomear
// ou mover a de lá — e o teste seguiria verde medindo outra coisa. Sem fallback,
// de propósito: se a linha derivada não estiver aqui, lanca_transacao recebe null,
// devolve null e a asserção reprova alto.
$valor_derivado_cha_t = null;
foreach ($ext2 as $l)
    if ($l['situacao'] === 'derivado' && $l['cha'] == $cha_t) $valor_derivado_cha_t = -$l['valor'];

$tra_deb = lanca_transacao($dt_entrega_t, 'debito_entrega', $con_t, $con_rede, $valor_derivado_cha_t,
                           'entrega lancada', array('cha' => $cha_t));
$ext3 = extrato_do_cestante($usr_t);
$ext3 = is_array($ext3) ? $ext3 : array();

$derivada_de_cha_t = 0;
$gravada_de_cha_t  = 0;
$derivada_da_velha = 0;
foreach ($ext3 as $l)
{
    if ($l['situacao'] === 'derivado' && $l['cha'] == $cha_t)     $derivada_de_cha_t++;
    if ($l['situacao'] === 'gravado'  && $l['cha'] == $cha_t)     $gravada_de_cha_t++;
    if ($l['situacao'] === 'derivado' && $l['cha'] == $cha_velha) $derivada_da_velha++;
}

verifica("entrega ja lancada aparece uma vez so, e as outras derivadas ficam",
    $tra_deb > 0 && $derivada_de_cha_t === 0 && $gravada_de_cha_t === 1
    && $derivada_da_velha === 1 && count($ext3) === 4,
    "derivada de cha_t = $derivada_de_cha_t · gravada de cha_t = $gravada_de_cha_t"
        . " · derivada da velha = $derivada_da_velha · linhas = " . count($ext3));


// Duas transações no MESMO instante: a ordem entre elas tem de continuar sendo a
// do tra_id, que é a que o ORDER BY da consulta estabelece. Um comparador que
// devolve 1 para os DOIS lados do empate — cada uma se dizendo maior que a outra —
// não é antissimétrico, e o PHP não confere: ele só entrega a ordem em que o
// algoritmo tropeçar, que aqui é a INVERTIDA (medido no PHP 8.4 do container).
// Empate devolvendo 0 preserva a ordem de inserção, porque usort é estável desde
// o PHP 8.0.
$dt_gemeo = '2019-03-05 09:00:00';
$tra_g1 = lanca_transacao($dt_gemeo, 'ajuste', $con_rede, $con_t, 1.00, 'gemeo 1');
$tra_g2 = lanca_transacao($dt_gemeo, 'ajuste', $con_rede, $con_t, 2.00, 'gemeo 2');
$ext4 = extrato_do_cestante($usr_t);
$ext4 = is_array($ext4) ? $ext4 : array();

$ordem_gemeos = array();
foreach ($ext4 as $l) if ($l['dt'] === $dt_gemeo) $ordem_gemeos[] = (int)$l['tra_id'];

verifica("duas gravadas no mesmo instante saem sempre na ordem do tra_id",
    $ordem_gemeos === array((int)$tra_g1, (int)$tra_g2),
    "ordem = " . json_encode($ordem_gemeos) . " · esperado = "
        . json_encode(array((int)$tra_g1, (int)$tra_g2)));


// Empate de data entre uma DERIVADA e uma GRAVADA. cha_dt_entrega e tra_dt são os
// dois datetime, então o empate exato acontece de verdade — e a data vem do banco
// justamente para a colisão ser real, e não um quase-empate de relógios diferentes.
//
// count($seq) === 2 é a guarda do fixture: prova que as duas linhas caíram MESMO
// no mesmo instante. Sem ela, um empate que não acontecesse deixaria a asserção
// verde sem haver nada para desempatar.
$tra_mesmo_dt = lanca_transacao($dt_entrega_velha, 'pagamento', $con_rede, $con_t, 3.00,
                                'pagamento no instante da entrega');
$ext5 = extrato_do_cestante($usr_t);
$ext5 = is_array($ext5) ? $ext5 : array();

$seq = array();
foreach ($ext5 as $l) if ($l['dt'] === $dt_entrega_velha) $seq[] = $l['situacao'];

verifica("no empate de data a derivada vem antes da gravada",
    count($seq) === 2 && $seq === array('derivado', 'gravado'),
    "sequencia em $dt_entrega_velha = " . json_encode($seq));


// Consulta que NÃO RODA não pode virar extrato vazio: vazio diria ao cestante que
// ele está quite. São DUAS consultas, e cada uma tem a sua alavanca.
//
// Primeira: a do débito derivado. ONLY_FULL_GROUP_BY recusa exatamente ela — o
// GROUP BY é por cha_id e a lista traz colunas não agregadas — e não toca na
// consulta de lançamentos, que não agrupa nada. O sql_mode é restaurado ANTES da
// asserção: uma falha no meio deixaria a sessão adulterada para tudo que vem
// depois.
$sql_mode_antes_ext = valor_escalar("SELECT @@SESSION.sql_mode");
executa_sql("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY'");
$ext_sem_derivado = extrato_do_cestante($usr_t);
executa_sql("SET SESSION sql_mode = " . prep_para_bd((string)$sql_mode_antes_ext));

// O === é obrigatório: em PHP array() == null é VERDADEIRO, e com == a asserção
// passaria também contra a versão que devolve lista vazia.
verifica("debito derivado recusado pelo servidor devolve null, nao extrato vazio",
    $ext_sem_derivado === null, var_export($ext_sem_derivado, true));

// Segunda: a de lançamentos gravados. A alavanca é a mesma TEMPORARY TABLE
// `contas` sem con_id usada no bloco de cima — ela recusa o JOIN em contas desta
// consulta (ERROR 1054) e NÃO toca em debitos_derivados, que não olha para contas.
// Por isso este bloco prova a guarda da segunda consulta sozinha: a primeira
// responde normalmente e mesmo assim o extrato tem de sair null, em vez de sair
// com os derivados e sem os pagamentos — que é o extrato que cobra a mais.
//
// CREATE e DROP TEMPORARY TABLE não fazem COMMIT implícito, então a transação que
// envolve o fixture sobrevive. A sombra é derrubada ANTES das asserções.
executa_sql("CREATE TEMPORARY TABLE contas (
    con_tipo  varchar(10) NOT NULL,
    con_usr   mediumint(6) unsigned DEFAULT NULL,
    con_nuc   mediumint(6) unsigned DEFAULT NULL,
    con_forn  mediumint(6) unsigned DEFAULT NULL,
    con_nome  varchar(120) DEFAULT NULL,
    con_chave varchar(30)  DEFAULT NULL) ENGINE=InnoDB");

$sombra_ext_de_pe  = (executa_sql("SELECT con_tipo FROM contas") !== false);
$derivado_na_sombra = debitos_derivados($usr_t);
$ext_sem_gravado    = extrato_do_cestante($usr_t);

executa_sql("DROP TEMPORARY TABLE contas");

// Guarda do fixture, e ao mesmo tempo a prova de que as duas alavancas atingem
// consultas DIFERENTES: com a sombra de pé o débito derivado continua respondendo.
verifica("com a sombra de pe o debito derivado ainda responde",
    $sombra_ext_de_pe && is_array($derivado_na_sombra) && count($derivado_na_sombra) > 0,
    "sombra = " . var_export($sombra_ext_de_pe, true)
        . " · derivados = " . var_export($derivado_na_sombra, true));

verifica("consulta de lancamentos recusada devolve null, nao extrato so com derivados",
    $ext_sem_gravado === null, var_export($ext_sem_gravado, true));

$ext_restaurado = extrato_do_cestante($usr_t);
verifica("derrubada a sombra, o extrato volta a sair inteiro",
    is_array($ext_restaurado) && count($ext_restaurado) === count($ext5),
    "linhas = " . (is_array($ext_restaurado) ? count($ext_restaurado) : var_export($ext_restaurado, true))
        . " · esperado = " . count($ext5));


// Cestante SEM conta não é cestante sem extrato: ele tem entrega e ainda não pagou
// nada. "Não tem conta" é uma resposta; "a consulta não rodou" é a ausência de
// uma. É por isso que o recorte do extrato é o con_usr do JOIN, e não um con_id
// vindo de conta_do_cestante(), cujo null junta os dois casos num só.
//
// $usr_t2 tem pedido entregue na mesma chamada de $usr_t e nunca ganhou conta —
// as tentativas de criar uma para ele, lá em cima, foram todas recusadas. A
// contagem exata em 1 também segura o recorte por cestante: sem ele, os
// lançamentos de $usr_t apareceriam aqui.
$conta_do_t2 = conta_do_cestante($usr_t2);
$ext_t2 = extrato_do_cestante($usr_t2);

verifica("cestante sem conta tem extrato com os derivados, e nao null",
    $conta_do_t2 === null && is_array($ext_t2) && count($ext_t2) === 1
    && $ext_t2[0]['situacao'] === 'derivado' && $ext_t2[0]['tra_id'] === null,
    "conta = " . var_export($conta_do_t2, true) . " · extrato = " . json_encode($ext_t2));


// O nome do tipo de produto entra no histórico por concatenação, e prodt_nome vem
// de um LEFT JOIN. NULL não chega aqui — cha_prodt é NOT NULL com FK para
// produtotipos, então o par sempre casa. O que chega é nome EM BRANCO: prodt_nome
// é NOT NULL, mas com sql_mode vazio um '' entra sem reclamação. Concatenado seco
// viraria 'entrega ' com espaço solto e sem dizer de quê.
//
// O nome volta ao original ANTES das asserções: entre a adulteração e a restauração
// não pode haver nada que possa falhar. produtotipos não está na contagem de
// tabelas do runner — e não adiantaria estar, porque o runner conta linhas e isto
// é mudança de valor. Quem desfaz é a restauração aqui e o rollback do fim.
$prodt_nome_antes = valor_escalar("SELECT prodt_nome FROM produtotipos WHERE prodt_id = 1");
executa_sql("UPDATE produtotipos SET prodt_nome = '' WHERE prodt_id = 1");
$ext_sem_nome = extrato_do_cestante($usr_t);
executa_sql("UPDATE produtotipos SET prodt_nome = " . prep_para_bd((string)$prodt_nome_antes)
            . " WHERE prodt_id = 1");

$hist_sem_nome = null;
foreach ((is_array($ext_sem_nome) ? $ext_sem_nome : array()) as $l)
    if ($l['situacao'] === 'derivado') { $hist_sem_nome = $l['historico']; break; }

verifica("tipo de produto sem nome nao vira 'entrega ' com espaco solto",
    $hist_sem_nome === 'entrega', var_export($hist_sem_nome, true));

verifica("o nome do tipo de produto foi devolvido ao original",
    valor_escalar("SELECT prodt_nome FROM produtotipos WHERE prodt_id = 1") === $prodt_nome_antes,
    var_export(valor_escalar("SELECT prodt_nome FROM produtotipos WHERE prodt_id = 1"), true));


// `cha` nos extras de lanca_transacao vale para QUALQUER tipo, não só para
// debito_entrega: um pagamento pode muito bem ser registrado contra a chamada a
// que se refere. Só o DÉBITO materializa a entrega, então só ele apaga a linha
// derivada — e é por isso que o filtro por tra_tipo existe.
//
// Sem esse filtro, um pagamento com chamada faria a dívida daquela entrega SUMIR
// do extrato: o cestante pagaria e o débito desapareceria junto, em vez de os dois
// aparecerem. É a cobrança a MENOS, gêmea da cobrança a mais que a deduplicação
// evita — e nenhum dos outros testes a pega, porque toda transação com chamada
// criada até aqui é debito_entrega.
//
// A contagem ANTES é a guarda do fixture: prova que havia mesmo uma linha derivada
// para se perder.
$ext_antes_pag_cha = extrato_do_cestante($usr_t);
$ext_antes_pag_cha = is_array($ext_antes_pag_cha) ? $ext_antes_pag_cha : array();

$derivada_velha_antes = 0;
foreach ($ext_antes_pag_cha as $l)
    if ($l['situacao'] === 'derivado' && $l['cha'] == $cha_velha) $derivada_velha_antes++;

$tra_pag_cha = lanca_transacao($agora_bd, 'pagamento', $con_rede, $con_t, 2.00,
                               'pagamento com chamada', array('cha' => $cha_velha));
$ext6 = extrato_do_cestante($usr_t);
$ext6 = is_array($ext6) ? $ext6 : array();

$derivada_velha_depois = 0;
$gravada_com_cha_velha = 0;
foreach ($ext6 as $l)
{
    if ($l['situacao'] === 'derivado' && $l['cha'] == $cha_velha) $derivada_velha_depois++;
    if ($l['situacao'] === 'gravado'  && $l['cha'] == $cha_velha) $gravada_com_cha_velha++;
}

verifica("pagamento com chamada nao apaga o debito derivado dela",
    $tra_pag_cha > 0 && $derivada_velha_antes === 1 && $derivada_velha_depois === 1
    && $gravada_com_cha_velha === 1,
    "derivada antes = $derivada_velha_antes · depois = $derivada_velha_depois"
        . " · gravada com a mesma chamada = $gravada_com_cha_velha");

// tra_historico é NULLABLE no banco. Uma transação nascida por lanca_transacao()
// nunca chega a NULL — o histórico passa por prep_para_bd() e um null vira '' —,
// então a linha com NULL de verdade vem por INSERT direto, que é o que uma carga
// ou uma correção à mão faz.
//
// O lado DERIVADO do extrato sempre monta uma string; o GRAVADO repassava o que
// viesse do banco. Sem normalizar, o mesmo campo sai com dois tipos diferentes na
// mesma lista, e quem descobriria seria a tela da Task 6.
//
// As duas pernas vão juntas para a base continuar coerente para
// transacoes_desbalanceadas().
$tra_sem_hist = insere("INSERT INTO transacoes (tra_dt, tra_tipo, tra_historico, tra_usr_registro)
    VALUES ('2021-05-05 07:00:00','ajuste',NULL,0)");
insere("INSERT INTO lancamentos (lan_tra, lan_con, lan_valor) VALUES ($tra_sem_hist, $con_t, 1.00)");
insere("INSERT INTO lancamentos (lan_tra, lan_con, lan_valor) VALUES ($tra_sem_hist, $con_rede, -1.00)");

// Guarda do fixture: prova que o NULL chegou mesmo ao banco. Sem ela, um INSERT que
// gravasse '' deixaria a asserção verde sem haver o que normalizar.
$hist_nulo_no_banco = (valor_escalar("SELECT tra_historico FROM transacoes WHERE tra_id = "
    . (int)$tra_sem_hist) === null);

$ext7 = extrato_do_cestante($usr_t);
$ext7 = is_array($ext7) ? $ext7 : array();

$achou_sem_hist   = false;
$hist_da_sem_hist = null;
foreach ($ext7 as $l)
    if ($l['tra_id'] === (int)$tra_sem_hist) { $achou_sem_hist = true; $hist_da_sem_hist = $l['historico']; }

verifica("historico nulo no banco sai como string, igual ao lado derivado",
    $hist_nulo_no_banco && $achou_sem_hist && is_string($hist_da_sem_hist),
    "nulo no banco = " . var_export($hist_nulo_no_banco, true)
        . " · achou a linha = " . var_export($achou_sem_hist, true)
        . " · historico = " . var_export($hist_da_sem_hist, true));


// Duas chamadas com o MESMO cha_dt_entrega. debitos_derivados() ordenava só pela
// data, sem desempate, e o servidor é livre para devolver os empates na ordem que
// quiser: medido na cópia de produção, o cestante 379 tem 411 linhas derivadas com
// 88 datas empatadas, e a mesma consulta devolveu TRÊS ordens diferentes em três
// corridas sobre dados que não mudaram.
//
// O extrato repassa isso fielmente — o comparador devolve 0 no empate e o usort é
// estável —, então a coluna `saldo` daquelas linhas mudava a cada leitura, para o
// mesmo cestante e os mesmos dados. O saldo final comuta, então não é erro de
// dinheiro; é um extrato que não se repete, e a Task 6 vai pôr isso numa tela.
//
// O conserto é o desempate por cha_id dentro de debitos_derivados(), que serve a
// TODOS os chamadores de uma vez; a estabilidade do usort carrega o extrato de
// graça. Desempatar no comparador do extrato consertaria só o extrato.
//
// Honestidade sobre o que esta asserção prova: sem o desempate ela só falha quando
// o servidor resolver devolver a outra ordem, o que não é garantido numa corrida.
// Quem a prova carregadora é a mutação `ORDER BY c.cha_dt_entrega, c.cha_id DESC`,
// determinística, que a derruba na hora.
$cha_gemea = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1, " . prep_para_bd($dt_entrega_velha) . ", '2014-05-20 10:00:00', '2014-05-28 10:00:00', 0.10, '2014-07-01 10:00:00')");
insere("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) VALUES ($cha_gemea, 1, 2)");
$ped_gemeo = insere("INSERT INTO pedidos (ped_usr, ped_usr_associado, ped_nuc, ped_cha, ped_fechado)
    VALUES ($usr_t, 1, 1, $cha_gemea, '1')");
insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES ($ped_gemeo, 1, 5, 2)");

$deb_gemeo = debitos_derivados($usr_t);
$deb_gemeo = is_array($deb_gemeo) ? $deb_gemeo : array();

// Guarda do fixture: sem DUAS linhas na mesma data não existe empate para desempatar.
$empatadas = array();
foreach ($deb_gemeo as $d) if ($d['cha_dt_entrega'] === $dt_entrega_velha) $empatadas[] = (int)$d['cha_id'];

// A lista inteira, e não só o par: a chave (data, cha_id) com o id preenchido à
// esquerda ordena como texto na mesma ordem em que o SQL a ordena por (data, id).
$chave_derivada = array();
foreach ($deb_gemeo as $d)
    $chave_derivada[] = $d['cha_dt_entrega'] . '#' . str_pad((string)$d['cha_id'], 10, '0', STR_PAD_LEFT);
$chave_ordenada = $chave_derivada;
sort($chave_ordenada);

verifica("debito derivado desempata a data pelo cha_id, e nao pela sorte do servidor",
    count($empatadas) === 2 && $empatadas === array((int)$cha_velha, (int)$cha_gemea)
    && $chave_derivada === $chave_ordenada,
    "empatadas = " . json_encode($empatadas) . " · esperado = "
        . json_encode(array((int)$cha_velha, (int)$cha_gemea))
        . " · chaves = " . json_encode($chave_derivada));

// O extrato herda o desempate de graça: o comparador devolve 0 entre duas derivadas
// de mesma data e o usort preserva a ordem de inserção, que é a da consulta.
$ext8 = extrato_do_cestante($usr_t);
$ext8 = is_array($ext8) ? $ext8 : array();

$ordem_derivadas_empatadas = array();
foreach ($ext8 as $l)
    if ($l['situacao'] === 'derivado' && $l['dt'] === $dt_entrega_velha)
        $ordem_derivadas_empatadas[] = (int)$l['cha'];

verifica("o extrato herda o desempate: derivadas de mesma data saem em ordem de chamada",
    $ordem_derivadas_empatadas === array((int)$cha_velha, (int)$cha_gemea),
    "ordem = " . json_encode($ordem_derivadas_empatadas) . " · esperado = "
        . json_encode(array((int)$cha_velha, (int)$cha_gemea)));


echo "\npermissao\n";

// $_SESSION não é privado deste bloco. lanca_transacao lê $_SESSION['usr.id'] para
// gravar tra_usr_registro (financeiro.inc.php:23), e a coluna NÃO tem chave
// estrangeira — conferido no SHOW CREATE TABLE transacoes. Sessão deixada suja aqui
// faria o bloco "caminho de producao", que roda depois do rollback e grava de
// verdade na cópia de produção, carimbar um id de usuário que o rollback acabou de
// apagar, sem o banco reclamar. Snapshot e restauração, como no sql_mode acima.
$sessao_antes = $_SESSION;

// pode_ver_financeiro() impõe DUAS coisas, ligadas por E: o papel Beta Tester e uma
// sessão logada. Papel de negócio não entra na conta — os dois primeiros casos abaixo
// deixam PAP_RESP_FINANCAS ligado justamente para provar que ele não fura a trava.
$_SESSION[PAP_BETA_TESTER]   = false;
$_SESSION[PAP_RESP_FINANCAS] = true;
$_SESSION[PAP_RESP_NUCLEO]   = false;
$_SESSION[PAP_ADM]           = false;
$_SESSION['usr.id']          = $usr_t;
$_SESSION['usr.nuc']         = 1;

verifica("sem beta tester o modulo nao abre, mesmo para financas",
    pode_ver_financeiro() === false);

// O administrador é o caso que decide se o módulo fica mesmo invisível:
// verifica_seguranca() valida qualquer chamada vinda de PAP_ADM sem sequer olhar o
// parâmetro (common.inc.php:103-110), então a trava do beta não pode depender dela.
$_SESSION[PAP_ADM] = true;
verifica("sem beta tester o modulo nao abre nem para o administrador",
    pode_ver_financeiro() === false);
$_SESSION[PAP_ADM] = false;

// Agora com o papel do beta, e com TODO papel de negócio DESLIGADO. O desligamento não é
// arrumação: enquanto o PAP_RESP_FINANCAS ficava ligado aqui, este teste passava também
// contra a forma antiga da função, em que um termo de papel ocupava o lugar do usr.id —
// e o nome do teste ("com beta tester e papel de negocio") ainda descrevia aquela forma.
// Medido: a mutação que troca `!empty($_SESSION['usr.id'])` por
// `!empty($_SESSION[PAP_RESP_FINANCAS])` era pega só de lado, pelos testes de
// pode_ver_conta_de(); nenhum teste falava da própria pode_ver_financeiro().
$_SESSION[PAP_BETA_TESTER]   = true;
$_SESSION[PAP_RESP_FINANCAS] = false;
$_SESSION[PAP_RESP_NUCLEO]   = false;
$_SESSION[PAP_ADM]           = false;
verifica("beta tester e sessao logada bastam: sem papel de negocio nenhum, abre",
    pode_ver_financeiro() === true);

// O OUTRO termo do E, que não tinha teste nenhum: medido, a mutação que apaga
// `&& !empty($_SESSION['usr.id'])` sobrevivia às 96 asserções. Papel sem sessão não é
// usuário — é o que sobra numa sessão meio montada, e o módulo não abre para isso.
$usr_id_guardado = $_SESSION['usr.id'];
unset($_SESSION['usr.id']);
verifica("beta tester sem sessao logada nao abre",
    pode_ver_financeiro() === false);
$_SESSION['usr.id'] = $usr_id_guardado;

$_SESSION[PAP_RESP_FINANCAS] = false;
$_SESSION[PAP_RESP_NUCLEO]   = false;
$_SESSION[PAP_ADM]           = false;
$_SESSION['usr.id']          = $usr_t;
verifica("cestante alcanca o proprio extrato",
    pode_ver_conta_de($usr_t) === true);

verifica("cestante nao alcanca o extrato de outro",
    pode_ver_conta_de($usr_t + 99999) === false);

// Escopo por núcleo conferido no banco, e não no que veio na URL. A sessão é de
// OUTRO usuário de propósito: com usr.id igual ao alvo, o ramo "o próprio"
// responderia antes e o vínculo de núcleo não seria exercitado.
$_SESSION[PAP_RESP_NUCLEO] = true;
$_SESSION['usr.id']        = $usr_t2;
$_SESSION['usr.nuc']       = 1;                 // $usr_t nasce com usr_nuc = 1
verifica("responsavel de nucleo alcanca o cestante do seu nucleo",
    pode_ver_conta_de($usr_t) === true);

$_SESSION['usr.nuc'] = 2;
verifica("responsavel de nucleo nao alcanca o cestante de outro nucleo",
    pode_ver_conta_de($usr_t) === false);

// A polaridade da falha aqui é a INVERSA da do resto do módulo: consulta que não
// roda resulta em acesso NEGADO. A alavanca é a sombra de TEMPORARY TABLE já usada
// no extrato, agora sobre usuarios e sem a coluna usr_id — o SELECT da função passa
// a ser recusado (ERROR 1054) sem que nada mais na sessão mude.
$_SESSION['usr.nuc'] = 1;
executa_sql("CREATE TEMPORARY TABLE usuarios (
    usr_nuc mediumint(6) unsigned DEFAULT NULL) ENGINE=InnoDB");

$sombra_usr_de_pe = (executa_sql("SELECT usr_id FROM usuarios") === false);
$pode_com_sombra  = pode_ver_conta_de($usr_t);

executa_sql("DROP TEMPORARY TABLE usuarios");

verifica("consulta recusada NEGA o acesso, em vez de conceder",
    $sombra_usr_de_pe && $pode_com_sombra === false,
    "sombra de pe = " . var_export($sombra_usr_de_pe, true)
        . " · pode = " . var_export($pode_com_sombra, true));

verifica("derrubada a sombra, o responsavel volta a alcancar o seu nucleo",
    pode_ver_conta_de($usr_t) === true);

// usr_id chega da URL como texto. Com o sql_mode vazio deste servidor, texto colado
// num id NÃO é recusado pelo banco: medido nesta cópia, `usr_id = '1 abc'` devolve a
// linha do usr_id 1, com warning 1292 e mais nada. Por isso a recusa é na entrada da
// função, antes de a consulta existir.
verifica("usr_id que nao e inteiro positivo e recusado na entrada",
    pode_ver_conta_de("$usr_t abc") === false
    && pode_ver_conta_de("1 OR 1=1") === false
    && pode_ver_conta_de("") === false
    && pode_ver_conta_de("0") === false
    && pode_ver_conta_de("-1") === false);

// ?usr_id[]=1 entrega ARRAY a request_get, e esse caso precisa de alavanca própria:
// sem a recusa por tipo, o retorno continua false — o que quebra é o silêncio, porque
// (string)array() emite "Array to string conversion" e segue. Warning é justamente o
// que o smoke.sh reprova na varredura das páginas, então a asserção olha para o aviso
// e não só para o retorno.
$aviso_array = '';
set_error_handler(function ($n, $s) use (&$aviso_array) { $aviso_array = $s; return true; });
$pode_array = pode_ver_conta_de(array($usr_t));
restore_error_handler();

verifica("usr_id em array e recusado sem emitir aviso",
    $pode_array === false && $aviso_array === '',
    "pode = " . var_export($pode_array, true) . " · aviso = " . var_export($aviso_array, true));

$_SESSION = $sessao_antes;

// Não é o `===` contra a própria variável que acabou de ser atribuída — esse não
// falharia nunca. O que se afirma é que a sessão voltou a ser a de uma corrida em
// CLI, sem usuário e sem papel: falha se alguém mover o snapshot para depois da
// primeira atribuição, ou apagar a restauração. Quem confere o efeito lá na frente
// é a asserção de tra_usr_registro, no bloco "caminho de producao".
verifica("o bloco de permissao devolveu a sessao ao que era",
    !isset($_SESSION['usr.id']) && !isset($_SESSION[PAP_BETA_TESTER]),
    "sessao = " . json_encode($_SESSION));


echo "\nresumo da tela\n";

// A tela não pode afirmar NADA sobre o saldo quando a consulta não rodou. É o motivo
// de o resumo devolver um estado, e não um saldo que possa ser nulo: `null < -0.005`
// e `null > 0.005` são os dois falsos, então um saldo nulo caindo na cadeia de
// comparações sairia pelo ramo final e a tela imprimiria "em dia" para quem deve.
//
// A alavanca é a de sempre: ONLY_FULL_GROUP_BY recusa a consulta agrupada de
// debitos_derivados() e extrato_do_cestante() sai null. Restaurado ANTES da asserção,
// para uma falha no meio não deixar a sessão adulterada.
$sql_mode_antes_resumo = valor_escalar("SELECT @@SESSION.sql_mode");
executa_sql("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY'");
$ext_indisponivel = extrato_do_cestante($usr_t);
executa_sql("SET SESSION sql_mode = " . prep_para_bd((string)$sql_mode_antes_resumo));

$resumo_indisponivel = resumo_do_extrato($ext_indisponivel);

verifica("extrato indisponivel nao vira saldo, e muito menos 'em dia'",
    $ext_indisponivel === null
    && $resumo_indisponivel['estado'] === 'indisponivel'
    && $resumo_indisponivel['saldo'] === null,
    "extrato = " . var_export($ext_indisponivel, true)
        . " · resumo = " . json_encode($resumo_indisponivel));

// Lista vazia é o CONTRÁRIO de null: a consulta rodou e não há o que mostrar. É a
// única entrada em que "em dia" é a resposta certa.
$resumo_vazio = resumo_do_extrato(array());
verifica("extrato vazio, esse sim, e 'em dia' com saldo zero",
    $resumo_vazio['estado'] === 'em_dia' && $resumo_vazio['saldo'] === 0.0,
    json_encode($resumo_vazio));

// O saldo do resumo é o da ÚLTIMA linha, que é onde extrato_do_cestante() deixa o
// acumulado. Sobre o extrato real do fixture, não sobre lista inventada.
$ext_real    = extrato_do_cestante($usr_t);
$ext_real    = is_array($ext_real) ? $ext_real : array();
$ultima      = count($ext_real) ? $ext_real[count($ext_real) - 1] : null;
$resumo_real = resumo_do_extrato($ext_real);

verifica("o resumo leva o saldo da ultima linha do extrato",
    $ultima !== null && $resumo_real['saldo'] === $ultima['saldo'],
    "linhas = " . count($ext_real) . " · resumo = " . json_encode($resumo_real)
        . " · ultima = " . json_encode($ultima));

// Os três estados de saldo, cada um com a sua fronteira. Meio centavo é o que separa
// "em dia" de dívida: o saldo chega de round(...,2) em round(...,2), então nenhuma
// linha alcançável cai dentro da faixa — ela existe para o zero, não para arredondar.
verifica("saldo negativo e devedor, positivo e credor, zero e em dia",
    resumo_do_extrato(array(array('saldo' => -10.0)))['estado'] === 'devedor'
    && resumo_do_extrato(array(array('saldo' =>  10.0)))['estado'] === 'credor'
    && resumo_do_extrato(array(array('saldo' =>   0.0)))['estado'] === 'em_dia'
    && resumo_do_extrato(array(array('saldo' => -0.004)))['estado'] === 'em_dia'
    && resumo_do_extrato(array(array('saldo' => -0.01)))['estado'] === 'devedor');

// A tela não afirma nada sobre dinheiro numa conta que ela não confirmou existir. É a
// mesma família do contrato de null, por outra porta: lá era "a consulta foi recusada",
// aqui é "a consulta rodou e não achou linha nenhuma". Antes desta rodada,
// ?usr_id=9999999 respondia HTTP 200 com o rótulo "em dia" e nome em branco — saldo
// zero porque não há lançamento de um id que não existe, o que é bem diferente de
// alguém estar quite.
//
// São TRÊS estados, e nenhum par deles pode se confundir: existe · não existe · não deu
// para perguntar. A busca é a mesma de conta_do_cestante(): só usr_id, sem usr_archive
// e sem papel, porque quem responde "existe" aqui não é quem decide quem pode ver — isso
// já passou pelo pode_ver_conta_de().
$cst_ok = cestante_da_conta($usr_t);
verifica("cestante que existe volta com estado ok e o nome que a tela mostra",
    $cst_ok['estado'] === 'ok' && $cst_ok['nome'] === 'tconta',
    json_encode($cst_ok));

// Um id que não existe. O + 99999 sai do alcance dos fixtures desta corrida sem depender
// de um literal que um dia pode virar linha de verdade na cópia de produção.
$cst_nao = cestante_da_conta($usr_t + 99999);
verifica("cestante que nao existe e 'inexistente', e nao um nome vazio",
    $cst_nao['estado'] === 'inexistente' && $cst_nao['nome'] === null,
    json_encode($cst_nao));

// A mesma sombra de TEMPORARY TABLE do bloco de permissão: sem a coluna usr_nome_curto o
// SELECT é recusado (ERROR 1054) e o estado tem de ser 'indisponivel'. Se ele virasse
// 'inexistente', a tela recusaria a conta dizendo que ela não existe justamente quando o
// banco não deu resposta — trocando "não sei" por "não tem".
executa_sql("CREATE TEMPORARY TABLE usuarios (
    usr_id mediumint(6) unsigned NOT NULL) ENGINE=InnoDB");

$sombra_nome_de_pe = (executa_sql("SELECT usr_nome_curto FROM usuarios") === false);
$cst_sem_banco     = cestante_da_conta($usr_t);

executa_sql("DROP TEMPORARY TABLE usuarios");

verifica("consulta recusada e 'indisponivel', nunca 'inexistente'",
    $sombra_nome_de_pe && $cst_sem_banco['estado'] === 'indisponivel'
    && $cst_sem_banco['nome'] === null,
    "sombra de pe = " . var_export($sombra_nome_de_pe, true)
        . " · resultado = " . json_encode($cst_sem_banco));

verifica("derrubada a sombra, o cestante volta a ser encontrado",
    cestante_da_conta($usr_t)['estado'] === 'ok');


echo "\npagamento\n";

// O pagamento credita o cestante e debita quem recebeu o dinheiro. As contas de
// destino deste fixture já nasceram no bloco "contas": $con_nuc_t é núcleo,
// $con_forn_t é produtor, $con_a e $con_b são da Rede. $con_t é a conta do
// cestante $usr_t — e é justamente ela que NÃO pode ser destino.

$destinos = contas_de_destino();

verifica("nucleo, produtor e rede entram na lista de destinos, com rotulo",
    is_array($destinos)
    && isset($destinos[$con_nuc_t]) && trim($destinos[$con_nuc_t]) !== ''
    && isset($destinos[$con_forn_t]) && trim($destinos[$con_forn_t]) !== ''
    && isset($destinos[$con_a]) && trim($destinos[$con_a]) !== '',
    json_encode(is_array($destinos) ? array_slice($destinos, 0, 6, true) : $destinos));

// É o defeito que o brief da tarefa trazia: o `<select>` da tela oferece só os
// destinos legítimos, mas um POST carrega qualquer con_id. Com a conta de OUTRO
// cestante como destino, o lançamento debitaria a conta dele — e as duas pernas
// continuariam somando zero. Razão íntegro e errado, que nenhum invariante pega.
verifica("conta de cestante nao aparece entre os destinos",
    is_array($destinos) && !isset($destinos[$con_t]),
    "con_t = " . var_export($con_t, true));

$saldo_antes_cest = saldo_da_conta($con_t);
$saldo_antes_nuc  = saldo_da_conta($con_nuc_t);

$tra_p = registra_pagamento($usr_t, '2026-08-10 09:00:00', 30.00, $con_nuc_t,
                            'http://exemplo/comprovante', 'pago na entrega');

verifica("registra_pagamento devolve o id da transacao",
    is_numeric($tra_p) && $tra_p > 0, var_export($tra_p, true));

verifica("o pagamento credita o cestante",
    round(saldo_da_conta($con_t) - $saldo_antes_cest, 2) == 30.00,
    "variacao = " . (saldo_da_conta($con_t) - $saldo_antes_cest));

verifica("o pagamento debita a conta de destino",
    round(saldo_da_conta($con_nuc_t) - $saldo_antes_nuc, 2) == -30.00,
    "variacao = " . (saldo_da_conta($con_nuc_t) - $saldo_antes_nuc));

verifica("o comprovante, a observacao e o tipo ficam guardados",
    valor_escalar("SELECT tra_comprovante FROM transacoes WHERE tra_id = " . (int)$tra_p) === 'http://exemplo/comprovante'
    && valor_escalar("SELECT tra_obs FROM transacoes WHERE tra_id = " . (int)$tra_p) === 'pago na entrega'
    && valor_escalar("SELECT tra_tipo FROM transacoes WHERE tra_id = " . (int)$tra_p) === 'pagamento');

// Pagamento direto ao produtor: quem recebeu o dinheiro foi ele, e é ele que fica
// devendo à Rede. O destino é escolha de quem lança, não consequência do núcleo.
$saldo_antes_forn = saldo_da_conta($con_forn_t);
registra_pagamento($usr_t, '2026-08-10 09:00:00', 20.00, $con_forn_t, '', '');

verifica("pagamento direto a produtor debita o produtor",
    round(saldo_da_conta($con_forn_t) - $saldo_antes_forn, 2) == -20.00,
    "variacao = " . (saldo_da_conta($con_forn_t) - $saldo_antes_forn));

// A contagem de lançamentos na MESMA asserção é o que torna o teste real: sem ela,
// uma versão que devolvesse null DEPOIS de gravar passaria verde.
//
// $usr_t3 nasceu no bloco "contas" e nunca ganhou conta — as tentativas de lá foram
// todas recusadas —, então a conta dele nasce aqui, só para ser oferecida como
// destino ilegítimo.
$con_t3    = conta_do_cestante($usr_t3, true);
$lan_antes = (int)valor_escalar("SELECT COUNT(*) FROM lancamentos");

verifica("conta de cestante nao recebe pagamento, e nada e gravado",
    registra_pagamento($usr_t, '2026-08-10 09:00:00', 30.00, $con_t3, '', '') === null
    && (int)valor_escalar("SELECT COUNT(*) FROM lancamentos") === $lan_antes,
    "con_t3 = " . var_export($con_t3, true)
        . " · lancamentos antes = $lan_antes · depois = " . valor_escalar("SELECT COUNT(*) FROM lancamentos"));

verifica("con_id que nao existe nao recebe pagamento, e nada e gravado",
    registra_pagamento($usr_t, '2026-08-10 09:00:00', 30.00, $con_t3 + 999999, '', '') === null
    && registra_pagamento($usr_t, '2026-08-10 09:00:00', 30.00, 'abc', '', '') === null
    && (int)valor_escalar("SELECT COUNT(*) FROM lancamentos") === $lan_antes);

// pg_destino[0][] entrega ARRAY onde se espera escalar, e no PHP 8 `isset($a[$k])` com
// $k array é TypeError, não false — medido no container. Sem a recusa por tipo, a tela
// de pagamentos cai inteira por causa de um nome de campo no POST, e a alavanca deste
// teste é essa queda: sem a guarda, a suíte morre aqui em vez de ficar vermelha.
verifica("destino que chega como array e recusado, e nao derruba a pagina",
    registra_pagamento($usr_t, '2026-08-10 09:00:00', 30.00, array($con_nuc_t), '', '') === null
    && (int)valor_escalar("SELECT COUNT(*) FROM lancamentos") === $lan_antes);

// Conta arquivada é conta que a administração tirou de circulação. Ela continua
// existindo — e o saldo dela continua contando —, mas não é mais oferecida nem
// aceita. Devolve a coluna ao original ANTES das asserções.
executa_sql("UPDATE contas SET con_archive = 1 WHERE con_id = " . (int)$con_forn_t);
$destinos_arq  = contas_de_destino();
$pag_arquivado = registra_pagamento($usr_t, '2026-08-10 09:00:00', 15.00, $con_forn_t, '', '');
executa_sql("UPDATE contas SET con_archive = 0 WHERE con_id = " . (int)$con_forn_t);

verifica("conta arquivada sai da lista e nao recebe pagamento",
    is_array($destinos_arq) && !isset($destinos_arq[$con_forn_t])
    && $pag_arquivado === null
    && (int)valor_escalar("SELECT COUNT(*) FROM lancamentos") === $lan_antes,
    "pagamento = " . var_export($pag_arquivado, true));

$destinos_desarq = contas_de_destino();
verifica("desarquivada, a conta volta a ser destino",
    is_array($destinos_desarq) && isset($destinos_desarq[$con_forn_t]));

// A polaridade de contas_de_destino(), que o brief deixava em aberto: consulta
// recusada devolve NULL, e lista vazia quer dizer "não há destino cadastrado" —
// que é o estado da cópia de produção hoje, com `contas` zerada. A tela diz coisas
// diferentes para cada um, e por isso os dois não podem se confundir.
//
// A alavanca é uma TEMPORARY TABLE sem nuc_id sobre `nucleos`, e NÃO a sombra de
// `contas` usada nos outros blocos. A escolha é o teste: sombreando `contas`, quem
// recusa o pagamento é conta_do_cestante(), que cai na mesma sombra, e a guarda
// daqui fica sem alavanca — medido, a versão que aceita o destino quando a lista
// não vem sobreviveu às 125 asserções. Com a sombra em `nucleos`, o LEFT JOIN de
// contas_de_destino() é recusado (ERROR 1054) e todo o resto continua de pé: se a
// guarda cair, o pagamento é gravado de verdade, e a contagem pega.
$lan_antes_sombra = (int)valor_escalar("SELECT COUNT(*) FROM lancamentos");

executa_sql("CREATE TEMPORARY TABLE nucleos (
    nuc_nome_curto varchar(100) NOT NULL) ENGINE=InnoDB");

$sombra_dest_de_pe  = (executa_sql("SELECT nuc_id FROM nucleos") === false);
$conta_ainda_de_pe  = (conta_do_cestante($usr_t) == $con_t);
$destinos_sem_banco = contas_de_destino();
$pag_sem_banco      = registra_pagamento($usr_t, '2026-08-10 09:00:00', 30.00, $con_nuc_t, '', '');

executa_sql("DROP TEMPORARY TABLE nucleos");

// Guarda do fixture: sem ela o teste ficaria verde por não haver o que quebrar — e a
// segunda metade é o que separa esta sombra da de `contas`.
verifica("a sombra sem nuc_id recusa so a lista de destinos, e nao a busca de conta",
    $sombra_dest_de_pe && $conta_ainda_de_pe,
    "sombra de pe = " . var_export($sombra_dest_de_pe, true)
        . " · conta de pe = " . var_export($conta_ainda_de_pe, true));

verifica("consulta recusada devolve null, e nao lista vazia de destinos",
    $destinos_sem_banco === null, var_export($destinos_sem_banco, true));

// Sem lista não há contra o que conferir o destino, e destino não conferido é o
// defeito inteiro. A alavanca é a versão que "conserta" a tela deixando passar
// quando a lista não vem: essa grava, e a contagem a pega.
verifica("sem a lista de destinos nao se grava pagamento nenhum",
    $pag_sem_banco === null
    && (int)valor_escalar("SELECT COUNT(*) FROM lancamentos") === $lan_antes_sombra,
    "pagamento = " . var_export($pag_sem_banco, true)
        . " · lancamentos antes = $lan_antes_sombra · depois = "
        . valor_escalar("SELECT COUNT(*) FROM lancamentos"));

// O pagamento tem de aparecer no extrato e mover o saldo PARA CIMA — é a regra de
// sinal do módulo, e é o teste que a segura: trocar as pernas em registra_pagamento
// faz o saldo afundar, e as duas pernas continuam somando zero.
$resumo_antes  = resumo_do_extrato(extrato_do_cestante($usr_t));
$tra_ext       = registra_pagamento($usr_t, '2026-08-11 09:00:00', 7.00, $con_nuc_t, '', '');
$extrato_pos   = extrato_do_cestante($usr_t);
$resumo_depois = resumo_do_extrato($extrato_pos);

verifica("o pagamento sobe o saldo do cestante, na regra de sinal do modulo",
    is_numeric($tra_ext) && $tra_ext > 0
    && round($resumo_depois['saldo'] - $resumo_antes['saldo'], 2) == 7.00,
    "antes = " . var_export($resumo_antes['saldo'], true)
        . " · depois = " . var_export($resumo_depois['saldo'], true));

$linha_pag = null;
foreach ((is_array($extrato_pos) ? $extrato_pos : array()) as $l)
    if ($l['tra_id'] === (int)$tra_ext) { $linha_pag = $l; break; }

verifica("o pagamento aparece no extrato como lancamento gravado",
    $linha_pag !== null && $linha_pag['situacao'] === 'gravado'
    && $linha_pag['valor'] == 7.00 && $linha_pag['cha'] === null,
    json_encode($linha_pag));


// --- leitura do formulário em lote -------------------------------------------
//
// A tela não tem alavanca automática nenhuma, então a leitura do POST mora numa
// função para poder ter uma. O que se testa aqui é o que o addendum chama de A5 —
// os arrays do POST não são fonte de verdade sobre a própria forma — mais a data,
// que é um caminho para derrubar a página.

$dt_ok = date('d/m/Y');

$lote_ok = linhas_de_pagamento(array(
    'pg_usr'         => array($usr_t, $usr_t3),
    'pg_dt'          => array($dt_ok, $dt_ok),
    'pg_valor'       => array('30,00', ''),
    'pg_destino'     => array($con_nuc_t, $con_nuc_t),
    'pg_comprovante' => array('pix', ''),
));

verifica("a linha preenchida e lida e a em branco e pulada sem virar recusa",
    count($lote_ok['linhas']) === 1 && $lote_ok['ignoradas'] === 0
    && $lote_ok['linhas'][0]['usr'] == $usr_t
    && $lote_ok['linhas'][0]['valor'] == 30.00
    && $lote_ok['linhas'][0]['dt'] === date('Y-m-d')
    && $lote_ok['linhas'][0]['comprovante'] === 'pix',
    json_encode($lote_ok));

// sizeof() sobre string lança TypeError no PHP 8: um POST com pg_usr=1, escalar em
// vez de array, derrubaria a tela inteira. Aqui não há linha para ler, e ponto.
$lote_escalar = linhas_de_pagamento(array(
    'pg_usr'     => '1',
    'pg_dt'      => $dt_ok,
    'pg_valor'   => '30,00',
    'pg_destino' => (string)$con_nuc_t,
));

verifica("POST sem a forma de lote nao vira linha nenhuma, e nao derruba a pagina",
    $lote_escalar['linhas'] === array() && $lote_escalar['ignoradas'] === 0,
    json_encode($lote_escalar));

// Arrays paralelos de tamanhos diferentes: o índice que falta vira linha
// malformada, e linha malformada se pula — não se adivinha.
$lote_torto = linhas_de_pagamento(array(
    'pg_usr'     => array($usr_t, $usr_t3),
    'pg_dt'      => array($dt_ok),
    'pg_valor'   => array('30,00'),
    'pg_destino' => array($con_nuc_t),
));

verifica("indice que falta num dos arrays vira linha ignorada, nao adivinhada",
    count($lote_torto['linhas']) === 1 && $lote_torto['ignoradas'] === 1,
    json_encode($lote_torto));

// A data é caminho de queda, e não só de erro: date_format(false, ...) é TypeError
// no PHP 8, e date_create_from_format devolve false para "ontem". Já '30/02/2026'
// é pior — ele PARSEIA e escorrega para 02/03, então a conferência olha os avisos,
// e não só o retorno.
$lote_data = linhas_de_pagamento(array(
    'pg_usr'     => array($usr_t, $usr_t, $usr_t),
    'pg_dt'      => array('ontem', '30/02/2026', $dt_ok . 'abc'),
    'pg_valor'   => array('30,00', '30,00', '30,00'),
    'pg_destino' => array($con_nuc_t, $con_nuc_t, $con_nuc_t),
));

verifica("data que nao e data vira linha ignorada, e nao derruba a pagina",
    $lote_data['linhas'] === array() && $lote_data['ignoradas'] === 3,
    json_encode($lote_data));

// Valor: o que não vira número positivo não vira lançamento. '1.234,56' entra
// porque é como a Rede escreve dinheiro; sem o tratamento do separador de milhar,
// formata_numero_para_mysql() devolveria '1,234.56', que não é número.
$lote_valor = linhas_de_pagamento(array(
    'pg_usr'     => array($usr_t, $usr_t, $usr_t, $usr_t),
    'pg_dt'      => array($dt_ok, $dt_ok, $dt_ok, $dt_ok),
    'pg_valor'   => array('0', '-5', 'trinta', '1.234,56'),
    'pg_destino' => array($con_nuc_t, $con_nuc_t, $con_nuc_t, $con_nuc_t),
));

verifica("valor que nao e numero positivo e ignorado, e o separador de milhar passa",
    count($lote_valor['linhas']) === 1 && $lote_valor['ignoradas'] === 3
    && $lote_valor['linhas'][0]['valor'] == 1234.56,
    json_encode($lote_valor));

// pg_destino[0][] entrega ARRAY onde se espera escalar. Sem a recusa por tipo, o
// valor seguiria para dentro de registra_pagamento e o (string) de algum ponto
// adiante emitiria "Array to string conversion" — aviso é o que o smoke.sh reprova.
$aviso_lote = '';
set_error_handler(function ($n, $s) use (&$aviso_lote) { $aviso_lote = $s; return true; });
$lote_array = linhas_de_pagamento(array(
    'pg_usr'     => array(array($usr_t)),
    'pg_dt'      => array($dt_ok),
    'pg_valor'   => array('30,00'),
    'pg_destino' => array($con_nuc_t),
));
restore_error_handler();

verifica("campo que chega como array e ignorado sem emitir aviso",
    $lote_array['linhas'] === array() && $lote_array['ignoradas'] === 1 && $aviso_lote === '',
    json_encode($lote_array) . " · aviso = " . var_export($aviso_lote, true));


// --- quem pode lançar --------------------------------------------------------
//
// A trava da tela mora numa função pela mesma razão da leitura do POST: escrita
// solta dentro do conta_pagamentos.php, nenhum teste a alcança. Snapshot e
// restauração da sessão como no bloco de permissão — lanca_transacao lê
// $_SESSION['usr.id'], e o bloco "caminho de producao" confere que ele saiu limpo.
$sessao_antes_pag = $_SESSION;

$_SESSION[PAP_BETA_TESTER]   = false;
$_SESSION[PAP_RESP_FINANCAS] = false;
$_SESSION[PAP_RESP_NUCLEO]   = false;
$_SESSION[PAP_ADM]           = true;
$_SESSION['usr.id']          = $usr_t;
$_SESSION['usr.nuc']         = 1;

// O administrador é o caso que decide se o módulo continua invisível: é ele que
// verifica_seguranca() valida sem olhar o parâmetro (common.inc.php:103-110).
verifica("sem beta tester ninguem lanca pagamento, nem o administrador",
    pode_lancar_pagamento() === false);

$_SESSION[PAP_BETA_TESTER] = true;
verifica("beta tester mais administrador lanca",
    pode_lancar_pagamento() === true);

$_SESSION[PAP_ADM]         = false;
$_SESSION[PAP_RESP_NUCLEO] = true;
verifica("beta tester mais responsavel de nucleo lanca",
    pode_lancar_pagamento() === true);

$_SESSION[PAP_RESP_NUCLEO]   = false;
$_SESSION[PAP_RESP_FINANCAS] = true;
verifica("beta tester mais responsavel financas lanca",
    pode_lancar_pagamento() === true);

// O cestante comum passa por pode_ver_financeiro() — vê o próprio extrato — e não
// passa por aqui. É a distinção entre as duas funções, e sem este caso a lista de
// papéis poderia sumir inteira sem nenhum teste reclamar.
$_SESSION[PAP_RESP_FINANCAS] = false;
verifica("cestante comum ve o proprio extrato, mas nao lanca pagamento",
    pode_ver_financeiro() === true && pode_lancar_pagamento() === false);

$_SESSION = $sessao_antes_pag;

verifica("o bloco de pagamento devolveu a sessao ao que era",
    !isset($_SESSION['usr.id']) && !isset($_SESSION[PAP_BETA_TESTER]),
    "sessao = " . json_encode($_SESSION));

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

// Esta linha GRAVOU de verdade na cópia de produção, e o autor do registro saiu de
// $_SESSION['usr.id'] (financeiro.inc.php:23). Numa corrida em CLI não há sessão,
// então tem de sair 0. Se o bloco de permissão lá em cima deixar a sessão suja, sai
// aqui o id de um usuário de fixture que o rollback já apagou — e tra_usr_registro
// não tem chave estrangeira para reclamar, conferido no SHOW CREATE TABLE.
$autor_prod = valor_escalar("SELECT tra_usr_registro FROM transacoes WHERE tra_id = " . (int)$tra_prod);

verifica("o registro gravado de verdade nao carrega usuario de fixture",
    (int)$autor_prod === 0,
    "tra_usr_registro = " . var_export($autor_prod, true)
        . " · sessao = " . json_encode($_SESSION));

echo "\n";
if ($falhas === 0) { echo "TODOS OS $total TESTES PASSARAM\n"; exit(0); }
echo "$falhas de $total TESTES FALHARAM\n";
exit(1);
