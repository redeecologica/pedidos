<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_PEDIDO] || $_SESSION[PAP_RESP_NUCLEO]);
  top();
?>

<?php

		$action = request_get("action",-1);
		if($action==-1) redireciona(PAGINAPRINCIPAL);

		$nuc_id =  request_get("nuc_id","");		

		if ( $action ==  ACAO_INCLUIR) // inserir
		{
			$nuc_nome_completo = "";
			$nuc_nome_curto = "";
			$nuc_email = "";
			$nuc_entrega_horario = "";
			$nuc_entrega_endereco = "";
			$nuc_archive = "";		
		}
		else if ($action == ACAO_SALVAR) // salvar formulário preenchido
		{
			
 			 $campos = array('nuc_nome_completo','nuc_nome_curto','nuc_entrega_horario','nuc_entrega_endereco','nuc_email','nuc_archive');  			
			 $sql=prepara_sql_atualizacao("nuc_id",$campos,"nucleos");
     		 $res = executa_sql($sql);
			 if($nuc_id=="") $nuc_id=id_inserido();	
			 
			 if($res) 
			 {
				$action=ACAO_EXIBIR_LEITURA; //volta para modo visualização somente leitura
				adiciona_mensagem_status(MSG_TIPO_SUCESSO,"Informações do núcleo " . $_REQUEST["nuc_nome_curto"] . " salvas com sucesso.");
			 }
			 else
			 {
				adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações do núcleo " . $_REQUEST["nuc_nome_curto"] . ".");				 
			 }
			 escreve_mensagem_status();			 			 			 
			 		
		}
		

		if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO)  // exibir para visualização, ou exibir para edição		
		{
		  $sql = "SELECT * FROM nucleos WHERE nuc_id=". prep_para_bd($nuc_id) ;
 		  $res = executa_sql($sql);
  	      if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
		  {		  
			$nuc_nome_completo = $row["nuc_nome_completo"];
			$nuc_nome_curto = $row["nuc_nome_curto"];
			$nuc_email = $row["nuc_email"]; 
			$nuc_entrega_horario = $row["nuc_entrega_horario"];
			$nuc_entrega_endereco = $row["nuc_entrega_endereco"];			
			$nuc_archive = $row["nuc_archive"];
		
		   }
		}		

?>

<?php 
 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações do Núcleo</strong>
  </div>
 <div class="panel-body">
 
<table class="table-condensed table-info-cadastro">
		<tbody>
    		<tr>
				<th>Nome Completo:</th> <td><?php echo($nuc_nome_completo); ?></td>
			</tr>	    
    		<tr>
				<th>Nome Curto:</th> <td><?php echo($nuc_nome_curto); ?></td>
			</tr>            
    		<tr>
				<th>Email de Contato:</th>	<td><?php echo($nuc_email); ?></td>
			</tr>
    		<tr>
				<th>Horário de Entrega:</th> <td><?php echo($nuc_entrega_horario); ?></td>
			</tr>        
    		<tr>
				<th>Endereço de Entrega:</th> <td><?php echo($nuc_entrega_endereco); ?></td>
			</tr>     
    		<tr>
				<th>Situação:</th> <td><?php echo( ($nuc_archive==1)?"Inativo":"Ativo"); ?></td>
			</tr>                                           
        </tbody>
    
 </table>
  </div>
  
      <div class="panel-footer">
      		<div class="col-sm-offset-2">
         	 	<a class="btn btn-primary" href="nucleo.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&nuc_id=<?php echo($nuc_id); ?>"><i class="glyphicon glyphicon-edit glyphicon-white"></i> editar</a>
         	&nbsp;&nbsp;
         		<a class="btn btn-default" href="nucleos.php"><i class="glyphicon glyphicon-list"></i> listar núcleos</a>
             </div>
       </div>
    
 </div> 
   
	
<?php 

	
 }
 else
 {

?>
   <form id="form_nucleo" class="form-horizontal" role="form" action="nucleo.php" method="post">
       <fieldset>
<div class="panel panel-default">
  <div class="panel-heading">
     <strong>Atualização de Informações do Núcleo</strong>
  </div>

 <div class="panel-body">

          <input type="hidden" name="nuc_id" value="<?php echo($nuc_id); ?>" />
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />  
            <div class="form-group">
               <label class="control-label col-sm-2" for="nuc_nome_completo">Nome Completo</label>
                <div class="col-sm-4">
                   <input type="text" name="nuc_nome_completo" class="form-control" required="required" value="<?php echo($nuc_nome_completo); ?>" placeholder="Nome Completo"/>
                   </div>
            </div>
            
            <div class="form-group">
               <label class="control-label col-sm-2" for="nuc_nome_curto">Nome Curto</label>
               <div class="col-sm-2">
                   <input type="text" name="nuc_nome_curto" class="form-control"  required="required" value="<?php echo($nuc_nome_curto); ?>" placeholder="Nome Curto" />
                  </div>
            </div>
            
             <div class="form-group">
                   <label class="control-label col-sm-2" for="nuc_email">Email </label>
                   <div class="col-sm-4">
                    <input type="text" class="form-control" id="nuc_email" name="nuc_email" value="<?php echo($nuc_email); ?>" placeholder="Email" />
                    </div>
            </div>        
          
       
             <div class="form-group">
                <label class="control-label col-sm-2" for="nuc_entrega_horario">Horário de Entrega</label>
                <div class="col-sm-4">
                    <textarea name="nuc_entrega_horario" rows="3"  class="form-control"  placeholder="Horário de Entrega"><?php echo($nuc_entrega_horario); ?></textarea>
                  </div>
            </div>
          
          
            <div class="form-group">
                <label class="control-label col-sm-2" for="nuc_entrega_endereco">Endereço de Entrega</label>
                <div class="col-sm-4">
                    <textarea name="nuc_entrega_endereco" rows="4"  class="form-control" placeholder="Endereço de Entrega"><?php echo($nuc_entrega_endereco); ?></textarea>
                  </div>
            </div>  
       
             <div class="form-group">
                <label class="control-label col-sm-2" for="nuc_archive">Situação: </label> 
                <div class="col-sm-2">      
                    <select name="nuc_archive" id="nuc_archive" class="form-control">
                        <option value="0" <?php echo(($nuc_archive==0)?" selected" : ""); ?> >Ativo</option>
                        <option value="1" <?php echo(($nuc_archive==1)?" selected" : ""); ?> >Inativo</option>            
                    </select>   
                  </div>
            </div>  
	</div>  <!-- div panel-body --> 

   		<div class="panel-footer">          
		  <div class="form-group">
	          <div class="col-sm-offset-2">
                   <button class="btn btn-primary" type="submit" class="form-control"><i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   &nbsp;&nbsp;
                   <button class="btn btn-default" type="button" onclick="javascript:location.href='nucleos.php'" class="form-control"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
              </div>
          </div>   
        </div>   <!-- div panel-footer -->        
     
  </div>  <!-- div panel -->     

     </fieldset> 
    </form>

<script type="text/javascript">
	$(function() {
		$("#form_nucleo").submit(validaNucleo);
	}); 
</script> 
    <?php  
   }

   footer();
?>
