<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_ADM]);
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$prodt_id =  request_get("prodt_id","");		

		if ( $action ==  ACAO_INCLUIR) // inserir
		{
			$prodt_nome = "";
			$prodt_mutirao = 0;	
			$prodt_taxa_percentual_padrao = formata_moeda(0);	
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{	
			if($prodt_id=="")
			{
				$sql = "INSERT INTO produtotipos (prodt_nome,prodt_mutirao,prodt_taxa_percentual_padrao) VALUES (" ;
				$sql.= prep_para_bd($_REQUEST["prodt_nome"]) . ", ";
				$sql.= prep_para_bd($_REQUEST["prodt_mutirao"]) . ", ";
				$sql.= prep_para_bd(formata_numero_para_mysql($_REQUEST["prodt_taxa_percentual_padrao"])) . ") " ;				
				
				$res = executa_sql($sql);
				$prodt_id = id_inserido();
			}
			else
			{
			
				$sql = "UPDATE produtotipos SET " ;
				$sql.= " prodt_nome = " .  prep_para_bd($_REQUEST["prodt_nome"]) . ", ";
				$sql.= " prodt_mutirao = " .  prep_para_bd($_REQUEST["prodt_mutirao"]) . ", ";
				$sql.= " prodt_taxa_percentual_padrao = " . prep_para_bd(formata_numero_para_mysql($_REQUEST["prodt_taxa_percentual_padrao"])) . " " ;				
				$sql.= " WHERE prodt_id = " .  prep_para_bd($prodt_id) . " ";								
				$res = executa_sql($sql);				
			}
		 
			 if($res) 
			 {
				
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do tipo " . $_REQUEST["prodt_nome"] . " salvas com sucesso.");
			 }
			 else
			 {
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações do tipo " . $_REQUEST["prodt_nome"] . ".");				 
			 }
			 escreve_mensagem_status();			 			 			 
			 		
		}
		

		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição		
		{
		  $sql = "SELECT prodt_nome, prodt_mutirao, FORMAT(prodt_taxa_percentual_padrao,2) as prodt_taxa_percentual_padrao FROM produtotipos WHERE prodt_id=". prep_para_bd($prodt_id) ;
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {		  
			$prodt_nome = $row["prodt_nome"];
			$prodt_mutirao = $row["prodt_mutirao"];
			$prodt_taxa_percentual_padrao = formata_moeda($row["prodt_taxa_percentual_padrao"]);		
		   }
		}		

?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
 <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações do Tipo de Produto / Chamada</strong>
  </div>
 <div class="panel-body"> 

<table class="table-condensed table-info-cadastro">
		<tbody>
    		<tr>
				<th>Nome: </th> <td><?php echo($prodt_nome); ?></td>
			</tr>	    
 
    		<tr>
				<th>Associado à funcionalidade Mutirão?</th> <td><?php echo( ($prodt_mutirao==1)?"Sim":"Não"); ?></td>
			</tr>                                    
            
    		<tr>
				<th>Taxa Percentual Padrão: </th> <td><?php echo($prodt_taxa_percentual_padrao); ?></td>
			</tr>	    

            
        </tbody>
    
</table>
  
   </div>  
  
        <div class="panel-footer">
      		<div class="col-sm-offset-2">
         	 	<a class="btn btn-primary" href="produtotipo.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&prodt_id=<?php echo($prodt_id); ?>"><i class="glyphicon glyphicon-edit glyphicon-white"></i> editar</a>
         	&nbsp;&nbsp;
         		<a class="btn btn-default" href="produtotipos.php"><i class="glyphicon glyphicon-list"></i> listar tipos</a>
             </div>
       </div>
       
  </div>       
    
	
<?php 

	
 }
 else
 {

?>
<form id="form_nucleo" class="form-horizontal" action="produtotipo.php" method="post">
    <fieldset>
    <div class="panel panel-default">
      <div class="panel-heading">
         <strong>Atualização de Informações do Tipo de Produto/Chamada</strong>
      </div>
    
     <div class="panel-body">        
          <input type="hidden" name="prodt_id" value="<?php echo($prodt_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
            <div class="form-group">
               <label class="control-label col-sm-3" for="prodt_nome">Nome</label>
                 <div class="col-sm-4">
                   <input type="text" name="prodt_nome" class="form-control" required="required" value="<?php echo($prodt_nome); ?>"/>
                  </div>
            </div>
      
       
                   <div class="form-group">
                <label class="control-label col-sm-3" for="nuc_archive">Tem funcionalidade Mutirão? </label>
                  <div class="col-sm-2">
                
                    <select name="prodt_mutirao" id="prodt_mutirao" class="form-control">
                        <option value="0" <?php echo(($prodt_mutirao==0)?" selected" : ""); ?> >Não</option>
                        <option value="1" <?php echo(($prodt_mutirao==1)?" selected" : ""); ?> >Sim</option>            
                    </select>   
                    
                  </div>
            </div>  
            
              <div class="form-group">
               <label class="control-label col-sm-3" for="prodt_taxa_percentual_padrao">Taxa Percentual Padrão</label>
                 <div class="col-sm-2">
                   <input type="text" name="prodt_taxa_percentual_padrao" class="form-control" required="required" value="<?php echo($prodt_taxa_percentual_padrao); ?>"/>
                  </div>
                  <span class="help-block">Taxa aplicável no final do pedido para o caso de associados. Se 3%, informar 0,03. Informar no máximo 2 casas decimais. </span>
            </div>
            
            
          
        </div>  <!-- div panel-body --> 
    
            <div class="panel-footer">          
              <div class="form-group">
                  <div class="col-sm-offset-2">
                   <button class="btn btn-primary" type="submit"><i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href='produtotipos.php'"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>              </div>   
            </div>   <!-- div panel-footer -->        
         
      </div>  <!-- div panel -->            
      </fieldset> 
    </form>


    <?php  
   }

   footer();
?>
