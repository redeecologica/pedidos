<?php

require_once("phpmailer/class.phpmailer.php");
require_once("settings.php");

session_start();

define('PAGINAPRINCIPAL', "inicio.php");

//nome dos papéis tal como na tabela papeis
define('PAP_ADM',"Administrador");
define('PAP_RESP_NUCLEO',"Responsável por Núcleo");
define('PAP_RESP_PEDIDO',"Responsável por Pedido");
define('PAP_RESP_MUTIRAO',"Responsável pelo Mutirão");
define('PAP_BETA_TESTER',"Beta Tester");
define('PAP_ACOMPANHA_PRODUTOR',"Acompanhamento de Produtor");
define('PAP_ACOMPANHA_RELATORIOS',"Acompanhamento Relatórios");
 

define('ACAO_EXIBIR_LEITURA',0);
define('ACAO_EXIBIR_EDICAO',1);
define('ACAO_SALVAR',2);
define('ACAO_INCLUIR',3);

define('ACAO_EXCLUIR',4);

define('ACAO_CONFIRMAR_PEDIDO',5);
define('ACAO_CANCELAR_PEDIDO',6);

define('MSG_TIPO_ERRO',3);
define('MSG_TIPO_AVISO',2);
define('MSG_TIPO_INFO',1);
define('MSG_TIPO_SUCESSO',0);

define('TAXA_ASSOCIADO',0.03);

define('URL_ABSOLUTA', "http://" . $_SERVER["SERVER_NAME"]. substr($_SERVER["PHP_SELF"],0,strrpos($_SERVER["PHP_SELF"],"/")));	

$meses = array("","janeiro", "fevereiro", "março", "abril", "maio", "junho", "julho", "agosto", "setembro", "outubro", "novembro", "dezembro");

$msg_tipo_erros = array(MSG_TIPO_SUCESSO => "success", MSG_TIPO_INFO => "info", MSG_TIPO_AVISO => "warning", MSG_TIPO_ERRO => "danger");

date_default_timezone_set ('America/Sao_Paulo');

$conn_link = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME );

if (mysqli_connect_errno()) 
{
	redireciona("bd_fora.php");
}

mysqli_set_charset($conn_link,'utf8');




function verifica_seguranca($parametro_validacao = true)
{
	$validado = false;
	
	if(( isset($_SESSION["usr.id"]) && strlen($_SESSION["usr.id"]) ) )
	{
		if(isset($_SESSION[PAP_ADM]) && $_SESSION[PAP_ADM] ) $validado = true;
		else
		{
			$validado = $parametro_validacao;			
		}
	}
	
	if(! $validado )
	{
		if(isset($_SESSION["usr.id"]) && strlen($_SESSION["usr.id"]))
		{
			 adiciona_mensagem_status(MSG_TIPO_ERRO,"Usuário não possui permissão para a ação executada.");
			 $pagina=PAGINAPRINCIPAL;
		}
		else $pagina="login.php";
		
		header("Location:$pagina");
		redireciona("$pagina");
		
		exit();
	}
}

function redireciona($pagina)
{
	require_once("registro_visita.inc.php");
	echo("<script>location.href='$pagina'</script>");
	exit();
}

function prep_para_bd($texto)
{
		global $conn_link;
		return "'" . mysqli_real_escape_string($conn_link,$texto) . "'";
}


function prep_para_html($texto)
{
		$quebra_linha = array("\r\n", "\n", "\r");
		$sem_special = htmlspecialchars($texto, ENT_QUOTES);
		return str_replace($quebra_linha,'<br />',$sem_special);
		
}

function adiciona_popover_descricao($titulo,$texto)
{
	if(isset($texto) && $texto!="")
	{
		echo(" <span class='btn-popover' data-content='" .  prep_para_html($texto) . "' data-html='true' data-title='" . $titulo . "' data-trigger='hover'><i class='glyphicon glyphicon-info-sign'></i></span>");
	}	
}

	
				


function formata_numero_para_mysql($numero)
{
	return str_replace('_',',',str_replace(',','.',str_replace('.','_',$numero)));
}

function formata_numero_de_mysql($numero)
{
	return str_replace('_','.',str_replace('.',',',str_replace(',','_',$numero)));
}

function formata_moeda($numero)
{
	return number_format($numero, 2, ',', '.');
}



