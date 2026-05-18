/**
 * Injects the digital-content withdrawal waiver checkbox into the block-based
 * WooCommerce checkout. The checkbox is optional — order placement is never
 * blocked depending on its state.
 *
 * Positioning: the checkbox is rendered inside `.wc-block-checkout__terms` and
 * always reinserted as the immediate previous sibling of WooCommerce's native
 * terms-and-conditions checkbox (identified by `input#terms-and-conditions`),
 * so it stays "after any other custom checkbox, just before the native one"
 * regardless of how other plugins inject theirs. A cleanup pass removes any
 * duplicate produced by third-party plugins whose selectors over-match.
 *
 * Dynamic visibility: the script is enqueued on every checkout page load and
 * watches StoreAPI cart mutations via a `window.fetch` interceptor. After each
 * cart change it asks the server whether the current cart still qualifies for
 * the waiver and shows / removes the wrapper accordingly. The same interceptor
 * also injects the checkbox value into the StoreAPI checkout POST body under
 * `extensions['apg-withdrawal']['digital_waiver']`, so the server hook can
 * persist it to order meta.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */
( function () {
	'use strict';

	var config          = window.apgWithdrawalCheckout || {};
	var labelText       = config.label || '';
	var ajaxUrl         = config.ajaxUrl || '';
	var recheckNonce    = config.recheckNonce || '';
	var WRAPPER_ID      = 'apg-withdrawal-digital-waiver-wrapper';
	var INPUT_ID        = 'apg-withdrawal-digital-waiver-input';
	var TERMS_CONTAINER = '.wc-block-checkout__terms';
	var WC_TERMS_INPUT  = 'input#terms-and-conditions';
	var STORE_CART      = '/wc/store/v1/cart';
	var STORE_CHECKOUT  = '/wc/store/v1/checkout';
	var EXTENSION_KEY   = 'apg-withdrawal';

	var shouldRender    = !! config.initialQualifies;
	var refreshInflight = false;

	/**
	 * Builds the wrapper DOM that mirrors WooCommerce's native block-checkbox structure.
	 *
	 * @returns {HTMLDivElement}
	 */
	function buildCheckbox() {
		var wrapper = document.createElement( 'div' );
		wrapper.id = WRAPPER_ID;
		wrapper.className = 'wc-block-components-checkbox apg-withdrawal-digital-waiver';

		var labelEl = document.createElement( 'label' );
		labelEl.setAttribute( 'for', INPUT_ID );

		var input = document.createElement( 'input' );
		input.id = INPUT_ID;
		input.name = 'apg_withdrawal_digital_waiver';
		input.className = 'wc-block-components-checkbox__input';
		input.type = 'checkbox';

		var svgNS = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS( svgNS, 'svg' );
		svg.setAttribute( 'class', 'wc-block-components-checkbox__mark' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'viewBox', '0 0 24 20' );
		var path = document.createElementNS( svgNS, 'path' );
		path.setAttribute( 'd', 'M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z' );
		svg.appendChild( path );

		var textSpan = document.createElement( 'span' );
		textSpan.className = 'wc-block-components-checkbox__label';
		textSpan.textContent = labelText;

		labelEl.appendChild( input );
		labelEl.appendChild( svg );
		labelEl.appendChild( textSpan );
		wrapper.appendChild( labelEl );

		return wrapper;
	}

	/**
	 * Cleans up content that third-party plugins inject into the terms block as a
	 * side-effect of overly broad jQuery selectors.
	 *
	 * Two passes are run, in order:
	 *
	 *   1. Removes every sibling strictly between our wrapper and the native WooCommerce
	 *      terms-and-conditions checkbox. Our wrapper is intentionally placed as the
	 *      immediate previous sibling of the native checkbox; anything injected between
	 *      them comes from plugins (e.g. apg-gdpr-texts-for-forms) whose
	 *      `.wp-block-woocommerce-checkout-terms-block .wc-block-components-checkbox`
	 *      selector also matches our wrapper, causing their `.after()` call to inject
	 *      a duplicate copy of their content next to us.
	 *
	 *   2. Within the terms block, finds any `id` that appears more than once and
	 *      keeps only the latest occurrence. For each earlier duplicate it also walks
	 *      back removing immediate preceding siblings that have no `id` (and are not
	 *      our wrapper or the native checkbox), because plugins typically inject a
	 *      group of text + list + checkbox where only the checkbox carries an `id`,
	 *      and the whole group must be removed together.
	 *
	 * Both passes are idempotent: if the DOM is already clean, no nodes are removed.
	 */
	function cleanupForeignInjections() {
		var ourWrapper = document.getElementById( WRAPPER_ID );
		if ( ! ourWrapper || ! ourWrapper.parentNode ) { return; }

		var termsContainer = ourWrapper.parentNode;
		var wcInput        = termsContainer.querySelector( WC_TERMS_INPUT );
		var wcWrapper      = wcInput && wcInput.closest ? wcInput.closest( '.wc-block-components-checkbox' ) : null;

		if ( wcWrapper && wcWrapper.parentNode === termsContainer ) {
			var node = ourWrapper.nextElementSibling;
			while ( node && node !== wcWrapper ) {
				var nextNode = node.nextElementSibling;
				node.parentNode.removeChild( node );
				node = nextNode;
			}
		}

		var termsBlock = ourWrapper.closest ? ourWrapper.closest( '.wp-block-woocommerce-checkout-terms-block' ) : null;
		if ( ! termsBlock ) { termsBlock = termsContainer; }

		var idMap      = {};
		var allWithIds = Array.prototype.slice.call( termsBlock.querySelectorAll( '[id]' ) );
		var duplicates = [];
		for ( var i = 0; i < allWithIds.length; i++ ) {
			var el = allWithIds[ i ];
			if ( idMap[ el.id ] ) {
				duplicates.push( idMap[ el.id ] );
			}
			idMap[ el.id ] = el;
		}
		for ( var j = 0; j < duplicates.length; j++ ) {
			var dup = duplicates[ j ];
			if ( ! dup.parentNode ) { continue; }
			var prev = dup.previousElementSibling;
			dup.parentNode.removeChild( dup );
			while ( prev && ! prev.id ) {
				if ( prev === ourWrapper || ( wcWrapper && prev === wcWrapper ) ) { break; }
				var prevPrev = prev.previousElementSibling;
				prev.parentNode.removeChild( prev );
				prev = prevPrev;
			}
		}
	}

	/**
	 * Removes our wrapper from the DOM if it is currently present. Called when the
	 * cart no longer contains a qualifying product so the waiver disappears in real
	 * time. Safe to call repeatedly.
	 */
	function removeWrapper() {
		var existing = document.getElementById( WRAPPER_ID );
		if ( existing && existing.parentNode ) {
			existing.parentNode.removeChild( existing );
		}
	}

	/**
	 * Ensures our wrapper sits inside `.wc-block-checkout__terms`, immediately before
	 * the WooCommerce native terms-and-conditions checkbox (identified by its native
	 * `input#terms-and-conditions`). After repositioning, runs cleanupForeignInjections
	 * so plugins with overly broad checkbox selectors do not leave duplicate nodes
	 * inside the terms block. Idempotent: cheap when already in position.
	 *
	 * Respects `shouldRender`: when the cart does not qualify, any existing wrapper
	 * is removed and no new one is inserted.
	 */
	function ensurePosition() {
		if ( ! shouldRender ) {
			removeWrapper();
			return;
		}

		var termsContainer = document.querySelector( TERMS_CONTAINER );
		if ( ! termsContainer ) { return; }

		var wcInput   = termsContainer.querySelector( WC_TERMS_INPUT );
		var wcWrapper = wcInput && wcInput.closest ? wcInput.closest( '.wc-block-components-checkbox' ) : null;

		var ourWrapper = document.getElementById( WRAPPER_ID );
		if ( ! ourWrapper ) {
			ourWrapper = buildCheckbox();
		}

		if ( wcWrapper && wcWrapper.parentNode === termsContainer ) {
			if ( wcWrapper.previousElementSibling !== ourWrapper ) {
				termsContainer.insertBefore( ourWrapper, wcWrapper );
			}
		} else if ( termsContainer.firstElementChild !== ourWrapper ) {
			termsContainer.insertBefore( ourWrapper, termsContainer.firstChild );
		}

		cleanupForeignInjections();
	}

	/**
	 * Asks the server whether the current cart still qualifies for the digital-content
	 * waiver. Called after a StoreAPI cart mutation. Cheap to call: in-flight requests
	 * are deduplicated.
	 */
	function refreshQualification() {
		if ( refreshInflight || ! ajaxUrl ) { return; }
		refreshInflight = true;

		var url = ajaxUrl + ( ajaxUrl.indexOf( '?' ) === -1 ? '?' : '&' ) +
			'action=apg_withdrawal_check_cart_waiver&nonce=' + encodeURIComponent( recheckNonce );

		window.fetch( url, { credentials: 'same-origin' } )
			.then( function ( response ) { return response.json(); } )
			.then( function ( payload ) {
				refreshInflight = false;
				if ( ! payload || ! payload.success || ! payload.data ) { return; }
				var newShould = !! payload.data.qualifies;
				if ( newShould === shouldRender ) { return; }
				shouldRender = newShould;
				ensurePosition();
			} )
			.catch( function () { refreshInflight = false; } );
	}

	/**
	 * Wraps `window.fetch` to (a) inject the current waiver state into the StoreAPI
	 * checkout request body so the server-side hook can persist it to order meta,
	 * and (b) trigger a re-check after any StoreAPI cart mutation so the wrapper
	 * is shown / removed as products are added or removed mid-checkout.
	 *
	 * Designed to be safe alongside other interceptors: it only touches the body
	 * when it can confidently parse it as JSON, and propagates the original
	 * promise resolution unchanged.
	 */
	function installFetchInterceptor() {
		var originalFetch = window.fetch;
		if ( typeof originalFetch !== 'function' ) { return; }

		window.fetch = function ( input, init ) {
			var url    = typeof input === 'string' ? input : ( input && input.url ? input.url : '' );
			var method = init && init.method ? String( init.method ).toUpperCase() : 'GET';

			if (
				url.indexOf( STORE_CHECKOUT ) !== -1 &&
				'POST' === method &&
				init && typeof init.body === 'string' &&
				shouldRender
			) {
				try {
					var parsed = JSON.parse( init.body );
					if ( parsed && typeof parsed === 'object' ) {
						var checkbox = document.getElementById( INPUT_ID );
						if ( checkbox ) {
							parsed.extensions = parsed.extensions || {};
							parsed.extensions[ EXTENSION_KEY ] = { digital_waiver: !! checkbox.checked };
							init.body = JSON.stringify( parsed );
						}
					}
				} catch ( e ) { /* leave body untouched on parse error */ }
			}

			var promise = originalFetch.call( this, input, init );

			if ( url.indexOf( STORE_CART ) !== -1 && 'GET' !== method ) {
				promise.then( function () {
					setTimeout( refreshQualification, 50 );
				}, function () { /* ignore fetch errors here */ } );
			}

			return promise;
		};
	}

	/**
	 * Runs the initial insertion, installs the fetch interceptor (for StoreAPI cart
	 * change detection and checkout POST payload injection) and starts a MutationObserver
	 * that re-asserts our position on every DOM mutation, so block re-renders and
	 * other plugins cannot displace or duplicate us.
	 */
	function init() {
		installFetchInterceptor();
		ensurePosition();

		var observer = new MutationObserver( function () {
			ensurePosition();
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
