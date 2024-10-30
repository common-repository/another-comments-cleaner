(function($){
	"use strict";
	var $spinner = $( '<img alt="" src="" />' );
	
	/**
	 *  show the black overlay during AJAX calls
	 */
	var $overlay = {
		overlay: $( '<div />' ).attr( 'id', 'ancc-overlay' ).css({
			position: 'fixed',
			width: '100%',
			height: '100%',
			top: 0,
			right: 0,
			bottom: 0,
			left:  0,
			backgroundColor: 'rgba(0,0,0,0.5)',
			display: 'none',
		}).append( $( '<div />' ).attr( 'id', 'ancc-container' ).css({
			width: 300,
			height: 160,
			lineHeight: '160px',
			border: '1px solid #d6d6d6',
			backgroundColor: 'rgba(255,255,255,0.9)',
			margin: '150px auto auto auto',
			textAlign: 'center',
		}).append( $( '<div id="ancc-inner"></div>').css({
			display: 'inline-block',
			verticalAlign: 'middle',
			lineHeight: 'normal',
		}) ) ),
		
		show: function(){
			this.overlay.find( '#ancc-inner' ).append( $spinner );
			this.overlay.show();
		},
		
		msg: function( msg ){
			this.overlay.find( '#ancc-inner' ).html( '<span>' + msg + '</span>' );
			setTimeout(function(){$overlay.close()}, 1500);
		},
		
		close: function(){
			this.overlay.hide();
			this.overlay.find( '#ancc-inner' ).empty();
		}
	}
	
	/**
	 *  save status/actions map
	 */
	$( document ).on( 'submitForm_actionsMap', function(){
		var pending = $( '[name="map[pending]"]' ).val();
		var spam = $( '[name="map[spam]"]' ).val();
		var trash = $( '[name="map[trash]"]' ).val();
		$overlay.show();
		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				nonce: ancc.nonce,
				action: 'ancc-save-map',
				pending: pending,
				spam: spam,
				trash: trash,
				t: new Date().getTime(),
			},
			success: function ( resp, textStatus, XHR ) {
				if ( undefined !== resp.status && resp.status ) {
					$overlay.msg( resp.msg );
				} else {
					$overlay.msg( anccLocale.unknownError );
					console.log( resp );
				}
			},
			error: function ( request, textStatus, err ) {
				$overlay.msg( anccLocale.unknownError );
				console.log( err );
			}
		});
	} )
	
	/**
	 *  clean immediately
	 */
	$( document ).on( 'submitForm_clean', function(){
		var pending = $( '[name="map[pending]"]' ).val();
		var spam = $( '[name="map[spam]"]' ).val();
		var trash = $( '[name="map[trash]"]' ).val();
		$overlay.show();
		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				nonce: ancc.nonce,
				action: 'ancc-immediate-clean',
				pending: pending,
				spam: spam,
				trash: trash,
				t: new Date().getTime(),
			},
			success: function ( resp, textStatus, XHR ) {
				if ( undefined !== resp.status && resp.status ) {
					$overlay.msg( resp.msg );
					if ( undefined !== resp.stats ) {
						for( var cl in resp.stats ) {
							if ( $( '#last-exec .' + cl ).length ) {
								$( '#last-exec .' + cl ).html( resp.stats[cl] );
							}
						}
					}
				} else {
					$overlay.msg( anccLocale.unknownError );
					console.log( resp );
				}
			},
			error: function ( request, textStatus, err ) {
				$overlay.msg( anccLocale.unknownError );
				console.log( err );
			}
		});
	} )
	
	/**
	 *  save schedule
	 */
	$( document ).on( 'submitForm_sched', function(){
		if ( !$( '[name="custom-h"]' ).prop( 'disabled' ) ) {
			var h = parseInt( $( '[name="custom-h"]' ).val(), 10 );
			var m = parseInt( $( '[name="custom-m"]' ).val(), 10 );
			if ( h > 23 || h < 0 ) {
				$( '[name="custom-h"]' ).val( 12 );
			}
			if ( m > 59 || m < 0 ) {
				$( '[name="custom-m"]' ).val( 30 );
			}
		}
		var args = $( 'form#sched' ).serialize();
		$overlay.show();
		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				nonce: ancc.nonce,
				action: 'ancc-sched',
				args: args,
				t: new Date().getTime(),
			},
			success: function ( resp, textStatus, XHR ) {
				if ( undefined !== resp.status && resp.status ) {
					$overlay.msg( resp.msg );
					if ( resp.nextRun ) {
						$( '#last-exec .nextRun' ).html( resp.nextRun );
					}
				} else {
					$overlay.msg( anccLocale.unknownError );
					console.log( resp );
				}
			},
			error: function ( request, textStatus, err ) {
				$overlay.msg( anccLocale.unknownError );
				console.log( err );
			}
		});
	} )
	
	/**
	 *  intercept form submission and triger the appropiate event
	 */
	$( document ).on( 'submit', '#ancc form', function( ev ){
		ev.preventDefault();
		var $el = $( this );
		if ( $el.attr( 'id' ) ) {
			$( document.body ).trigger( 'submitForm_' + $el.attr( 'id' ) );
		}
	} )
	
	/**
	 *  switching between types of period
	 */
	$( document ).on( 'click', 'input[name="period"]', function(){
		var group = $( this ).attr( 'id' ).substr( 6 );
		$( '.radio-fields' ).not( '.fields-' + group ).prop( 'disabled', true );
		$( '.fields-' + group ).prop( 'disabled', false );
	} )
	
	$(function(){
		$( '#wpwrap' ).append( $overlay.overlay );
		$spinner.attr( 'src', ancc.adminUrl + '/images/spinner-2x.gif' );
	})
	
})(jQuery)
