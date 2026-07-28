jQuery( function ( $ ) {
	'use strict';

	var frame;

	$( '#bgcp-upload-image' ).on( 'click', function ( e ) {
		e.preventDefault();

		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title: 'Select Gift Card Image',
			button: { text: 'Use this image' },
			multiple: false,
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#bgcp_gift_card_image_id' ).val( attachment.id );
			$( '#bgcp-image-preview' ).html(
				'<img src="' + attachment.url + '" style="max-width:400px; display:block;" />'
			);
			$( '#bgcp-remove-image' ).show();
		} );

		frame.open();
	} );

	$( '#bgcp-remove-image' ).on( 'click', function ( e ) {
		e.preventDefault();
		$( '#bgcp_gift_card_image_id' ).val( '' );
		$( '#bgcp-image-preview' ).html( '' );
		$( this ).hide();
	} );

	$( document ).on( 'click', '.bgcp-confirm-disable', function ( e ) {
		var message = ( window.bgcpAdmin && window.bgcpAdmin.confirmDisable ) || 'Disable this gift card?';
		if ( ! window.confirm( message ) ) { // eslint-disable-line no-alert
			e.preventDefault();
		}
	} );
} );
