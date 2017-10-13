// GLOBAL VARS
var num_flights = 10; // max 140 / 1 page
var max_pages = Math.ceil(140 / num_flights); // 140 max flights, default 20 / page
var way = 'arrivals'; // arrivals/departures
var lang = 'bra'; // bra / usa
var anim_duration = 100;
var anim_active = true;
var loading = false;

function animar_tela(item, flights){
	
	var my_duration = anim_duration;
	
	if (!anim_active) my_duration = 0;
	/*
	if ($('#aa').attr('checked') == 'checked')
		my_duration = 0;
	else
		my_duration = anim_duration;
	*/
	if (item == undefined) item = 1;
	var total = $('#flight_table tbody tr').length;
	//if (item>total) return;
	
	$("#loader").fadeOut(100);
	
	$('#flight_table tbody tr:nth-child('+item+')').transition({
		  perspective: '1200px',
		  rotateX: '180deg',
		  opacity: 0.8,
		  duration: my_duration
	}, function(){
		
		if (flights.VOO[item-1] == undefined) {
			
			$('#flight_table tbody tr:nth-child('+(item+1)+')').html("<td><p class='marca grande'>&nbsp;</p></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>");
			
		} else {
		
		var voo = flights.VOO[item-1];
			
		var status_color;
		
		// if no gate/terminal available set as blank
		if (voo.NUM_GATE instanceof Object == true) voo.NUM_GATE = "-";
		if (voo.NUM_TPS instanceof Object == true) voo.NUM_TPS = "-";
		
		// flight status
		if (voo.DSC_STATUS == "Cancelado" || voo.DSC_STATUS == "Canceled") status_color = 'canceled';
		if (voo.DSC_STATUS == "Atrasado" || voo.DSC_STATUS == "Delayed") status_color = 'delayed';
		if (voo.DSC_STATUS == "Embarq Imediato" || voo.DSC_STATUS == "Check-in Open") status_color = 'checkin';
		
		// airline icon
		var airline = removeAccents(voo.NOM_CIA.toLowerCase());
			airline = airline.replace(' ', '-');
			airline = airline.replace(' ', '-');
			airline = airline.replace('-- panama', '');
			airline = airline.replace('---one', '');
			airline = airline.replace('---ava', '');
			airline = airline.replace('-mercosur', '');
			airline = airline.replace('-royal-dutch airlines', '');
			
			
			
			
		// airline icon
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(1)').find('p').removeClass();
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(1)').find('p').addClass('marca');
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(1)').find('p').addClass('grande');
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(1)').find('p').addClass(airline);
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(1)').find('p').text(voo.NOM_CIA);
		
		// location
		var location = '';
		if (voo.SIG_UF.toString().length == 2 && voo.SIG_UF.toString() != "XX") {
			location = voo.NOM_LOCALIDADE + ', ' + voo.SIG_UF;
		} else {
			location = voo.NOM_LOCALIDADE + ', ' + voo.NOM_PAIS;
		}
		
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(2)').text(voo.NUM_VOO);
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(3)').text(voo.HOR_PREV);
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(4)').text(voo.HOR_CONF);
		
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(5)').text(location);
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(5)').prepend('<img src="assets/flags/'+voo.NOM_PAIS+'.png" /> ');
		
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(6)').text(voo.NUM_GATE);
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(7)').text(voo.NUM_TPS);
		
		// status
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(8)').removeClass();
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(8)').addClass(status_color);
		
		$('#flight_table tbody tr:nth-child('+(item)+')').find('td:nth-child(8)').text(voo.DSC_STATUS);
		
		}
		
		animar_tela(item+1, flights);
		
		//$('html, body').stop().animate({
	   //     scrollTop: $('#flight_table tbody tr:nth-child('+(item+1)+')').offset().top-400
	   // }, 500);
		
	}).transition({
		  opacity: 1.0,
		  perspective: '1200px',
		  rotateX: '0deg',
		  duration: my_duration
	}, function(){
		
		// release the loading flag
		if (item==num_flights) loading = false;
		
	});
	
	
}

function load_data(){
	
	// if already loading, skip....
	if (loading) return 0;
	
	way = $('#sentido').val();
	
	if ($("#flight_table tbody tr").length != $("#num_voos").val()){
		$("#flight_table tbody").html('');
		num_flights = $('#num_voos').val();
		warm_up();
	}
	
	var way_text = '';
	if (lang=='bra') {
		way_text = (way=='arrivals') ? 'Chegadas':'Partidas';
	}
	if (lang=='usa') {
		way_text = (way=='arrivals') ? 'Arrivals':'Departures';
	}
	
	$('.way').text(way_text);
	
	loading = true;
	$("#loader").fadeIn(300);
	
	get_flights(function(flights){
		animar_tela(1, flights);
	});
	
	
	
}

function relogio(){
	var Digital=new Date();
	var hours=Digital.getHours();
	var minutes=Digital.getMinutes();
	var seconds=Digital.getSeconds();
	if (hours < 10) hours = "0"+hours;
	if (minutes < 10) minutes = "0"+minutes;
	if (seconds < 10) seconds = "0"+seconds;
	$(".time").html(hours+":"+minutes+":"+seconds);
	
	// refresh
	//if (seconds % 10 == 0) animar_tela();
	
	setTimeout('relogio()',1000);
}

$(document).ready(function(){

	warm_up();
	
	//setTimeout('animar_tela()', 1000);
	relogio();
	/*
	$('#flight_table > tbody  > tr').each(function() {
		//$(this).addClass("flip_in");
		
		$(this).transition({
			  perspective: '700px',
			  rotateX: '180deg',
			  duration: 300
		}).transition({
			  perspective: '700px',
			  rotateX: '0deg',
			  duration: 300
		});
		
				
	});
	
	*/

});



function warm_up(){
	
	for (var i=0; i<num_flights; i++){
		$("#flight_table tbody").append("<tr><td><p class='marca grande'>&nbsp;</p></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>");
	}
	
}


function get_flights(callback){
	
	way = $('#sentido').val();
	num_flights = $('#num_voos').val();
	
	if (way=='arrivals') way_flag = false; else way_flag=true;
	
	$.getJSON( "lista.php",
		{
	    	icao: $('#aeroporto').val(),
	    	idioma: lang,
	    	partida: way_flag,
	    	exibirFinalizados: false,
	    	registrosPagina: num_flights,
	    	pagina: 1
	    }
	).done(function( data ) {
			callback(data);
	  })
	  .fail(function( jqxhr, textStatus, error ) {
		    var err = textStatus + ", " + error;
		    alert( "Request Failed: " + err );
		    loading=false;
		    $("#loader").fadeOut(100);
	});
	
	
}



function removeAccents(text)
{																   
  text = text.replace(new RegExp('[ÁÀÂÃ]','gi'), 'a');
  text = text.replace(new RegExp('[ÉÈÊ]','gi'), 'e');
  text = text.replace(new RegExp('[ÍÌÎ]','gi'), 'i');
  text = text.replace(new RegExp('[ÓÒÔÕ]','gi'), 'o');
  text = text.replace(new RegExp('[ÚÙÛ]','gi'), 'u');
  text = text.replace(new RegExp('[Ç]','gi'), 'c');
  return text;				 
}