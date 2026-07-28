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
} )( jQuery );
