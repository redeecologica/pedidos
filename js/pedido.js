function calculaTotalPedido(e){
	$(this).formataInput();
	var valorInformado = $(this).val().replace(",",".");
	var item = $(this).getItemNumber();
	if (valorInformado == "") {
		$('#totalprod_'+item).text("0,0");
		formataTotalProdEPedido(0, item);
		return;
	}
	if (!$.isNumeric(valorInformado)){
		alert("Por favor, informe uma quantidade válida para o pedido.");
		$(this).focus();
		$('#totalprod_'+item).text("0,0");
		formataTotalProdEPedido(0, item);
		return;
	}
	var multiploValido = $('#multiploprod_'+item).val();
	if(	(valorInformado*100)%(multiploValido*100)!=0){
		alert('A quantidade informada deve ser um múltiplo de '+multiploValido.replace(".",",")+".");
		$(this).focus();
		$('#totalprod_'+item).text("0,0");
		formataTotalProdEPedido(0, item);
		return;
	}
	formataTotalProdEPedido(valorInformado, item);

}

function formataTotalProdEPedido(valorInformado, item) {
	var totalProd = valorInformado*$('#valorprod_'+item).val();
	totalProd = Math.round(totalProd*100)/100;
	$('#totalprod_'+item).text(totalProd);
	$('#totalprod_'+item).formataValor();
	
	var totalPedido = 0;
	$(".total_prod").each(function(){
		totalPedido += +$(this).text().replace(",",".");
	});
	totalPedido = Math.round(totalPedido*100)/100;
	$("#total_pedido").text(totalPedido);
	$("#total_pedido").formataValor();
}

(function($) {
  $.fn.formataValor = function() {
	return this.each(function() {
		if ($(this).text().indexOf(',')!=-1) return;
		if ($(this).text().indexOf('.')==-1)
			$(this).text($(this).text()+',00');
		else {
			$(this).text($(this).text().replace(".",","));
			if ($(this).text().substring($(this).text().indexOf(',')+1).length < 2)
				$(this).text($(this).text()+'0');
		}
	});
  }
})(jQuery);	

(function($) {
  $.fn.formataInput = function() {
	return this.each(function() {
		var item = $(this).getItemNumber();
		if ($('#multiploprod_'+item).val() % 1 == 0){
			if ($(this).val().indexOf(',')!=-1) {
				$(this).val($(this).val().substring(0,$(this).val().indexOf(',')));
			}
		} else {
			if ($(this).val().indexOf(',')!=-1) {
				$(this).val($(this).val().substring(0,$(this).val().indexOf(',')+3));
				while ($(this).val().substring($(this).val().indexOf(',')+1).length < 1	)
					$(this).val($(this).val()+'0');
			} else {
				$(this).val($(this).val()+',0');
			}
		}
	});
  }
})(jQuery);	

(function($) {
  $.fn.getItemNumber = function() {
	return $(this).attr('id').substring($(this).attr('id').indexOf('_')+1);
  }
})(jQuery);	

(function($) {
  $.fn.multiplicadorInteiroItem = function() {
	return ($('#multiploprod_'+$(this).getItemNumber()).val() % 1 == 0);

  }
})(jQuery);	
(function($) {
  $.fn.verificaEnterSetas = function(e){
	e.stopImmediatePropagation();
	if( e.which == 13 || e.which == 38 || e.which == 40 )	{
		e.preventDefault();
		var $input = $('input:visible');
		if( $(this).is( $input.last() ) && e.which == 13) {
			$('form').submit();
		} else if ( e.which == 13 || e.which == 40 ) {
			$input.eq( $(this).index('input:visible') + 1 ).focus();
		}
		else if( e.which == 38){
			$input.eq( $(this).index('input:visible') - 1 ).focus();
		}
	}
  }
})(jQuery);	

function enforceNumeric (e, enforceInt) {
	
	var code = (e.keyCode ? e.keyCode : e.which);
	var functional = false;
	
	if (typeof(enforceInt) === "undefined") { enforceInt = false; }

	//0 to 9
	if((code >= 48 && code <= 57) || (code >= 96 && code <= 105)) functional = true;

	// vírgula
	if (code ==  188 && !enforceInt) functional = true;

	// Backspace, Tab, Enter, Delete, up/down/left/right arrows
	if (code ==  8) functional = true;
	if (code ==  9) functional = true;
	if (code == 13) functional = true;
	if (code == 46) functional = true;
	if (code == 37) functional = true;
	if (code == 38) functional = true;
	if (code == 39) functional = true;
	if (code == 40) functional = true;

	if (!functional)
	{
		e.preventDefault();
		e.stopPropagation();
	}
}

function keyCheck(e){
	enforceNumeric(e,$(this).multiplicadorInteiroItem());
	$(this).verificaEnterSetas(e);
}