function formata_data_para_mysql($data)
{

	return date_format(date_create_from_format("d/m/Y",$data),"Y-m-d");
}

function formata_data_hora_para_mysql($data)
{
	return date_format(date_create_from_format("d/m/Y G:i",$data),"Y-m-d G:i:00");
}

function prepara_sql_atualizacao($key_field,$fields,$table,$key_value = "") 
{ 
	$key_value_temp = request_get($key_field,$key_value);

    if ($key_value_temp!="") 
	{ 
        $update_fields = array(); 
        foreach ($fields as $field) 
		{ 
			if(isset($_REQUEST[$field]))
			{
            	$update_fields[] = "$field = ". prep_para_bd(($_REQUEST[$field])); 
			}
        } 
        return "UPDATE $table SET " . join(',',$update_fields) . 
               " WHERE $key_field = ". $key_value_temp; 
    } 
	else 
	{ 
        $insert_values = array(); 
        foreach ($fields as $field) 
		{ 
			if(isset($_REQUEST[$field]))
			{
            	$insert_values[] = prep_para_bd(($_REQUEST[$field])); 
			}
        } 
        return "INSERT INTO $table (" . join(',',$fields) .  
               ") VALUES (" . join(',',$insert_values) . ')'; 
    } 

} 



function top($titulo = "Sistema de Pedidos Online")
{
	require("header.inc.php"); 
}


function footer()
{
	require("footer.inc.php");
}

function request_get($parametro, $valor_padrao)
{
	$retorno = $valor_padrao;
	if(isset($_POST["$parametro"])) $retorno = $_POST["$parametro"];
	else if(isset($_GET["$parametro"])) $retorno = $_GET["$parametro"];
	return $retorno;
}


function id_inserido()
{
	global $conn_link;
	return mysqli_insert_id($conn_link);	
}

function executa_sql($sql,$aborta_se_erro = 0) {

	global $conn_link;
	
    if(empty($sql) OR !($conn_link)) 
		return 0;
	
   $res = mysqli_query($conn_link, $sql);
   
   if(!$res && $aborta_se_erro) die(mysql_error());

  	//echo "sql = $sql;";
				 
    return $res;
}

function adiciona_mensagem_status($tipo, $texto) 
{
	if(!isset($_SESSION['msg.type'])) $_SESSION['msg.type'] = $tipo;
	else if($_SESSION['msg.type'] < $tipo) $_SESSION['msg.type'] = $tipo; 
	
	if(isset($_SESSION['msg.text']) && $_SESSION['msg.text']!="") $_SESSION['msg.text'] .= "<br />" . $texto;
	else $_SESSION['msg.text'] = $texto;
	
}

function escreve_mensagem_status() 
{
	global $msg_tipo_erros;
  
	if(isset($_SESSION["msg.type"]) && $_SESSION["msg.type"]!=-1 && isset($_SESSION["msg.text"]) && $_SESSION["msg.text"]!="") 
	{
	  ?>

	  <div class="alert alert-<?php echo($msg_tipo_erros[$_SESSION["msg.type"]]);?>">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		 <?php echo(ltrim($_SESSION["msg.text"]));?>
	  
      </div>
      
	
	  <?php
	  $_SESSION["msg.text"]="";
	  $_SESSION["msg.type"]=-1;	  
	}
}


function get_usr_from_ped_id($ped_id)
{
	$sql = "SELECT ped_usr FROM pedidos WHERE ped_id = " . prep_para_bd($ped_id) ;
	$ped_usr="";
	if($row = mysqli_fetch_array(executa_sql($sql),MYSQLI_ASSOC))
	{
		$ped_usr = $row["ped_usr"];
	}	
	return $ped_usr;
}

function pedido_esta_dentro_do_prazo($ped_id)
{
	$sql = "SELECT cha_dt_max > NOW() as no_prazo FROM chamadas INNER JOIN pedidos on ped_cha = cha_id WHERE ped_id = " . prep_para_bd($ped_id) ;
	$no_prazo = false;
	if($row = mysqli_fetch_array(executa_sql($sql),MYSQLI_ASSOC))
	{
		$no_prazo = $row["no_prazo"];
	}	
	return $no_prazo;
}


