jQuery(document).ready(function($){

	// setup variables
	var $dashySel = $("#dashy-sel"),
	    $dashyBox = $("#dashy-box");

	// set key binding to open dashy
	$(document).bind('keydown', 'ctrl+space', showAwp);


	// shows dashy
	function showAwp(){
		$dashyBox.addClass('showing');
		$dashySel.select2( 'open' );
	}


	// select2 format function
	function format(item) {
		if ( item.parent.length > 0 ){
			return '<span class="dashylabel">[' + item.parent + ']</span> ' + item.name;
		} else if ( item.cpt.length > 0 ){
			return '<span class="dashylabel">[' + item.cpt + ']</span> ' + item.name;
		} else {
			return '<span class="dashylabel">[' + item.name + ']</span> ' + item.name;
		}
	}


	// go to URL when selected in select2
	function dashy_go_to_url( url ){
		url = url.replace(/&amp;/g, '&');
		window.location.href = url;
	}


	// loads new data for dashy - usually to list posts/pages/cpt
	function dashy_load_new_data( selectData, shouldOpen, e ){

		e.preventDefault();

		$dashySel.select2('data', null);
		$dashySel.select2( 'destroy' );
		$dashySel.select2({
			data:{ results: selectData, text: function(item) { return item.name; } },
			formatSelection: format,
			formatResult: format
		});	

		if ( shouldOpen ){
			$dashySel.select2( 'open' );
		} 
		$dashySel.select2('data', null);
	}


	// setup select2
	$dashySel.select2({
		data:{ results: dashy_menu.main_menu, text: function(item) { return item.name; } },
		formatSelection: format,
		formatResult: format
	});


	// process selecting of item in select2
	$dashySel.on("select2-selecting", function( e ) { 

		if ( e.choice.type == 'url' ){
			dashy_go_to_url( e.choice.url );
		} else if ( e.choice.type == 'select' ) {			
			dashy_load_new_data(  eval( 'dashy_menu.dashy_' + e.choice.var ), true,  e );
			$dashySel.data('s2data', 'pages');

		}

	});


	// process closing of select2
	$dashySel.on("select2-close", function( e ) {

		if ( $dashySel.data( 's2data' ) != 'default' ){
			dashy_load_new_data( dashy_menu.main_menu, false, e );
			$dashySel.data('s2data', 'default');
		}

		$dashyBox.removeClass('showing');

	});

});