function emailValido(email){
	return /^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))$/i.test(email);
	}

function verificaHora() {
	if ($(this).val() == "") return true;
	var hora = $(this).val().substring(0,2);
	var min = $(this).val().substring(3,5);
	if (hora>23 || min>59) {
		alert("Hora inválida");
		$(this).val("");
	}
}

function verificaDatas(){
	if ($("#cha_dt_min").val() != "" && $("#cha_dt_max").val() != "") {
		var dataMin = $("#cha_dt_min").data('datepicker').getDate();
		var dataMax = $("#cha_dt_max").data('datepicker').getDate();
		if (dataMin >= dataMax) {
			alert('A data de Término do Pedido precisa ser posterior à data de Início do Pedido.');
			$(this).val("");
			return false;
		}
	}
	if ($("#cha_dt_entrega").val() != "" && ($("#cha_dt_min").val() != "" || $("#cha_dt_max").val() != "")) {
		var dataEntrega = $("#cha_dt_entrega").data('datepicker').getDate();
		var dataMin = $("#cha_dt_min").data('datepicker').getDate();
		var dataMax = $("#cha_dt_max").data('datepicker').getDate();
		if (($("#cha_dt_min").val() != "" && dataMin >= dataEntrega) || ($("#cha_dt_max").val() != "" && dataMax >= dataEntrega)) {
			alert('A Data da Entrega precisa ser posterior às datas de Início do Pedido e Término do Pedido.');
			$(this).val("");
			return false;
		}
	}
}

$(function() {
        // Add Confirmation dialogs for all Deletes
        $("a.confirm-delete").on('click', function(event) {
            return confirm('Confirma a operação ?');
        });
});


function validaCestante(){
	if ($("#usr_nuc").val() == -1) {
		alert("Por favor, selecione um núcleo antes de salvar suas alterações.");
		$("#usr_nuc").focus();
		return false;
	}
	
	$("#usr_email").val($.trim($("#usr_email").val()));
	
	
	if (!emailValido($("#usr_email").val())) {
		alert("Por favor, informe um email válido.");
		$("#usr_email").focus();
		return false;
	}
	if($("#usr_email_alternativo").val()!=="") {
		var emails = $("#usr_email_alternativo").val().split(",");
		var emailInvalido = false;
		$.each( emails, function() {
			if(!emailValido($.trim(this))) {
				emailInvalido = true;
				return false;
			}
		});
		if (emailInvalido) {
			alert("Por favor, informe email(s) válido(s).");
			$("#usr_email_alternativo").focus();
			return false;
		}
	}
	
	if ($("#usr_asso").val() == -1) {
		alert("Por favor, selecione o tipo de associação antes de salvar suas alterações.");
		$("#usr_asso").focus();
		return false;
	}	
}

function validaNucleo(){
	
	$("#nuc_email").val($.trim($("#nuc_email").val()));
		
	if (!emailValido($("#nuc_email").val())) {
		alert("Por favor, informe um email válido.");
		$("#nuc_email").focus();
		return false;
	}
	
	if ($("#nuc_nuct").val() == -1) {
		alert("Por favor, selecione o tipo de Núcleo antes de salvar suas alterações.");
		$("#nuc_nuct").focus();
		return false;
	}
		
}

function validaProduto(){
	if ($("#prod_forn").val() == -1) {
		alert("Por favor, selecione um Produtor antes de salvar suas alterações.");
		$("#prod_forn").focus();
		return false;
	}
}

function validaNumero(){
	if ($(this).val() == "") {
		return
	}
	var valorInformado = $(this).val().replace(",",".");
	if (!$.isNumeric(valorInformado)){
		alert("Por favor, informe um valor válido.");
		$(this).focus();
		return;
	}
}

function verificaSenha(e) {
	$("input[type=password]").each(function(){
		if ($(this).val().length < 4 || $(this).val().length > 8) {
			alert("Por favor, informe a senha com no mínimo 4 e no máximo 8 dígitos.");
			e.preventDefault();
			$(this).focus();
			return false;
		}
		if ($("input[type=password]").eq(0).val() != $("input[type=password]").eq(1).val()) {
			alert("As senhas não coincidem. Por favor, informe novamente.");
			e.preventDefault();
			$("input[type=password]").eq(0).focus();
			return false;
		}
	});
}

function colaDistribuindo(colecaoDestino, primeiroItem, pastedText){
	var splitedText = pastedText.split("\n");
	var proxDestino = primeiroItem;
	for(var i=0; i< splitedText.length; i++) {
		if( splitedText[i].length==0 &&  i==splitedText.length-1  ) continue;
		var valorColar = splitedText[i].split("\t")[0];
		if(valorColar[0]=='-'  || valorColar=='') valorColar = "0";		
		if($.isNumeric(valorColar.replace(",",".")))
		{
			proxDestino.val(valorColar); 
			proxDestino = $(colecaoDestino.get(colecaoDestino.index(proxDestino)+1));
		}
		if(!proxDestino || colecaoDestino.index(proxDestino)==-1) break;
		else proxDestino.focus();
	}
}


