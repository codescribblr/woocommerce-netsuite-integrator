jQuery(document).ready(function($){

	$('.order_actions .resend-netsuite').click(function(e){
		e.preventDefault();
		$.ajax({
			method: "GET",
			url: $(this).attr('href'),
			dataType: "json"
		}).always(function(response){
			$('#wpbody-content .wrap h1:first-child').after('<div class="notice '+response.type+' fade"><p>'+response.message+'</p></div>')
		}).done(function(response){
			$("#post-"+response.order_num+" .order_status").html('<mark class="sent-netsuite tips">Sent to NetSuite</mark>');
			$("#post-"+response.order_num+" [data-colname='Netsuite ID']").html(response.netsuite_id);
			$("#post-"+response.order_num+" .order_actions .resend-netsuite").remove();
		});
	});

});