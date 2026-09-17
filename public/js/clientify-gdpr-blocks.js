(function () {
	'use strict';

	var FIELD_ID   = 'clientify-gdpr-block-field';
	var gdprText   = '';
	var gdprChecked = false;

	function getGdprText() {
		return (
			window.clientify_gdpr_params && clientify_gdpr_params.gdpr_text
				? clientify_gdpr_params.gdpr_text
				: 'Acepto el envío de comunicaciones comerciales y promociones. '
		);
	}

	function sendConsentToCheckout( value ) {
		if (
			typeof wp !== 'undefined' &&
			wp.data &&
			wp.data.dispatch( 'wc/store/checkout' ) &&
			typeof wp.data.dispatch( 'wc/store/checkout' ).setExtensionData === 'function'
		) {
			wp.data.dispatch( 'wc/store/checkout' ).setExtensionData(
				'clientify-addons',
				{ gdpr_consent: value }
			);
		}
	}

	function buildField() {
		var container = document.createElement( 'div' );
		container.id        = FIELD_ID;
		container.className = 'clientify-gdpr-checkout-field';
		container.style.cssText = 'margin-bottom:16px;';

		var label = document.createElement( 'label' );
		label.style.cssText = 'display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;';

		var checkbox = document.createElement( 'input' );
		checkbox.type    = 'checkbox';
		checkbox.id      = 'clientify-gdpr-checkbox-block';
		checkbox.checked = gdprChecked;
		checkbox.style.cssText = 'margin:0;flex-shrink:0;';

		checkbox.addEventListener( 'change', function () {
			gdprChecked = checkbox.checked;
			sendConsentToCheckout( gdprChecked );
		} );

		var span = document.createElement( 'span' );
		span.textContent = gdprText;

		label.appendChild( checkbox );
		label.appendChild( span );
		container.appendChild( label );

		return container;
	}

	function findAnchor() {
		// Selector priority: actions row → place order button parent → payment block
		return (
			document.querySelector( '.wc-block-checkout__actions' ) ||
			document.querySelector( '.wp-block-woocommerce-checkout-order-summary-block' ) &&
				document.querySelector( '.wc-block-checkout__actions_row' ) ||
			document.querySelector( '[class*="checkout__actions"]' )
		);
	}

	function inject() {
		// Remove stale field if anchor moved (React re-renders)
		var existing = document.getElementById( FIELD_ID );

		var anchor = findAnchor();
		if ( ! anchor ) return;

		var desiredParent = anchor.parentNode;

		if ( existing ) {
			// Already in the right place — just sync checked state
			if ( existing.parentNode === desiredParent ) {
				var cb = document.getElementById( 'clientify-gdpr-checkbox-block' );
				if ( cb ) cb.checked = gdprChecked;
				return;
			}
			// Anchor moved, remove and re-inject
			existing.parentNode.removeChild( existing );
		}

		desiredParent.insertBefore( buildField(), anchor );
	}

	function init() {
		gdprText = getGdprText();

		// Try immediate injection
		inject();

		// Watch for React re-renders
		var observer = new MutationObserver( function () {
			inject();
		} );

		observer.observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