function colaDistribuindoEntrega(colecaoDestino, primeiroItem, pastedText){
	var splitedText = pastedText.split("\n");
	var proxDestino = primeiroItem;
	for(var i=0; i< splitedText.length; i++) {		
		var valorColar = splitedText[i].split("\t")[0];
		if( valorColar.length==0 &&  (i==0  ||  i==splitedText.length-1)  ) continue;
		if(valorColar[0]=='-'  || valorColar=='') valorColar = "0";	
		if($.isNumeric(valorColar.replace(",",".")))
		{
			proxDestino.val(valorColar); 
			proxDestino = $(colecaoDestino.get(colecaoDestino.index(proxDestino)+1));
		}
		if(!proxDestino || colecaoDestino.index(proxDestino)==-1) break;
		else proxDestino.focus();
	}
}

$(function() {
	$('.btn-enviando')
	  .click(function () {
		var btn = $(this);
		btn.button('loading');
	  });
	  
	$('.btn-popover').popover();

	$(".propaga-colar").on("paste", function(e){
		var pastedText = undefined;
		if (window.clipboardData && window.clipboardData.getData) { // IE
			pastedText = window.clipboardData.getData('Text');
		} else if (e.originalEvent.clipboardData && e.originalEvent.clipboardData.getData) {
			pastedText = e.originalEvent.clipboardData.getData('text/plain');
		}
		colaDistribuindo($(".propaga-colar"), $(this), pastedText);
		return false; // Prevent the default handler from running.
	});
	
	$(".propaga-colar-2").on("paste", function(e){
		var pastedText = undefined;
		if (window.clipboardData && window.clipboardData.getData) { // IE
			pastedText = window.clipboardData.getData('Text');
		} else if (e.originalEvent.clipboardData && e.originalEvent.clipboardData.getData) {
			pastedText = e.originalEvent.clipboardData.getData('text/plain');
		}
		colaDistribuindo($(".propaga-colar-2"), $(this), pastedText);
		return false; // Prevent the default handler from running.
	});
	
	$(".propaga-colar-entrega").on("paste", function(e){
		var pastedText = undefined;
		if (window.clipboardData && window.clipboardData.getData) { // IE
			pastedText = window.clipboardData.getData('Text');
		} else if (e.originalEvent.clipboardData && e.originalEvent.clipboardData.getData) {
			pastedText = e.originalEvent.clipboardData.getData('text/plain');
		}
		colaDistribuindoEntrega($(".propaga-colar-entrega"), $(this), pastedText);
		return false; // Prevent the default handler from running.
	});	
		
	
	$(".seleciona_produtos_fornecedor").change(function(){
		var produtos = null;
		if(this.checked){
			if($(this).val()!='X') {
				produtos = $('input[id^="chaprod_prod_disponibilidade"][value='+$(this).val()+'][data-fornecedor='+$(this).attr("data-fornecedor")+']');
				produtos.each(function(){
					this.checked = true;
				});
			} else {
				produtos = $('input[id^="chaprod_prod_disponibilidade"][data-fornecedor="'+$(this).attr('data-fornecedor')+'"][type="radio"]');
				produtos.each(function(){
					var itemNum = $(this).attr('id').split("_")[3];
					var valorAnterior = $("#chaprod_prod_disponibilidade_anterior_"+itemNum+"_X").val();
					if($(this).val()==valorAnterior) {
						this.checked = true;
					} else {
						this.checked = false;
					}
				});
				
			}
		}
	});
	
	$("#marca_todos_nucleos").change(function(){
		var marcado = this.checked;
		$(".nucleos").each(function(){
			this.checked = marcado;
		});
	});
});


function replicaDados(replica_origem,replica_destino){
		elementos_origem = $('input[class^="' + replica_origem + '"]');
		elementos_destino = $('input[class^="' + replica_destino + '"]');		
		for(var i=0; i < elementos_origem.length; i++) {			
			elementos_destino[i].value = elementos_origem[i].value;			
		}
}


function selectElementContents(el) {
    var body = document.body, range, sel;
    if (document.createRange && window.getSelection) {
        range = document.createRange();
        sel = window.getSelection();
        sel.removeAllRanges();
        try {
            range.selectNodeContents(el);
            sel.addRange(range);
        } catch (e) {
            range.selectNode(el);
            sel.addRange(range);
        }
    } else if (body.createTextRange) {
        range = body.createTextRange();
        range.moveToElementText(el);
        range.select();
    }
}


$(function() {	
	$(".numero-positivo").bind('keydown', enforceNumeric);
	
}); 