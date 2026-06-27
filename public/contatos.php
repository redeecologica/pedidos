<?php  
  require  "common.inc.php"; 
  verifica_seguranca();
  top();
?>

    <link rel="stylesheet" type="text/css" href="css/datatables-1.13.11.min.css"/>
    <style>
		tfoot {
		display: table-header-group;
		}
	</style>
	<script type="text/javascript" src="js/datatables-1.13.11.min.js"></script>
    
     
   <legend>Precisa do contato de um cestante da Rede? Experimente buscar.</legend>       
 
 	<table id="tb_contatos" class="display table table-striped table-bordered table-condensed">
		<thead>
			<tr>
				<th width="80px">Núcleo</th>
				<th>Assoc.</th>                
        		<th width="80px">Nome Completo</th>
        		<th>Desde</th>                                
				<th>Contatos</th>
				<th>Email</th>
				<th>Emails adicionais</th>  
                <th>Atividades na Rede</th>
                <th>Profissão</th>
                <th>Habilidades</th>                          
		
			</tr>
		</thead>
		
        		<tfoot>
			<tr class="form-group-sm"> 
			
				<th><input type="text" class="form-control" placeholder="buscar" style="width:60px;"></th>
				<th><input type="text" class="form-control" placeholder="buscar" style="width:40px;"></th>
				<th><input type="text" class="form-control" placeholder="buscar" style="width:80px;"></th>                
        		<th><input type="text" class="form-control" placeholder="buscar" style="width:60px;"></th>
				<th><input type="text" class="form-control" placeholder="buscar" style="width:80px;"></th>
				<th><input type="text" class="form-control" placeholder="buscar" style="width:100px;"></th>
				<th><input type="text" class="form-control" placeholder="buscar" style="width:100px;"></th>    
				<th><input type="text" class="form-control" placeholder="buscar" style="width:80px;"></th>
				<th><input type="text" class="form-control" placeholder="buscar" style="width:80px;"></th>
				<th><input type="text" class="form-control" placeholder="buscar" style="width:80px;"></th>                                                                
 			</tr>
		</tfoot>

        
        <tbody>
				<?php
					
					$sql = "SELECT usr_id, usr_associado, usr_nome_completo, usr_email, usr_atividades, usr_profissao, usr_habilidades, ";
					$sql.= "usr_email_alternativo, usr_contatos, nuc_nome_curto, DATE_FORMAT(usr_desde,'%Y-%m-%d') usr_desde ";
					$sql.= "FROM usuarios LEFT JOIN nucleos ON usr_nuc = nuc_id ";
					$sql.= "LEFT JOIN associacaotipos ON usr_asso = asso_id ";
					// contas administrativas ficam fora da lista pelo TIPO de associação,
					// não pelo prefixo do nome (que deixava escapar contas mal nomeadas)
					$sql.= "WHERE (asso_nome IS NULL OR asso_nome <> 'ADM Sistema') AND usr_archive='0' ";
					$sql.= "ORDER BY  usr_nome_completo "; 
								
					$res = executa_sql($sql);
					$contador = 0;
				    if($res)
					{
					 while ($row = mysqli_fetch_array($res,MYSQLI_ASSOC)) 
				     {
				?>				 
				  <tr>             
					 <td><small><?php echo(h($row['nuc_nome_curto']));?></small></td>   
					 <td><small><?php echo($row['usr_associado']? "Sim" : "Não"); ?></small></td>
					 <td><small><?php echo(h($row['usr_nome_completo']));?></small></td>
					 <td nowrap="nowrap"><small><?php echo(h($row['usr_desde']));?></small></td> 
                     <td><small><?php echo(h($row['usr_contatos']));?></small></td>                      
					 <td><small><?php echo(h($row['usr_email']));?></small></td>   
					 <td><small><?php echo(h($row['usr_email_alternativo']));?></small></td>     
					 <td><small><?php echo(h($row['usr_atividades']));?></small></td>                           
                     <td><small><?php echo(h($row['usr_profissao']));?></small></td>                           
                     <td><small><?php echo(h($row['usr_habilidades']));?></small></td>
                   </tr>
				<?php 
				     }
				   }
				?>
		</tbody>
	</table>
    


    <script type="text/javascript">
	  
	  $(document).ready(function() {		 
		// DataTable
		var table = $('#tb_contatos').DataTable( {
        "language": {
            "lengthMenu": "Exibir _MENU_ registros por pagina",
            "zeroRecords": "Nada encontrado - desculpe",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros (Pagina _PAGE_ de _PAGES_)", 
            "infoEmpty": "Nenhum registro disponivel",
            "infoFiltered": " do universo de _MAX_ registros",
			"sSearch": "Buscar em todos os campos:",
			"sSortAscending": "ative para ordenar coluna de forma crescente",
			"sSortDescending": "ative para ordenar coluna de forma decrescente",
		    "oPaginate": {
				"sFirst": "Primeiro",
				"sLast": "Ultimo",
				"sNext": "Proximo",
				"sPrevious": "Anterior"
      		},
			"decimal": ",",
            "thousands": "."
			}
		,	
		  "columnDefs": [
				{
					"targets": [ 0 ],
					"visible": true,
					"searchable": true
				},
				{
					"targets": [ 3 ],
					"visible": true
				}
			]
		 ,
		  "lengthChange": true, 
		  "pageLength": 5,
  	      "lengthMenu": [ 5, 10, 30]				  
		
		} );
		
		table.columns().every( function () {
			var that = this;
	 
			$( 'input', this.footer() ).on( 'keyup change', function () {
				if ( that.search() !== this.value ) {
					that
						.search( this.value )
						.draw();
				}
			} );
		} );
	  } );

	 </script>
   

<?php 
 
	footer();
?>
