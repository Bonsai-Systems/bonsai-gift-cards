jQuery( function ( $ ) {
	'use strict';

	var frame;

	$( '#bgcp-upload-image' ).on( 'click', function ( e ) {
		e.preventDefault();

		if ( frame ) {
			frame.open();
			return;
		}

		var strings = window.bgcpAdmin || {};

		frame = wp.media( {
			title: strings.mediaTitle || 'Select gift card image',
			button: { text: strings.mediaButton || 'Use this image' },
			multiple: false,
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#bgcp_gift_card_image_id' ).val( attachment.id );
			// Built with .attr() rather than an HTML string so the URL can't inject markup.
			$( '#bgcp-image-preview' ).empty().append(
				$( '<img>' ).attr( { src: attachment.url, alt: strings.previewAlt || '' } )
			);
			$( '#bgcp-remove-image' ).prop( 'hidden', false );
		} );

		frame.open();
	} );

	$( '#bgcp-remove-image' ).on( 'click', function ( e ) {
		e.preventDefault();
		$( '#bgcp_gift_card_image_id' ).val( '' );
		$( '#bgcp-image-preview' ).empty();
		$( this ).prop( 'hidden', true );
	} );

	$( document ).on( 'click', '.bgcp-confirm-disable', function ( e ) {
		var message = ( window.bgcpAdmin && window.bgcpAdmin.confirmDisable ) || 'Disable this gift card?';
		if ( ! window.confirm( message ) ) { // eslint-disable-line no-alert
			e.preventDefault();
		}
	} );

	$( document ).on( 'click', '.bgcp-toggle-edit', function ( e ) {
		e.preventDefault();
		var $row = $( '.bgcp-edit-row' ).filter( '[data-code="' + $( this ).data( 'code' ) + '"]' );
		$row.prop( 'hidden', ! $row.prop( 'hidden' ) );
	} );

	$( document ).on( 'click', '.bgcp-toggle-redeem', function ( e ) {
		e.preventDefault();
		var $row = $( '.bgcp-redeem-row' ).filter( '[data-code="' + $( this ).data( 'code' ) + '"]' );
		$row.prop( 'hidden', ! $row.prop( 'hidden' ) );
	} );
} );
