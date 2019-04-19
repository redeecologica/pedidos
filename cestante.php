<?php  
  require  "common.inc.php"; 

	$action = request_get("action",-1);
	if($action==-1) redireciona(PAGINAPRINCIPAL);
	$usr_id =  request_get("usr_id","");
  
    verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO] || ($_SESSION["usr.id"]== $usr_id) );
  
  top();
?>

<?php

		$gera_primeira_senha = request_get("gera_primeira_senha","");

		if($gera_primeira_senha!="")
		{
			$sucesso_cria_senha = gera_primeira_senha_acesso($usr_id);

			if($sucesso_cria_senha)
			{
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Senha de primeiro acesso foi gerada e enviada para o email principal do cestante, com cópia para os emails alternativos.");				
			}
			else
			{
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar gerar senha de primeiro acesso e enviá-la ao cestante.");					
			}
			escreve_mensagem_status();			
		}
		
		
		if ( $action == ACAO_INCLUIR) // exibe formulário vazio para inserir novo registro
		{
			$usr_nome_completo = "";
			$usr_nome_curto = "";
			$usr_email = "";
			$usr_email_alternativo = "";			
			$usr_contatos = "";
			$usr_endereco = "";
			$usr_nuc = "";
			$usr_archive = "";		
			$nuc_nome_curto = "";		
			$usr_associado=0;
			$usr_asso=-1;
			$usr_desde="";	
			$usr_atividades="";							
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{			
			 $sucesso=false;			 
			 $salvar = true;
			 
 			 $sql = "SELECT usr_nome_completo FROM usuarios WHERE usr_email=" . prep_para_bd(request_get('usr_email',""));
			 $sql.= " AND usr_id <> " . prep_para_bd($usr_id) ;
			 $res = executa_sql($sql);
			 if($res && mysqli_num_rows($res))
			 {
				$row = mysqli_fetch_array($res,MYSQLI_ASSOC);
			 	$usr_outro = $row["usr_nome_completo"];
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Já existe cestante cadastrado com o email " . request_get('usr_email',""). ". Favor informar outro e tantar salvar novamente; ou então solicitar ao responsável no núcleo a utilização da respectiva conta, hoje em nome de '$usr_outro'.");		
				$salvar=false;		
			 }	
			 
			 $sql = "SELECT usr_nome_completo FROM usuarios WHERE usr_nuc = " . prep_para_bd(request_get('usr_nuc',""));
			 $sql.= " AND usr_nome_curto = " . prep_para_bd(request_get('usr_nome_curto',"")) ;
			 $sql.= " AND usr_archive='0' AND usr_id <> " . prep_para_bd($usr_id) ;
			 $res = executa_sql($sql);
			 if($res && mysqli_num_rows($res))			 
			 {
				 $row = mysqli_fetch_array($res,MYSQLI_ASSOC);
			 	$usr_outro = $row["usr_nome_completo"];
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Já existe cestante neste núcleo cadastrado com o nome curto " . request_get('usr_nome_curto',""). ". Favor informar outro e tantar salvar novamente; ou então solicitar ao responsável no núcleo a utilização do respectivo nome, hoje associado a '$usr_outro'.");				
				$salvar = false;
			 }
			 
			 if($salvar)		 
			 {		
				 $usr_desde = request_get("usr_desde","");					  
				 if ($usr_desde=="") $bd_usr_desde = 'Null';
				 else $bd_usr_desde = prep_para_bd(formata_data_para_mysql($usr_desde));
				 
				 if($usr_id=="")
				 {						 
					 $sql="INSERT INTO usuarios (usr_nome_completo, usr_nome_curto, usr_contatos, usr_endereco, usr_email, usr_email_alternativo,  ";
					 $sql.="usr_nuc, usr_archive, usr_associado, usr_atividades, usr_desde) ";
					 $sql.=" VALUES ( ";					 
					 $sql.=trim(prep_para_bd(request_get("usr_nome_completo",""))) . ", ";
					 $sql.=trim(prep_para_bd(request_get("usr_nome_curto",""))) . ", ";
					 $sql.=trim(prep_para_bd(request_get("usr_contatos",""))) . ", ";
					 $sql.= trim(prep_para_bd(request_get("usr_endereco",""))) . ", ";
					 $sql.= trim(prep_para_bd(request_get("usr_email",""))) . ", ";
					 $sql.= trim(prep_para_bd(request_get("usr_email_alternativo",""))) . ", ";	
					 $sql.= prep_para_bd(request_get("usr_nuc","")) . ", ";
					 $sql.= prep_para_bd(request_get("usr_archive","")) . ", ";
					 $sql.= prep_para_bd(request_get("usr_associado","")) . ", ";
					 $sql.= prep_para_bd(request_get("usr_ass","")) . ", "; 
					 $sql.= trim(prep_para_bd(request_get("usr_atividades",""))) . ", ";
					 $sql.= $bd_usr_desde . " ) ";
					 					 
				 }
				 else
				 {
					 $sql="UPDATE usuarios SET ";
					 $sql.="usr_nome_completo = " . trim(prep_para_bd(request_get("usr_nome_completo",""))) . ", ";
					 $sql.="usr_nome_curto = " . trim(prep_para_bd(request_get("usr_nome_curto",""))) . ", ";
					 $sql.="usr_contatos = " . trim(prep_para_bd(request_get("usr_contatos",""))) . ", ";
					 $sql.="usr_endereco = " . trim(prep_para_bd(request_get("usr_endereco",""))) . ", ";
					 $sql.="usr_email = " . trim(prep_para_bd(request_get("usr_email",""))) . ", ";
					 $sql.="usr_email_alternativo = " . trim(prep_para_bd(request_get("usr_email_alternativo",""))) . ", ";	
					 $sql.="usr_nuc = " . prep_para_bd(request_get("usr_nuc","")) . ", ";
					 $sql.="usr_archive = " . prep_para_bd(request_get("usr_archive","")) . ", ";
					 $sql.="usr_associado = " . prep_para_bd(request_get("usr_associado","")) . ", ";
					 $sql.="usr_asso = " . prep_para_bd(request_get("usr_asso","")) . ", ";				 
					 $sql.="usr_atividades = " . trim(prep_para_bd(request_get("usr_atividades",""))) . ", ";
					 $sql.="usr_desde = " . $bd_usr_desde . " ";
					 $sql.="WHERE usr_id = " . prep_para_bd(request_get("usr_id",""));
				 }
				  				 
				 $res = executa_sql($sql);				 
				 if($usr_id=="") $usr_id = id_inserido();	
				 if($res)
				 {
					  $sucesso = true;
					  
					  
					if(request_get('usr_nuc',0)!=0)
          			{	   
					  
						// atualiza pedidos em aberto para o novo núcleo e para o novo status de associação  
						$sql = "UPDATE pedidos SET ";
						$sql.= " ped_usr_associado =  " . prep_para_bd(request_get('usr_associado',0)) . ", ";
						if (request_get('usr_archive',0)==1) $sql.= " ped_fechado =  0, "; // caso o cestante tenha ficado inativo, cancela o pedido em aberto
						$sql.= " ped_nuc =  " . prep_para_bd(request_get('usr_nuc',0)) . " ";
						$sql.= " WHERE ped_usr=" . prep_para_bd($usr_id) . " AND ";	
						$sql.= " ped_cha IN( SELECT cha_id FROM chamadas where cha_dt_max > now() ) ";
						$res2 = executa_sql($sql);
						if(!$res2) $sucesso = false;
						
						// remove produtos que não estão disponíveis para este novo núcleo do cestante
						$sql = "UPDATE pedidoprodutos SET ";
						$sql.= " pedprod_quantidade = '0' ";		
						$sql.= " WHERE ";
						$sql.= " pedprod_ped IN  ";
						$sql.= "   (SELECT ped_id FROM pedidos LEFT JOIN chamadas ON ped_cha = cha_id WHERE ped_usr=" . prep_para_bd($usr_id) . " AND  cha_dt_max > now() ) ";
						$sql.= " AND pedprod_prod NOT IN ";
						$sql.= "   (SELECT prod_id FROM produtos LEFT JOIN fornecedores ON prod_forn = forn_id ";
						$sql.= "     LEFT JOIN nucleofornecedores ON prod_forn = nucforn_forn ";
						$sql.= "     WHERE nucforn_nuc=" .prep_para_bd(request_get('usr_nuc',0)) . ")";
						$res3 = executa_sql($sql);
						if(!$res3) $sucesso = false;
					}

				 }
				 if(!$sucesso)
				 {
					adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar algumas informações do(a) cestante " . $_REQUEST["usr_nome_curto"] . ".");								 
				 }
			 }
			 
			 if($sucesso) 
			 {
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do(a) cestante " . $_REQUEST["usr_nome_curto"] . " salvas com sucesso.");				
			 }
			 else
			 {
				
				$usr_nome_completo = request_get('usr_nome_completo',"");
				$usr_nome_curto = request_get('usr_nome_curto',"");
				$usr_email = request_get('usr_email',"");
				$usr_email_alternativo = request_get('usr_email_alternativo',"");			
				$usr_contatos = request_get('usr_contatos',"");
				$usr_endereco = request_get('usr_endereco',"");
				$usr_nuc = request_get('usr_nuc',"");
				$usr_archive = request_get('usr_archive',"");		
				$nuc_nome_curto = "";		
				$usr_associado=request_get('usr_associado',0);
				$usr_asso=request_get('usr_asso',-1);	
				$usr_desde=request_get('usr_desde',"");	
				$usr_atividades=request_get('usr_atividades',"");					
							
				
			 }
			escreve_mensagem_status();
			 
		 
		}


		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição
		{
		  $sql = "SELECT usr_nome_completo, usr_nome_curto, usr_email, usr_email_alternativo, usr_contatos, usr_endereco, usr_nuc, usr_archive, ";
		  $sql.= "usr_associado, usr_asso, asso_nome, asso_descricao, DATE_FORMAT(usr_desde,'%d/%m/%Y') usr_desde, ";
		  $sql.= "usr_senha is null as usr_sem_senha_acesso, nuc_nome_curto, usr_atividades ";
		  $sql.= "FROM usuarios ";
		  $sql.= "LEFT JOIN nucleos ON usr_nuc = nuc_id ";
		  $sql.= "LEFT JOIN associacaotipos ON usr_asso = asso_id ";		  
		  $sql.= "WHERE usr_id=". prep_para_bd($usr_id);
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {		  
			$usr_nome_completo = $row["usr_nome_completo"];
			$usr_nome_curto = $row["usr_nome_curto"]; 
			$usr_email = $row["usr_email"]; 
			$usr_email_alternativo = $row["usr_email_alternativo"]; 			
			$usr_contatos = $row["usr_contatos"];
			$usr_endereco = $row["usr_endereco"];
			$usr_nuc = $row["usr_nuc"];						
			$usr_archive = $row["usr_archive"];
			$usr_associado = $row["usr_associado"];
			$usr_asso = $row["usr_asso"];
			$asso_nome = $row["asso_nome"];
			$asso_descricao = $row["asso_descricao"];
			$nuc_nome_curto = $row['nuc_nome_curto'];
			$usr_sem_senha_acesso = $row['usr_sem_senha_acesso'];
			$usr_desde = $row['usr_desde'];
			$usr_atividades = $row['usr_atividades'];			

		   }
		}		
		
?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações do Cestante</strong>
  </div>
 <div class="panel-body">

<table class="table-condensed table-info-cadastro" style="width: inherit !important;">
		<tbody>
    		<tr>
				<th style="width: 1%; white-space: nowrap;">Nome Completo:</th> <td><?php echo($usr_nome_completo); ?></td>
			</tr>	    
    		<tr>
				<th>Nome Curto:</th> <td><?php echo($usr_nome_curto); ?></td>
			</tr>            
    		<tr>
				<th>Email Principal:</th>	<td><?php echo($usr_email); ?></td>
			</tr>

    		<tr>
				<th>Emails Adicionais:</th>	<td><?php echo($usr_email_alternativo); ?></td>
			</tr>
            
    		<tr>
				<th>Contatos:</th> <td><?php echo($usr_contatos); ?></td>
			</tr>        
    		<tr>
				<th>Endereço:</th> <td><?php echo($usr_endereco); ?></td>
			</tr>    
	   		<tr>
				<th>Data de Entrada:</th> <td><?php echo($usr_desde); ?></td>
			</tr>                   
             
			<tr>
				<th>Atividades Atuais:</th> <td><?php echo($usr_atividades); ?></td>
			</tr>   
            
                         
    		<tr>
				<th>Núcleo:</th> <td><?php echo($nuc_nome_curto); ?></td>
			</tr> 
    		<tr>
				<th>Associado:</th> <td><?php echo( ($usr_associado==1)?"Sim":"Não"); ?></td>
			</tr>  
    		<tr>
				<th>Tipo Associação:</th> <td><?php echo( $asso_nome); ?>  <?php adiciona_popover_descricao("Associação " . $asso_nome, $asso_descricao); ?></td>
			</tr>                        
                       
    		<tr>
				<th>Situação:</th> 
                <td>
					<?php echo( ($usr_archive==1)?"Inativo":"Ativo"); ?>
                 
                     <?php 
                if($usr_archive==0 && $usr_sem_senha_acesso && ($_SESSION[PAP_ADM]  || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_RESP_PEDIDO] ) )
                 {							 
                 ?>               
                    <span class='text-error'> (sem senha cadastrada)</span> &nbsp;
				<button type="button" class="btn btn-danger btn-sm btn-enviando" data-loading-text="gerando..." onclick="javascript:location.href='cestante.php?action=<?php echo(ACAO_EXIBIR_LEITURA); ?>&usr_id=<?php echo($usr_id); ?>&gera_primeira_senha=1'">
            <i class="glyphicon glyphicon-lock glyphicon-white"></i> Gerar primeira senha de acesso</button>
                         		                     
                 <?php 					 
                 }
                 
                 ?>
                 
                </td>
			</tr>                                    
                        
            
        </tbody>    
  </table>
  </div>
    
    <div class="panel-footer">
      		<div class="col-sm-offset-2">    
         	 	<a class="btn btn-primary" href="cestante.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&usr_id=<?php echo($usr_id); ?>"><i class="glyphicon glyphicon-edit glyphicon-white"></i> editar</a>
         	&nbsp;&nbsp;
	         		<?php 
					if($_SESSION[PAP_ADM]  || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_RESP_PEDIDO] )
					  { 
					 ?>
                     	  <a class="btn btn-default" href="cestantes.php"><i class="glyphicon glyphicon-list"></i> listar cestantes</a> 
            		<?php
					   } // fim do if tem permisao
					
					?>
              </div>
    
   	 </div>
    
  </div>
  
   
	
