<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO]);
  top();
?>

<?php
		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$prod_id =  request_get("prod_id","");

		if ( $action == ACAO_INCLUIR) // exibe formulário vazio para inserir novo registro
		{
			$prod_nome = "";
			$prod_prodt = "";
			$prod_forn = "";
			$prod_unidade = "";
			$prod_valor_compra = "";
			$prod_valor_venda = "";
			$prod_valor_venda_margem = "";
			$prod_multiplo_venda = "";
			$prod_descricao = "";
			$forn_nome_curto = "";
			$prodt_nome = "";
	
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{	
			if($prod_id=="") // inclusão de novo produto
			{				
				$sql="SELECT (MAX(prod_id)+1) AS proximo_prod_id FROM produtos";			
				if($row = mysqli_fetch_array(executa_sql($sql),MYSQLI_ASSOC))
				{
					$prod_id = $row["proximo_prod_id"];
				}				
				
			}
			else // produto já existia; esquema para salvar histórico dos valores
			{
					$sql = "UPDATE produtos SET ";
					$sql.= "prod_fim_validade = NOW() ";
					$sql.= "WHERE prod_ini_validade <= NOW() AND prod_fim_validade >= NOW() ";
					$sql.= "AND prod_id=". prep_para_bd($prod_id);
					$res = executa_sql($sql);
			}

			$sql = "INSERT INTO produtos (prod_id, prod_ini_validade, prod_fim_validade, prod_nome, prod_prodt, prod_forn, ";
			$sql.= "prod_unidade, prod_valor_compra, prod_valor_venda, prod_valor_venda_margem, prod_multiplo_venda, prod_descricao) ";
			$sql.= " VALUES (". prep_para_bd($prod_id) . ", NOW(), '9999-12-31', ";
			$sql.= prep_para_bd($_REQUEST["prod_nome"]) . ", ";
			$sql.= prep_para_bd($_REQUEST["prod_prodt"]) . ", ";
			$sql.= prep_para_bd($_REQUEST["prod_forn"]) . ", ";
			$sql.= prep_para_bd($_REQUEST["prod_unidade"]) . ", ";
			$sql.= prep_para_bd(formata_numero_para_mysql($_REQUEST["prod_valor_compra"])) . ", ";
			$sql.= prep_para_bd(formata_numero_para_mysql($_REQUEST["prod_valor_venda"])) . ", ";
			$sql.= prep_para_bd(formata_numero_para_mysql($_REQUEST["prod_valor_venda_margem"])) . ", ";			
			$sql.= prep_para_bd(formata_numero_para_mysql($_REQUEST["prod_multiplo_venda"])) . ", ";									
			$sql.= prep_para_bd($_REQUEST["prod_descricao"]) . ") ";			

	   		 $res = executa_sql($sql);
			 $prod_auto_inc = id_inserido();
			 if($res) 
			 {
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do produto " . $_REQUEST["prod_nome"] . " salvas com sucesso.");	
			 }
			 else
			 {
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações do produto " . $_REQUEST["prod_nome"] . ".");				 
			 }
			 escreve_mensagem_status();
			 
		}
		
		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição
		{
		  $sql = "SELECT prod_auto_inc, prod_nome, prod_prodt, prod_forn, prod_unidade, FORMAT(prod_valor_compra,2) prod_valor_compra,  FORMAT(prod_valor_venda,2) prod_valor_venda,  FORMAT(prod_valor_venda_margem,2) prod_valor_venda_margem, FORMAT(prod_multiplo_venda,2) prod_multiplo_venda, prod_descricao, forn_nome_curto, prodt_nome FROM produtos ";
		  $sql.= "LEFT JOIN fornecedores ON prod_forn = forn_id  ";
		  $sql.= "LEFT JOIN produtotipos ON prod_prodt = prodt_id  ";
		  $sql.= "WHERE prod_ini_validade <= NOW() AND prod_fim_validade >= NOW() ";
		  $sql.= "AND prod_id=". prep_para_bd($prod_id);
		  $sql.= " ORDER BY prod_ini_validade DESC ";
		  
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {	
		  	$prod_auto_inc = $row["prod_auto_inc"];	  
			$prod_nome = $row["prod_nome"];
			$prod_prodt = $row["prod_prodt"];
			$prod_forn = $row["prod_forn"];
			$prod_unidade = $row["prod_unidade"];
			$prod_valor_compra = formata_moeda($row["prod_valor_compra"]);
			$prod_valor_venda = formata_moeda($row["prod_valor_venda"]);
			$prod_valor_venda_margem = formata_moeda($row["prod_valor_venda_margem"]);
			$prod_multiplo_venda = formata_moeda($row["prod_multiplo_venda"]);
			$prod_descricao =  $row["prod_descricao"];
			$forn_nome_curto = $row["forn_nome_curto"];
			$prodt_nome = $row["prodt_nome"];

		   }
		}	

?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
 <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações do Produto</strong>
  </div>
 <div class="panel-body">
 
<table class="table-condensed table-info-cadastro">
		<tbody>
			<tr>
				<th>Tipo:</th> <td><?php echo($prodt_nome); ?></td>
			</tr>
            <tr>
				<th>Produtor:</th> <td><?php echo($forn_nome_curto); ?></td>
			</tr>        
    		<tr>
				<th>Nome:</th> <td><?php echo($prod_nome); ?></td>
			</tr>	    
    		<tr>
				<th>Unidade:</th> <td><?php echo($prod_unidade); ?></td>
			</tr>            
    		<tr>
				<th>Compra:</th><td>R$ <?php echo($prod_valor_compra); ?></td>
			</tr>
    		<tr>
				<th>Venda:</th> <td>R$ <?php echo($prod_valor_venda); ?></td>
			</tr>        
    		<tr>
				<th>Venda com Margem:</th><td>R$ <?php echo($prod_valor_venda_margem); ?></td>
			</tr>     
    		<tr>
				<th>Pedido Mínimo:</th> <td><?php echo($prod_multiplo_venda); ?></td>
			</tr>                               
    		<tr>
				<th>Descrição:</th> <td><?php echo( prep_para_html($prod_descricao) ); ?></td>
			</tr>	    
        </tbody>
    
  </table>
  
  </div>
        <div class="panel-footer">
      		<div class="col-sm-offset-2">
         	 	<a class="btn btn-primary" href="produto.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&prod_id=<?php echo($prod_id); ?>"><i class="glyphicon glyphicon-edit glyphicon-white"></i> editar</a>
         	&nbsp;&nbsp;
         		<a class="btn btn-default" href="produtos.php"><i class="glyphicon glyphicon-list"></i> listar produtos</a>             </div>
       </div>
       
 </div>
   
	
<?php 

	
 }
 else  //visualização para edição
 {

?>
 <form id="form_produto" class="form-horizontal" action="produto.php" method="post">
    <fieldset>        
        <div class="panel panel-default">
          <div class="panel-heading">
             <strong>Atualização de Informações do Produto</strong>
          </div>      
        
         <div class="panel-body">
                 
          <input type="hidden" name="prod_id" value="<?php echo($prod_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  

                 <div class="form-group">
                   <label class="control-label col-sm-2" for="prod_prodt">Tipo</label>
                   <div class="col-sm-2">                
                     <select name="prod_prodt" id="prod_prodt" class="form-control">
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
                                 if($row['prodt_id']==$prod_prodt) echo(" selected");
                                 echo (">" . $row['prodt_nome'] . "</option>");
                              }
                            }
                        ?>            
                     </select>                       
                   </div>
                 </div>
                 

                 <div class="form-group">
                  <label class="control-label col-sm-2" for="prod_forn">Produtor</label>
                  <div class="col-sm-4">                                       
                    <select name="prod_forn" id="prod_forn" class="form-control">
                    	<option value="-1">SELECIONAR</option>
						<option value="-1">-------------</option>                                            
						<?php
                            
							$sql = "SELECT forn_id, forn_archive, forn_nome_curto ";
							$sql.= "FROM fornecedores ";
							$sql.= "ORDER BY forn_archive, forn_nome_curto ";
							$res = executa_sql($sql);
							if($res)
							{
							  $arquivados=0;
							  while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
							  {
								 if(!$arquivados)
								 {
									 if($row["forn_archive"]==1) 
									 {
										 echo("<option value='-1'>-------------</option>");									 
										 $arquivados=1;
									 }
								 }
								 echo("<option value='" . $row['forn_id'] . "'");
								 if($row['forn_id']==$prod_forn) echo(" selected");
								 echo (">" . $row['forn_nome_curto'] . "</option>");
							  }
							}
				   
							?>                    
                    </select>    
               
                                                   
                  </div>                  
            	</div>  

       
                 


            <div class="form-group">
               <label class="control-label col-sm-2" for="prod_nome">Nome</label>
                 <div class="col-sm-4">
                   <input type="text" name="prod_nome" class="form-control" required="required" value="<?php echo($prod_nome); ?>" placeholder="Nome"/>
                  </div>
            </div>
            
  			<div class="form-group">
                <label class="control-label col-sm-2" for="prod_descricao">Descrição</label>
                  <div class="col-sm-4">
                    <textarea name="prod_descricao" rows="3"  class="form-control" placeholder="Descrição"><?php echo($prod_descricao); ?></textarea>
                  </div>
            </div>
          
            
             <div class="form-group">
                   <label class="control-label col-sm-2" for="prod_unidade">Unidade</label>
                   <div class="col-sm-2">   
                    <input type="text" required="required"  class="form-control" name="prod_unidade" value="<?php echo($prod_unidade); ?>" placeholder="ex.: 1dz, 250g, 1kg,..." />
    			   </div>
            </div>        
          
       
             <div class="form-group">
                <label class="control-label col-sm-2" for="prod_valor_compra">Compra (R$)</label>
                  <div class="col-sm-2">
                   <input type="text" required="required"  class="form-control numero" name="prod_valor_compra" value="<?php echo($prod_valor_compra); ?>"/>
                  </div>
             </div>


             <div class="form-group">
                <label class="control-label col-sm-2" for="prod_valor_venda">Venda (R$)</label>
                  <div class="col-sm-2">                    
					<input type="text" required="required"  class="form-control numero" name="prod_valor_venda" value="<?php echo($prod_valor_venda); ?>" />                    
                  </div>
            </div>
            
      		 <div class="form-group">
                <label class="control-label col-sm-2" for="prod_valor_venda_margem">Venda com Margem (R$)</label>
                  <div class="col-sm-2">                    
					<input type="text" required="required"  class="form-control numero" name="prod_valor_venda_margem" value="<?php echo($prod_valor_venda_margem); ?>" />                    
                  </div>
            </div>
          
          
            
      		 <div class="form-group">
                <label class="control-label col-sm-2" for="prod_multiplo_venda">Pedido Mínimo</label>
                  <div class="col-sm-2">                    
					<input type="text" required="required"  class="form-control numero" name="prod_multiplo_venda" value="<?php echo($prod_multiplo_venda); ?>" />                    
                  </div>
            </div>

	</div>  <!-- div panel-body --> 

   		<div class="panel-footer">          
		  <div class="form-group">
	          <div class="col-sm-offset-2">
                   <button class="btn btn-primary" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href='produtos.php'"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
              </div>
          </div>   
        </div>   <!-- div panel-footer -->        
     
  </div>  <!-- div panel --> 
            
          
          
      </fieldset> 
    </form>
<script type="text/javascript">
	$(function() {
		$("#form_produto").submit(validaProduto);
		$(".numero").bind('keydown', keyCheck);
		$(".numero").on('blur', validaNumero);
	}); 
</script> 
    
    <?php  
   }

   footer();
?>
