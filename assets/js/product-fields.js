( function ( $ ) {
	'use strict';

	$( function () {
		var $select = $( '#bgcp_amount' );
		if ( ! $select.length ) {
			return;
		}

		var $customRow   = $( '.bgcp-custom-amount-row' );
		var $customInput = $( '#bgcp_amount_custom' );

		$select.on( 'change', function () {
			if ( 'custom' === $select.val() ) {
				$customRow.show();
				$customInput.attr( 'name', 'bgcp_amount' );
				$select.removeAttr( 'name' );
			} else {
				$customRow.hide();
				$customInput.removeAttr( 'name' );
				$select.attr( 'name', 'bgcp_amount' );
			}
		} );
	} );

	$( function () {
		var $hardCopy    = $( '#bgcp_hard_copy' );
		if ( ! $hardCopy.length ) {
			return;
		}

		var $addressBox  = $( '.bgcp-hard-copy-address' );
		var $requiredFields = $addressBox.find( '#bgcp_ship_name, #bgcp_ship_address_1, #bgcp_ship_city, #bgcp_ship_postcode, #bgcp_ship_country' );

		$hardCopy.on( 'change', function () {
			if ( $hardCopy.is( ':checked' ) ) {
				$addressBox.show();
				$requiredFields.attr( 'required', 'required' );
			} else {
				$addressBox.hide();
				$requiredFields.removeAttr( 'required' );
			}
		} );
	} );
} )( jQuery );
