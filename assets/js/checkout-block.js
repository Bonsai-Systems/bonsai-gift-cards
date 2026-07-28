( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.element ) {
		return;
	}

	var el              = wp.element.createElement;
	var useState        = wp.element.useState;
	var useEffect       = wp.element.useEffect;
	var registerPlugin  = wp.plugins.registerPlugin;
	var ExperimentalOrderMeta =
		window.wc && window.wc.blocksCheckout ? window.wc.blocksCheckout.ExperimentalOrderMeta : null;

	if ( ! ExperimentalOrderMeta ) {
		return;
	}

	var settings = window.bgcpCheckout || {};
	var i18n     = settings.i18n || {};

	function request( path, code ) {
		return window
			.fetch( settings.restUrl + path, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': settings.nonce,
				},
				credentials: 'same-origin',
				body: JSON.stringify( { code: code } ),
			} )
			.then( function ( res ) {
				return res.json().then( function ( data ) {
					return { ok: res.ok, data: data };
				} );
			} );
	}

	function GiftCardField( props ) {
		var cart = props.cart;
		var extensions = cart && cart.extensions ? cart.extensions : {};

		var codeState    = useState( '' );
		var code         = codeState[ 0 ];
		var setCode      = codeState[ 1 ];

		var busyState    = useState( false );
		var busy         = busyState[ 0 ];
		var setBusy      = busyState[ 1 ];

		var errorState   = useState( '' );
		var error        = errorState[ 0 ];
		var setError     = errorState[ 1 ];

		var appliedState = useState( [] );
		var applied      = appliedState[ 0 ];
		var setApplied   = appliedState[ 1 ];

		useEffect( function () {
			window
				.fetch( settings.restUrl + '/applied', { credentials: 'same-origin' } )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( data ) {
					if ( data && data.cards ) {
						setApplied( data.cards );
					}
				} );
		}, [] );

		function refreshCart() {
			// Nudge the Store API to recalculate — the checkout block listens
			// for cart changes via wp.data, so a simple dispatched event is
			// enough to have totals re-render after the fee changes server-side.
			if ( window.wp && window.wp.data && window.wp.data.dispatch ) {
				try {
					window.wp.data.dispatch( 'wc/store/cart' ).invalidateResolutionForStore();
				} catch ( e ) {
					window.location.reload();
				}
			} else {
				window.location.reload();
			}
		}

		function apply() {
			if ( ! code ) {
				return;
			}
			setBusy( true );
			setError( '' );
			request( '/apply', code ).then( function ( result ) {
				setBusy( false );
				if ( ! result.ok ) {
					setError( result.data.message || 'Error' );
					return;
				}
				setApplied( result.data.cards || [] );
				setCode( '' );
				refreshCart();
			} );
		}

		function remove( cardCode ) {
			setBusy( true );
			request( '/remove', cardCode ).then( function ( result ) {
				setBusy( false );
				if ( result.ok ) {
					setApplied( result.data.cards || [] );
					refreshCart();
				}
			} );
		}

		return el(
			'div',
			{ className: 'bgcp-checkout-field wc-block-components-panel' },
			el( 'h3', {}, i18n.label || 'Have a gift card?' ),
			applied.map( function ( card ) {
				return el(
					'div',
					{ className: 'bgcp-applied-card', key: card.code },
					el( 'span', {}, card.code + ' — ' + card.balance ),
					el(
						'button',
						{
							type: 'button',
							className: 'bgcp-remove-btn',
							onClick: function () {
								remove( card.code );
							},
						},
						i18n.remove || 'Remove'
					)
				);
			} ),
			el(
				'div',
				{ className: 'bgcp-field-row' },
				el( 'input', {
					type: 'text',
					value: code,
					placeholder: i18n.placeholder || 'Enter code',
					onChange: function ( e ) {
						setCode( e.target.value );
					},
				} ),
				el(
					'button',
					{
						type: 'button',
						disabled: busy,
						onClick: apply,
					},
					busy ? ( i18n.applying || 'Applying…' ) : ( i18n.apply || 'Apply' )
				)
			),
			error ? el( 'p', { className: 'bgcp-error' }, error ) : null
		);
	}

	registerPlugin( 'bgcp-gift-card-field', {
		render: function () {
			return el(
				ExperimentalOrderMeta,
				{},
				el( GiftCardField, {} )
			);
		},
	} );
} )( window.wp );
