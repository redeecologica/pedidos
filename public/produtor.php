<?php  
  require  "common.inc.php"; 
  
  $action = request_get("action",-1);
  if($action==-1) redireciona(PAGINAPRINCIPAL);
		
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO]  || $_SESSION[PAP_ACOMPANHA_PRODUTOR]);
  top();
?>

<?php
 

		
		$forn_id =  request_get("forn_id","");


		if ( $action == ACAO_INCLUIR) // exibe formulário vazio para inserir novo registro
		{
			$forn_prodt = "";
			$forn_nome_completo = "";
			$forn_nome_curto = "";
			$forn_email = "";
			$forn_contatos = "";
			$forn_endereco = "";
			$forn_archive = "";
			$forn_id = "";
			$forn_link_info = "";
			$forn_info_chamada = "";
		
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{
 			 $campos = array('forn_prodt','forn_nome_completo','forn_nome_curto','forn_contatos','forn_endereco','forn_email','forn_archive','forn_link_info','forn_info_chamada');  			
			 $sql=prepara_sql_atualizacao("forn_id",$campos,"fornecedores");
     		 
			 $res = executa_sql($sql);
			 
 			 if($forn_id=="") $forn_id = id_inserido();			 
			
			 if($res) 
				{							 
				// remove nucleos que não estão marcados
				$sql = "DELETE FROM nucleofornecedores ";
				$sql.= "WHERE nucforn_forn=". prep_para_bd($forn_id) . " ";	
				if(!empty($_REQUEST["nucforn_nuc"])) $sql.= "AND nucforn_nuc NOT IN (". str_replace(",","','",prep_para_bd(implode(",", $_REQUEST['nucforn_nuc']))) . ")";	
				$res = executa_sql($sql);			
	
				// insere nucleos marcados (somente os que já não estavam selecionados)
				if(!empty($_REQUEST['nucforn_nuc'])) 				
				{				
					$sql = "INSERT IGNORE INTO nucleofornecedores (nucforn_forn, nucforn_nuc) VALUES ";
					$primeiro = 1;
					foreach ($_REQUEST['nucforn_nuc'] as $nucforn_nuc) 
					{
						if($primeiro) $primeiro = 0; else $sql.= ", ";					
						$sql.= "( " . prep_para_bd($forn_id) . ", " . prep_para_bd($nucforn_nuc) . " ) ";
					}	
					$res = executa_sql($sql);	
				}
			 }				
			 			 
			 if($res) 
			 {
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do produtor " . $_REQUEST["forn_nome_curto"] . " salvas com sucesso.");				
				escreve_mensagem_status();
			 }		
			 
 			 
		}
		
		
		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição
		{
		  $sql = "SELECT fornecedores.*, prodt_nome FROM fornecedores ";
  		  $sql.= "LEFT JOIN produtotipos ON forn_prodt = prodt_id  ";
		  $sql.= "WHERE forn_id=" . prep_para_bd($forn_id) ;
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {	
		  	$forn_prodt = $row["forn_prodt"];
			$prodt_nome = $row["prodt_nome"];
			$forn_nome_completo = $row["forn_nome_completo"];
			$forn_nome_curto = $row["forn_nome_curto"];
			$forn_email = $row["forn_email"]; 
			$forn_contatos = $row["forn_contatos"];
			$forn_endereco = $row["forn_endereco"];			
			$forn_archive = $row["forn_archive"];
			$forn_link_info = $row["forn_link_info"];
			$forn_info_chamada = $row["forn_info_chamada"];
						
		   }
		   
			$sql =  "SELECT nuc_nome_curto FROM nucleofornecedores ";
			$sql.= "LEFT JOIN nucleos on nucforn_nuc =  nuc_id ";
			$sql.= "WHERE nucforn_forn = " . prep_para_bd($forn_id) . " ";
			$sql.= " ORDER BY nuc_nome_curto ";		
			$res = executa_sql($sql);
			$nucforn_nucleos = "nenhum";			
			if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
			{	
				$nucforn_nucleos=$row["nuc_nome_curto"];
				while($row = mysqli_fetch_array($res,MYSQLI_ASSOC))
				{			
					$nucforn_nucleos.= ", " . $row["nuc_nome_curto"];
				}			
			}
				   
		}		

?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações do Produtor</strong>
  </div>
 <div class="panel-body"> 

<table class="table-condensed table-info-cadastro">
		<tbody>
        
			<tr>
				<th>Tipo:</th> <td><?php echo($prodt_nome); ?></td>
			</tr>        
    		<tr>
				<th>Nome Completo:</th> <td><?php echo($forn_nome_completo); ?></td>
			</tr>	    
    		<tr>
				<th>Nome Curto:</th> <td><?php echo($forn_nome_curto); ?></td>
			</tr>            
    		<tr>
				<th>Email:</th>	<td><?php echo($forn_email); ?></td>
			</tr>
    		<tr>
				<th>Contatos:</th> <td><?php echo($forn_contatos); ?></td>
			</tr>        
    		<tr>
				<th>Endereço:</th> <td><?php echo($forn_endereco); ?></td>
			</tr>   
    		<tr>
				<th>Link com info do produtor para o cestante:</th> <td><?php echo($forn_link_info); ?></td>
			</tr>   
    		<tr>
				<th>Informações para chamada:</th> <td><?php echo(prep_para_html($forn_info_chamada)); ?></td>
			</tr>                
    		<tr>
				<th>Núcleos que atende:</th><td><?php echo(prep_para_html($nucforn_nucleos)); ?></td>
			</tr>  
    		<tr>
				<th>Situação:</th> <td><?php echo( ($forn_archive==1)?"Inativo":"Ativo"); ?></td>
			</tr>                                    
        </tbody>
    
</table>
  </div>  
  
        <div class="panel-footer">
      		<div class="col-sm-offset-2">
         	 	<a class="btn btn-primary" href="produtor.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&forn_id=<?php echo($forn_id); ?>"><i class="glyphicon glyphicon-edit glyphicon-white"></i> editar</a>
         	&nbsp;&nbsp;
         		<a class="btn btn-default" href="produtores.php"><i class="glyphicon glyphicon-list"></i> listar produtores</a>
             </div>
       </div>
       
  </div>       
    
   
	
<?php 

	
 }
 else
 {

?>
<form class="form-horizontal" action="produtor.php" method="post"  role="form">
   <fieldset>
    
    <div class="panel panel-default">
      <div class="panel-heading">
         <strong>Atualização de Informações do Produtor</strong>
      </div>
      
 	 <div class="panel-body">         
          <input type="hidden" name="forn_id" value="<?php echo($forn_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
          
          
          
                 <div class="form-group">
                   <label class="control-label col-sm-2" for="forn_prodt">Tipo</label>
                   <div class="col-sm-2">                
                     <select name="forn_prodt" id="forn_prodt" class="form-control">
                       	<option value="-1">SELECIONAR</option>
						<?php
                            
                            $sql = "SELECT prodt_id, prodt_nome ";
                            $sql.= "FROM produtotipos ";
                            $sql.= "ORDER BY prodt_nome ";
                            $res = executa_sql($sql);
                            if($res)
                            {
                              while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
                              {
                                 echo("<option value='" . $row['prodt_id'] . "'");
                                 if($row['prodt_id']==$forn_prodt) echo(" selected");
                                 echo (">" . $row['prodt_nome'] . "</option>");
                              }
                            }
                        ?>            
                     </select>                       
                   </div>
                 </div>
                 
                           
            <div class="form-group">
               <label class="control-label col-sm-2" for="forn_nome_completo">Nome Completo</label>
                 <div class="col-sm-6">
                   <input type="text" name="forn_nome_completo" required="required" value="<?php echo($forn_nome_completo); ?>" placeholder="Nome Completo" class="form-control"/>
                  </div>
            </div>
            
            <div class="form-group">
               <label class="control-label col-sm-2" for="forn_nome_curto">Nome Curto</label>
                 <div class="col-sm-4">
                   <input type="text" name="forn_nome_curto"  required="required" value="<?php echo($forn_nome_curto); ?>" placeholder="Nome Curto" class="form-control" />
                 </div>  
            </div>
            
             <div class="form-group">
                   <label class="control-label col-sm-2" for="forn_email">Email </label>
                   <div class="col-sm-4">   
                    <input type="text" class="form-control" name="forn_email" value="<?php echo($forn_email); ?>" placeholder="Email" />
    			   </div>
            </div>        
          
       
             <div class="form-group">
                <label class="control-label col-sm-2" for="forn_contatos">Contatos</label>
                  <div class="col-sm-5">
                    <textarea name="forn_contatos" rows="3"  class="form-control" placeholder="Contatos (telefone fixo, celular,...)"><?php echo($forn_contatos); ?></textarea>
                  </div>
            </div>
          
          
            <div class="form-group">
                <label class="control-label col-sm-2" for="forn_endereco">Endereço</label>
                  <div class="col-sm-5">
                    <textarea name="forn_endereco" rows="4"  class="form-control" placeholder="Endereço"><?php echo($forn_endereco); ?></textarea>
                  </div>
            </div>  
            
             <div class="form-group">
                   <label class="control-label col-sm-2" for="forn_link_info">Link para descrição</label>
                   <div class="col-sm-7">   
                    <input type="text" class="form-control" name="forn_link_info" value="<?php echo($forn_link_info); ?>" placeholder="Link com informações sobre o produtor para o cestante" />
    			   </div>
            </div>   
            
            <div class="form-group">
                <label class="control-label col-sm-2" for="forn_info_chamada">Informações para realizar chamada</label>
                  <div class="col-sm-7">
                    <textarea name="forn_info_chamada" rows="6"  class="form-control" placeholder="Informações do produtor para auxiliar na criação da chamada"><?php echo($forn_info_chamada); ?></textarea>
                  </div>
            </div>              
       

        

            <div class="form-group">
             <label class="control-label col-sm-2" for="nucforn_nuc">
             	Núcleos que atende<br>
                     <label class="checkbox">
                        <input id="marca_todos_nucleos" type="checkbox" value="*"> Marcar Todos
                    </label>                
             </label>             
             
             <div class="col-sm-9">
				<?php
					$sql =  "SELECT nuc_id, nuc_nome_curto, nucforn_nuc FROM nucleos ";
					$sql.= "LEFT JOIN nucleofornecedores on nucforn_nuc =  nuc_id ";
					$sql.= "AND nucforn_forn = " . ($forn_id == "" ? "'0'" : prep_para_bd($forn_id) ) . " ";
					$sql.= "WHERE nuc_archive='0' ORDER BY nuc_nome_curto ";
					$res = executa_sql($sql);					
				    if($res)
					{
					   $total = mysqli_num_rows($res);
					   $por_coluna = floor($total / 3);
					   $resto = fmod($total , 3);
					   $cont=0; $coluna=0;
					   while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				       {	
							$cont++;						
							if($cont == 1)
							{
								$coluna++;
								echo("<div class='col-sm-3'>");
							}		
							echo("<label class='checkbox'><input name='nucforn_nuc[]' type='checkbox' class='nucleos'");
							if($row["nucforn_nuc"]) 
							{
								echo(" checked='checked' ");
							}
							echo("value='" . $row["nuc_id"] . "'>" . $row["nuc_nome_curto"] );
							echo("</label>");				
							if($cont == $por_coluna + ($resto >= $coluna)  )
							{
								echo("</div>");
								$cont=0;
							}
					   }
					   if($cont!=0) echo("</div>");
	 			    }
					 
				   ?> 
              </div>              
            </div>    
            
            
       
                   <div class="form-group">
                <label class="control-label col-sm-2" for="forn_archive">Situação: </label>
                  <div class="col-sm-2">
                
                    <select name="forn_archive" id="forn_archive" class="form-control">
                        <option value="0" <?php echo( ($forn_archive ==0) ?" selected" : ""); ?> >Ativo</option>
                        <option value="1" <?php echo( ($forn_archive ==1) ?" selected" : ""); ?> >Inativo</option>            
                    </select>   
                    
                  </div>
            </div>  
	</div>  <!-- div panel-body --> 

   		<div class="panel-footer">          
		  <div class="form-group">
	          <div class="col-sm-offset-2">
                   <button class="btn btn-primary" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href='produtores.php'"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
              </div>
          </div>   
        </div>   <!-- div panel-footer -->        
     
  </div>  <!-- div panel --> 
            
          
      </fieldset> 
    </form>
    
    <?php  
   }

   footer();
?>
