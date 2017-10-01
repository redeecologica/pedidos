<?php  
  require  "common.inc.php"; 
  verifica_seguranca($_SESSION[PAP_RESP_FINANCAS]);
  top();
  
    
	$action = request_get("action",-1);
	if($action==-1) redireciona(PAGINAPRINCIPAL);

	$cha_id =  request_get("cha_id","");
	if($cha_id=="") redireciona(PAGINAPRINCIPAL);
			 
		
	
	if ($action<>-1) // por enquanto, vai precisar para todos os casos
	{
	  $sql = " SELECT DATE_FORMAT(cha_dt_entrega,'%d/%m/%Y') cha_dt_entrega, cha_prodt, prodt_nome, DATE_FORMAT(cha_dt_prazo_contabil,'%d/%m/%Y') cha_dt_prazo_contabil,";
	  $sql.= " DATE_FORMAT(cha_dt_prazo_contabil,'%H:%i') cha_dt_prazo_contabil_hh  FROM chamadas ";
	  $sql.= " LEFT JOIN produtotipos ON cha_prodt = prodt_id ";
	  $sql.= " WHERE cha_id=". prep_para_bd($cha_id) . " ";
	  
	  $res = executa_sql($sql);
	  if ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
	  {				  
		$cha_dt_entrega = $row["cha_dt_entrega"];
		$cha_prodt = $row["cha_prodt"];
		$prodt_nome = $row["prodt_nome"];
		$cha_dt_prazo_contabil = $row["cha_dt_prazo_contabil"];
		$cha_dt_prazo_contabil_hh = $row["cha_dt_prazo_contabil_hh"];		
	  }
	}	

			
	if ($action == ACAO_SALVAR) // salvar formulário preenchido
	{

		$sql = "UPDATE chamadas SET ";
		$sql.= " cha_dt_prazo_contabil  = " . prep_para_bd(formata_data_hora_para_mysql($_REQUEST["cha_dt_prazo_contabil"] . " " .  $_REQUEST["cha_dt_prazo_contabil_hh"])) . " ";
		$sql.= "WHERE cha_id=". prep_para_bd($cha_id) . " ";	
		$res = executa_sql($sql);


		if($res) 
		{							
			$action=ACAO_EXIBIR_LEITURA; 
			adiciona_mensagem_status(MSG_TIPO_SUCESSO,"As informações de prazo contábil relacionadas à chamada de " . $cha_dt_entrega . " foram salvas com sucesso.");

			if(isset($_POST['back_url']))
			{
				redireciona($_POST['back_url']);
			}
		}
		else
		{
			adiciona_mensagem_status(MSG_TIPO_ERRO,"Erro ao tentar salvar informações de prazo contábil relacionadas à chamada de " . $cha_dt_entrega . ".");								
		}
		escreve_mensagem_status();
	
	}
	
	if ($action == ACAO_EXIBIR_LEITURA || $action == ACAO_EXIBIR_EDICAO )  // exibir para visualização, ou exibir para edição
	{

	}	
	
?>
 
<ul class="nav nav-tabs">
  <li><a href="financas.php">Finanças</a></li>
  <li><a href="recebimento.php?action=0&recebimento=final"><i class="glyphicon glyphicon-road"></i> Confirmação Entrega dos Produtores</a></li>
  <li class="active"><a href="#"><i class="glyphicon glyphicon-calendar"></i> Configuração Prazos</a></li>  
</ul>
                                    
<br>
   
  <?php   

 if($action==ACAO_EXIBIR_LEITURA)  //visualização somente leitura
 {
 ?>	  
 		
 <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Informações da Chamada</strong>
  </div>
 <div class="panel-body">
         
               <table class="table table-striped table-bordered table-condensed table-hover">
                <thead>
                    <tr>
                        <th colspan="2">Prazo contábil para <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></th>
                     </tr>
                </thead>
                <tbody>
                    <tr>
                      <td><?php echo(is_null($cha_dt_prazo_contabil) ? "ainda não definido" : ($cha_dt_prazo_contabil . " " . $cha_dt_prazo_contabil_hh)); ?></td>
                        <td>
                          <a class="btn btn-primary" href="financas_prazo.php?action=<?php echo(ACAO_EXIBIR_EDICAO); ?>&cha_id=<?php echo($cha_id); ?>"><i class="glyphicon glyphicon-edit glyphicon-white"></i> editar</a>
                        </td>
                    </tr>
                </tbody>    
                </table>	
 
 </div>               
<?php 
	
 }
 else  //visualização para edição
 {

?>

 <div class="panel panel-default">
  <div class="panel-heading">
     <strong>Prazo contábil para <?php echo($prodt_nome . " - " . $cha_dt_entrega); ?></strong>
  </div>
 <div class="panel-body">
         
        
    <form class="form-horizontal"  method="post">
        <fieldset> 
        
          <input type="hidden" name="cha_id" value="<?php echo($cha_id); ?>" />     
          <input type="hidden" name="action" value="<?php echo(ACAO_SALVAR); ?>" />
    
          <input type="hidden" name="back_url" id="back_url" value="<?php echo(isset($_POST['back_url']) ? $_POST['back_url'] : ""); ?>" />    
	          <?php if( ! isset($_POST['back_url'])) echo("<script>document.getElementById(\"back_url\").value = document.referrer;</script>"); ?>
            

        
        
             <div class="form-group">
                   <label class="control-label col-sm-2" for="cha_dt_prazo_contabil">Prazo Contábil</label>
                   <div class="col-sm-2">   
                   Data: <input type="text" class="data form-control" id="cha_dt_prazo_contabil" name="cha_dt_prazo_contabil" required="required" value="<?php echo($cha_dt_prazo_contabil); ?>"/>
                   </div>
                   <div class="col-sm-2">                      
                   Hora: <input type="text" id="cha_dt_prazo_contabil_hh" name="cha_dt_prazo_contabil_hh"  required="required" class="hora form-control" value="<?php echo($cha_dt_prazo_contabil_hh); ?>"/>
    			   </div>
            </div>                     

           <div align="right">

                
                   <button type="submit"  class="btn btn-primary btn-enviando" data-loading-text="salvando...">
            <i class="glyphicon glyphicon-ok glyphicon-white"></i> salvar alterações</button>
                   
                   &nbsp;&nbsp;
                   
                   
                   
                   <button class="btn btn-default" type="button" onclick="javascript:location.href=document.referrer;"><i class="glyphicon glyphicon-off"></i> descartar alterações</button>
                                 
               </div>

      </fieldset> 
    </form>
 
 </div>
 </div>




<script type="text/javascript">
	$(function() {
		$(".data").datepicker({
			format: 'dd/mm/yyyy',
			language: 'pt-BR',
			autoclose: true
		}).on('changeDate', verificaDatas);
			
		$(".hora").mask("99:99");
		$(".hora").blur(verificaHora);	
		
		$(".numero").bind('keydown', keyCheck);
		$(".numero").on('blur', validaNumero);		
	}); 
</script>    


    
    <?php   
	
   }

   footer();
?>
