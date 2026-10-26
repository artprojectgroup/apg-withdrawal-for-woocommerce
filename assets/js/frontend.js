/**
 * Frontend interactivity for the APG Withdrawal for WooCommerce form.
 *
 * Reads configuration from the global `apgWithdrawal` object that is
 * localised by PHP via wp_localize_script() in apg_withdrawal_render_form().
 *
 * @package APG_Withdrawal_For_WooCommerce
 */
( function () {
	'use strict';

	var config = window.apgWithdrawal || {};

	window.apgWithdrawalInitForm = function ( root ) {
		root = root || document;

		var orderField        = document.getElementById( 'order_id' );
		var scopeField        = document.getElementById( 'scope' );
		var productsRow       = root.querySelector( '.apg-withdrawal-products-row' );
		var itemsList         = root.querySelector( '.apg-withdrawal-items' ) || document.querySelector( '.apg-withdrawal-items' );
		var allSelects        = root.querySelectorAll( '.apg-withdrawal-selectwoo' );
		var wrapper           = root.querySelector( '.apg-withdrawal-form-wrapper' ) || document.querySelector( '.apg-withdrawal-form-wrapper' );
		if ( wrapper && root.querySelector( '.apg-withdrawal-form' ) ) {
			wrapper._apgFormHtml = wrapper.innerHTML;
		}
		var emailField        = document.getElementById( 'email' );
		var isGuestOrderInput = orderField && orderField.tagName === 'INPUT';
		var productsMap       = config.productsMap || {};
		var orderItemsNonce   = config.orderItemsNonce || '';
		var selectedProducts  = config.selectedProducts || [];
		var selectedQuantities = config.selectedQuantities || {};
		var lastRenderedOrderId = null;
		var ajaxUrl           = config.ajaxUrl || '';
		var ordersWarning     = config.ordersWarning || {};
		var noResultsText     = config.i18n && config.i18n.noResults ? config.i18n.noResults : '';
		var unitsText         = config.i18n && config.i18n.units ? config.i18n.units : '';
		var warningMessages   = config.warningMessages || {};
		var productWarningTemplate = config.productWarningTemplate || '';
		var noticePlaceholder     = config.noticePlaceholder || '__APG_NOTICE_MESSAGE__';

		/**
		 * HTML-escapes arbitrary text before it is substituted into a server-rendered
		 * template via innerHTML.
		 *
		 * Quotes are encoded as well, which a helper built on textContent/innerHTML
		 * does not do: that kind of helper is only correct in text position, and
		 * silently stops being correct the day a value lands inside an attribute.
		 * This one is safe in both, so the escape never depends on remembering
		 * where the template happens to put the placeholder today.
		 *
		 * @param {string} text Untrusted plain text.
		 * @returns {string} HTML-escaped string.
		 */
		function escHtml( text ) {
			if ( text === null || typeof text === 'undefined' ) {
				return '';
			}
			return String( text )
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' )
				.replace( /'/g, '&#039;' );
		}

		/**
		 * Replaces the contents of a toggleable notice wrapper with the server-rendered
		 * WooCommerce notice template (either `block-notices/*.php` or `notices/*.php` —
		 * whichever WooCommerce picked for the current theme) with the message substituted
		 * into the placeholder. No assumption is made about the template's internal structure.
		 *
		 * @param {HTMLElement} wrapperEl Outer toggleable element rendered by PHP.
		 * @param {string}      template  Server-rendered template HTML containing the placeholder.
		 * @param {string}      message   Message text to inject (will be HTML-escaped).
		 */
		function renderNotice( wrapperEl, template, message ) {
			if ( ! wrapperEl ) { return; }
			if ( ! template ) {
				wrapperEl.textContent = message;
				return;
			}
			wrapperEl.innerHTML = template.split( noticePlaceholder ).join( escHtml( message ) );
		}

		function initSelectWoo( element, placeholder, allowClear, minimumResultsForSearch ) {
			if ( ! window.jQuery || ! window.jQuery.fn || ! window.jQuery.fn.selectWoo || ! element ) {
				return;
			}

			var $element = window.jQuery( element );

			if ( $element.hasClass( 'select2-hidden-accessible' ) ) {
				$element.selectWoo( 'destroy' );
			}

			$element.selectWoo( {
				width: '100%',
				placeholder: placeholder,
				allowClear: !! allowClear,
				minimumResultsForSearch: minimumResultsForSearch,
				language: {
					noResults: function () { return noResultsText; }
				}
			} );
		}

		/**
		 * Moves keyboard focus to freshly injected content. Replacing the form with the
		 * confirmation step — or with a notice — leaves focus on a button that no longer
		 * exists, so screen readers would never announce the new step and keyboard users
		 * would restart from the top of the document (WCAG 2.1 AA, 3.2.2 and 4.1.3).
		 *
		 * @param {HTMLElement} element Element that should receive focus.
		 */
		function moveFocusTo( element ) {
			if ( ! element ) { return; }
			if ( ! element.hasAttribute( 'tabindex' ) ) {
				element.setAttribute( 'tabindex', '-1' );
			}
			try {
				element.focus( { preventScroll: false } );
			} catch ( e ) {
				element.focus();
			}
		}

		/**
		 * Locks a form while its submission is in flight, and reports whether the
		 * caller may proceed.
		 *
		 * A second click lands a whole round trip before the first answer comes
		 * back, and each click used to be a request of its own. The server refuses
		 * a repeat anyway, but it should never have to: the click that cannot
		 * leave the browser is the one that costs nothing.
		 *
		 * @param {HTMLFormElement} form Form being submitted.
		 * @return {boolean} True when this is the first submission in flight.
		 */
		function lockForm( form ) {
			if ( form.getAttribute( 'data-apg-submitting' ) === '1' ) {
				return false;
			}

			form.setAttribute( 'data-apg-submitting', '1' );
			Array.prototype.forEach.call(
				form.querySelectorAll( 'button[type="submit"], input[type="submit"]' ),
				function ( button ) {
					button.disabled = true;
					button.setAttribute( 'aria-disabled', 'true' );
				}
			);

			return true;
		}

		/**
		 * Releases a form locked by lockForm(), so a submission the server turned
		 * down can be corrected and sent again.
		 *
		 * @param {HTMLFormElement} form Form to unlock.
		 */
		function unlockForm( form ) {
			form.removeAttribute( 'data-apg-submitting' );
			Array.prototype.forEach.call(
				form.querySelectorAll( 'button[type="submit"], input[type="submit"]' ),
				function ( button ) {
					button.disabled = false;
					button.removeAttribute( 'aria-disabled' );
				}
			);
		}

		function submitAjax( form, actionName ) {
			if ( ! lockForm( form ) ) {
				return;
			}

			var formData = new window.FormData( form );
			formData.append( 'action', actionName );

			window.fetch( ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( payload ) {
				if ( ! wrapper || ! payload || ! payload.success || ! payload.data || ! payload.data.html ) {
					// The submission was refused, so the form stays where it is and
					// has to take another attempt.
					unlockForm( form );

					if ( payload && payload.data && payload.data.message ) {
						wrapper.insertAdjacentHTML( 'afterbegin', payload.data.message );
						moveFocusTo( wrapper.firstElementChild );
					}
					return;
				}

				wrapper.innerHTML = payload.data.html;
				var backBtn = wrapper.querySelector( '.apg-withdrawal-back-btn' );
				if ( backBtn ) {
					backBtn.addEventListener( 'click', function () {
						if ( ! wrapper._apgFormHtml ) {
							window.location.reload();
							return;
						}
						var confirmForm = wrapper.querySelector( '.apg-withdrawal-confirmation' );
						var getVal = function ( n ) {
							var inp = confirmForm && confirmForm.querySelector( '[name="' + n + '"]' );
							return inp ? inp.value : '';
						};
						var getVals = function ( n ) {
							if ( ! confirmForm ) { return []; }
							return Array.prototype.map.call(
								confirmForm.querySelectorAll( '[name="' + n + '"]' ),
								function ( i ) { return i.value; }
							);
						};
						var getQuantities = function () {
							var map = {};
							if ( ! confirmForm ) { return map; }
							Array.prototype.forEach.call(
								confirmForm.querySelectorAll( '[name^="apg_withdrawal_quantities["]' ),
								function ( input ) {
									var id = input.name.replace( 'apg_withdrawal_quantities[', '' ).replace( ']', '' );
									map[ id ] = input.value;
								}
							);
							return map;
						};
						var snap = {
							name:       getVal( 'apg_withdrawal_name' ),
							email:      getVal( 'apg_withdrawal_email' ),
							phone:      getVal( 'apg_withdrawal_phone' ),
							order:      getVal( 'apg_withdrawal_order' ),
							scope:      getVal( 'apg_withdrawal_scope' ),
							details:    getVal( 'apg_withdrawal_details' ),
							iban:       getVal( 'apg_withdrawal_iban' ),
							products:   getVals( 'apg_withdrawal_products[]' ),
							quantities: getQuantities()
						};
						// Feed the selection back through the config the initialiser reads, so
						// the rebuilt item rows come back ticked with the same quantities on
						// both the logged-in and the guest path.
						config.selectedProducts   = snap.products;
						config.selectedQuantities = snap.quantities;
						wrapper.innerHTML = wrapper._apgFormHtml;
						// For guest forms the order INPUT must carry the value before init so
						// the field is not left disabled and the line items are fetched again.
						var preOrdEl     = document.getElementById( 'order_id' );
						var isGuestInput = preOrdEl && preOrdEl.tagName === 'INPUT';
						var preEmailEl   = document.getElementById( 'email' );
						if ( preEmailEl )                 { preEmailEl.value = snap.email; }
						if ( isGuestInput && snap.order ) { preOrdEl.value   = snap.order; }
						window.apgWithdrawalInitForm( wrapper );
						var nameEl    = document.getElementById( 'name' );
						var emailEl   = document.getElementById( 'email' );
						var phoneEl   = document.getElementById( 'phone' );
						var detailsEl = document.getElementById( 'details' );
						var ibanEl    = document.getElementById( 'iban' );
						var ordEl     = document.getElementById( 'order_id' );
						var scpEl     = document.getElementById( 'scope' );
						if ( nameEl )    { nameEl.value    = snap.name; }
						if ( emailEl )   { emailEl.value   = snap.email; }
						if ( phoneEl )   { phoneEl.value   = snap.phone; }
						if ( detailsEl ) { detailsEl.value = snap.details; }
						if ( ibanEl )    { ibanEl.value    = snap.iban; }
						if ( window.jQuery ) {
							if ( scpEl && snap.scope ) { window.jQuery( scpEl ).val( snap.scope ).trigger( 'change' ); }
							// Logged-in path: order already SELECT after init — set it synchronously.
							if ( ordEl && ordEl.tagName === 'SELECT' && snap.order ) {
								window.jQuery( ordEl ).val( snap.order ).trigger( 'change' );
							}
						} else {
							if ( scpEl ) { scpEl.value = snap.scope; }
							if ( ordEl && ordEl.tagName === 'SELECT' ) { ordEl.value = snap.order; }
						}
					} );
				}
				window.apgWithdrawalInitForm( wrapper );
				moveFocusTo(
					wrapper.querySelector( '#apg-withdrawal-confirmation-title' ) ||
					wrapper.querySelector( '.woocommerce-message, .woocommerce-error, .wc-block-components-notice-banner' ) ||
					wrapper.firstElementChild
				);
			} ).catch( function () {
				form.submit();
			} );
		}

		/**
		 * Builds one selectable row per order line: a checkbox that puts the line in
		 * scope plus a quantity input capped at the units originally ordered. Mirrors
		 * apg_withdrawal_render_item_rows() in PHP; both must stay in sync.
		 *
		 * @param {Object} items Line-item data keyed by line-item ID.
		 */
		function renderItems( items ) {
			if ( ! itemsList ) { return; }

			itemsList.innerHTML = '';

			Object.keys( items ).forEach( function ( itemId ) {
				var item     = items[ itemId ] || {};
				var ordered  = Math.max( 1, parseInt( item.quantity, 10 ) || 1 );
				var isOn     = selectedProducts.indexOf( itemId ) !== -1;
				var stored   = parseInt( selectedQuantities[ itemId ], 10 );
				var quantity = isNaN( stored ) ? ordered : Math.max( 1, Math.min( ordered, stored ) );

				var row = document.createElement( 'li' );
				row.className = 'apg-withdrawal-item';

				var checkboxId = 'apg_withdrawal_item_' + itemId;
				var quantityId = 'apg_withdrawal_qty_' + itemId;

				var checkboxLabel = document.createElement( 'label' );
				checkboxLabel.className = 'checkbox';
				checkboxLabel.setAttribute( 'for', checkboxId );

				var checkbox = document.createElement( 'input' );
				checkbox.type    = 'checkbox';
				checkbox.id      = checkboxId;
				checkbox.name    = 'products[]';
				checkbox.value   = itemId;
				checkbox.checked = isOn;

				checkboxLabel.appendChild( checkbox );
				checkboxLabel.appendChild( document.createTextNode( ' ' + ( item.label || '' ) ) );

				var quantityLabel = document.createElement( 'label' );
				quantityLabel.className = 'apg-withdrawal-item-quantity';
				quantityLabel.setAttribute( 'for', quantityId );
				quantityLabel.appendChild( document.createTextNode( unitsText + ' ' ) );

				var quantityInput = document.createElement( 'input' );
				quantityInput.type      = 'number';
				quantityInput.id        = quantityId;
				quantityInput.name      = 'quantities[' + itemId + ']';
				quantityInput.value     = quantity;
				quantityInput.min       = '1';
				quantityInput.max       = String( ordered );
				quantityInput.step      = '1';
				quantityInput.inputMode  = 'numeric';
				quantityInput.disabled  = ! isOn;

				quantityLabel.appendChild( quantityInput );

				checkbox.addEventListener( 'change', function () {
					quantityInput.disabled = ! checkbox.checked;
					if ( checkbox.checked && ! quantityInput.value ) {
						quantityInput.value = String( ordered );
					}
				} );

				row.appendChild( checkboxLabel );
				row.appendChild( quantityLabel );
				itemsList.appendChild( row );
			} );
		}

		/**
		 * Snapshots what is currently ticked, so a re-render triggered by something
		 * other than an order change — switching scope back and forth, for instance —
		 * does not silently throw the customer's selection away.
		 */
		function captureItemSelection() {
			if ( ! itemsList ) { return; }

			var picked = [];
			var amounts = {};

			Array.prototype.forEach.call(
				itemsList.querySelectorAll( 'input[type="checkbox"][name="products[]"]' ),
				function ( checkbox ) {
					if ( ! checkbox.checked ) { return; }
					picked.push( checkbox.value );
					var quantityInput = document.getElementById( 'apg_withdrawal_qty_' + checkbox.value );
					if ( quantityInput && quantityInput.value ) {
						amounts[ checkbox.value ] = quantityInput.value;
					}
				}
			);

			selectedProducts   = picked;
			selectedQuantities = amounts;
		}

		function syncProducts() {
			var currentOrderField = document.getElementById( 'order_id' );
			if ( ! currentOrderField || ! scopeField || ! productsRow || ! itemsList ) {
				return;
			}

			var orderId = currentOrderField.value;
			var items = productsMap[ orderId ] || {};
			var itemCount = Object.keys( items ).length;

			// Same order as last time: carry the current ticks over the re-render.
			// A different order means different line items, so the selection is dropped.
			if ( lastRenderedOrderId === orderId ) {
				captureItemSelection();
			} else if ( lastRenderedOrderId !== null ) {
				selectedProducts   = [];
				selectedQuantities = {};
			}
			lastRenderedOrderId = orderId;

			// Disable "Specific products only" when the order has a single item.
			var partialOpt = Array.prototype.filter.call( scopeField.options || [], function ( o ) { return o.value === 'partial'; } )[ 0 ];
			if ( partialOpt ) {
				var wasDisabled = partialOpt.disabled;
				partialOpt.disabled = itemCount <= 1;
				if ( itemCount <= 1 && scopeField.value === 'partial' ) {
					scopeField.value = 'full';
				}
				if ( wasDisabled !== ( itemCount <= 1 ) && window.jQuery ) {
					try {
						var $scope = window.jQuery( scopeField );
						if ( $scope.hasClass( 'select2-hidden-accessible' ) ) { $scope.selectWoo( 'destroy' ); }
						initSelectWoo( scopeField, '', false, 0 );
						window.jQuery( scopeField ).on( 'change select2:select select2:clear', syncProducts );
					} catch ( e ) { /* ignore */ }
				}
			}

			var isPartial = 'partial' === scopeField.value;

			renderItems( items );

			productsRow.style.display = isPartial ? '' : 'none';
			productsRow.hidden        = ! isPartial;

			var warningDiv = root.querySelector ? root.querySelector( '.apg-withdrawal-product-warning' ) : null;
			if ( warningDiv ) {
				var warningType = ordersWarning[ orderId ];
				if ( warningType && warningMessages[ warningType ] ) {
					renderNotice( warningDiv, productWarningTemplate, warningMessages[ warningType ] );
					warningDiv.hidden = false;
				} else {
					warningDiv.innerHTML = '';
					warningDiv.hidden = true;
				}
			}
		}

		allSelects.forEach( function ( select ) {
			initSelectWoo( select, '', select.id === 'order_id', 0 );
		} );

		if ( orderField ) {
			orderField.addEventListener( 'change', syncProducts );
		}

		if ( scopeField ) {
			scopeField.addEventListener( 'change', syncProducts );
		}

		if ( window.jQuery && orderField && scopeField ) {
			window.jQuery( orderField ).on( 'change select2:select select2:clear', syncProducts );
			window.jQuery( scopeField ).on( 'change select2:select select2:clear', syncProducts );
		}

		/**
		 * Loads the line items of the order the guest identified. The server only
		 * answers when the order number and the e-mail address belong together,
		 * which is the same proof the submission itself requires, so nothing is
		 * fetched on the strength of an order number alone.
		 */
		function fetchOrderItems() {
			if ( ! isGuestOrderInput || ! emailField ) { return; }

			var orderInput = document.getElementById( 'order_id' );
			var emailVal   = emailField.value.trim();
			var orderVal   = orderInput ? orderInput.value.trim() : '';

			if ( ! emailVal || ! orderVal ) { return; }

			var formData = new window.FormData();
			formData.append( 'action', 'apg_withdrawal_get_order_items' );
			formData.append( 'nonce', orderItemsNonce );
			formData.append( 'email', emailVal );
			formData.append( 'order_id', orderVal );

			window.fetch( ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( payload ) {
				if ( ! payload || ! payload.success || ! payload.data ) { return; }
				productsMap[ orderVal ] = payload.data.items || {};
				if ( payload.data.warning ) {
					ordersWarning[ orderVal ] = payload.data.warning;
				} else {
					delete ordersWarning[ orderVal ];
				}
				// Force a redraw: syncProducts() short-circuits when the order has
				// not changed, and here it is the item data that just arrived.
				lastRenderedOrderId = null;
				syncProducts();
			} ).catch( function () { /* leave the form as it is */ } );
		}

		if ( isGuestOrderInput && emailField ) {
			emailField.addEventListener( 'input', function () {
				var inp = document.getElementById( 'order_id' );
				if ( inp && inp.tagName === 'INPUT' ) {
					inp.disabled = ! emailField.value.trim();
				}
			} );
			emailField.addEventListener( 'blur', fetchOrderItems );
			if ( orderField ) {
				orderField.addEventListener( 'blur', fetchOrderItems );
			}
			if ( emailField.value.trim() ) {
				fetchOrderItems();
			}
		}

		syncProducts();

		if ( window.jQuery ) {
			window.jQuery( document.body ).trigger( 'wc-enhanced-select-init' );
		}

		root.querySelectorAll( '.apg-withdrawal-form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				submitAjax( form, 'apg_withdrawal_preview_ajax' );
			} );
		} );

		root.querySelectorAll( '.apg-withdrawal-confirmation' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				submitAjax( form, 'apg_withdrawal_confirm_ajax' );
			} );
		} );
	};

	window.addEventListener( 'load', function () {
		window.apgWithdrawalInitForm( document );
	} );
}() );
