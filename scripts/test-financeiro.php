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
// O piso de DATA_CORTE_FINANCEIRO é definido AQUI, antes do require, porque o
// financeiro.inc.php usa if(!defined).
//
// 2014-01-01 não é arbitrário: mantém o fixture de 2014-06-01 (que testa janela de
// preço e congelavel) dentro do alcance, e fica DEPOIS de 2013-01-01, quando o
// produto 1 passa a ter versão. Um piso mais antigo faria o teste da fronteira
// passar pelo motivo errado — a entrega seria excluída pela janela de preço, não
// pelo corte, e as duas causas ficariam indistinguíveis.
define('DATA_CORTE_FINANCEIRO', '2014-01-01 00:00:00');

require "/var/www/html/financeiro.inc.php";

echo "\nrazao\n";

mysqli_begin_transaction($conn_link);

// A transação é nossa: avisa o módulo, senão lanca_transacao abre um BEGIN
// aninhado — que no MySQL faz COMMIT implícito desta e o rollback do fim não
// desfaz nada.
$financeiro_em_transacao = true;

// ---------------------------------------------------------------------------
// Núcleos e produtores DESCARTÁVEIS, criados aqui dentro da transação.
//
// Antes a suíte pendurava as contas de teste em ids fixos da cópia (núcleo 2, 3,
// 21; produtor 2, 3, 10). Isso a fazia depender de `contas` estar VAZIA, e produção
// nunca vai estar — assim que o módulo entrar, todo núcleo e produtor ativo terá a
// sua conta, e a UNIQUE de con_nuc/con_forn passaria a recusar os INSERTs do
// fixture.
//
// Pior que o incômodo: com aquelas contas já existentes, o teste da chave reservada
// passava pelo motivo ERRADO. Ele espera null de cria_conta(), e o null viria da
// UNIQUE de con_nuc em vez da regra da chave — verde sem testar nada.
//
// Entidades próprias resolvem os dois: o vínculo é garantidamente livre, e elas são
// ATIVAS, o que importa porque contas_de_destino() exclui núcleo e produtor
// arquivados. Tudo desfeito no rollback.
$nuc_livre = array();
for ($i = 0; $i < 4; $i++)
    $nuc_livre[] = insere("INSERT INTO nucleos (nuc_nome_curto, nuc_nome_completo, nuc_archive)
        VALUES ('nucteste$i', 'Nucleo de teste $i', 0)");

$forn_livre = array();
for ($i = 0; $i < 3; $i++)
    $forn_livre[] = insere("INSERT INTO fornecedores (forn_prodt, forn_nome_curto, forn_nome_completo, forn_archive)
        VALUES (1, 'fornteste$i', 'Produtor de teste $i', 0)");

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
    cria_conta('nucleo',      array('con_nuc'  => $nuc_livre[0],  'con_chave' => 'rede_principal')) === null
    && cria_conta('produtor', array('con_forn' => $forn_livre[0], 'con_chave' => 'rede_principal')) === null
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
$con_chave_a = cria_conta('nucleo', array('con_nuc' => $nuc_livre[1], 'con_chave' => 'teste_chave_unica'));

verifica("chave repetida e recusada pelo banco",
    is_numeric($con_chave_a) && $con_chave_a > 0
    && cria_conta('produtor', array('con_forn' => $forn_livre[1], 'con_chave' => 'teste_chave_unica')) === null,
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
// Núcleo 21 e produtor 10 são ATIVOS de propósito (o 2 já é usado acima): contas_de_destino() não lista
// conta de núcleo ou produtor arquivado, e o fixture antes usava o núcleo 1 e o
// produtor 1, os dois arquivados nesta cópia. Passava porque não havia o filtro; com
// ele, o destino sumia da lista e registra_pagamento() recusava o pagamento — a
// falha aparecia nos testes de pagamento, longe de onde a causa estava.
$con_nuc_t = cria_conta('nucleo', array('con_nuc' => $nuc_livre[2], 'con_nome' => 'Teste Nucleo'));
verifica("conta de nucleo nasce com o vinculo e o rotulo",
    valor_escalar("SELECT COUNT(*) FROM contas WHERE con_id = " . (int)$con_nuc_t
        . " AND con_tipo = 'nucleo' AND con_nuc = " . (int)$nuc_livre[2] . " AND con_nome = 'Teste Nucleo'") == 1,
    var_export($con_nuc_t, true));

$con_forn_t = cria_conta('produtor', array('con_forn' => $forn_livre[2]));
verifica("conta de produtor nasce com o vinculo",
    valor_escalar("SELECT COUNT(*) FROM contas WHERE con_id = " . (int)$con_forn_t
        . " AND con_tipo = 'produtor' AND con_forn = " . (int)$forn_livre[2]) == 1,
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

// ---------------------------------------------------------------------------
// Piso da data de corte: entrega anterior à entrada em operação não vira débito.
// A suíte roda com DATA_CORTE_FINANCEIRO = 2010-01-01 (definida no topo), então
// o par abaixo cerca exatamente essa fronteira.
// ---------------------------------------------------------------------------
$cha_antes_corte = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1, '2013-12-31 23:59:59', '2013-12-01 10:00:00', '2013-12-20 10:00:00', 0.10, '2014-01-20 10:00:00')");
$cha_no_corte = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1, '2014-01-01 00:00:00', '2013-12-01 10:00:00', '2013-12-20 10:00:00', 0.10, '2014-01-20 10:00:00')");

// Cestante PRÓPRIO para o corte. Pendurar estas duas entregas no $usr_t poluiria
// os testes de extrato, que conferem contagem e posição exatas das linhas dele.
$usr_corte = insere("INSERT INTO usuarios (usr_nome_completo, usr_nome_curto, usr_email, usr_senha, usr_archive, usr_nuc)
    VALUES ('Teste Corte', 'corte', 'testecorte@dev.local', 'x', '0', 1)");

foreach (array($cha_antes_corte, $cha_no_corte) as $c_corte)
{
    insere("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade) VALUES ($c_corte, 1, 2)");
    $ped_corte = insere("INSERT INTO pedidos (ped_usr, ped_usr_associado, ped_nuc, ped_cha, ped_fechado)
        VALUES ($usr_corte, 1, 1, $c_corte, '1')");
    insere("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
        VALUES ($ped_corte, 1, 2, 2)");
}

$deb_corte = debitos_derivados($usr_corte);
$chas_corte = array();
foreach ((array)$deb_corte as $d) $chas_corte[$d['cha_id']] = true;

verifica("entrega ANTERIOR a data de corte nao vira debito",
    !isset($chas_corte[$cha_antes_corte]),
    "cha $cha_antes_corte (2013-12-31) apareceu, e o corte e 2014-01-01");

verifica("entrega NA data de corte vira debito",
    isset($chas_corte[$cha_no_corte]),
    "cha $cha_no_corte (2014-01-01) nao apareceu");

// O extrato TEM de repassar o congelavel: a tela marca "a confirmar" e esse aviso é
// sobre o valor PODER MUDAR, não sobre estar lançado. Sem o campo, toda linha
// derivada levava o aviso — 606 das 679 chamadas do cestante 101 já estão fechadas.
$ext_cong = extrato_do_cestante($usr_t);
$deriv_cong = array();
foreach ((array)$ext_cong as $l) if ($l['situacao'] === 'derivado') $deriv_cong[$l['cha']] = $l;

verifica("o extrato repassa congelavel nas linhas derivadas",
    isset($deriv_cong[$cha_t]) && array_key_exists('congelavel', $deriv_cong[$cha_t]),
    var_export(isset($deriv_cong[$cha_t]) ? $deriv_cong[$cha_t] : null, true));

verifica("chamada de prazo vencido chega ao extrato como congelavel",
    isset($deriv_cong[$cha_velha]) && $deriv_cong[$cha_velha]['congelavel'] === true,
    var_export(isset($deriv_cong[$cha_velha]) ? $deriv_cong[$cha_velha]['congelavel'] : null, true));

verifica("chamada de prazo no futuro chega ao extrato como NAO congelavel",
    isset($deriv_cong[$cha_t]) && $deriv_cong[$cha_t]['congelavel'] === false,
    var_export(isset($deriv_cong[$cha_t]) ? $deriv_cong[$cha_t]['congelavel'] : null, true));

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
// sessão logada. Papel de negócio não entra na conta — os primeiros casos abaixo deixam
// PAP_RESP_FINANCAS ligado justamente para provar que ele não fura a trava.
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

// E a MESMA pergunta feita à função que as telas realmente chamam. Não é repetição da
// asserção acima: pode_ver_conta_de() tem o portão do beta numa linha PRÓPRIA, e essa
// linha não tinha teste nenhum. Medido na revisão da branch: apagá-la sobrevivia às 126
// asserções, e movê-la para depois do atalho de PAP_ADM/PAP_RESP_FINANCAS também —
// porque TODAS as asserções daquela função rodavam com PAP_BETA_TESTER ligado, e nenhuma
// dizia "sem beta, nega".
//
// Com a linha apagada, um administrador SEM Beta Tester passa a alcançar a conta de
// qualquer cestante, e conta_cestante.php:27 é o ÚNICO portão daquela tela — o
// verifica_seguranca() de :13 não recebe parâmetro, de propósito. Seria o defeito que a
// trava do beta existe para fechar, reaberto com a suíte verde.
//
// O caso do ADM é o que pega a linha MOVIDA (o atalho responderia antes dela).
verifica("sem beta tester ninguem alcanca conta nenhuma, nem o administrador",
    pode_ver_conta_de($usr_t) === false);

$_SESSION[PAP_ADM] = false;
$_SESSION[PAP_RESP_FINANCAS] = false;

// E este é o que pega a linha APAGADA: sem papel nenhum, a sessão é do próprio $usr_t,
// então o ramo "o próprio" responderia true se o portão do beta não viesse antes.
verifica("sem beta tester nem o proprio cestante alcanca a propria conta",
    pode_ver_conta_de($usr_t) === false);

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

// saldo_da_conta() era a última da família a devolver 0.0 quando a consulta não roda —
// indistinguível de conta zerada. A alavanca é a sombra de TEMPORARY TABLE que o resto da
// suíte já usa, agora sobre `lancamentos` e sem a coluna lan_valor: o SUM é recusado
// (ERROR 1054) sem que mais nada mude.
//
// A asserção é `=== null` de propósito. Escrita como `!$saldo`, ela passaria também
// contra a versão defeituosa, porque 0.0 é falsy — é o mesmo cuidado que o teste de
// conta_do_cestante() já documenta.
$saldo_real = saldo_da_conta($con_t);

executa_sql("CREATE TEMPORARY TABLE lancamentos (
    lan_id  int(10) unsigned NOT NULL,
    lan_tra int(10) unsigned NOT NULL,
    lan_con mediumint(6) unsigned NOT NULL) ENGINE=InnoDB");

$sombra_lan_de_pe = (executa_sql("SELECT lan_valor FROM lancamentos") === false);
$saldo_sem_banco  = saldo_da_conta($con_t);

executa_sql("DROP TEMPORARY TABLE lancamentos");

// Guarda do fixture: sem ela o teste ficaria verde por não haver o que quebrar.
verifica("a sombra sem lan_valor faz o servidor recusar a soma do saldo",
    $sombra_lan_de_pe, var_export($sombra_lan_de_pe, true));

verifica("saldo de consulta recusada e null, e nao conta zerada",
    $saldo_sem_banco === null, var_export($saldo_sem_banco, true));

verifica("derrubada a sombra, o saldo volta a ser o mesmo float de antes",
    is_float($saldo_real) && saldo_da_conta($con_t) === $saldo_real,
    "antes = " . var_export($saldo_real, true)
        . " · depois = " . var_export(saldo_da_conta($con_t), true));


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

// Duas contas com o MESMO rótulo, que é o caso que a revisão da branch mediu: a conta
// principal da Rede já existe ($con_rede, criada por conta_da_rede() com con_chave), e
// cria_conta() aceita uma segunda 'Rede Ecológica' — con_nome não tem UNIQUE, e não deve
// ter, porque é rótulo e não identidade. Sem desempate, as duas viram <option> idênticos
// na tela que move dinheiro, e quem escolhe a errada credita uma conta que NÃO é a
// contraparte dos débitos de entrega.
//
// A asserção olha para os dois lados da regra: as repetidas ficam diferentes entre si e
// cada uma carrega o próprio con_id, e a NÃO repetida continua limpa. Sem a segunda
// metade, uma versão que carimbasse #con_id em toda linha passaria verde.
// O nome sai do banco, e não de um literal: um teste do bloco de identidade renomeia a
// conta da Rede à mão, de propósito, para provar que a busca é por con_chave. Literal
// aqui daria dois nomes DIFERENTES e o teste ficaria verde sem duplicata nenhuma.
$nome_da_rede = valor_escalar("SELECT con_nome FROM contas WHERE con_id = " . (int)$con_rede);
$con_rede_dup = cria_conta('rede', array('con_nome' => $nome_da_rede));
$dest_dup     = contas_de_destino();

verifica("rotulo repetido ganha desempate visivel nas DUAS contas, e o unico fica limpo",
    is_numeric($con_rede_dup) && $con_rede_dup != $con_rede && is_array($dest_dup)
    && $dest_dup[$con_rede] !== $dest_dup[$con_rede_dup]
    && strpos($dest_dup[$con_rede], '#' . $con_rede) !== false
    && strpos($dest_dup[$con_rede_dup], '#' . $con_rede_dup) !== false
    && strpos($dest_dup[$con_nuc_t], '#') === false,
    "nome no banco=" . json_encode($nome_da_rede)
        . " rede=" . json_encode(isset($dest_dup[$con_rede]) ? $dest_dup[$con_rede] : null)
        . " dup=" . json_encode(isset($dest_dup[$con_rede_dup]) ? $dest_dup[$con_rede_dup] : null)
        . " nucleo=" . json_encode(isset($dest_dup[$con_nuc_t]) ? $dest_dup[$con_nuc_t] : null));

// Some com a duplicata: o resto do bloco conta destinos e lançamentos, e uma conta a mais
// deixada para trás mudaria número alheio.
executa_sql("DELETE FROM contas WHERE con_id = " . (int)$con_rede_dup);

verifica("desfeita a duplicata, o rotulo da conta da Rede volta a ficar limpo",
    strpos(contas_de_destino()[$con_rede], '#') === false);

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

// ---------------------------------------------------------------------------
echo "\nedicao da descricao de um lancamento\n";
// ---------------------------------------------------------------------------

// lanca_transacao() carimba tra_usr_registro a partir da sessao, entao o bloco
// precisa de um usr.id — e precisa DEVOLVER a sessao como estava, senao um teste
// mais adiante ve usuario de fixture onde nao devia. Ja aconteceu.
$sessao_usr_antes = isset($_SESSION['usr.id']) ? $_SESSION['usr.id'] : null;
$_SESSION['usr.id'] = $usr_t;
$tra_ed = lanca_transacao('2026-03-01 10:00:00', 'pagamento', $con_nuc_t, $con_t, 33.00,
    'texto original', array('comprovante' => 'http://original.example/a.pdf'));

verifica("fixture da edicao foi lancado",
    is_numeric($tra_ed) && $tra_ed > 0, var_export($tra_ed, true));

$saldo_antes_ed = saldo_da_conta($con_t);

verifica("editar a descricao devolve true",
    edita_descricao_transacao($tra_ed, 'texto corrigido', 'http://novo.example/b.pdf') === true);

verifica("o texto e o comprovante mudaram",
    valor_escalar("SELECT tra_historico FROM transacoes WHERE tra_id = " . (int)$tra_ed) === 'texto corrigido'
    && valor_escalar("SELECT tra_comprovante FROM transacoes WHERE tra_id = " . (int)$tra_ed) === 'http://novo.example/b.pdf');

// O ponto da funcao: editar descricao NAO pode mexer em dinheiro.
verifica("o saldo NAO muda ao editar a descricao",
    abs(saldo_da_conta($con_t) - $saldo_antes_ed) < 0.001,
    "antes=$saldo_antes_ed depois=" . saldo_da_conta($con_t));

verifica("as duas pernas continuam somando zero",
    !in_array((int)$tra_ed, array_map('intval', transacoes_desbalanceadas()), true));

// O rastro e a razao de a funcao existir: sem ele a edicao ficaria invisivel, e a
// linha continuaria dizendo que foi registrada por quem a criou, na data original.
verifica("o rastro registra quem editou e quando",
    (int)valor_escalar("SELECT tra_usr_alteracao FROM transacoes WHERE tra_id = " . (int)$tra_ed) === (int)$usr_t
    && valor_escalar("SELECT tra_dt_alteracao FROM transacoes WHERE tra_id = " . (int)$tra_ed) !== null);

verifica("lancamento nao editado tem o rastro NULO",
    valor_escalar("SELECT tra_dt_alteracao FROM transacoes WHERE tra_id = " . (int)$tra_p) === null);

verifica("tra_id invalido nao grava nada",
    edita_descricao_transacao(0, 'x', 'y') === false
    && edita_descricao_transacao('abc', 'x', 'y') === false);

// UPDATE recusado pelo servidor: executa_sql devolve false e a funcao tem de dizer
// false. Sombreia transacoes com uma TEMPORARY sem as colunas.
executa_sql("CREATE TEMPORARY TABLE transacoes (tra_id int)");
$disse_ok = edita_descricao_transacao($tra_ed, 'nao devia gravar', 'x');
executa_sql("DROP TEMPORARY TABLE transacoes");

verifica("UPDATE recusado NAO e relatado como sucesso",
    $disse_ok === false,
    var_export($disse_ok, true));

verifica("e o texto continua o de antes",
    valor_escalar("SELECT tra_historico FROM transacoes WHERE tra_id = " . (int)$tra_ed) === 'texto corrigido');

// NAO HA TESTE para o outro retorno de executa_sql, e a razao vale registrar.
//
// executa_sql (common.inc.php:389) devolve o INTEIRO 0 quando nao ha conexao, e
// `0 !== false` e VERDADEIRO — foi por isso que a funcao passou a exigir `=== true`.
// Mas esse caminho e INALCANCAVEL aqui: edita_descricao_transacao() chama
// prep_para_bd() antes de executa_sql(), e prep_para_bd faz
// mysqli_real_escape_string($conn_link, ...), que no PHP 8 LANCA com conexao nula.
// Tentei a alavanca e o processo morre em common.inc.php:221, sem chegar ao UPDATE.
//
// Ou seja: `=== true` e a comparacao certa e continua, mas o fail-open que ela fecha
// nao era explotavel nesta funcao. Registrado assim em vez de fingir que ha guarda.

// cestante_da_transacao(): quem chama precisa dela para aplicar pode_ver_conta_de().
verifica("cestante_da_transacao acha o dono da conta",
    cestante_da_transacao($tra_ed) === (int)$usr_t,
    var_export(cestante_da_transacao($tra_ed), true));

verifica("transacao inexistente nao tem dono",
    cestante_da_transacao(99999999) === null);

if ($sessao_usr_antes === null) unset($_SESSION['usr.id']);
else                            $_SESSION['usr.id'] = $sessao_usr_antes;

// ---------------------------------------------------------------------------
echo "\nadministracao de contas\n";
// ---------------------------------------------------------------------------

// cria_contas_que_faltam() e idempotente por construcao: cria_conta() esbarra na
// UNIQUE de con_nuc/con_forn. A segunda passada tem de criar ZERO.
$criadas_1 = cria_contas_que_faltam();
$criadas_2 = cria_contas_que_faltam();

verifica("cria_contas_que_faltam cria na primeira passada",
    is_int($criadas_1) && $criadas_1 > 0, var_export($criadas_1, true));

verifica("e cria ZERO na segunda — idempotente",
    $criadas_2 === 0, var_export($criadas_2, true));

// Nucleo arquivado nao ganha conta: nao e destino valido, e a conta seria lixo.
verifica("nucleo arquivado nao ganha conta",
    (int)valor_escalar("SELECT COUNT(*) FROM contas c JOIN nucleos n ON n.nuc_id = c.con_nuc
                        WHERE c.con_tipo = 'nucleo' AND n.nuc_archive = 1") === 0);

verifica("produtor arquivado nao ganha conta",
    (int)valor_escalar("SELECT COUNT(*) FROM contas c JOIN fornecedores f ON f.forn_id = c.con_forn
                        WHERE c.con_tipo = 'produtor' AND f.forn_archive = 1") === 0);

// renomear muda o rotulo e SO o rotulo
$con_ren = cria_conta('rede', array('con_nome' => 'Rede Teste Renomear', 'con_chave' => 'rede_teste_ren'));
verifica("renomear devolve true e troca o rotulo",
    renomeia_conta($con_ren, 'Rede (conta Fulana)') === true
    && valor_escalar("SELECT con_nome FROM contas WHERE con_id = " . (int)$con_ren) === 'Rede (conta Fulana)');

verifica("renomear NAO mexe no tipo nem na chave",
    valor_escalar("SELECT con_tipo FROM contas WHERE con_id = " . (int)$con_ren) === 'rede'
    && valor_escalar("SELECT con_chave FROM contas WHERE con_id = " . (int)$con_ren) === 'rede_teste_ren');

// rotulo em branco vira <option> invisivel numa tela que move dinheiro
verifica("nome vazio e recusado",
    renomeia_conta($con_ren, '') === false && renomeia_conta($con_ren, '   ') === false);

verifica("con_id invalido e recusado",
    renomeia_conta(0, 'x') === false && arquiva_conta('abc', true) === false);

// arquivar tira dos destinos sem apagar
$antes_arq = count((array)contas_de_destino());
verifica("arquivar devolve true", arquiva_conta($con_ren, true) === true);

verifica("conta arquivada sai da lista de destinos",
    count((array)contas_de_destino()) === $antes_arq - 1,
    "antes=$antes_arq depois=" . count((array)contas_de_destino()));

verifica("mas a conta continua existindo",
    (int)valor_escalar("SELECT COUNT(*) FROM contas WHERE con_id = " . (int)$con_ren) === 1);

verifica("desarquivar traz de volta",
    arquiva_conta($con_ren, false) === true
    && count((array)contas_de_destino()) === $antes_arq);

// ---------------------------------------------------------------------------
echo "\ndestinos repartidos em grupos\n";
// ---------------------------------------------------------------------------

// GUARDA ESTRUTURAL, e nao teste discriminante: hoje contas_de_destino() ACHATA o
// que contas_de_destino_por_grupo() devolve, entao as duas sao iguais por construcao
// e esta assercao nao tem como falhar — conferido por mutacao, injetar conta fantasma
// no grupo faz a plana herda-la junto.
//
// Fica porque o dia em que alguem separar as duas em consultas independentes, ela
// passa a valer: e a plana que registra_pagamento() usa para validar o destino do
// POST, e divergencia entre elas seria a tela oferecendo o que a fronteira recusa.
$g_sem  = contas_de_destino_por_grupo();
$p_sem  = contas_de_destino();
$ids_g  = array();
foreach ((array)$g_sem as $grupo) foreach ($grupo['contas'] as $cid => $r) $ids_g[] = $cid;
$ids_p = array_keys((array)$p_sem);
sort($ids_g); sort($ids_p);

verifica("agrupada e plana tem exatamente as mesmas contas",
    $ids_g === $ids_p,
    "agrupada=" . count($ids_g) . " plana=" . count($ids_p));

verifica("nenhum grupo vem vazio",
    count(array_filter((array)$g_sem, function ($x) { return !count($x['contas']); })) === 0);

// Com nucleo em foco, o caixa DELE sai num grupo proprio, antes dos outros nucleos.
$g_foco = contas_de_destino_por_grupo(21);
$titulos = array();
foreach ((array)$g_foco as $grupo) $titulos[] = $grupo['titulo'];

verifica("com nucleo em foco aparece o grupo 'Nucleo deste painel'",
    in_array('Núcleo deste painel', $titulos, true),
    implode(' | ', $titulos));

verifica("sem nucleo em foco NAO aparece esse grupo",
    !in_array('Núcleo deste painel', array_map(function ($x) { return $x['titulo']; }, (array)$g_sem), true));

// A ordem dos grupos e a ordem em que a pessoa procura.
$pos_rede = array_search('Contas da Rede', $titulos, true);
$pos_foco = array_search('Núcleo deste painel', $titulos, true);
$pos_prod = array_search('Produtores', $titulos, true);
verifica("ordem: Rede, nucleo em foco, ... , produtores por ultimo",
    $pos_rede !== false && $pos_foco !== false && $pos_prod !== false
    && $pos_rede < $pos_foco && $pos_foco < $pos_prod,
    implode(' | ', $titulos));

// ---------------------------------------------------------------------------
echo "\ncomprovante como link\n";
// ---------------------------------------------------------------------------

verifica("http e https viram link",
    comprovante_como_link('http://exemplo.org/a.pdf') === 'http://exemplo.org/a.pdf'
    && comprovante_como_link('https://drive.google.com/file/d/abc/view') === 'https://drive.google.com/file/d/abc/view');

// O que este teste protege: virar href sem conferir o esquema aceitaria
// javascript:..., e um clique executaria script escolhido por quem lancou o
// pagamento. Lista de PERMISSAO, nao de bloqueio.
$hostis = array(
    'javascript:alert(1)',
    'JavaScript:alert(1)',
    'data:text/html;base64,PHNjcmlwdD4=',
    'vbscript:msgbox(1)',
    'file:///etc/passwd',
    '//exemplo.org/sem-esquema',
);
$virou_link = array();
foreach ($hostis as $h) if (comprovante_como_link($h) !== '') $virou_link[] = $h;

verifica("esquema que nao e http/https nunca vira link",
    count($virou_link) === 0,
    "viraram link: " . implode(' | ', $virou_link));

verifica("texto comum nao vira link",
    comprovante_como_link('recibo 4432 do caderno') === ''
    && comprovante_como_link('') === '' && comprovante_como_link(null) === '');

// Aspas e sinais de marcacao fora, para o valor nao escapar do atributo href.
verifica("url com aspas ou marcacao nao vira link",
    comprovante_como_link('https://x.org/a" onmouseover="alert(1)') === ''
    && comprovante_como_link('https://x.org/<script>') === '');

// ---------------------------------------------------------------------------
echo "\nultimo lancamento do extrato\n";
// ---------------------------------------------------------------------------

verifica("sem extrato devolve null",
    ultimo_lancamento(null) === null && ultimo_lancamento(array()) === null);

// Só linha gravada conta: débito derivado não é lançamento, e contá-lo faria a
// coluna responder "quando entregaram" em vez de "quando pagou".
$so_derivadas = array(
    array('situacao' => 'derivado', 'dt' => '2026-01-01 00:00:00', 'valor' => -10.0),
    array('situacao' => 'derivado', 'dt' => '2026-02-01 00:00:00', 'valor' => -20.0),
);
verifica("extrato so com linhas derivadas devolve null",
    ultimo_lancamento($so_derivadas) === null,
    var_export(ultimo_lancamento($so_derivadas), true));

// O extrato chega ordenado por data crescente, então o último gravado é o mais
// recente. Este teste cai se alguém trocar o foreach por "o primeiro que achar".
$mistura = array(
    array('situacao' => 'derivado', 'dt' => '2026-01-01 00:00:00', 'valor' => -10.0),
    array('situacao' => 'gravado',  'dt' => '2026-02-01 00:00:00', 'valor' =>  30.0),
    array('situacao' => 'derivado', 'dt' => '2026-03-01 00:00:00', 'valor' => -40.0),
    array('situacao' => 'gravado',  'dt' => '2026-04-01 00:00:00', 'valor' =>  50.0),
);
$u = ultimo_lancamento($mistura);
verifica("devolve o ultimo gravado, nao o primeiro nem o ultimo da lista",
    $u !== null && $u['dt'] === '2026-04-01 00:00:00' && $u['valor'] == 50.0,
    var_export($u, true));


// ---------------------------------------------------------------------------
echo "\nCaixa do nucleo: as quatro pernas (spec 4)\n";
// ---------------------------------------------------------------------------

// Le as pernas de uma transacao como con_id => valor. O invariante do modulo e que
// somem zero; cada teste abaixo confere ALEM disso QUAL conta ficou com QUAL sinal,
// porque somar zero e verdade tambem quando as duas pernas estao trocadas — e
// trocadas elas dizem o contrario do que aconteceu.
function pernas_de($tra_id)
{
    $res = executa_sql("SELECT lan_con, lan_valor FROM lancamentos WHERE lan_tra = " . (int)$tra_id);
    $p = array();
    if ($res) while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))
        $p[(int)$row['lan_con']] = round((float)$row['lan_valor'], 2);
    return $p;
}

// cria_contas_que_faltam(), exercitada acima, ja abriu conta para TODO nucleo ativo —
// inclusive os de fixture. Entao aqui se procura primeiro: cria_conta devolveria null
// pela UNIQUE de con_nuc, e $con_caixa vazio faria as pernas serem lidas pela chave "".
$con_caixa = conta_do_nucleo($nuc_livre[3]);
if (!$con_caixa)
    $con_caixa = cria_conta('nucleo', array('con_nuc' => $nuc_livre[3], 'con_nome' => 'Caixa Teste'));

verifica("o caixa de teste existe (guarda do fixture: sem ele nada abaixo pode falhar)",
    $con_caixa > 0, var_export($con_caixa, true));

verifica("conta_do_nucleo acha a conta do nucleo",
    conta_do_nucleo($nuc_livre[3]) == $con_caixa,
    "esperado $con_caixa, veio " . var_export(conta_do_nucleo($nuc_livre[3]), true));

verifica("conta_do_nucleo devolve null para nucleo sem conta",
    conta_do_nucleo(99999999) === null,
    var_export(conta_do_nucleo(99999999), true));

// DESPESA — saiu dinheiro do caixa para alguem que NAO tem conta no sistema: o
// motorista. A outra perna vai para a conta de contrapartida, e nao para a Rede: dizer
// que a Rede assumiu o custo era verdade no modelo antigo e deixou de ser quando a
// despesa virou custo do proprio nucleo, medido no resultado.
$con_contra = conta_de_contrapartida();
$tra_desp = lanca_movimento_nucleo($nuc_livre[3], 'despesa', '2026-08-01 10:00:00', 45.00,
    null, array('categoria' => 'passagens', 'historico' => 'passagem do motorista',
                'favorecido' => 'Seu Antunes'));
$p = pernas_de($tra_desp);
verifica("despesa: caixa +45 e contrapartida -45, sem tocar na Rede",
    $tra_desp && isset($p[$con_caixa]) && $p[$con_caixa] == 45.00
             && isset($p[$con_contra]) && $p[$con_contra] == -45.00
             && !isset($p[$con_rede]),
    "tra=" . var_export($tra_desp, true) . " pernas=" . json_encode($p));

verifica("e guarda QUEM recebeu, que nao tem conta e nao teria onde ficar",
    valor_escalar("SELECT tra_favorecido FROM transacoes WHERE tra_id = " . (int)$tra_desp) === 'Seu Antunes',
    var_export(valor_escalar("SELECT tra_favorecido FROM transacoes WHERE tra_id = " . (int)$tra_desp), true));

// A conta escolhida no POST e IGNORADA em despesa, nao recusada — a tela nao oferece o
// campo, mas um POST antigo ou forjado ainda pode trazer um. Recusar repetiria o erro
// do mv_categoria: campo que a tela nao mostra derrubando lancamento legitimo.
$tra_ign = lanca_movimento_nucleo($nuc_livre[3], 'despesa', '2026-08-01 11:00:00', 5.00,
    $con_forn_t, array('categoria' => 'outros'));
$p_ign = pernas_de($tra_ign);
verifica("conta mandada numa despesa e ignorada, e o lancamento acontece",
    $tra_ign && isset($p_ign[$con_contra]) && !isset($p_ign[$con_forn_t]),
    json_encode($p_ign));

verifica("despesa grava a categoria",
    valor_escalar("SELECT tra_categoria FROM transacoes WHERE tra_id = " . (int)$tra_desp) === 'passagens',
    var_export(valor_escalar("SELECT tra_categoria FROM transacoes WHERE tra_id = " . (int)$tra_desp), true));

// REPASSE — as MESMAS pernas da despesa, de proposito. O que separa os dois e o
// tra_tipo, e e por isso que a categoria existe: sem ela o relatorio nao conseguiria
// dizer se o dinheiro foi gasto ou entregue.
$tra_rep = lanca_movimento_nucleo($nuc_livre[3], 'repasse', '2026-08-02 10:00:00', 300.00,
    $con_rede, array('historico' => 'repasse da entrega de julho'));
$p = pernas_de($tra_rep);
verifica("repasse: nucleo +300 e rede -300, mesmas pernas da despesa",
    $tra_rep && $p[$con_caixa] == 300.00 && $p[$con_rede] == -300.00,
    "tra=" . var_export($tra_rep, true) . " pernas=" . json_encode($p));

verifica("repasse NAO tem categoria",
    valor_escalar("SELECT tra_categoria FROM transacoes WHERE tra_id = " . (int)$tra_rep) === null,
    var_export(valor_escalar("SELECT tra_categoria FROM transacoes WHERE tra_id = " . (int)$tra_rep), true));

// PAGAMENTO A PRODUTOR — o nucleo paga direto, encurtando a transferencia bancaria.
$tra_pp = lanca_movimento_nucleo($nuc_livre[3], 'pagamento_produtor', '2026-08-03 10:00:00', 120.00,
    $con_forn_t, array('historico' => 'pago direto na entrega'));
$p = pernas_de($tra_pp);
verifica("pagamento a produtor: nucleo +120 e produtor -120",
    $tra_pp && $p[$con_caixa] == 120.00 && $p[$con_forn_t] == -120.00,
    "tra=" . var_export($tra_pp, true) . " pernas=" . json_encode($p));

// OUTRA RECEITA — entrou dinheiro no caixa, de quem tambem nao tem conta: doacao,
// rendimento. Unica das quatro em que o caixa cresce, e por decisao do time ela e
// receita DO NUCLEO, entrando no equilibrio dele.
$tra_rec = lanca_movimento_nucleo($nuc_livre[3], 'receita', '2026-08-04 10:00:00', 60.00,
    null, array('historico' => 'doacao de cestante'));
$p = pernas_de($tra_rec);
verifica("outra receita: caixa -60 e contrapartida +60 (o caixa cresce)",
    $tra_rec && $p[$con_caixa] == -60.00 && $p[$con_contra] == 60.00
             && !isset($p[$con_rede]),
    "tra=" . var_export($tra_rec, true) . " pernas=" . json_encode($p));

verifica("as quatro somam zero, como toda transacao do modulo",
    count(transacoes_desbalanceadas()) === 0,
    json_encode(transacoes_desbalanceadas()));

// ---------------------------------------------------------------------------
echo "\nCaixa do nucleo: o que NAO pode virar lancamento\n";
// ---------------------------------------------------------------------------

verifica("tipo que nao existe e recusado",
    lanca_movimento_nucleo($nuc_livre[3], 'saque', '2026-08-05 10:00:00', 10.00, $con_rede) === null);

verifica("despesa SEM categoria e recusada",
    lanca_movimento_nucleo($nuc_livre[3], 'despesa', '2026-08-05 10:00:00', 10.00, $con_rede) === null);

verifica("despesa com categoria inventada e recusada",
    lanca_movimento_nucleo($nuc_livre[3], 'despesa', '2026-08-05 10:00:00', 10.00, $con_rede,
        array('categoria' => 'churrasco')) === null);

// O formulario manda mv_categoria em TODO lancamento — esconder o campo com
// display:none nao impede o envio. Recusar por isso rejeitava todo repasse feito pela
// tela. O que a guarda precisa garantir e que categoria nao seja GRAVADA fora de
// despesa; a assercao e essa, e nao a recusa.
// Num caixa DIFERENTE do que o bloco do extrato confere, senao esta linha entraria
// naquela contagem e o teste de "as quatro formas" passaria a medir cinco.
$tra_cat_ign = lanca_movimento_nucleo($nuc_livre[2], 'repasse', '2026-08-05 10:00:00', 10.00,
    $con_rede, array('categoria' => 'passagens'));

verifica("categoria fora de despesa e IGNORADA, e o lancamento acontece",
    $tra_cat_ign > 0, var_export($tra_cat_ign, true));

verifica("e nao fica gravada: tra_categoria segue NULL",
    valor_escalar("SELECT tra_categoria FROM transacoes WHERE tra_id = " . (int)$tra_cat_ign) === null,
    var_export(valor_escalar("SELECT tra_categoria FROM transacoes WHERE tra_id = " . (int)$tra_cat_ign), true));

// A contraparte e a fronteira: sem esta guarda um POST forjado lancaria despesa
// contra a conta de um cestante, tirando dele dinheiro que ele nao gastou.
verifica("contraparte que nao esta em contas_de_destino e recusada (conta de cestante)",
    lanca_movimento_nucleo($nuc_livre[3], 'repasse', '2026-08-05 10:00:00', 10.00, $con_t) === null);

verifica("repasse contra conta de PRODUTOR e recusado",
    lanca_movimento_nucleo($nuc_livre[3], 'repasse', '2026-08-05 10:00:00', 10.00, $con_forn_t) === null);

verifica("pagamento a produtor contra conta da REDE e recusado",
    lanca_movimento_nucleo($nuc_livre[3], 'pagamento_produtor', '2026-08-05 10:00:00', 10.00, $con_rede) === null);

verifica("contraparte igual a propria conta do nucleo e recusada",
    lanca_movimento_nucleo($nuc_livre[3], 'repasse', '2026-08-05 10:00:00', 10.00, $con_caixa) === null);

// pg_destino[] no POST entrega ARRAY onde se espera escalar; no PHP 8 isso e
// TypeError dentro do isset(), e a tela cairia inteira por causa de um nome de campo.
verifica("contraparte em ARRAY nao derruba a pagina, so recusa",
    lanca_movimento_nucleo($nuc_livre[3], 'repasse', '2026-08-05 10:00:00', 10.00, array(1)) === null);

verifica("valor zero e negativo sao recusados",
    lanca_movimento_nucleo($nuc_livre[3], 'repasse', '2026-08-05 10:00:00', 0, $con_rede) === null
    && lanca_movimento_nucleo($nuc_livre[3], 'repasse', '2026-08-05 10:00:00', -5, $con_rede) === null);

verifica("nucleo SEM conta e recusado",
    lanca_movimento_nucleo(99999999, 'repasse', '2026-08-05 10:00:00', 10.00, $con_rede) === null);

// Nenhuma das recusas acima pode ter deixado meia transacao gravada.
verifica("nenhuma recusa gravou transacao pela metade",
    count(transacoes_desbalanceadas()) === 0
    && valor_escalar("SELECT COUNT(DISTINCT lan_tra) FROM lancamentos WHERE lan_con = " . (int)$con_caixa) == 5,
    "desbalanceadas=" . json_encode(transacoes_desbalanceadas())
        . " no caixa=" . var_export(valor_escalar("SELECT COUNT(DISTINCT lan_tra) FROM lancamentos WHERE lan_con = " . (int)$con_caixa), true));

// ---------------------------------------------------------------------------
echo "\nCaixa do nucleo: categorias e extrato\n";
// ---------------------------------------------------------------------------

// ---------------------------------------------------------------------------
echo "\nCaixa do nucleo: o POST que o FORMULARIO manda, e nao so a forma da API\n";
// ---------------------------------------------------------------------------

// ESTE bloco existe por causa de um defeito que passou por 210 testes verdes.
//
// A tela esconde o campo Categoria quando o tipo nao e despesa — mas display:none NAO
// impede o envio, entao o navegador manda mv_categoria em TODO lancamento. Os testes
// acima chamam a funcao com os campos que aquele tipo usa, que e a forma da API, e
// nunca a forma que o formulario produz. Resultado: repasse pela tela era recusado
// sempre, e nenhuma assercao via.
//
// A regra que fica: quando existe uma TELA chamando, teste tambem a chamada QUE ELA FAZ.
$caixa_form = conta_do_nucleo($nuc_livre[1]);
if (!$caixa_form)
    $caixa_form = cria_conta('nucleo', array('con_nuc' => $nuc_livre[1], 'con_nome' => 'Caixa Formulario'));

verifica("o caixa deste bloco existe (guarda do fixture)", $caixa_form > 0, var_export($caixa_form, true));

foreach (array('despesa' => $con_rede, 'repasse' => $con_rede,
               'receita' => $con_rede, 'pagamento_produtor' => $con_forn_t) as $tipo_form => $contra)
{
    // exatamente o que a tela monta: categoria SEMPRE, historico e comprovante sempre
    $tra_form = lanca_movimento_nucleo($nuc_livre[1], $tipo_form, '2026-08-06 10:00:00', 25.00,
        $contra, array('categoria' => 'outros', 'historico' => 'como o formulario manda',
                       'comprovante' => ''));

    verifica("$tipo_form aceita o POST completo do formulario",
        $tra_form > 0, var_export($tra_form, true));

    // e a categoria so fica gravada onde ela significa alguma coisa
    $cat_form = valor_escalar("SELECT tra_categoria FROM transacoes WHERE tra_id = " . (int)$tra_form);
    verifica("$tipo_form grava categoria so quando e despesa",
        $tipo_form === 'despesa' ? ($cat_form === 'outros') : ($cat_form === null),
        var_export($cat_form, true));
}

// ---------------------------------------------------------------------------
echo "\ncontraparte: para onde o dinheiro foi\n";
// ---------------------------------------------------------------------------

// O extrato dizia que houve um pagamento e nao dizia para onde. A tela de edicao do
// cestante precisa mostrar isso porque a conta NAO se edita: quem corrige a descricao
// tem de poder conferir o destino antes de decidir se o caso e de ajuste.
$tra_dest = registra_pagamento($usr_t, '2026-08-07', 40.00, $con_nuc_t, '', '');
$ext_dest = extrato_do_cestante($usr_t);
$linha_dest = null;
foreach ((array)$ext_dest as $l) if ($l['tra_id'] === $tra_dest) $linha_dest = $l;

verifica("o extrato do cestante traz a conta de destino do pagamento",
    $linha_dest !== null && $linha_dest['contraparte'] === 'Núcleo ' . valor_escalar(
        "SELECT nuc_nome_curto FROM nucleos WHERE nuc_id = " . (int)$nuc_livre[2]),
    var_export($linha_dest ? $linha_dest['contraparte'] : null, true));

verifica("e o tipo, para a tela nao chamar de 'destino' o que destino nao e",
    $linha_dest !== null && $linha_dest['tipo'] === 'pagamento',
    var_export($linha_dest ? $linha_dest['tipo'] : null, true));

// Linha DERIVADA nao e lancamento e nao tem outro lado. A chave existe assim mesmo,
// para a tela nao ter de testar a situacao antes de ler — mesma regra de 'comprovante'.
$derivada = null;
foreach ((array)$ext_dest as $l) if ($l['situacao'] === 'derivado') { $derivada = $l; break; }
verifica("linha derivada tem a chave contraparte, vazia",
    $derivada !== null && array_key_exists('contraparte', $derivada) && $derivada['contraparte'] === '',
    $derivada === null ? 'sem linha derivada no fixture' : var_export($derivada['contraparte'], true));

// O prefixo importa: sem ele "Urca" (nucleo) se confunde com uma conta da Rede chamada
// "Urca", e as duas apareceriam identicas na tela de edicao.
verifica("o rotulo leva o prefixo do tipo, como na lista de destinos",
    rotulo_de_contraparte(array('contra_tipo' => 'nucleo',   'nuc_nome_curto'  => 'Urca')) === 'Núcleo Urca'
 && rotulo_de_contraparte(array('contra_tipo' => 'produtor', 'forn_nome_curto' => 'Bionatur')) === 'Produtor Bionatur'
 && rotulo_de_contraparte(array('contra_tipo' => 'cestante', 'usr_nome_curto'  => 'Bruss')) === 'Bruss'
 && rotulo_de_contraparte(array('contra_tipo' => 'rede',     'contra_nome'     => 'Rede Ecológica 1')) === 'Rede Ecológica 1');

// Coluna ausente e o caso normal, nao excecao: cada consulta traz so o que precisa, e
// ler chave inexistente sairia como warning no meio da tela.
verifica("coluna ausente nao vira warning nem texto inventado",
    rotulo_de_contraparte(array()) === ''
 && rotulo_de_contraparte(array('contra_tipo' => 'nucleo')) === 'Núcleo ');

$so_rede = contas_de_destino_do_tipo('rede');
$so_forn = contas_de_destino_do_tipo('produtor');
$todas_d = contas_de_destino();

verifica("contas_de_destino_do_tipo('rede') traz a conta da Rede e nenhum produtor",
    is_array($so_rede) && isset($so_rede[$con_rede]) && !isset($so_rede[$con_forn_t]),
    "rede=$con_rede forn=$con_forn_t · " . json_encode(array_keys((array)$so_rede)));

verifica("contas_de_destino_do_tipo('produtor') traz o produtor e nenhuma conta da Rede",
    is_array($so_forn) && isset($so_forn[$con_forn_t]) && !isset($so_forn[$con_rede]));

// Herda o recorte de contas_de_destino(), e nao um SELECT proprio: e essa heranca que
// mantem as regras de arquivamento e o desempate de rotulo valendo nas duas.
verifica("o filtro por tipo e SUBCONJUNTO da lista de destinos, nunca acrescenta",
    is_array($so_rede) && is_array($so_forn) && is_array($todas_d)
    && count(array_diff_key($so_rede, $todas_d)) === 0
    && count(array_diff_key($so_forn, $todas_d)) === 0);

verifica("os rotulos sao os MESMOS da lista completa",
    is_array($so_rede) && isset($so_rede[$con_rede]) && $so_rede[$con_rede] === $todas_d[$con_rede],
    var_export(isset($so_rede[$con_rede]) ? $so_rede[$con_rede] : null, true));

verifica("tipo que nao existe devolve lista vazia, e nao null",
    contas_de_destino_do_tipo('marciano') === array());

$cats = categorias_de_despesa();
verifica("as seis categorias da planilha estao la",
    is_array($cats) && count($cats) === 6
    && isset($cats['passagens'], $cats['expediente'], $cats['motorista'],
             $cats['entregas'], $cats['bancarias'], $cats['outros']),
    json_encode(array_keys((array)$cats)));

$ext = extrato_do_nucleo($nuc_livre[3]);
verifica("o extrato do nucleo traz os cinco lancamentos",
    is_array($ext) && count($ext) === 5,
    "veio " . var_export(is_array($ext) ? count($ext) : $ext, true));

// Saldo corrente somado linha a linha, como no extrato do cestante: 45 + 300 + 120 - 60.
verifica("o saldo corrente fecha em 410,00",
    is_array($ext) && count($ext) === 5 && round(end($ext)['saldo'], 2) == 410.00,
    is_array($ext) && $ext ? "saldo final = " . var_export(end($ext)['saldo'], true) : 'sem extrato');

verifica("o extrato vem em ordem cronologica",
    is_array($ext) && count($ext) === 5
    && $ext[0]['dt'] <= $ext[1]['dt'] && $ext[1]['dt'] <= $ext[2]['dt']
    && $ext[2]['dt'] <= $ext[3]['dt'] && $ext[3]['dt'] <= $ext[4]['dt'],
    is_array($ext) ? json_encode(array_map(function ($l) { return $l['dt']; }, $ext)) : 'sem extrato');

verifica("a linha de despesa carrega QUEM recebeu",
    is_array($ext) && $ext[0]['favorecido'] === 'Seu Antunes',
    is_array($ext) && isset($ext[0]) ? var_export($ext[0]['favorecido'], true) : 'sem extrato');

// Em repasse e pagamento a produtor o favorecido E a conta, e nao se grava duas vezes.
verifica("linha sem favorecido devolve string vazia, e nao null",
    is_array($ext) && $ext[2]['favorecido'] === '',
    is_array($ext) && isset($ext[2]) ? var_export($ext[2]['favorecido'], true) : '?');

verifica("a linha de despesa carrega a categoria legivel",
    is_array($ext) && $ext[0]['tipo'] === 'despesa' && $ext[0]['categoria'] === 'passagens',
    is_array($ext) && isset($ext[0]) ? json_encode($ext[0]) : 'sem extrato');

// CONTRATO: consulta que nao roda devolve null, nunca lista vazia — "nao deu para
// perguntar" lido como "o caixa esta vazio" e a mentira que este modulo existe para
// nao contar. Mesma sombra de TEMPORARY TABLE que o resto da suite usa.
executa_sql("CREATE TEMPORARY TABLE lancamentos (
    lan_id  int(10) unsigned NOT NULL,
    lan_tra int(10) unsigned NOT NULL,
    lan_con mediumint(6) unsigned NOT NULL) ENGINE=InnoDB");
$sombra_de_pe   = (executa_sql("SELECT lan_valor FROM lancamentos") === false);
$ext_sem_banco  = extrato_do_nucleo($nuc_livre[3]);
executa_sql("DROP TEMPORARY TABLE lancamentos");

verifica("a sombra sem lan_valor faz o servidor recusar o extrato do nucleo",
    $sombra_de_pe, var_export($sombra_de_pe, true));

verifica("extrato de consulta recusada e null, e nao caixa vazio",
    $ext_sem_banco === null, var_export($ext_sem_banco, true));

verifica("derrubada a sombra, o extrato volta com as cinco linhas",
    is_array(extrato_do_nucleo($nuc_livre[3])) && count(extrato_do_nucleo($nuc_livre[3])) === 5);

// ---------------------------------------------------------------------------
echo "\nnucleo em foco: a regra mora numa funcao so\n";
// ---------------------------------------------------------------------------

// A spec exige escopo por nucleo IMPOSTO, nao sugerido. Com duas telas de caixa a
// regra passaria a existir em duas copias, e e assim que uma delas fica para tras.
$papeis_guardados = array(
    PAP_RESP_FINANCAS => isset($_SESSION[PAP_RESP_FINANCAS]) ? $_SESSION[PAP_RESP_FINANCAS] : null,
    PAP_ADM           => isset($_SESSION[PAP_ADM])           ? $_SESSION[PAP_ADM]           : null,
    PAP_RESP_NUCLEO   => isset($_SESSION[PAP_RESP_NUCLEO])   ? $_SESSION[PAP_RESP_NUCLEO]   : null,
    PAP_BETA_TESTER   => isset($_SESSION[PAP_BETA_TESTER])   ? $_SESSION[PAP_BETA_TESTER]   : null,
);
$sessao_guardada = array(
    'usr.id'  => isset($_SESSION['usr.id'])  ? $_SESSION['usr.id']  : null,
    'usr.nuc' => isset($_SESSION['usr.nuc']) ? $_SESSION['usr.nuc'] : null,
);

function sessao_de_teste($papeis, $nuc)
{
    foreach (array(PAP_RESP_FINANCAS, PAP_ADM, PAP_RESP_NUCLEO, PAP_BETA_TESTER) as $p)
        unset($_SESSION[$p]);
    foreach ($papeis as $p) $_SESSION[$p] = 1;
    $_SESSION['usr.id']  = 1;
    $_SESSION['usr.nuc'] = $nuc;
}

// responsavel de nucleo: o pedido da URL nao entra na conta, nunca
sessao_de_teste(array(PAP_BETA_TESTER, PAP_RESP_NUCLEO), $nuc_livre[3]);

verifica("resp. de nucleo sem pedir nada cai no proprio nucleo",
    nucleo_do_caixa_em_foco("") === (int)$nuc_livre[3],
    var_export(nucleo_do_caixa_em_foco(""), true));

verifica("resp. de nucleo pedindo OUTRO nucleo continua no proprio",
    nucleo_do_caixa_em_foco((string)$nuc_livre[2]) === (int)$nuc_livre[3],
    "pediu $nuc_livre[2], veio " . var_export(nucleo_do_caixa_em_foco((string)$nuc_livre[2]), true));

verifica("pedido em ARRAY nao derruba nada, so e ignorado",
    nucleo_do_caixa_em_foco(array(9)) === (int)$nuc_livre[3]);

// financas: escolhe, e a escolha ainda passa por pode_lancar_no_caixa
sessao_de_teste(array(PAP_BETA_TESTER, PAP_RESP_FINANCAS), $nuc_livre[3]);

verifica("financas alcanca o nucleo que pedir",
    nucleo_do_caixa_em_foco((string)$nuc_livre[2]) === (int)$nuc_livre[2],
    var_export(nucleo_do_caixa_em_foco((string)$nuc_livre[2]), true));

verifica("financas sem pedir nada cai no nucleo da propria sessao",
    nucleo_do_caixa_em_foco("") === (int)$nuc_livre[3]);

// sem o papel Beta Tester o modulo inteiro esta fechado, e isto e o que garante que
// a extracao nao deixou a trava para tras
sessao_de_teste(array(PAP_RESP_FINANCAS), $nuc_livre[3]);
verifica("sem Beta Tester nao ha nucleo em foco",
    nucleo_do_caixa_em_foco((string)$nuc_livre[3]) === "",
    var_export(nucleo_do_caixa_em_foco((string)$nuc_livre[3]), true));

// cestante comum nao opera caixa de ninguem
sessao_de_teste(array(PAP_BETA_TESTER), $nuc_livre[3]);
verifica("sessao sem papel de negocio nao alcanca caixa nenhum",
    nucleo_do_caixa_em_foco("") === "");

// ---------------------------------------------------------------------------
echo "\nfluxo de caixa mensal\n";
// ---------------------------------------------------------------------------

sessao_de_teste(array(PAP_BETA_TESTER, PAP_RESP_FINANCAS), $nuc_livre[3]);

// Caixa proprio, para o fluxo nao somar os lancamentos dos outros blocos.
$nuc_fx = insere("INSERT INTO nucleos (nuc_nome_curto, nuc_nome_completo, nuc_archive)
    VALUES ('nucfluxo', 'Nucleo do fluxo', 0)");
$con_fx = cria_conta('nucleo', array('con_nuc' => $nuc_fx, 'con_nome' => 'Caixa Fluxo'));
$usr_fx = insere("INSERT INTO usuarios (usr_nome_completo, usr_nome_curto, usr_email, usr_senha, usr_archive, usr_nuc)
    VALUES ('Cestante Fluxo','fluxo','fluxo@teste.local','x','0'," . (int)$nuc_fx . ")");

verifica("o caixa do fluxo existe (guarda do fixture)", $con_fx > 0, var_export($con_fx, true));

// ANO ANTERIOR: entra 100 e nao sai nada. Vira saldo de abertura de 2026.
registra_pagamento($usr_fx, '2025-12-10', 100.00, $con_fx, '', '');

// 2026: janeiro entra 1.000; fevereiro gasta 45 de passagem, 500 de motorista,
// repassa 300 e recebe 60 de doacao.
registra_pagamento($usr_fx, '2026-01-15', 1000.00, $con_fx, '', '');
lanca_movimento_nucleo($nuc_fx, 'despesa', '2026-02-03', 45.00,  $con_rede, array('categoria' => 'passagens'));
lanca_movimento_nucleo($nuc_fx, 'despesa', '2026-02-10', 500.00, $con_rede, array('categoria' => 'motorista'));
lanca_movimento_nucleo($nuc_fx, 'repasse', '2026-02-20', 300.00, $con_rede);
lanca_movimento_nucleo($nuc_fx, 'receita', '2026-02-25', 60.00,  $con_rede);

$fx = fluxo_de_caixa_mensal($nuc_fx, 2026);

verifica("o fluxo devolve os doze meses",
    is_array($fx) && isset($fx['meses']) && count($fx['meses']) === 12,
    is_array($fx) ? json_encode(array_keys($fx)) : var_export($fx, true));

verifica("o saldo de abertura vem do que sobrou do ano anterior",
    is_array($fx) && round($fx['saldo_anterior'], 2) == 100.00,
    is_array($fx) ? var_export($fx['saldo_anterior'], true) : '?');

// ENTRADAS aparecem como valor POSITIVO no relatorio, embora a perna do caixa seja
// negativa: quem le uma prestacao de contas le "entrou 1.000", nao "-1.000".
verifica("janeiro: entrou 1.000 e nao saiu nada",
    is_array($fx) && round($fx['entradas'][1], 2) == 1000.00 && round($fx['saidas'][1], 2) == 0.00,
    is_array($fx) ? "entradas=" . $fx['entradas'][1] . " saidas=" . $fx['saidas'][1] : '?');

verifica("fevereiro: entrou 60 de doacao e sairam 845",
    is_array($fx) && round($fx['entradas'][2], 2) == 60.00
                  && round($fx['saidas'][2], 2) == 845.00,
    is_array($fx) ? "entradas=" . $fx['entradas'][2] . " saidas=" . $fx['saidas'][2] : '?');

function linha_do_fluxo($fx, $chave)
{
    foreach ((array)$fx['linhas'] as $l) if ($l['chave'] === $chave) return $l;
    return null;
}

verifica("cada categoria de despesa e uma linha propria",
    ($l = linha_do_fluxo($fx, 'despesa:passagens')) && round($l['meses'][2], 2) == 45.00
 && ($l = linha_do_fluxo($fx, 'despesa:motorista')) && round($l['meses'][2], 2) == 500.00
 && ($l = linha_do_fluxo($fx, 'despesa:outros'))    && round($l['meses'][2], 2) == 0.00);

// A escolha que voce confirmou: repasse NAO e custo do nucleo, e somado as despesas
// tornaria a linha de despesa incomparavel entre nucleos.
verifica("repasse fica FORA do bloco de despesas",
    ($l = linha_do_fluxo($fx, 'repasse')) && $l['bloco'] === 'repasses'
 && ($d = linha_do_fluxo($fx, 'despesa:motorista')) && $d['bloco'] === 'despesas');

verifica("o total de despesas do mes nao inclui o repasse",
    is_array($fx) && round($fx['total_despesas'][2], 2) == 545.00,
    is_array($fx) ? var_export($fx['total_despesas'][2], true) : '?');

verifica("saldo do mes = entrou - saiu",
    is_array($fx) && round($fx['saldo_mes'][1], 2) == 1000.00
                  && round($fx['saldo_mes'][2], 2) == -785.00,
    is_array($fx) ? "jan=" . $fx['saldo_mes'][1] . " fev=" . $fx['saldo_mes'][2] : '?');

// O acumulado tem de bater com o proprio caixa: e o mesmo dinheiro, contado de dois
// jeitos. Se divergirem, um dos dois esta mentindo e nao da para saber qual.
verifica("o acumulado de dezembro bate com o saldo da conta",
    is_array($fx) && round($fx['saldo_acumulado'][12], 2) == round(-saldo_da_conta($con_fx), 2),
    is_array($fx) ? "fluxo=" . $fx['saldo_acumulado'][12] . " conta=" . (-saldo_da_conta($con_fx)) : '?');

verifica("o acumulado de janeiro ja inclui a abertura",
    is_array($fx) && round($fx['saldo_acumulado'][1], 2) == 1100.00,
    is_array($fx) ? var_export($fx['saldo_acumulado'][1], true) : '?');

// CATEGORIA APOSENTADA. lanca_movimento_nucleo() recusa categoria fora da lista, mas a
// lista e do CODIGO e a categoria fica gravada no banco: no dia em que alguem renomear
// ou remover uma das seis, as despesas antigas passam a ter categoria que o codigo nao
// conhece. Elas nao podem sair do bloco de despesas, porque o valor delas ja esta no
// total de despesas — e detalhe que nao soma o proprio total e o defeito que faz um
// relatorio financeiro perder a confianca de quem o le.
$con_rede_fx = conta_da_rede();
lanca_transacao('2026-03-05', 'despesa', $con_rede_fx, $con_fx, 77.00, 'categoria aposentada',
    array('categoria' => 'combustivel'));

$fx2 = fluxo_de_caixa_mensal($nuc_fx, 2026);

$linha_velha = linha_do_fluxo($fx2, 'despesa:combustivel');
verifica("despesa de categoria aposentada vira linha propria",
    $linha_velha !== null && round($linha_velha['meses'][3], 2) == 77.00,
    var_export($linha_velha, true));

verifica("e fica no bloco de DESPESAS, nao em 'outros'",
    $linha_velha !== null && $linha_velha['bloco'] === 'despesas',
    $linha_velha ? var_export($linha_velha['bloco'], true) : '?');

// A verificacao que amarra tudo: o que a tela mostra no total tem de ser a soma do que
// ela mostra nas linhas. Sem isto o bloco fecha errado sem nada reclamar.
$soma_detalhe = 0.0;
foreach ((array)$fx2['linhas'] as $l)
    if ($l['bloco'] === 'despesas') $soma_detalhe = round($soma_detalhe + $l['meses'][3], 2);

verifica("as linhas de despesa somam exatamente o total de despesas do mes",
    round($soma_detalhe, 2) == round($fx2['total_despesas'][3], 2),
    "detalhe=$soma_detalhe total=" . $fx2['total_despesas'][3]);

// Ano sem movimento nao e erro: e um ano sem movimento.
$fx_vazio = fluxo_de_caixa_mensal($nuc_fx, 2019);
verifica("ano sem lancamento devolve doze meses zerados, e nao null",
    is_array($fx_vazio) && round($fx_vazio['entradas'][6], 2) == 0.00
                        && round($fx_vazio['saldo_anterior'], 2) == 0.00,
    var_export($fx_vazio === null ? null : $fx_vazio['saldo_anterior'], true));

verifica("nucleo sem caixa devolve null, e nao um relatorio zerado",
    fluxo_de_caixa_mensal(99999999, 2026) === null);

// CONTRATO da familia: consulta que nao roda devolve null. Num relatorio de dinheiro,
// "nao deu para perguntar" exibido como "nao houve movimento" e a mentira de sempre.
executa_sql("CREATE TEMPORARY TABLE lancamentos (
    lan_id  int(10) unsigned NOT NULL,
    lan_tra int(10) unsigned NOT NULL,
    lan_con mediumint(6) unsigned NOT NULL) ENGINE=InnoDB");
$sombra_fx = (executa_sql("SELECT lan_valor FROM lancamentos") === false);
$fx_sem_bd = fluxo_de_caixa_mensal($nuc_fx, 2026);
executa_sql("DROP TEMPORARY TABLE lancamentos");

verifica("a sombra sem lan_valor faz o servidor recusar o fluxo", $sombra_fx);
verifica("fluxo de consulta recusada e null, e nao ano sem movimento",
    $fx_sem_bd === null, var_export($fx_sem_bd, true));

// devolve a sessao ao estado em que estava, senao os testes seguintes herdam papeis
foreach ($papeis_guardados as $p => $v) { if ($v === null) unset($_SESSION[$p]); else $_SESSION[$p] = $v; }
foreach ($sessao_guardada as $k => $v)  { if ($v === null) unset($_SESSION[$k]); else $_SESSION[$k] = $v; }


// ---------------------------------------------------------------------------
echo "\nrateio: quotas\n";
// ---------------------------------------------------------------------------

// Nucleos de fixture com os tres tipos mais um sentinela, para as contas serem
// conferiveis sem depender de quantos nucleos a copia de producao tem hoje.
$tipo_id = array();
foreach (array('Semanal', 'Quinzenal', 'Mensal') as $nome_tipo)
    $tipo_id[$nome_tipo] = valor_escalar("SELECT nuct_id FROM nucleotipos WHERE nuct_nome = '$nome_tipo'");

verifica("os tres tipos de nucleo existem no fixture",
    $tipo_id['Semanal'] && $tipo_id['Quinzenal'] && $tipo_id['Mensal'],
    json_encode($tipo_id));

verifica("a quota padrao mora no TIPO, como dado e nao como lista em PHP",
    valor_escalar("SELECT nuct_quota_rateio FROM nucleotipos WHERE nuct_id = " . (int)$tipo_id['Semanal']) == 4.0
 && valor_escalar("SELECT nuct_quota_rateio FROM nucleotipos WHERE nuct_id = " . (int)$tipo_id['Quinzenal']) == 2.0
 && valor_escalar("SELECT nuct_quota_rateio FROM nucleotipos WHERE nuct_id = " . (int)$tipo_id['Mensal']) == 1.0);

$quotas_antes = quotas_de_rateio();
verifica("quotas_de_rateio devolve nucleo => quota",
    is_array($quotas_antes) && count($quotas_antes) > 0,
    var_export($quotas_antes === null ? null : count($quotas_antes), true));

// sentinela: existe como nucleo, nao entra no rateio
$nuc_sent = insere("INSERT INTO nucleos (nuc_nome_curto, nuc_nome_completo, nuc_archive, nuc_nuct, nuc_quota_rateio)
    VALUES ('nucsent','Sentinela de teste',0," . (int)$tipo_id['Semanal'] . ",0.0)");
$quotas = quotas_de_rateio();
verifica("nucleo com quota 0 fica FORA da lista, mesmo sendo semanal",
    is_array($quotas) && !isset($quotas[$nuc_sent]),
    json_encode(array_keys((array)$quotas)));

// excecao por nucleo: popular paga meia quota
$nuc_pop = insere("INSERT INTO nucleos (nuc_nome_curto, nuc_nome_completo, nuc_archive, nuc_nuct, nuc_quota_rateio)
    VALUES ('nucpop','Popular de teste',0," . (int)$tipo_id['Mensal'] . ",0.5)");
$quotas = quotas_de_rateio();
verifica("a excecao do nucleo manda sobre o padrao do tipo",
    is_array($quotas) && isset($quotas[$nuc_pop]) && (float)$quotas[$nuc_pop] == 0.5,
    var_export(isset($quotas[$nuc_pop]) ? $quotas[$nuc_pop] : null, true));

// nucleo arquivado nao rateia: nao ha entrega para ele
$nuc_arq = insere("INSERT INTO nucleos (nuc_nome_curto, nuc_nome_completo, nuc_archive, nuc_nuct)
    VALUES ('nucarq','Arquivado de teste',1," . (int)$tipo_id['Semanal'] . ")");
$quotas = quotas_de_rateio();
verifica("nucleo arquivado nao entra no rateio",
    is_array($quotas) && !isset($quotas[$nuc_arq]));

// ---------------------------------------------------------------------------
echo "\nrateio: as duas regras, e a sobra\n";
// ---------------------------------------------------------------------------

$quotas = quotas_de_rateio();
$n      = count($quotas);
$soma_q = array_sum($quotas);

// REGRA IGUAL: a quota nao entra na conta. E a regra dos custos fixos da Rede —
// hospedagem custa o mesmo tendo o nucleo uma ou quatro entregas por mes.
$sug_ig = sugere_rateio(1000.00, 'igual');
verifica("regra IGUAL divide pelo NUMERO de nucleos, nao pelas quotas",
    is_array($sug_ig) && count($sug_ig) === $n
    && abs(reset($sug_ig) - floor(1000.00 / $n * 100) / 100) < 0.005,
    "n=$n primeiro=" . var_export(reset($sug_ig), true));

verifica("na regra IGUAL todos recebem o mesmo valor",
    is_array($sug_ig) && count(array_unique(array_map('strval', $sug_ig))) === 1,
    json_encode(array_unique(array_map('strval', (array)$sug_ig))));

// REGRA POR QUOTA: proporcional as entregas. Semanal paga 4x o que o mensal paga.
$sug_q = sugere_rateio(2530.00, 'quota');
verifica("regra QUOTA e proporcional: quem tem 4 paga 4x quem tem 1",
    is_array($sug_q) && isset($quotas[$nuc_pop])
    && abs($sug_q[$nuc_pop] - floor(2530.00 * 0.5 / $soma_q * 100) / 100) < 0.005,
    "soma_quotas=$soma_q popular=" . var_export(isset($sug_q[$nuc_pop]) ? $sug_q[$nuc_pop] : null, true));

// A SOBRA FICA COM A REDE. Truncar em vez de arredondar garante isso sempre: a soma
// atribuida nunca passa do total, entao nenhum nucleo e cobrado por centavo que a
// divisao nao produziu. Arredondando, a soma poderia ULTRAPASSAR o gasto real.
verifica("a soma atribuida nunca passa do valor da despesa",
    is_array($sug_ig) && array_sum($sug_ig) <= 1000.00 + 0.0001
 && is_array($sug_q)  && array_sum($sug_q)  <= 2530.00 + 0.0001,
    "igual=" . array_sum((array)$sug_ig) . " quota=" . array_sum((array)$sug_q));

verifica("e a sobra e de centavos, nao de reais",
    is_array($sug_q) && (2530.00 - array_sum($sug_q)) < 1.00,
    "sobra=" . (2530.00 - array_sum((array)$sug_q)));

verifica("regra que nao existe devolve null, e nao um rateio inventado",
    sugere_rateio(100.00, 'por_simpatia') === null);

verifica("valor zero ou negativo nao gera rateio",
    sugere_rateio(0, 'igual') === null && sugere_rateio(-5, 'quota') === null);

// ---------------------------------------------------------------------------
echo "\ndespesa da Rede: lancamento mais atribuicao\n";
// ---------------------------------------------------------------------------

$cats_rede = categorias_de_despesa_da_rede();
verifica("as seis areas da Rede estao la",
    is_array($cats_rede) && count($cats_rede) === 6
    && isset($cats_rede['mutirao'], $cats_rede['logistica'], $cats_rede['pedidos'],
             $cats_rede['financas'], $cats_rede['sistemas'], $cats_rede['admin']),
    json_encode(array_keys((array)$cats_rede)));

$con_origem = cria_conta('rede', array('con_nome' => 'Rede Origem Teste', 'con_chave' => 'rede_origem_teste'));
if (!$con_origem) $con_origem = valor_escalar("SELECT con_id FROM contas WHERE con_chave = 'rede_origem_teste'");
$con_rede_pr = conta_da_rede();

$rateio_confirmado = sugere_rateio(302.68, 'igual');
$tra_rede = lanca_despesa_da_rede('2026-04-05', 'sistemas', 302.68, $con_origem,
    'Hospedagem, dominio e resp. sistemas', $rateio_confirmado);

verifica("a despesa da Rede vira transacao",
    $tra_rede > 0, var_export($tra_rede, true));

verifica("com as duas pernas: a conta de origem entrega o dinheiro, a Rede assume o custo",
    ($p = pernas_de($tra_rede)) && round($p[$con_origem], 2) == 302.68
                                && round($p[$con_rede_pr], 2) == -302.68,
    json_encode(pernas_de($tra_rede)));

verifica("e guarda a categoria da area",
    valor_escalar("SELECT tra_categoria FROM transacoes WHERE tra_id = " . (int)$tra_rede) === 'sistemas');

verifica("a atribuicao fica em `rateios`, uma linha por nucleo",
    valor_escalar("SELECT COUNT(*) FROM rateios WHERE rat_tra = " . (int)$tra_rede) == count($rateio_confirmado),
    var_export(valor_escalar("SELECT COUNT(*) FROM rateios WHERE rat_tra = " . (int)$tra_rede), true));

// O PONTO DA DECISAO: rateio e ATRIBUICAO, nao divida. Se ele virasse lancamento, o
// saldo do nucleo mudaria — e o caixa deixaria de significar "quanto tenho em caixa",
// que foi o pedido que abriu esta conversa.
$saldo_nuc_antes = saldo_da_conta($con_caixa);
lanca_despesa_da_rede('2026-04-06', 'admin', 60.00, $con_origem, 'Despesas bancarias',
    sugere_rateio(60.00, 'igual'));

verifica("rateio NAO mexe no saldo de caixa de nucleo nenhum",
    saldo_da_conta($con_caixa) == $saldo_nuc_antes,
    "antes=$saldo_nuc_antes depois=" . saldo_da_conta($con_caixa));

verifica("e nao quebra o invariante: toda transacao continua somando zero",
    count(transacoes_desbalanceadas()) === 0,
    json_encode(transacoes_desbalanceadas()));

// ---------------------------------------------------------------------------
echo "\ndespesa da Rede: o que e recusado\n";
// ---------------------------------------------------------------------------

verifica("categoria fora das seis areas e recusada",
    lanca_despesa_da_rede('2026-04-07', 'churrasco', 10.00, $con_origem, 'x',
        sugere_rateio(10.00, 'igual')) === null);

verifica("conta de origem que nao e da Rede e recusada",
    lanca_despesa_da_rede('2026-04-07', 'admin', 10.00, $con_forn_t, 'x',
        sugere_rateio(10.00, 'igual')) === null);

// Quem lanca pode AJUSTAR a sugestao — foi decisao explicita que o rateio e sempre
// confirmado a mao. Mas ajustar para mais do que a Rede gastou criaria custo do nada.
$ajustado_demais = sugere_rateio(100.00, 'igual');
$primeiro = array_keys($ajustado_demais);
$ajustado_demais[$primeiro[0]] = 100000.00;
verifica("atribuir MAIS do que a despesa custou e recusado",
    lanca_despesa_da_rede('2026-04-07', 'admin', 100.00, $con_origem, 'x', $ajustado_demais) === null);

// Atribuir MENOS e legitimo: a sobra fica com a Rede, por decisao registrada.
$ajustado_a_menos = array($nuc_pop => 10.00);
$tra_menos = lanca_despesa_da_rede('2026-04-08', 'admin', 100.00, $con_origem, 'so um nucleo', $ajustado_a_menos);
verifica("atribuir MENOS e aceito: a sobra fica com a Rede",
    $tra_menos > 0 && valor_escalar("SELECT COUNT(*) FROM rateios WHERE rat_tra = " . (int)$tra_menos) == 1,
    var_export($tra_menos, true));

verifica("nucleo que nao rateia nao pode receber atribuicao",
    lanca_despesa_da_rede('2026-04-09', 'admin', 10.00, $con_origem, 'x',
        array($nuc_sent => 10.00)) === null);

verifica("valor negativo em atribuicao e recusado",
    lanca_despesa_da_rede('2026-04-09', 'admin', 10.00, $con_origem, 'x',
        array($nuc_pop => -10.00)) === null);

// ---------------------------------------------------------------------------
echo "\nrateio: o que o nucleo ve\n";
// ---------------------------------------------------------------------------

$vistos = rateios_do_nucleo($nuc_pop, '2026-04-01', '2026-05-01');
verifica("o nucleo ve os rateios do periodo, com de onde vieram",
    is_array($vistos) && count($vistos) >= 2
    && isset($vistos[0]['historico'], $vistos[0]['categoria'], $vistos[0]['valor'], $vistos[0]['dt']),
    is_array($vistos) ? json_encode($vistos[0]) : var_export($vistos, true));

verifica("fora do periodo nao aparece",
    is_array($f = rateios_do_nucleo($nuc_pop, '2027-01-01', '2027-02-01')) && count($f) === 0,
    var_export($f, true));

// CONTRATO da familia: consulta que nao roda devolve null, nao "nenhum rateio".
executa_sql("CREATE TEMPORARY TABLE rateios (
    rat_tra int(10) unsigned NOT NULL, rat_nuc mediumint(6) unsigned NOT NULL) ENGINE=InnoDB");
$sombra_rat = (executa_sql("SELECT rat_valor FROM rateios") === false);
$rat_sem_bd = rateios_do_nucleo($nuc_pop, '2026-04-01', '2026-05-01');
executa_sql("DROP TEMPORARY TABLE rateios");

verifica("a sombra sem rat_valor faz o servidor recusar a consulta", $sombra_rat);
verifica("rateio de consulta recusada e null, e nao lista vazia",
    $rat_sem_bd === null, var_export($rat_sem_bd, true));


// ---------------------------------------------------------------------------
echo "\nresultado do nucleo: o ponto de equilibrio\n";
// ---------------------------------------------------------------------------

// Nucleo, cestantes e chamadas proprios: a conta so e conferivel se nada da copia de
// producao entrar nela.
$nuc_res = insere("INSERT INTO nucleos (nuc_nome_curto, nuc_nome_completo, nuc_archive, nuc_nuct)
    VALUES ('nucresult','Nucleo do resultado',0," . (int)$tipo_id['Mensal'] . ")");
$con_res = cria_conta('nucleo', array('con_nuc' => $nuc_res, 'con_nome' => 'Caixa Resultado'));

$usr_assoc = insere("INSERT INTO usuarios (usr_nome_completo,usr_nome_curto,usr_email,usr_senha,usr_archive,usr_nuc)
    VALUES ('Associado','assoc','assoc@teste.local','x','0'," . (int)$nuc_res . ")");
$usr_nao   = insere("INSERT INTO usuarios (usr_nome_completo,usr_nome_curto,usr_email,usr_senha,usr_archive,usr_nuc)
    VALUES ('Nao associado','naoassoc','naoassoc@teste.local','x','0'," . (int)$nuc_res . ")");

$forn_res  = insere("INSERT INTO fornecedores (forn_prodt, forn_nome_curto, forn_nome_completo, forn_archive)
    VALUES (1,'fornres','Fornecedor do resultado',0)");

// produto com compra 10, venda 10 (sem margem para associado) e venda_margem 13
$prod_res = insere("INSERT INTO produtos (prod_id, prod_prodt, prod_forn, prod_nome, prod_unidade,
    prod_valor_compra, prod_valor_venda, prod_valor_venda_margem, prod_ini_validade, prod_fim_validade,
    prod_multiplo_venda, prod_retornavel)
    VALUES (900001,1," . (int)$forn_res . ",'Produto resultado','kg',10.00,10.00,13.00,
    '2020-01-01 00:00:00','2030-01-01 00:00:00',1,0)");
$prod_res_id = 900001;

// produto de ASSOCIACAO: 60 de venda, 0 de compra (a Rede fica com tudo)
$prodt_assoc = valor_escalar("SELECT prodt_id FROM produtotipos WHERE prodt_nome LIKE 'Associa%'");
$prod_ass = insere("INSERT INTO produtos (prod_id, prod_prodt, prod_forn, prod_nome, prod_unidade,
    prod_valor_compra, prod_valor_venda, prod_valor_venda_margem, prod_ini_validade, prod_fim_validade,
    prod_multiplo_venda, prod_retornavel)
    VALUES (900002," . (int)$prodt_assoc . "," . (int)$forn_res . ",'Anuidade','un',0.00,60.00,60.00,
    '2020-01-01 00:00:00','2030-01-01 00:00:00',1,0)");
$prod_ass_id = 900002;

verifica("o fixture do resultado esta de pe",
    $con_res > 0 && $prodt_assoc > 0, "conta=$con_res prodt_assoc=" . var_export($prodt_assoc, true));

// chamada de PRODUTOS em maio/2026, com taxa de 5%
$cha_prod = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual)
    VALUES (1,'2026-05-10 23:59:59','2026-05-01 00:00:00','2026-05-05 23:59:59',0.05)");
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade)
    VALUES (" . (int)$cha_prod . "," . (int)$prod_res_id . ",1)");

// chamada de ASSOCIACAO no mesmo mes, sem taxa
$cha_ass = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual)
    VALUES (" . (int)$prodt_assoc . ",'2026-05-12 23:59:59','2026-05-01 00:00:00','2026-05-08 23:59:59',0.00)");
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade)
    VALUES (" . (int)$cha_ass . "," . (int)$prod_ass_id . ",1)");

// ASSOCIADO leva 10 unidades: paga 10 x 10 = 100, mais 5% = 5 de taxa
$ped_a = insere("INSERT INTO pedidos (ped_cha, ped_usr, ped_nuc, ped_fechado, ped_usr_associado)
    VALUES (" . (int)$cha_prod . "," . (int)$usr_assoc . "," . (int)$nuc_res . ",1,'1')");
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_a . "," . (int)$prod_res_id . ",10,10)");

// NAO ASSOCIADO leva 10: paga 10 x 13 = 130; margem = (13 - 10) x 10 = 30
$ped_n = insere("INSERT INTO pedidos (ped_cha, ped_usr, ped_nuc, ped_fechado, ped_usr_associado)
    VALUES (" . (int)$cha_prod . "," . (int)$usr_nao . "," . (int)$nuc_res . ",1,'0')");
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_n . "," . (int)$prod_res_id . ",10,10)");

// ANUIDADE do associado: 60
$ped_ass = insere("INSERT INTO pedidos (ped_cha, ped_usr, ped_nuc, ped_fechado, ped_usr_associado)
    VALUES (" . (int)$cha_ass . "," . (int)$usr_assoc . "," . (int)$nuc_res . ",1,'1')");
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_ass . "," . (int)$prod_ass_id . ",1,1)");

// DESPESA PROPRIA do nucleo: motorista, 40
lanca_movimento_nucleo($nuc_res, 'despesa', '2026-05-15', 40.00, $con_rede, array('categoria' => 'motorista'));

// OUTRA RECEITA do proprio nucleo: doacao de 25. Por decisao do time, ela conta para o
// equilibrio do nucleo — quem consegue doacao esta de fato em melhor situacao.
lanca_movimento_nucleo($nuc_res, 'receita', '2026-05-20', 25.00, null,
    array('historico' => 'doacao de cestante'));

// RATEIO: 12 carimbados neste nucleo
lanca_despesa_da_rede('2026-05-02', 'sistemas', 300.00, $con_origem, 'Sistemas de maio',
    array($nuc_res => 12.00));

$r = resultado_do_nucleo($nuc_res, 2026, 5);

verifica("a anuidade entra na receita do nucleo",
    is_array($r) && round($r['receita']['associacao'], 2) == 60.00,
    is_array($r) ? var_export($r['receita'], true) : var_export($r, true));

verifica("a taxa de 5% do associado entra, e e sobre o valor entregue",
    is_array($r) && round($r['receita']['taxa'], 2) == 5.00,
    is_array($r) ? var_export($r['receita']['taxa'], true) : '?');

verifica("a margem do nao associado sai de prod_valor_venda_margem",
    is_array($r) && round($r['receita']['margem_nao_associado'], 2) == 30.00,
    is_array($r) ? var_export($r['receita']['margem_nao_associado'], true) : '?');

verifica("o associado nao gera margem de produto quando venda = compra",
    is_array($r) && round($r['receita']['margem_produto'], 2) == 0.00,
    is_array($r) ? var_export($r['receita']['margem_produto'], true) : '?');

verifica("a doacao entra como receita propria do nucleo",
    is_array($r) && round($r['receita']['outras'], 2) == 25.00,
    is_array($r) ? var_export($r['receita'], true) : '?');

verifica("receita total = 60 + 5 + 30 + 25",
    is_array($r) && round($r['receita']['total'], 2) == 120.00,
    is_array($r) ? var_export($r['receita']['total'], true) : '?');

// A assercao que amarra as duas pontas: o total tem de ser a soma das PARTES, e nao um
// numero calculado a parte. Sem ela, uma linha nova na receita — foi o caso de 'outras'
// — entra no total sem entrar na conta que a tela mostra.
$soma_partes = 0.0;
foreach ($r['receita'] as $k => $v) if ($k !== 'total') $soma_partes = round($soma_partes + $v, 2);

verifica("o total da receita e exatamente a soma das linhas que a compoem",
    round($soma_partes, 2) == round($r['receita']['total'], 2),
    "partes=$soma_partes total=" . $r['receita']['total']);

verifica("a despesa propria do nucleo entra no custo, por categoria",
    is_array($r) && round($r['custo']['proprias']['motorista'], 2) == 40.00
                 && round($r['custo']['total_proprias'], 2) == 40.00,
    is_array($r) ? json_encode($r['custo']['proprias']) : '?');

verifica("o rateio entra no custo, e vem aberto por despesa da Rede",
    is_array($r) && round($r['custo']['total_rateio'], 2) == 12.00
                 && count($r['custo']['rateio']) === 1
                 && $r['custo']['rateio'][0]['categoria'] === 'sistemas',
    is_array($r) ? json_encode($r['custo']['rateio']) : '?');

verifica("resultado = receita - custo = 120 - 52",
    is_array($r) && round($r['resultado'], 2) == 68.00,
    is_array($r) ? var_export($r['resultado'], true) : '?');

// O sinal e o que a tela vai dizer em palavras, entao tem de ser inequivoco.
verifica("resultado positivo se diz superavitario",
    is_array($r) && $r['situacao'] === 'superavitario', is_array($r) ? $r['situacao'] : '?');

// Mesmo nucleo, mes SEM nada: zero em tudo, e nao null.
$r0 = resultado_do_nucleo($nuc_res, 2026, 7);
verifica("mes sem movimento devolve zeros, e nao null",
    is_array($r0) && round($r0['receita']['total'], 2) == 0.00
                  && round($r0['resultado'], 2) == 0.00
                  && $r0['situacao'] === 'equilibrio',
    var_export($r0 === null ? null : $r0['situacao'], true));

// Mes so com custo e deficitario — o caso que o time disse ser comum e OK. Junho tem
// so a despesa, e nenhuma entrega: e o retrato do nucleo que nao se paga.
lanca_movimento_nucleo($nuc_res, 'despesa', '2026-06-10', 80.00, null,
    array('categoria' => 'motorista', 'favorecido' => 'motorista de junho'));

$r_def = resultado_do_nucleo($nuc_res, 2026, 6);
verifica("mes com custo e sem receita se diz deficitario",
    is_array($r_def) && round($r_def['resultado'], 2) == -80.00
    && $r_def['situacao'] === 'deficitario',
    is_array($r_def) ? $r_def['situacao'] . ' ' . $r_def['resultado'] : '?');

verifica("nucleo que nao existe devolve null",
    resultado_do_nucleo(99999999, 2026, 5) === null);

verifica("mes fora de 1..12 devolve null",
    resultado_do_nucleo($nuc_res, 2026, 13) === null
 && resultado_do_nucleo($nuc_res, 2026, 0) === null);

// CONTRATO da familia. Aqui a sombra vai sobre `rateios`, que e a perna nova.
executa_sql("CREATE TEMPORARY TABLE rateios (
    rat_tra int(10) unsigned NOT NULL, rat_nuc mediumint(6) unsigned NOT NULL) ENGINE=InnoDB");
$sombra_res = (executa_sql("SELECT rat_valor FROM rateios") === false);
$r_sem_bd = resultado_do_nucleo($nuc_res, 2026, 5);
executa_sql("DROP TEMPORARY TABLE rateios");

verifica("a sombra sem rat_valor faz o servidor recusar o resultado", $sombra_res);
verifica("resultado de consulta recusada e null, e nao um nucleo em equilibrio",
    $r_sem_bd === null, var_export($r_sem_bd, true));


// ---------------------------------------------------------------------------
echo "\ndespesas da Rede: lista e reajuste do rateio\n";
// ---------------------------------------------------------------------------

$lista = despesas_da_rede('2026-04-01', '2026-05-01');
verifica("a lista traz as despesas da Rede do periodo",
    is_array($lista) && count($lista) >= 3,
    is_array($lista) ? count($lista) : var_export($lista, true));

// A sobra aparece de proposito: rateio incompleto viraria custo que ninguem ve, e
// resultado de nucleo bom demais.
$so_um = null;
foreach ((array)$lista as $l) if ($l['historico'] === 'so um nucleo') $so_um = $l;
verifica("a lista mostra quanto sobrou para a Rede em cada despesa",
    $so_um !== null && round($so_um['valor'], 2) == 100.00
                    && round($so_um['rateado'], 2) == 10.00
                    && round($so_um['sobra'], 2) == 90.00,
    var_export($so_um, true));

// A REGRA DE RATEIO NAO FICA GRAVADA — so o resultado dela. Como sugere_rateio() e
// deterministica, a regra se descobre perguntando a ela o que cada uma daria.
verifica("a regra se descobre a partir do rateio gravado",
    regra_do_rateio(300.00, sugere_rateio(300.00, 'igual')) === 'igual'
 && regra_do_rateio(300.00, sugere_rateio(300.00, 'quota')) === 'quota',
    "igual->" . regra_do_rateio(300.00, sugere_rateio(300.00, 'igual'))
    . " quota->" . regra_do_rateio(300.00, sugere_rateio(300.00, 'quota')));

// '' QUANDO NAO DA PARA SABER, e isso nao e falha: rateio ajustado a mao perdeu a regra,
// e chutar uma faria a tela afirmar uma escolha que ninguem fez.
$mao = sugere_rateio(300.00, 'igual');
$um_nuc = array_keys($mao)[0];
$mao[$um_nuc] = round($mao[$um_nuc] + 7.00, 2);
verifica("rateio ajustado a mao nao finge ter regra",
    regra_do_rateio(300.00, $mao) === '', var_export(regra_do_rateio(300.00, $mao), true));

verifica("rateio vazio ou ausente tambem devolve vazio",
    regra_do_rateio(300.00, array()) === ''
 && regra_do_rateio(300.00, null) === '');

// Atribuicao so para UM nucleo nao veio de sugestao nenhuma: as duas espalham por todos.
verifica("rateio parcial nao e confundido com sugestao",
    regra_do_rateio(300.00, array($um_nuc => 300.00)) === '');

// A ORDEM: por AREA, na sequencia que a caixa de selecao oferece, e depois por
// descricao. Por data as mesmas linhas apareciam embaralhadas todo mes, e a lista e
// conferida contra a planilha da Rede, que agrupa por area.
$ordem_cats = array_keys(categorias_de_despesa_da_rede());
$pos = array();
foreach ((array)$lista as $k => $l) $pos[] = array_search($l['categoria'], $ordem_cats, true);

$em_ordem = true;
for ($k = 1; $k < count($pos); $k++) if ($pos[$k] < $pos[$k-1]) $em_ordem = false;

verifica("a lista vem agrupada por area, na ordem da caixa de selecao",
    $em_ordem, json_encode(array_map(function ($l) { return $l['categoria']; }, (array)$lista)));

// e dentro da area, pela descricao
$dentro_ok = true;
for ($k = 1; $k < count($lista); $k++)
    if ($lista[$k]['categoria'] === $lista[$k-1]['categoria']
        && strcasecmp($lista[$k]['historico'], $lista[$k-1]['historico']) < 0) $dentro_ok = false;

verifica("e dentro de cada area, pela descricao",
    $dentro_ok, json_encode(array_map(function ($l) {
        return $l['categoria'] . '/' . $l['historico']; }, (array)$lista)));

// DE ONDE O DINHEIRO SAIU, por despesa. E o que "repetir o mes anterior" usa para
// pre-preencher a conta de cada linha: a mesma despesa costuma sair sempre da mesma
// conta, e reescolher doze vezes e convite a errar numa.
$com_origem = null;
foreach ((array)$lista as $l) if ($l['historico'] === 'so um nucleo') $com_origem = $l;
verifica("a lista diz de qual conta cada despesa saiu",
    $com_origem !== null && (int)$com_origem['origem'] === (int)$con_origem,
    "origem=" . var_export($com_origem === null ? null : $com_origem['origem'], true)
    . " esperado " . (int)$con_origem);

verifica("fora do periodo nao entra",
    is_array($v = despesas_da_rede('2027-01-01', '2027-02-01')) && count($v) === 0);

// TRES PERNAS. lanca_transacao so escreve duas, e e o unico a escrever em `lancamentos`
// — mas o schema aceita tres, e tres somando zero podem ter DUAS negativas. Se a leitura
// do valor fosse pelo SINAL, a juncao duplicaria a linha e o valor sairia dobrado.
//
// Isso nao seria detalhe: este valor e o TETO do rateio, e lido para mais deixaria
// carimbar nos nucleos mais do que a Rede gastou. Por isso a leitura e pela CONTA.
$tra_3 = insere("INSERT INTO transacoes (tra_dt, tra_tipo, tra_historico, tra_categoria, tra_usr_registro)
    VALUES ('2026-04-20 00:00:00','despesa_rede','tres pernas','admin',0)");
insere("INSERT INTO lancamentos (lan_tra, lan_con, lan_valor) VALUES ($tra_3, " . (int)$con_rede_pr . ", -100.00)");
insere("INSERT INTO lancamentos (lan_tra, lan_con, lan_valor) VALUES ($tra_3, " . (int)$con_t . ", -50.00)");
insere("INSERT INTO lancamentos (lan_tra, lan_con, lan_valor) VALUES ($tra_3, " . (int)$con_origem . ", 150.00)");

$l3 = null;
foreach ((array)despesas_da_rede('2026-04-01','2026-05-01') as $l)
    if ($l['tra_id'] === (int)$tra_3) $l3 = $l;

verifica("com tres pernas o valor sai da conta que carrega o custo, e nao dobrado",
    $l3 !== null && round($l3['valor'], 2) == 100.00,
    var_export($l3, true));

verifica("e a linha aparece UMA vez, nao duas",
    count(array_filter((array)despesas_da_rede('2026-04-01','2026-05-01'),
        function ($x) use ($tra_3) { return $x['tra_id'] === (int)$tra_3; })) === 1);

// o teto do rateio segue o mesmo numero: 100, nao 150 nem 200
verifica("o teto do rateio e o valor da perna de custo",
    redefine_rateio($tra_3, array($nuc_pop => 100.00)) === true
 && redefine_rateio($tra_3, array($nuc_pop => 100.01)) === false,
    json_encode(rateio_da_despesa($tra_3)));

// Esta fixture viola o invariante do modulo DE PROPOSITO — ele exige exatamente duas
// pernas, e ela tem tres. Deixa-la de pe faria toda assercao posterior sobre o
// invariante falhar por causa dela, apontando para o lugar errado.
executa_sql("DELETE FROM rateios WHERE rat_tra = " . (int)$tra_3);
executa_sql("DELETE FROM lancamentos WHERE lan_tra = " . (int)$tra_3);
executa_sql("DELETE FROM transacoes WHERE tra_id = " . (int)$tra_3);

verifica("e a fixture de tres pernas se limpa, para o invariante seguir conferivel",
    count(transacoes_desbalanceadas()) === 0,
    json_encode(transacoes_desbalanceadas()));

// ---------------------------------------------------------------------------
// A Rede paga produtor direto — nao e despesa, e nao se rateia.
//
// A Rede quita o que JA DEVE pela mercadoria entregue. O custo dela ja foi para quem a
// recebeu, no debito do cestante; rateando, cada nucleo seria cobrado de novo pelo mesmo
// produto. Por isso funcao propria, e nao mais uma area de despesa.
$saldo_forn_antes = saldo_da_conta($con_forn_t);
$saldo_orig_antes = saldo_da_conta($con_origem);

$tra_pp = lanca_pagamento_a_produtor_da_rede('2026-04-15', $con_forn_t, 250.00, $con_origem,
    'pagamento do mel de marco');

verifica("a Rede consegue pagar um produtor direto da conta dela",
    $tra_pp !== null, var_export($tra_pp, true));

// As pernas sao as mesmas do caixa do nucleo, trocando o caixa pela conta da Rede: quem
// segurava o dinheiro passa a segurar menos, e a conta do produtor registra o recebido.
verifica("as pernas sao produtor -250 e conta de origem +250",
    round(saldo_da_conta($con_forn_t) - $saldo_forn_antes, 2) == -250.00
 && round(saldo_da_conta($con_origem) - $saldo_orig_antes, 2) ==  250.00,
    "produtor=" . round(saldo_da_conta($con_forn_t) - $saldo_forn_antes, 2)
    . " origem=" . round(saldo_da_conta($con_origem) - $saldo_orig_antes, 2));

verifica("e a transacao tem exatamente duas pernas somando zero",
    count(transacoes_desbalanceadas()) === 0,
    json_encode(transacoes_desbalanceadas()));

// O ponto INTEIRO da funcao separada: nenhum nucleo carrega isto.
verifica("nao grava rateio nenhum: pagar produtor nao e custo a repartir",
    is_array($r_pp = rateio_da_despesa($tra_pp)) && count($r_pp) === 0,
    var_export($r_pp, true));

verifica("e nao aparece entre as despesas da Rede do mes",
    count(array_filter((array)despesas_da_rede('2026-04-01','2026-05-01'),
        function ($x) use ($tra_pp) { return $x['tra_id'] === (int)$tra_pp; })) === 0,
    json_encode(array_map(function ($x) { return $x['tra_id']; },
        (array)despesas_da_rede('2026-04-01','2026-05-01'))));

// A posicao do produtor soma o que caiu na conta dele sem olhar tipo nenhum, entao o
// pagamento da Rede abate o mesmo saldo que o pagamento de nucleo abateria.
$pos_pp = posicao_dos_produtores('2026-04-01', '2026-05-01');
$linha_pp = null;
foreach ((array)$pos_pp as $x) if ((int)$x['forn_id'] === (int)$forn_livre[2]) $linha_pp = $x;
verifica("e a posicao do produtor ja conta esse pagamento, sem precisar saber quem pagou",
    $linha_pp !== null && round($linha_pp['pago'], 2) == 250.00,
    var_export($linha_pp, true));

// COMPROVANTE, como no pagamento de cestante: e o que transforma "consta que pagamos" em
// "aqui esta". Quem cobra de novo por engano e justamente quem nao achou o registro.
$tra_comp = lanca_pagamento_a_produtor_da_rede('2026-04-17', $con_forn_t, 15.00, $con_origem,
    'com comprovante', 'https://banco.exemplo/extrato/123');
verifica("o pagamento guarda o comprovante que a tela coleta",
    $tra_comp !== null
 && valor_escalar("SELECT tra_comprovante FROM transacoes WHERE tra_id = " . (int)$tra_comp)
    === 'https://banco.exemplo/extrato/123',
    var_export(valor_escalar("SELECT tra_comprovante FROM transacoes WHERE tra_id = " . (int)$tra_comp), true));

// Opcional de verdade: pagamento em dinheiro nao tem link, e exigir um faria inventar.
$tra_scomp = lanca_pagamento_a_produtor_da_rede('2026-04-17', $con_forn_t, 15.00, $con_origem, 'sem');
verifica("sem comprovante o campo fica NULL, e nao string vazia",
    $tra_scomp !== null
 && valor_escalar("SELECT tra_comprovante FROM transacoes WHERE tra_id = " . (int)$tra_scomp) === null,
    var_export(valor_escalar("SELECT tra_comprovante FROM transacoes WHERE tra_id = " . (int)$tra_scomp), true));

// ---- o que NAO pode virar pagamento ----
verifica("valor zero ou negativo nao vira pagamento",
    lanca_pagamento_a_produtor_da_rede('2026-04-15', $con_forn_t, 0, $con_origem, 'x') === null
 && lanca_pagamento_a_produtor_da_rede('2026-04-15', $con_forn_t, -5, $con_origem, 'x') === null);

// Pagar produtor da conta de um cestante tiraria dele dinheiro que ele nao gastou.
verifica("a origem tem de ser conta da REDE, nao de cestante nem de produtor",
    lanca_pagamento_a_produtor_da_rede('2026-04-15', $con_forn_t, 10.00, $con_forn_t, 'x') === null
 && lanca_pagamento_a_produtor_da_rede('2026-04-15', $con_forn_t, 10.00, $con_t, 'x') === null);

// O destino e a conta de um produtor. Mandar a conta da Rede faria a Rede pagar a si
// mesma, e o saldo do produtor seguiria dizendo que ela deve.
verifica("o destino tem de ser conta de PRODUTOR",
    lanca_pagamento_a_produtor_da_rede('2026-04-15', $con_origem, 10.00, $con_origem, 'x') === null
 && lanca_pagamento_a_produtor_da_rede('2026-04-15', $con_t, 10.00, $con_origem, 'x') === null);

// Array onde se espera escalar e TypeError dentro do isset() no PHP 8: a tela inteira
// cairia por causa de um `origem[]` no POST. Mesma guarda das irmas.
verifica("array no lugar de conta e recusa, e nao pagina em branco",
    lanca_pagamento_a_produtor_da_rede('2026-04-15', array($con_forn_t), 10.00, $con_origem, 'x') === null
 && lanca_pagamento_a_produtor_da_rede('2026-04-15', $con_forn_t, 10.00, array($con_origem), 'x') === null);

// Descricao vazia nao deixa a linha do extrato muda.
$tra_pp2 = lanca_pagamento_a_produtor_da_rede('2026-04-16', $con_forn_t, 10.00, $con_origem, '   ');
verifica("descricao em branco vira o rotulo padrao, e nao linha sem texto",
    $tra_pp2 !== null
 && valor_escalar("SELECT tra_historico FROM transacoes WHERE tra_id = " . (int)$tra_pp2)
    === 'pagamento a produtor',
    var_export(valor_escalar("SELECT tra_historico FROM transacoes WHERE tra_id = " . (int)$tra_pp2), true));


// ---------------------------------------------------------------------------
// A JANELA: despesa recente se conserta digitando de novo; velha, so com ajuste.
//
// Dois meses, e nao trinta dias: quem fecha o mes trabalha nos primeiros dias do
// seguinte, e trinta dias fechariam a porta no dia 1o.
verifica("despesa deste mes e do mes passado sao editaveis",
    despesa_da_rede_editavel(date('Y-m-d')) === true
 && despesa_da_rede_editavel(date('Y-m-d', strtotime('first day of last month'))) === true);

// O ULTIMO DIA FORA DA JANELA, calculado em duas etapas de proposito: PHP le
// 'first day of last month -1 day' como 'first day of last month' e ignora o resto —
// devolve o dia 1o, nao o dia anterior a ele. A primeira versao deste teste caiu nisso.
$primeiro_editavel = strtotime('first day of last month');
$ultimo_congelado  = date('Y-m-d', strtotime('-1 day', $primeiro_editavel));

verifica("despesa de dois meses atras ja esta congelada",
    despesa_da_rede_editavel($ultimo_congelado) === false
 && despesa_da_rede_editavel('2019-06-01') === false,
    "primeiro editavel = " . date('Y-m-d', $primeiro_editavel)
    . " · ultimo congelado = " . $ultimo_congelado);

verifica("data invalida nao abre a janela",
    despesa_da_rede_editavel('') === false && despesa_da_rede_editavel('nao e data') === false);

// ---- a edicao no lugar ----
$dt_agora  = date('Y-m-d');
$tra_ed = lanca_despesa_da_rede($dt_agora, 'sistemas', 300.00, $con_origem, 'antes da correcao',
    array($nuc_pop => 100.00));
verifica("fixture da edicao: a despesa nasce", $tra_ed !== null, var_export($tra_ed, true));

$saldo_orig_ed = saldo_da_conta($con_origem);

verifica("corrige valor, area, descricao e rateio de uma vez",
    edita_despesa_da_rede($tra_ed, $dt_agora, 'admin', 250.00, $con_origem,
        'depois da correcao', array($nuc_pop => 50.00)) === true);

$lin_ed = null;
foreach ((array)despesas_da_rede(date('Y-m-01'), date('Y-m-01', strtotime('+1 month'))) as $l)
    if ($l['tra_id'] === (int)$tra_ed) $lin_ed = $l;

verifica("a lista ja mostra o valor, a area e a descricao novos",
    $lin_ed !== null && round($lin_ed['valor'],2) == 250.00
 && $lin_ed['categoria'] === 'admin' && $lin_ed['historico'] === 'depois da correcao',
    var_export($lin_ed, true));

// O RATEIO E REFEITO JUNTO: fracao do valor, e o antigo deixaria a despesa sem fechar.
verifica("e o rateio foi refeito com o valor novo",
    ($r_ed = rateio_da_despesa($tra_ed)) && count($r_ed) === 1 && round($r_ed[$nuc_pop],2) == 50.00,
    json_encode($r_ed));

// AS PERNAS acompanham: 300 viraram 250, e a conta de origem sente a diferenca.
verifica("as duas pernas foram reescritas pelo valor novo",
    round(saldo_da_conta($con_origem) - $saldo_orig_ed, 2) == -50.00,
    "delta = " . round(saldo_da_conta($con_origem) - $saldo_orig_ed, 2));

verifica("e a transacao segue com duas pernas somando zero",
    count(transacoes_desbalanceadas()) === 0, json_encode(transacoes_desbalanceadas()));

// O CARIMBO: sem ele a linha continuaria dizendo que foi registrada por quem a criou,
// com o valor de outra pessoa.
verifica("a edicao carimba quando foi alterada",
    valor_escalar("SELECT tra_dt_alteracao FROM transacoes WHERE tra_id = " . (int)$tra_ed) !== null);

// ---- o que a edicao NAO pode fazer ----
//
// FORA DA JANELA. A trava e conferida no BANCO, com a data que esta gravada — a tela
// esconde o botao, mas o POST chega igual.
$tra_velha = lanca_despesa_da_rede('2019-06-05', 'admin', 100.00, $con_origem, 'velha', array());
executa_sql("UPDATE transacoes SET tra_dt = '2019-06-05 00:00:00' WHERE tra_id = " . (int)$tra_velha);
verifica("despesa velha NAO se edita, mesmo com o POST chegando",
    edita_despesa_da_rede($tra_velha, '2019-06-05', 'admin', 999.00, $con_origem, 'x', array()) === false
 && round((float)valor_escalar("SELECT -lan_valor FROM lancamentos WHERE lan_tra = " . (int)$tra_velha
        . " AND lan_con = " . (int)conta_da_rede()), 2) == 100.00);

verifica("transacao que NAO e despesa da Rede nao se edita por aqui",
    edita_despesa_da_rede($tra_pp, $dt_agora, 'admin', 10.00, $con_origem, 'x', array()) === false);

verifica("valor nao positivo, area inexistente e origem que nao e da Rede sao recusados",
    edita_despesa_da_rede($tra_ed, $dt_agora, 'admin',  0.00, $con_origem, 'x', array()) === false
 && edita_despesa_da_rede($tra_ed, $dt_agora, 'churrasco', 10.00, $con_origem, 'x', array()) === false
 && edita_despesa_da_rede($tra_ed, $dt_agora, 'admin', 10.00, $con_forn_t, 'x', array()) === false);

// O teto do rateio segue o valor NOVO, e nao o antigo.
verifica("rateio maior que o valor novo e recusado, e nada e gravado pela metade",
    edita_despesa_da_rede($tra_ed, $dt_agora, 'admin', 40.00, $con_origem, 'x',
        array($nuc_pop => 40.01)) === false
 && ($r2 = rateio_da_despesa($tra_ed)) && round($r2[$nuc_pop],2) == 50.00,
    json_encode(isset($r2) ? $r2 : null));


// ---- reajuste ----
$antes_rat = rateio_da_despesa($tra_rede);
verifica("rateio_da_despesa devolve nucleo => valor",
    is_array($antes_rat) && count($antes_rat) > 0,
    var_export($antes_rat === null ? null : count($antes_rat), true));

verifica("reajustar substitui o conjunto inteiro",
    redefine_rateio($tra_rede, array($nuc_pop => 5.00)) === true
    && ($d = rateio_da_despesa($tra_rede)) && count($d) === 1
    && round($d[$nuc_pop], 2) == 5.00,
    json_encode(rateio_da_despesa($tra_rede)));

// A despesa em si NAO muda: para corrigir dinheiro lanca-se outra, como no resto do
// modulo. O que se corrige aqui e para quem o custo foi apontado.
verifica("o reajuste nao mexe no valor nem nas pernas da despesa",
    ($p = pernas_de($tra_rede)) && round($p[$con_origem], 2) == 302.68,
    json_encode(pernas_de($tra_rede)));

verifica("reajustar para mais do que a despesa custou e recusado",
    redefine_rateio($tra_rede, array($nuc_pop => 999999.00)) === false
    && count((array)rateio_da_despesa($tra_rede)) === 1,
    json_encode(rateio_da_despesa($tra_rede)));

verifica("reajustar para um nucleo que nao rateia e recusado",
    redefine_rateio($tra_rede, array($nuc_sent => 5.00)) === false);

// So despesa da Rede tem rateio: apontar custo num pagamento de cestante seria carimbar
// no nucleo dinheiro que nao e custo de ninguem.
verifica("transacao que nao e despesa da Rede nao aceita rateio",
    redefine_rateio($tra_desp, array($nuc_pop => 5.00)) === false);

verifica("transacao que nao existe nao aceita rateio",
    redefine_rateio(99999999, array($nuc_pop => 5.00)) === false);

// Esvaziar e legitimo: a Rede absorve tudo.
verifica("rateio pode ser esvaziado, e ai a Rede absorve a despesa inteira",
    redefine_rateio($tra_rede, array()) === true
    && count((array)rateio_da_despesa($tra_rede)) === 0);


// ---------------------------------------------------------------------------
echo "\nquota: gravada no banco, sugerida pelo tipo\n";
// ---------------------------------------------------------------------------

$lista_q = nucleos_e_quotas();
verifica("a lista traz nucleo, tipo, sugestao do tipo e quota que vale",
    is_array($lista_q) && count($lista_q) > 0
    && isset($lista_q[0]['nome'], $lista_q[0]['tipo'], $lista_q[0]['sugerida'], $lista_q[0]['vale']),
    is_array($lista_q) ? json_encode($lista_q[0]) : var_export($lista_q, true));

function quota_de($lista, $nuc)
{
    foreach ((array)$lista as $l) if ($l['nuc_id'] === (int)$nuc) return $l;
    return null;
}

// O nucleo popular do bloco anterior tem 0,5 gravado, contra 1 sugerido pelo tipo
// Mensal. As duas aparecem separadas para quem edita saber do que esta discordando.
$q_pop = quota_de($lista_q, $nuc_pop);
verifica("quando ha quota propria, ela e a que vale — e a sugestao continua visivel",
    $q_pop !== null && $q_pop['propria'] == 0.5 && $q_pop['sugerida'] == 1.0
                    && $q_pop['vale'] == 0.5,
    var_export($q_pop, true));

// Nucleo que ninguem tocou ainda: quota propria nula, e vale a do tipo.
$nuc_novo = insere("INSERT INTO nucleos (nuc_nome_curto, nuc_nome_completo, nuc_archive, nuc_nuct)
    VALUES ('nucsemquota','Sem quota definida',0," . (int)$tipo_id['Semanal'] . ")");
$q_novo = quota_de(nucleos_e_quotas(), $nuc_novo);
verifica("nucleo que nunca passou pela tela vale a sugestao do tipo",
    $q_novo !== null && $q_novo['propria'] === null && $q_novo['vale'] == 4.0,
    var_export($q_novo, true));

// ARQUIVADO aparece, marcado: se voltar a ativo tem de reaparecer com a quota que tinha,
// e escondido a quota ressurgiria sem ninguem ter olhado para ela.
$q_arq = quota_de(nucleos_e_quotas(), $nuc_arq);
verifica("nucleo arquivado aparece na lista, marcado como arquivado",
    $q_arq !== null && $q_arq['arquivado'] === true,
    var_export($q_arq, true));

// ---- gravar ----
verifica("gravar torna a quota explicita, e ela passa a mandar",
    define_quotas_de_rateio(array($nuc_novo => 2.0)) === true
    && ($q = quota_de(nucleos_e_quotas(), $nuc_novo)) && $q['propria'] == 2.0 && $q['vale'] == 2.0,
    json_encode(quota_de(nucleos_e_quotas(), $nuc_novo)));

verifica("e o rateio passa a usar a quota gravada, nao a do tipo",
    ($qs = quotas_de_rateio()) && isset($qs[$nuc_novo]) && $qs[$nuc_novo] == 2.0,
    var_export(isset($qs[$nuc_novo]) ? $qs[$nuc_novo] : null, true));

// ZERO nao e ausencia de quota: e "este nucleo NAO rateia", que e o caso de Logistica e
// Mutirao. Por isso zero e valor valido, e nao motivo de recusa.
verifica("zero e valor valido, e tira o nucleo do rateio",
    define_quotas_de_rateio(array($nuc_novo => 0)) === true
    && !isset(((array)quotas_de_rateio())[$nuc_novo]));

verifica("varias de uma vez",
    define_quotas_de_rateio(array($nuc_novo => 4.0, $nuc_pop => 0.5)) === true
    && ($qs = quotas_de_rateio()) && $qs[$nuc_novo] == 4.0 && $qs[$nuc_pop] == 0.5);

// ---- o que e recusado ----
// A soma das quotas e o divisor de TODO MUNDO: uma quota invalida no meio da lista
// mudaria o rateio de todos os outros. Por isso confere tudo antes de gravar nada.
$antes_pop = ((array)quotas_de_rateio())[$nuc_pop];
verifica("quota negativa e recusada, e NENHUMA da leva e gravada",
    define_quotas_de_rateio(array($nuc_pop => 3.0, $nuc_novo => -1)) === false
    && ((array)quotas_de_rateio())[$nuc_pop] == $antes_pop,
    "antes=$antes_pop depois=" . ((array)quotas_de_rateio())[$nuc_pop]);

verifica("quota que nao e numero e recusada",
    define_quotas_de_rateio(array($nuc_pop => 'quatro')) === false);

verifica("id que nao e id e recusado",
    define_quotas_de_rateio(array('abc' => 1.0)) === false
 && define_quotas_de_rateio(array(0 => 1.0)) === false);

verifica("quota absurda e recusada",
    define_quotas_de_rateio(array($nuc_pop => 1000)) === false);

verifica("entrada que nao e array e recusada",
    define_quotas_de_rateio('tudo 4') === false);

// Lista vazia e recusa, e nao sucesso silencioso: vinda da tela significa formulario
// truncado, e responder "gravadas" a um POST que nao gravou nada e a mentira de sempre.
// Nao ha acao legitima de gravar zero quotas — para tirar um nucleo do rateio, grava-se 0.
verifica("lista vazia e recusada, e nao devolve sucesso sem gravar nada",
    define_quotas_de_rateio(array()) === false);


// ---------------------------------------------------------------------------
echo "\nestoque: mercadoria parada e ativo, nao prejuizo\n";
// ---------------------------------------------------------------------------

// Chamada de secos propria, com produto de compra 8 e venda 10 — a diferenca entre os
// dois precos e o que este bloco confere: estoque se avalia pelo que a Rede DESEMBOLSOU.
$prod_est = insere("INSERT INTO produtos (prod_id, prod_prodt, prod_forn, prod_nome, prod_unidade,
    prod_valor_compra, prod_valor_venda, prod_valor_venda_margem, prod_ini_validade, prod_fim_validade,
    prod_multiplo_venda, prod_retornavel)
    VALUES (900003,1," . (int)$forn_res . ",'Secos do estoque','kg',8.00,10.00,13.00,
    '2020-01-01 00:00:00','2030-01-01 00:00:00',1,0)");
$prod_est_id = 900003;

$cha_est = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual)
    VALUES (1,'2026-09-12 23:59:59','2026-09-01 00:00:00','2026-09-08 23:59:59',0.00)");
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade)
    VALUES (" . (int)$cha_est . "," . (int)$prod_est_id . ",1)");

$con_estoque = conta_de_estoque();
$con_rede_est = conta_da_rede();

verifica("a conta de estoque existe e nao e destino de pagamento",
    $con_estoque > 0 && !isset(((array)contas_de_destino())[$con_estoque]),
    var_export($con_estoque, true));

// ESTOCOU: entrou 10 e sobraram 30 (antes 20, depois 50). Variacao +30 unidades.
executa_sql("INSERT INTO estoque (est_cha, est_prod, est_prod_qtde_antes, est_prod_qtde_depois)
    VALUES (" . (int)$cha_est . "," . (int)$prod_est_id . ",20,50)");

// SALDO MEDIDO POR DIFERENCA, e nao em absoluto. A conta de estoque e UMA so no sistema
// inteiro, e a copia local pode ter lancamento anterior — foi o que aconteceu: um
// `estoque_abertura` sobrou de um teste manual e derrubou seis assercoes que afirmavam
// valores absolutos. E a mesma licao do TEST-2, em outra conta.
$est0 = round(-saldo_da_conta($con_estoque), 2);
function estoque_desde($base) { global $con_estoque; return round(-saldo_da_conta($con_estoque) - $base, 2); }

// ABERTURA: 20 unidades ja guardadas antes de o modulo comecar a lancar. Sem isso a
// conta guardaria so a soma das variacoes — o quanto o estoque MUDOU, e nao quanto vale.
//
// A abertura so acontece quando a conta esta VAZIA, entao aqui ela e exercitada pelo
// contrato de recusa: com lancamento anterior, devolve 0 e nao mexe em nada.
$ja_tinha = ($est0 != 0.0 || valor_escalar("SELECT COUNT(*) FROM lancamentos WHERE lan_con = " . (int)$con_estoque) > 0);
$ab = lanca_abertura_do_estoque($cha_est);

verifica("a abertura lanca o que ja estava guardado, ou recusa se a conta ja tem historia",
    $ja_tinha ? ($ab === 0 && estoque_desde($est0) == 0.00)
              : ($ab > 0   && estoque_desde($est0) == 160.00),
    "ja_tinha=" . var_export($ja_tinha, true) . " ab=" . var_export($ab, true)
        . " delta=" . estoque_desde($est0));

verifica("abrir duas vezes nao dobra o estoque",
    lanca_abertura_do_estoque($cha_est) === 0
    && estoque_desde($est0) == ($ja_tinha ? 0.00 : 160.00));

// DUAS CORRENTES. Secos e Secos Bimestral tem estoques independentes — a chamada
// anterior de uma Secos e a Secos anterior, nunca a Bimestral. Cada uma abre a sua.
//
// Guardando pela CONTA em vez de pela corrente, abrir a primeira trancava a segunda para
// sempre, e o estoque inicial dela nunca entrava: a conta ficava a menos, em silencio.
$prodt_bim = valor_escalar("SELECT prodt_id FROM produtotipos WHERE prodt_nome LIKE 'Secos Bime%'");
if ($prodt_bim)
{
    $cha_bim = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual)
        VALUES (" . (int)$prodt_bim . ",'2026-09-19 23:59:59','2026-09-01 00:00:00','2026-09-15 23:59:59',0.00)");
    executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade)
        VALUES (" . (int)$cha_bim . "," . (int)$prod_est_id . ",1)");
    executa_sql("INSERT INTO estoque (est_cha, est_prod, est_prod_qtde_antes, est_prod_qtde_depois)
        VALUES (" . (int)$cha_bim . "," . (int)$prod_est_id . ",7,7)");

    $antes_bim = round(-saldo_da_conta($con_estoque), 2);
    $ab_bim = lanca_abertura_do_estoque($cha_bim);

    verifica("a outra corrente abre a SUA abertura, mesmo com a primeira ja aberta",
        $ab_bim > 0 && round(-saldo_da_conta($con_estoque) - $antes_bim, 2) == 56.00,
        "ab=" . var_export($ab_bim, true)
            . " delta=" . round(-saldo_da_conta($con_estoque) - $antes_bim, 2));

    verifica("e abrir a mesma corrente duas vezes continua nao dobrando",
        lanca_abertura_do_estoque($cha_bim) === 0);
}

// daqui para baixo o ponto de partida e o que a abertura deixou
$est1 = round(-saldo_da_conta($con_estoque), 2);

$v = valor_do_estoque_da_chamada($cha_est);
verifica("o estoque e avaliado a preco de COMPRA, nao de venda",
    is_array($v) && round($v['antes'],2) == 160.00 && round($v['depois'],2) == 400.00
                 && round($v['variacao'],2) == 240.00,
    var_export($v, true));

$tra_est = lanca_estoque_da_chamada($cha_est);
$p = pernas_de($tra_est);
verifica("estocar move valor para o estoque e MELHORA a posicao da Rede",
    $tra_est && round($p[$con_estoque],2) == -240.00 && round($p[$con_rede_est],2) == 240.00,
    "tra=" . var_export($tra_est,true) . " pernas=" . json_encode($p));

verifica("e o estoque sobe 240: as 30 unidades que sobraram, a 8",
    estoque_desde($est1) == 240.00, var_export(estoque_desde($est1), true));

// IDEMPOTENTE: rodar de novo sem mudanca nao lanca nada.
verifica("rodar de novo sem mudanca nao lanca nada",
    lanca_estoque_da_chamada($cha_est) === 0,
    var_export(lanca_estoque_da_chamada($cha_est), true));

verifica("e o saldo continua o mesmo",
    estoque_desde($est1) == 240.00);

// CORRECAO: alguem conferiu e o estoque final era 45, nao 50. Lanca so a DIFERENCA —
// reescrever o lancamento anterior apagaria o que ja foi conferido.
executa_sql("UPDATE estoque SET est_prod_qtde_depois = 45
    WHERE est_cha = " . (int)$cha_est . " AND est_prod = " . (int)$prod_est_id);

$tra_corr = lanca_estoque_da_chamada($cha_est);
$p2 = pernas_de($tra_corr);
verifica("correcao lanca so a diferenca, e nao reescreve o que ja estava",
    $tra_corr > 0 && round($p2[$con_estoque],2) == 40.00 && round($p2[$con_rede_est],2) == -40.00,
    json_encode($p2));

verifica("a correcao devolve 40, deixando a variacao em 200: 25 unidades a 8",
    estoque_desde($est1) == 200.00, var_export(estoque_desde($est1), true));

// CONSUMIU: chamada seguinte comeca com 45 e termina com 5.
$cha_est2 = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual)
    VALUES (1,'2026-10-10 23:59:59','2026-10-01 00:00:00','2026-10-05 23:59:59',0.00)");
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade)
    VALUES (" . (int)$cha_est2 . "," . (int)$prod_est_id . ",1)");
executa_sql("INSERT INTO estoque (est_cha, est_prod, est_prod_qtde_antes, est_prod_qtde_depois)
    VALUES (" . (int)$cha_est2 . "," . (int)$prod_est_id . ",45,5)");

$tra_cons = lanca_estoque_da_chamada($cha_est2);
$p3 = pernas_de($tra_cons);
verifica("consumir estoque devolve o valor e PIORA a posicao da Rede — o custo se realiza",
    $tra_cons && round($p3[$con_estoque],2) == 320.00 && round($p3[$con_rede_est],2) == -320.00,
    json_encode($p3));

verifica("e o estoque cai 320: as 40 unidades consumidas, a 8",
    estoque_desde($est1) == -120.00, var_export(estoque_desde($est1), true));

// SEM VARIACAO nao e erro: e chamada que nao mexeu no estoque.
$cha_est3 = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual)
    VALUES (1,'2026-11-14 23:59:59','2026-11-01 00:00:00','2026-11-10 23:59:59',0.00)");
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade)
    VALUES (" . (int)$cha_est3 . "," . (int)$prod_est_id . ",1)");
executa_sql("INSERT INTO estoque (est_cha, est_prod, est_prod_qtde_antes, est_prod_qtde_depois)
    VALUES (" . (int)$cha_est3 . "," . (int)$prod_est_id . ",5,5)");

verifica("chamada sem variacao de estoque nao gera lancamento",
    lanca_estoque_da_chamada($cha_est3) === 0);

// Chamada SEM linha de estoque nenhuma: o normal para Frescos, que nao se guarda.
$cha_sem = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual)
    VALUES (1,'2026-12-12 23:59:59','2026-12-01 00:00:00','2026-12-08 23:59:59',0.00)");
verifica("chamada sem estoque nenhum devolve zero, e nao erro",
    lanca_estoque_da_chamada($cha_sem) === 0
    && ($z = valor_do_estoque_da_chamada($cha_sem)) && round($z['variacao'],2) == 0.00,
    var_export($z, true));

verifica("chamada que nao existe devolve null",
    valor_do_estoque_da_chamada(99999999) === null
 && lanca_estoque_da_chamada(99999999) === null);

// O INVARIANTE do modulo continua: toda transacao soma zero.
verifica("os lancamentos de estoque somam zero, como todos os outros",
    count(transacoes_desbalanceadas()) === 0,
    json_encode(transacoes_desbalanceadas()));

// A soma de tudo que foi lancado tem de bater com o estoque que existe HOJE nas
// chamadas tocadas — e o mesmo dinheiro contado de dois jeitos.
// A conta segue as duas chamadas: +25 unidades numa, -40 na outra, dando -15 a 8.
verifica("o movimento das duas chamadas soma o que elas de fato mexeram",
    estoque_desde($est1) == round(-15 * 8.00, 2), var_export(estoque_desde($est1), true));

// CONTRATO da familia: consulta que nao roda devolve null.
executa_sql("CREATE TEMPORARY TABLE estoque (
    est_cha mediumint(6) unsigned NOT NULL, est_prod mediumint(6) unsigned NOT NULL) ENGINE=InnoDB");
$sombra_est = (executa_sql("SELECT est_prod_qtde_antes FROM estoque") === false);
$v_sem_bd = valor_do_estoque_da_chamada($cha_est);
executa_sql("DROP TEMPORARY TABLE estoque");

verifica("a sombra sem as colunas faz o servidor recusar", $sombra_est);
verifica("valor de consulta recusada e null, e nao estoque zerado",
    $v_sem_bd === null, var_export($v_sem_bd, true));


// ---------------------------------------------------------------------------
echo "\nfechamento da chamada: ver antes de confirmar\n";
// ---------------------------------------------------------------------------

// cha_est3 (14/11/2026) tem estoque 5 -> 5: variacao zero e nada lancado.
// cha_sem  (12/12/2026) nao tem linha de estoque nenhuma.
// As duas nao devem entrar na fila — nao ha o que fechar nelas.
$fila = chamadas_a_fechar('2026-09-01', '2027-01-01');

function na_fila($fila, $cha) {
    foreach ((array)$fila as $f) if ($f['cha_id'] === (int)$cha) return $f;
    return null;
}

verifica("a fila traz o que ha para fechar, com o pendente aberto",
    is_array($fila) && ($f = na_fila($fila, $cha_est2)) !== null
    && isset($f['estoque']['antes'], $f['estoque']['depois'], $f['estoque']['falta']),
    is_array($fila) ? json_encode($fila) : var_export($fila, true));

// Chamada ja lancada aparece MARCADA como fechada, e nao some: quem confere precisa ver
// que ela foi tratada, senao a ausencia se confunde com esquecimento.
verifica("chamada ja lancada aparece marcada como fechada",
    ($f = na_fila($fila, $cha_est2)) && $f['fechada'] === true
                                     && abs($f['estoque']['falta']) < 0.005,
    var_export($f, true));

verifica("chamada sem variacao e sem lancamento fica FORA da fila",
    na_fila($fila, $cha_est3) === null && na_fila($fila, $cha_sem) === null);

// PENDENTE de verdade: uma chamada nova, com estoque e sem lancamento.
$cha_pend = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1,'2026-10-24 23:59:59','2026-10-01 00:00:00','2026-10-20 23:59:59',0.00, NOW() - INTERVAL 5 DAY)");
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade)
    VALUES (" . (int)$cha_pend . "," . (int)$prod_est_id . ",1)");
executa_sql("INSERT INTO estoque (est_cha, est_prod, est_prod_qtde_antes, est_prod_qtde_depois)
    VALUES (" . (int)$cha_pend . "," . (int)$prod_est_id . ",5,20)");

$f = na_fila(chamadas_a_fechar('2026-09-01','2027-01-01'), $cha_pend);
verifica("chamada com estoque e sem lancamento entra como PENDENTE, com o valor a lancar",
    $f !== null && $f['fechada'] === false && round($f['estoque']['falta'],2) == 120.00
                && round($f['estoque']['lancado'],2) == 0.00,
    var_export($f, true));

// O PRAZO CONTABIL e o que autoriza congelar: antes dele os insumos ainda mudam.
verifica("a fila diz se a chamada ja e congelavel",
    $f !== null && $f['congelavel'] === true, var_export($f['congelavel'], true));

$cha_cedo = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1,'2026-12-05 23:59:59','2026-12-01 00:00:00','2026-12-03 23:59:59',0.00, NOW() + INTERVAL 30 DAY)");
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade)
    VALUES (" . (int)$cha_cedo . "," . (int)$prod_est_id . ",1)");
executa_sql("INSERT INTO estoque (est_cha, est_prod, est_prod_qtde_antes, est_prod_qtde_depois)
    VALUES (" . (int)$cha_cedo . "," . (int)$prod_est_id . ",1,9)");

$fc = na_fila(chamadas_a_fechar('2026-09-01','2027-01-01'), $cha_cedo);
verifica("chamada com prazo contabil no futuro aparece, mas NAO congelavel",
    $fc !== null && $fc['congelavel'] === false && $fc['fechada'] === false,
    var_export($fc, true));

// Depois de fechar, a mesma chamada muda de estado na fila — e o valor pendente zera.
lanca_estoque_da_chamada($cha_pend);
$f2 = na_fila(chamadas_a_fechar('2026-09-01','2027-01-01'), $cha_pend);
verifica("fechar muda o estado na fila e zera o pendente",
    $f2 !== null && $f2['fechada'] === true && abs($f2['estoque']['falta']) < 0.005
                 && round($f2['estoque']['lancado'],2) == 120.00,
    var_export($f2, true));

// A janela vazia tem de ser uma que a base NAO alcance: 2019 tem chamadas reais na copia
// de producao, e a primeira tentativa deste teste passou a mao numa delas.
verifica("periodo sem chamada devolve lista vazia, e nao null",
    is_array($vz = chamadas_a_fechar('2035-01-01','2035-02-01')) && count($vz) === 0,
    var_export($vz, true));

// CONTRATO da familia.
executa_sql("CREATE TEMPORARY TABLE estoque (
    est_cha mediumint(6) unsigned NOT NULL, est_prod mediumint(6) unsigned NOT NULL) ENGINE=InnoDB");
$sombra_f = (executa_sql("SELECT est_prod_qtde_antes FROM estoque") === false);
$f_sem_bd = chamadas_a_fechar('2026-09-01','2027-01-01');
executa_sql("DROP TEMPORARY TABLE estoque");

verifica("a sombra faz o servidor recusar a fila", $sombra_f);
verifica("fila de consulta recusada e null, e nao 'nada a fechar'",
    $f_sem_bd === null, var_export($f_sem_bd, true));


// ---------------------------------------------------------------------------
// O BALANÇO DA CHAMADA TEM SUÍTE PRÓPRIA: scripts/test-balanco.sh.
//
// Ele subiu para produção antes deste módulo — só lê o que Entregas já registra, e
// não depende de tabela nova. As 41 asserções que ficavam aqui foram junto com o
// código, para balanco.inc.php e sua suíte. Mantê-las nos dois lugares faria uma
// mudança no balanço quebrar duas suítes, e quem mexesse perderia tempo procurando
// por que a do financeiro reclamava de algo que não é dela.
//
// O que ESTA suíte ainda prova sobre ele é o encanamento, logo abaixo: que carregar
// financeiro.inc.php basta para as funções do balanço existirem.
// ---------------------------------------------------------------------------
echo "\nbalanco: o encanamento entre os dois modulos\n";

verifica("carregar o financeiro traz junto as funcoes do balanco",
    function_exists('balanco_da_chamada')
 && function_exists('detalhe_do_nucleo_na_chamada')
 && function_exists('abas_entregas'),
    'balanco_da_chamada=' . var_export(function_exists('balanco_da_chamada'), true)
    . ' detalhe=' . var_export(function_exists('detalhe_do_nucleo_na_chamada'), true)
    . ' abas=' . var_export(function_exists('abas_entregas'), true));

// A REDECLARACAO NAO SE TESTA AQUI, e a tentativa anterior era uma tautologia que nao
// media nada. Os dois arquivos definem funcoes; menu.inc.php carrega o financeiro em
// toda pagina e as telas de Entregas carregam o balanco por conta propria. Sem o
// require_once as duas vias juntas derrubam a pagina — foi o estado em que o rebase
// deixou a branch por alguns minutos, e quem pegou foi o smoke, em 9 paginas de uma
// vez. E ele quem cobre isso, porque so uma pagina de verdade exercita as duas vias.


// ---------------------------------------------------------------------------
echo "\nmaterializacao: o debito derivado vira lancamento\n";
// ---------------------------------------------------------------------------

// Chamada com prazo contabil VENCIDO, dentro da data de corte, com um associado (paga
// taxa) e um nao associado (paga o preco com margem e nao paga taxa).
$cha_mat = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1,'2026-06-20 23:59:59','2026-06-01 00:00:00','2026-06-15 23:59:59',0.05, NOW() - INTERVAL 2 DAY)");
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade)
    VALUES (" . (int)$cha_mat . "," . (int)$prod_res_id . ",1)");

// associado: 10 x venda 10 = 100, mais 5% = 105
$ped_m1 = insere("INSERT INTO pedidos (ped_cha, ped_usr, ped_nuc, ped_fechado, ped_usr_associado)
    VALUES (" . (int)$cha_mat . "," . (int)$usr_assoc . "," . (int)$nuc_res . ",1,'1')");
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_m1 . "," . (int)$prod_res_id . ",10,10)");

// nao associado: 10 x venda_margem 13 = 130, sem taxa
$ped_m2 = insere("INSERT INTO pedidos (ped_cha, ped_usr, ped_nuc, ped_fechado, ped_usr_associado)
    VALUES (" . (int)$cha_mat . "," . (int)$usr_nao . "," . (int)$nuc_res . ",1,'0')");
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_m2 . "," . (int)$prod_res_id . ",10,10)");

$prev = debitos_a_materializar($cha_mat);

function linha_mat($prev, $usr) {
    foreach ((array)$prev as $l) if ($l['usr_id'] === (int)$usr) return $l;
    return null;
}

verifica("a previa mostra o que vai ser gravado, sem gravar",
    is_array($prev) && count($prev) === 2
    && ($a = linha_mat($prev, $usr_assoc)) && round($a['valor'],2) == 105.00
    && ($b = linha_mat($prev, $usr_nao))   && round($b['valor'],2) == 130.00,
    is_array($prev) ? json_encode($prev) : var_export($prev, true));

verifica("a taxa e so do associado, como no debito derivado",
    ($a = linha_mat($prev, $usr_assoc)) && round($a['taxa'],2) == 5.00
 && ($b = linha_mat($prev, $usr_nao))   && round($b['taxa'],2) == 0.00);

verifica("e nada foi gravado ainda",
    valor_escalar("SELECT COUNT(*) FROM transacoes WHERE tra_tipo='debito_entrega' AND tra_cha = " . (int)$cha_mat) == 0);

// ---- materializa ----
$m = materializa_debitos_da_chamada($cha_mat);
verifica("materializa os dois, somando 235",
    is_array($m) && $m['lancados'] === 2 && $m['pulados'] === 0 && round($m['valor'],2) == 235.00,
    var_export($m, true));

$con_assoc = conta_do_cestante($usr_assoc);
$con_rede_m = conta_da_rede();
$tra_m = valor_escalar("SELECT t.tra_id FROM transacoes t JOIN lancamentos l ON l.lan_tra=t.tra_id
    WHERE t.tra_tipo='debito_entrega' AND t.tra_cha = " . (int)$cha_mat . "
      AND l.lan_con = " . (int)$con_assoc);
$p = pernas_de($tra_m);
verifica("as pernas sao cestante -105 e Rede +105",
    round($p[$con_assoc],2) == -105.00 && round($p[$con_rede_m],2) == 105.00,
    json_encode($p));

verifica("e a chamada fica gravada na transacao, para o extrato dizer de onde veio",
    valor_escalar("SELECT tra_cha FROM transacoes WHERE tra_id = " . (int)$tra_m) == $cha_mat);

// A MESMA CONTA das duas maneiras: o valor materializado tem de bater com o que o debito
// derivado dizia. Se as duas regras divergirem, e aqui que aparece.
verifica("o valor materializado bate com o que o debito derivado calculava",
    ($a = linha_mat($prev, $usr_assoc)) && round($a['valor'],2) == 105.00
    && round(-$p[$con_assoc],2) == round($a['valor'],2));

// ---- idempotencia ----
$m2 = materializa_debitos_da_chamada($cha_mat);
verifica("rodar de novo nao dobra divida de ninguem",
    is_array($m2) && $m2['lancados'] === 0 && $m2['pulados'] === 2,
    var_export($m2, true));

verifica("e o saldo do cestante nao mudou",
    round(saldo_da_conta($con_assoc), 2) == -105.00,
    var_export(saldo_da_conta($con_assoc), true));

// A entrega ja lancada SAI da lista de derivados, senao o cestante veria a mesma entrega
// duas vezes e o saldo dobraria a divida.
$ext_m = extrato_do_cestante($usr_assoc);
$quantas_dessa_cha = 0;
foreach ((array)$ext_m as $l) if ((int)$l['cha'] === (int)$cha_mat) $quantas_dessa_cha++;
verifica("a entrega ja lancada aparece UMA vez no extrato, e nao duas",
    $quantas_dessa_cha === 1, "aparicoes = $quantas_dessa_cha");

// ---- o que e recusado ----
$cha_cedo_m = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1,'2026-06-27 23:59:59','2026-06-01 00:00:00','2026-06-22 23:59:59',0.05, NOW() + INTERVAL 10 DAY)");
verifica("chamada com prazo contabil no futuro NAO materializa",
    materializa_debitos_da_chamada($cha_cedo_m) === null);

$cha_sem_prazo = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual)
    VALUES (1,'2026-06-28 23:59:59','2026-06-01 00:00:00','2026-06-24 23:59:59',0.05)");
verifica("chamada SEM prazo contabil nao materializa — nao ha o que congelar",
    materializa_debitos_da_chamada($cha_sem_prazo) === null);

// Antes da data de corte a contabilidade nao comecou: materializar ali criaria divida que
// a Rede ja considera resolvida.
$cha_velha_m = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1,'2013-06-20 23:59:59','2013-06-01 00:00:00','2013-06-15 23:59:59',0.05,'2013-07-01 00:00:00')");
verifica("chamada anterior a data de corte nao materializa",
    materializa_debitos_da_chamada($cha_velha_m) === null);

verifica("chamada que nao existe devolve null",
    materializa_debitos_da_chamada(99999999) === null
 && debitos_a_materializar(99999999) === null);

// CONTRATO da familia.
executa_sql("CREATE TEMPORARY TABLE pedidoprodutos (
    pedprod_ped int(10) unsigned NOT NULL, pedprod_prod mediumint(6) unsigned NOT NULL) ENGINE=InnoDB");
$sombra_m = (executa_sql("SELECT pedprod_entregue FROM pedidoprodutos") === false);
$m_sem_bd = debitos_a_materializar($cha_mat);
executa_sql("DROP TEMPORARY TABLE pedidoprodutos");

verifica("a sombra faz o servidor recusar a previa", $sombra_m);
verifica("previa de consulta recusada e null, e nao 'nada a materializar'",
    $m_sem_bd === null, var_export($m_sem_bd, true));

// A fila olha os DOIS lados. A chamada da materializacao nao guarda estoque nenhum, e
// mesmo assim precisa aparecer — o que ela tem a fechar sao os debitos.
$fila_m = chamadas_a_fechar('2026-06-01', '2026-07-01');
$fm = na_fila($fila_m, $cha_mat);

verifica("chamada sem estoque mas com debitos entra na fila",
    $fm !== null && $fm['debitos']['ja_lancados'] === 2 && $fm['debitos']['a_lancar'] === 0,
    var_export($fm, true));

verifica("e aparece como fechada, porque nao sobra nada dos dois lados",
    $fm !== null && $fm['fechada'] === true && abs($fm['estoque']['falta']) < 0.005);

// Chamada nova, com entrega e sem materializar: pendente pelo lado do debito.
$cha_fm = insere("INSERT INTO chamadas (cha_prodt, cha_dt_entrega, cha_dt_min, cha_dt_max, cha_taxa_percentual, cha_dt_prazo_contabil)
    VALUES (1,'2026-06-21 23:59:59','2026-06-01 00:00:00','2026-06-16 23:59:59',0.00, NOW() - INTERVAL 1 DAY)");
executa_sql("INSERT INTO chamadaprodutos (chaprod_cha, chaprod_prod, chaprod_disponibilidade)
    VALUES (" . (int)$cha_fm . "," . (int)$prod_res_id . ",1)");
$ped_fm = insere("INSERT INTO pedidos (ped_cha, ped_usr, ped_nuc, ped_fechado, ped_usr_associado)
    VALUES (" . (int)$cha_fm . "," . (int)$usr_assoc . "," . (int)$nuc_res . ",1,'1')");
executa_sql("INSERT INTO pedidoprodutos (pedprod_ped, pedprod_prod, pedprod_quantidade, pedprod_entregue)
    VALUES (" . (int)$ped_fm . "," . (int)$prod_res_id . ",7,7)");

$fp = na_fila(chamadas_a_fechar('2026-06-01','2026-07-01'), $cha_fm);
verifica("chamada com debito por congelar entra como PENDENTE, com quantos e quanto",
    $fp !== null && $fp['fechada'] === false && $fp['debitos']['a_lancar'] === 1
                 && round($fp['debitos']['valor'],2) == 70.00,
    var_export($fp, true));



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