<?php 

	
 }
 else  //visualização para edição
 {

?>
 <form id="form_cestante" class="form-horizontal" role="form" action="cestante.php" method="post">
  <fieldset>
        
    <div class="panel panel-default">
      <div class="panel-heading">
         <strong>Atualização de Informações do Cestante</strong>
      </div>
	  <div class="panel-body">
         
          <input type="hidden" name="usr_id" value="<?php echo($usr_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
            <div class="form-group">
               <label class="control-label col-sm-2" for="usr_nome_completo">Nome Completo</label>
                 <div class="col-sm-4">
                   <input type="text" name="usr_nome_completo"  class="form-control" required="required" value="<?php echo($usr_nome_completo); ?>" placeholder="Nome Completo"/>
                  </div>
            </div>
            
            <div class="form-group">
               <label class="control-label col-sm-2" for="usr_nome_curto">Nome Curto</label>
                   <div class="col-sm-2">
                       <input type="text" class="form-control" name="usr_nome_curto" required="required" value="<?php echo($usr_nome_curto); ?>" placeholder="Nome Curto" />                    
                    </div>  
	                 <span class="help-block">Preferencialmente com no máximo 10 caracteres, para economizar na impressão de relatórios</span>
            </div>
            
             <div class="form-group">
                   <label class="control-label col-sm-2" for="usr_email">Email Principal</label>
                   <div class="col-sm-4">   
                    <input type="text" class="form-control" required="required" id="usr_email" name="usr_email" value="<?php echo($usr_email); ?>" placeholder="Email" /><br />                  
    			   </div>
                     <span class="help-block"> Utilizado para identificar a associação no sistema (login).</span>
            </div>        
       

             <div class="form-group">
                <label class="control-label col-sm-2" for="usr_email_alternativo">Emails Adicionais</label>
                  <div class="col-sm-4">
                    <textarea name="usr_email_alternativo" id="usr_email_alternativo" rows="3" class="form-control" placeholder="Emails adicionais para recebimento das comunicações."><?php echo($usr_email_alternativo); ?></textarea>
                  </div>
					<span class="help-block">Emails adicionais para recebimento das comunicações. Bastante útil para quem compartilha associação.<br>Informar valores separados por vírgula (ex.: fulano@dominio.com.br, ciclano@dominio.com.br)</span>
             </div>
                      
       
             <div class="form-group">
                <label class="control-label col-sm-2" for="usr_contatos">Contatos</label>
                  <div class="col-sm-4">
                    <textarea name="usr_contatos" rows="2" required="required"  class="form-control" placeholder="ex.: 8888-9999/2333-4567"><?php echo($usr_contatos); ?></textarea>
                    <br>                    
                  </div>
                  <span class="help-block">Contatos (telefone celular, fixo,...). Ex.: 8888-9999 / 2333-4567</span>
            </div>
          
          
            <div class="form-group">
                <label class="control-label col-sm-2" for="usr_endereco">Endereço</label>
                  <div class="col-sm-4">
                    <textarea name="usr_endereco" rows="4"  class="form-control" placeholder="Endereço"><?php echo($usr_endereco); ?></textarea>
                  </div>
            </div>  
            
            <div class="form-group">
                <label class="control-label col-sm-2" for="usr_desde">Data de Entrada</label>
                  <div class="col-sm-2">
                  	<input type="text"  value="<?php echo($usr_desde); ?>" required="required" class="data form-control" name="usr_desde" id="usr_desde"/ >                    
                  </div>
			<span class="help-block">Ex.: 15/09/2010. Data estimada em que você entrou na <?php echo(NOME_GRUPO_CONSUMO); ?>.</span>
            </div>      
            
  <div class="form-group">
                <label class="control-label col-sm-2" for="usr_atividades">Atuais atividades na <?php echo(NOME_GRUPO_CONSUMO); ?></label>
                  <div class="col-sm-4">
                    <textarea name="usr_atividades" rows="6" required="required"  class="form-control" placeholder="ex.: Acolhida, Comissão Gestora,..."><?php echo($usr_atividades); ?></textarea>
                    <br>                    
                  </div>
                  <span class="help-block">Exemplos: Acolhida; Comissão Gestora; Participação em mutirões no ano; Acompanhamento Produtor Ecobio;  Acompanhamento Produtor Biorga; Acompanhamento Produtor Amarea; ...</span>
            </div>                   


			<?php 
			   if($_SESSION[PAP_ADM]  || $_SESSION[PAP_RESP_NUCLEO] || $_SESSION[PAP_RESP_PEDIDO] )
                  { 
             ?>
             		
                     <?php
					 
					 
                                $sql = "SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega_formatado, ";
								$sql.= "DATE_FORMAT(cha_dt_max,'%d/%m/%Y %H:%i') cha_dt_max_formatado, ";
								$sql.= "cha_dt_max > now() dentro_prazo_edicao, ";						
								$sql.= "prodt_nome, ped_fechado, ped_usr_associado ";								
                                $sql.= "FROM usuarios ";
								$sql.= "LEFT JOIN pedidos ON ped_usr = usr_id ";
								$sql.= "LEFT JOIN chamadas ON ped_cha = cha_id ";
								$sql.= "LEFT JOIN produtotipos ON cha_prodt = prodt_id ";								
                                $sql.= "WHERE ped_usr=" . prep_para_bd($usr_id) . " AND ";	
								$sql.= " ( cha_dt_max > now() OR (cha_dt_max < now() AND cha_dt_entrega > now() AND ped_fechado='1' ) ) ";
								$sql.= "ORDER BY dentro_prazo_edicao DESC, cha_dt_entrega ";
                                $res3 = executa_sql($sql);

                                if($res3 && mysqli_num_rows($res3))
                                {
								  echo("<p class='bg-danger'><strong>Atenção ao alterar algum dos campos abaixo, pois há  pedido deste cestante ainda a ser entregue.</strong><br>");
								  $primeiro_fora = $primeiro_dentro = 1;
                                  while ($row = mysqli_fetch_array($res3,MYSQLI_ASSOC)) 
                                  {
									 if($row['dentro_prazo_edicao'])
									 {
										if($primeiro_dentro) echo("<br><strong>&nbsp; - Pedido(s) com chamada ainda em aberto:</strong><br>");
										$primeiro_dentro = 0;
									 }
									 else
									 {
										if($primeiro_fora) echo("<br><strong>&nbsp; - Pedido(s) com chamada já encerrada:</strong><br>");
										$primeiro_fora = 0;
									 }									 
									 echo("&nbsp; &nbsp; - " . $row['prodt_nome'] . " entrega em " . $row['cha_dt_entrega_formatado']);
									 echo(" (prazo edição pedido: " . $row['cha_dt_max_formatado'] . ") - ");
									 echo("Status pedido: " . ($row['ped_fechado']==1 ? "Enviado" : "Em elaboração") .  "<br>");	 									 
                                     
                                  } // end while
								  ?>
                                  <br><strong>Leia com atenção as orientações sobre o que ocorrerá com os pedidos ainda não entregues, para cada caso:</strong><br><br>
                                 
                                  <strong>Para pedido com chamada ainda em aberto:</strong><br>
                                   &nbsp; - Se alterar o núcleo do cestante, o núcleo de entrega do respectivo pedido será mudado para o novo núcleo. Como consequência: caso o pedido contenha produtos de produtores que não atendem este novo núcleo, estes produtos serão automaticamente removidos do pedido. <br>
                                  &nbsp; - Se alterar o status da associação (associado / não associado) do cestante, o status de associado do respectivo pedido será mudado para a nova situação.<br>
								  &nbsp; - Se alterar o cestante para inativo, seu respectivo pedido será automaticamente cancelado (vai para o status 'Em Elaboração'). Se por acaso o cestante retornar para ativo dentro deste período, precisará entrar no pedido e enviar novamente.<br>
                                  
                                  <br>
                                   <strong>Para pedido com chamada já encerrada:</strong><br>
                                 Para estes casos o pedido não pode mais ser alterado, pois se o prazo para edição terminou significa que a logística já começou. Então o pedido fica congelado. Se o cestante mudar de núcleo, precisará resgatar este pedido no núcleo antigo, onde será entregue. Se o cestante ficar inativo, precisará resolver o resgate / pagamento deste pedido. <br><br>                                 
                                  
                                  </p>
                                  
								  <?php
								  
                                } // end if tem pedidos a serem entregues
                    ?>   
                            
                    
                     <div class="form-group">
                      <label class="control-label col-sm-2" for="usr_nuc">Núcleo</label>
                      <div class="col-sm-3">                                       
                        <select name="usr_nuc" id="usr_nuc" class="form-control">
                            <option value="-1">SELECIONAR</option>
                            <?php
                                $sql = "SELECT nuc_id, nuc_nome_curto, nuc_archive ";
                                $sql.= "FROM nucleos ";
                                $sql.= "ORDER BY nuc_archive, nuc_nome_curto ";
                                $res2 = executa_sql($sql);
                                if($res2)
                                {
								  $arquivados=0;
                                  while ($row = mysqli_fetch_array($res2,MYSQLI_ASSOC)) 
                                  {
									 if(!$arquivados)
									 {
										 if($row["nuc_archive"]==1) 
										 {
											 echo("<option value='-1'>-------------</option>");									 
											 $arquivados=1;
										 }
									 }									 
                                     echo("<option value='" . $row['nuc_id'] . "'");
                                     if($row['nuc_id']==$usr_nuc) echo(" selected");
                                     echo (">" . $row['nuc_nome_curto'] . "</option>");									 
                                  }
                                }
								
								
                            ?>                        
                        </select>                
                      </div>                  
                    </div>  
    
             
                     <div class="form-group">
                       <label class="control-label col-sm-2" for="usr_associado">Associado</label>
                       <div class="col-sm-3">                
                         <select name="usr_associado" id="usr_associado" class="form-control">
                            <option value="1" <?php echo( ($usr_associado)==1?" selected" : ""); ?> >Associado</option>
                            <option value="0" <?php echo( ($usr_associado)==0?" selected" : ""); ?> >Não Associado</option>            
                         </select>                       
                       </div>
                     </div>                 




                     <div class="form-group">
                      <label class="control-label col-sm-2" for="usr_nuc">Tipo Associação</label>
                      <div class="col-sm-3">                                       
                        <select name="usr_asso" id="usr_asso" class="form-control">
                            <option value="-1">SELECIONAR</option>
                            <?php
							    $help_tipos = "";
                                $sql = "SELECT asso_id, asso_nome, asso_descricao ";
                                $sql.= "FROM associacaotipos ";
                                $sql.= "ORDER BY asso_nome ";
                                $res2 = executa_sql($sql);
                                if($res2)
                                {
                                  while ($row = mysqli_fetch_array($res2,MYSQLI_ASSOC)) 
                                  {
                                     echo("<option value='" . $row['asso_id'] . "'");
                                     if($row['asso_id']==$usr_asso) echo(" selected");
                                     echo (">" . $row['asso_nome'] . "</option>");	
									 $help_tipos.="<b>" . $row['asso_nome'] . "</b>: ";
									 $help_tipos.=$row['asso_descricao'] . "<br><br>";
                                  }
                                }
                            ?>                        
                        </select>
                      </div>     
                      <?php adiciona_popover_descricao("Tipos de Associação", $help_tipos); ?>             
                    </div>  
                    


           
                     <div class="form-group">
                       <label class="control-label col-sm-2" for="usr_archive">Situação</label>
                       <div class="col-sm-2">                
                         <select name="usr_archive" id="usr_archive" class="form-control">
                            <option value="0" <?php echo( ($usr_archive)==0?" selected" : ""); ?> >Ativo</option>
                            <option value="1" <?php echo( ($usr_archive)==1?" selected" : "");?> >Inativo</option>            
                         </select>                   
                       </div>
                     </div>
                     
                     

            <?php    
				  } // fim do if tem permissão edição
				  else
				  {
					 ?> 
                      <input type="hidden" name="usr_nuc" value="<?php echo($usr_nuc); ?>">
                      <input type="hidden" name="usr_archive" value="<?php echo($usr_archive); ?>">
                      <input type="hidden" name="usr_associado" value="<?php echo($usr_associado); ?>">
                      <input type="hidden" name="usr_asso" value="<?php echo($usr_asso); ?>">
                      
                                                   
                     <div class="form-group">
                      <label class="control-label col-sm-2" for="usr_nuc">Núcleo</label>
                      <div class="col-sm-2">                                       
                       	<span class="well well-sm"><?php echo($nuc_nome_curto); ?></span>
                      </div>                  
                    </div>  
        
                     <div class="form-group">
                       <label class="control-label col-sm-2" for="usr_associado">Associado</label>
                       <div class="col-sm-2">                
							<span class="well well-sm"><?php echo(($usr_associado)==1?"Sim" : "Não"); ?></span>
                       </div>
                     </div>                 
                     

                     <div class="form-group">
                      <label class="control-label col-sm-2" for="usr_nuc">Tipo Associação</label>
                      <div class="col-sm-2">                                       
                       	<span class="well well-sm"><?php echo($asso_nome); ?></span> <?php adiciona_popover_descricao("Associação " . $asso_nome, $asso_descricao); ?>
                      </div>                  
                    </div>  


           
                     <div class="form-group">
                       <label class="control-label col-sm-2" for="usr_archive">Situação</label>
                       <div class="col-sm-2">                
							<span class="well well-sm"><?php echo(($usr_archive)==0?"Ativo" : "Não Ativo"); ?></span>
	                  </div>
                     </div>     
                     
                                          
		
            <?php		  
				  } // fim do if (tem permissão)
            ?>
               
         
            
               
                    
	</div> 

   <div class="panel-footer">          
		  <div class="form-group">
	          <div class="col-sm-offset-2">
                   <button class="btn btn-primary" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <?php 
				   
				   if($usr_id!="") 
				   {
				   ?>
                   <button class="btn btn-default" type="button" onclick="javascript:location.href='cestante.php?&action=<?php echo(ACAO_EXIBIR_LEITURA); ?>&usr_id=<?php echo($usr_id); ?>'"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>                   
				   <?php 
				   }
				   else
				   {
				   ?>
					<button class="btn btn-default" type="button" onclick="javascript:location.href='cestantes.php'"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>                  
                   
                   <?php
				   }
				   ?>

              </div>
          </div>  
     </div> <!-- div panel-footer --> 
     
  </div>  <!-- div panel -->         
          
      </fieldset> 
    </form>
    
    
<script type="text/javascript">
	$(function() {
		$(".data").datepicker({
			format: 'dd/mm/yyyy',
			language: 'pt-BR',
			autoclose: true
		});
		
		$("#form_cestante").submit(validaCestante);
	}); 
	
			
</script> 

	  

</script> 
    <?php  
   }

   footer();
?>