function envia_email_cestante($usr_id,$assunto,$corpo_html,$corpo_texto)
{
	$sql = "SELECT usr_email, usr_email_alternativo, usr_nome_curto FROM usuarios WHERE usr_id = " . prep_para_bd($usr_id) ;
	if($row = mysqli_fetch_array(executa_sql($sql),MYSQLI_ASSOC))
	{
		$email_cc = (isset($row["usr_email_alternativo"]) && $row["usr_email_alternativo"]<>"") ? $row["usr_email_alternativo"] : "" ;
		return envia_email($row["usr_nome_curto"],$row["usr_email"],$email_cc, $assunto,$corpo_html,$corpo_texto);
	}
	return false;	
}

function envia_email($dest_nome, $dest_email, $dest_cc, $assunto,$corpo_html,$corpo_texto )
{ 
	// $dest_cc é separado por vírgula
	

	 
	$mail = new PHPMailer();
	 
	$mail->IsSMTP(); 
//	$mail->SMTPDebug = 2;
	$mail->Host = MAIL_HOST;
	$mail->Port = MAIL_PORT;
	$mail->SMTPAuth = true; 
	$mail->SMTPSecure = MAIL_SECURE;                 
	$mail->Username = MAIL_USER;
	$mail->Password = MAIL_PASS;
	$mail->From = MAIL_FROM;
	$mail->Sender = MAIL_SENDER;
	$mail->FromName = MAIL_FROM_NAME;
	 
	$mail->AddAddress($dest_email, $dest_nome);
	
	if($dest_cc!="")
	{		
		$emails_cc = explode(',', $dest_cc);
		foreach ($emails_cc as $email_cc)
		{
			$mail->AddCC($email_cc);
		}
	}

	$mail->CharSet = 'UTF-8';  
	$mail->Subject  = $assunto; 
	if($corpo_html=="")
	{
		$mail->IsHTML(false); 
		$mail->Body = $corpo_texto;
	}
	else
	{
		$mail->IsHTML(true); 
		$mail->Body = $corpo_html;
		$mail->AltBody = $corpo_texto;					
	}
	 
	$enviado = $mail->Send();
	 
	$mail->ClearAllRecipients();
	$mail->ClearAttachments();
	$mail->SmtpClose();
	
	 
	return $enviado;
	
}



function gera_primeira_senha_acesso($usr_id)
{
	$sucesso = false;
	$senha_gerada="";
	
	$sql="SELECT pass_nome FROM  temp_senhas ORDER BY RAND( ) LIMIT 1";		
	$res = executa_sql($sql);
	if($res && mysqli_num_rows($res))
	{
		$row = mysqli_fetch_array($res,MYSQLI_ASSOC);
		$senha_gerada = $row["pass_nome"];
		$sql = "UPDATE usuarios  SET usr_senha = " . prep_para_bd(crypt($senha_gerada,PASSWORD_SALT));
		$sql.= " WHERE usr_id = " . prep_para_bd($usr_id);	
		$res2 = executa_sql($sql);
		if($res2) $sucesso = true;	
		
		if($sucesso)
		{
			$sql="SELECT usr_email FROM usuarios WHERE usr_id = " . prep_para_bd($usr_id);		
			$res2 = executa_sql($sql);	
			$row = mysqli_fetch_array($res2,MYSQLI_ASSOC);
			$usr_email = $row["usr_email"];								
		}
	
	}	

	if($sucesso)
	{		
		$mensagem = "Sua conta foi criada no " . NOME_SISTEMA . ". Seja bem-vindo(a). \n\n";
		
		$mensagem.= "Para entrar no sistema, acesse o endereço " . URL_ABSOLUTA . " e, ao ser solicitado(a) pelo login e senha, informe:\n\n";
		$mensagem.= "login: $usr_email\n";
		$mensagem.="senha: $senha_gerada\n\n";
				
		$mensagem.="Esta é uma senha gerada automaticamente para que você possa realizar o primeiro acesso.\n";
		$mensagem.="A qualquer momento você poderá alterá-la: após fazer login no sistema, vá na opção 'Minha Conta' e depois 'Alterar Senha'.\n\n";			
		
		$mensagem.=get_texto_interno("txt_email_final_info_conta");
		
		
		return envia_email_cestante($usr_id,"Informações para Acesso ao " . NOME_SISTEMA ,"",$mensagem);
	}
	
	return false;
		
}

function get_hifen_se_zero($valor)
{
	if ($valor=="0,0" || $valor=="0") return "-";
	return $valor;
}


function get_ultima_chamada_pelo_tipo($prodt_id)
{
	$cha_id_anterior="";
		
	$sql = "SELECT cha_id FROM chamadas WHERE cha_prodt = " . prep_para_bd($prodt_id) . " ORDER BY cha_dt_entrega DESC limit 1" ;
	if($row = mysqli_fetch_array(executa_sql($sql),MYSQLI_ASSOC))
	{
		$cha_id_anterior = $row["cha_id"];
	}
	return 	$cha_id_anterior;	
}

function get_chamada_anterior($cha_id)
{
	$cha_id_anterior="";
	$cha_id_bd = prep_para_bd($cha_id);
	
	$sql = "SELECT cha_id FROM chamadas WHERE cha_prodt in (SELECT cha_prodt from chamadas where cha_id=" . $cha_id_bd  . ") AND cha_dt_entrega < (SELECT cha_dt_entrega FROM chamadas WHERE cha_id=" . $cha_id_bd  . ") AND cha_id <> " . $cha_id_bd  . " ORDER BY cha_dt_entrega DESC limit 1" ;
	if($row = mysqli_fetch_array(executa_sql($sql),MYSQLI_ASSOC))
	{
		$cha_id_anterior = $row["cha_id"];
	}
	return 	$cha_id_anterior;	
}
  
function importar_estoque_anterior($cha_id)
{
	
	$cha_id_anterior=get_chamada_anterior($cha_id);
	
	if($cha_id_anterior!="")
	{
		$sql = " INSERT INTO estoque (est_cha, est_prod, est_prod_qtde_antes) ";
		$sql.= " SELECT " . prep_para_bd($cha_id) . ", est_prod, est_prod_qtde_depois ";
		$sql.= " FROM estoque estoque_anterior WHERE estoque_anterior.est_cha = " . prep_para_bd($cha_id_anterior);
		$sql.= " ON DUPLICATE KEY UPDATE est_prod_qtde_antes = estoque_anterior.est_prod_qtde_depois ";
		$res = executa_sql($sql);		
		if($res) return true;
	}
	
	return false;
		
}

function get_texto_interno($nome_interno)
{
	
	$sql="SELECT txt_conteudo_publicado FROM textos where txt_nome_curto = " . prep_para_bd($nome_interno);		
	$res = executa_sql($sql);
	if($res && mysqli_num_rows($res))
	{
		$row = mysqli_fetch_array($res,MYSQLI_ASSOC);
		return $row['txt_conteudo_publicado'];
	}
	return "";
}

function chaprod_recebido_get_sum_dist_quantidade($cha_id)
{
	// função utilizada quando o tipo de chamada não tem mutirão, ou seja, quando no processo não ocorre uma contagem geral 
	// dos produtos recebidos antes da distribuição aos núcleos, então o recebido pela rede passa a ser o somatório 
	// da quantidade recebida pelos núcleos
	
	$sql = "UPDATE chamadaprodutos cp1 ";
	$sql.= "INNER JOIN ";
	$sql.= "( ";
	$sql.= " SELECT chaprod_cha, chaprod_prod, SUM(dist_quantidade) total_acumulado ";
	$sql.= " FROM chamadaprodutos ";
	$sql.= " INNER JOIN chamadas ON cha_id = chaprod_cha ";
	$sql.= " INNER JOIN produtos ON prod_id = chaprod_prod ";
	$sql.= " INNER JOIN distribuicao ON dist_cha = chaprod_cha AND dist_prod = chaprod_prod ";
	$sql.= " WHERE chaprod_cha= " . prep_para_bd($cha_id) .  " AND chaprod_disponibilidade <> '0' AND ";
	$sql.= " prod_ini_validade<=cha_dt_entrega AND prod_fim_validade>=cha_dt_entrega ";
	$sql.= " GROUP BY prod_id ";
	$sql.= ") cp2 ON cp1.chaprod_cha = cp2.chaprod_cha AND cp1.chaprod_prod = cp2.chaprod_prod ";
	$sql.= " SET cp1.chaprod_recebido = cp2.total_acumulado ";
	$sql.= " WHERE cp1.chaprod_cha= " . prep_para_bd($cha_id);		
	$res2 = executa_sql($sql);	
}



?>
