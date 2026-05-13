/**
 * Frontend interactivity for the WC - APG Withdrawal form.
 *
 * Reads configuration from the global `apgWithdrawal` object that is
 * localised by PHP via wp_localize_script() in apg_withdrawal_render_form().
 *
 * @package WC_APG_Withdrawal
 */
( function () {
	'use strict';

	var config = window.apgWithdrawal || {};

	window.apgWithdrawalInitForm = function ( root ) {
		root = root || document;

		var orderField        = document.getElementById( 'order_id' );
		var scopeField        = document.getElementById( 'scope' );
		var productsRow       = root.querySelector( '.apg-withdrawal-products-row' );
		var productsField     = document.getElementById( 'products' );
		var allSelects        = root.querySelectorAll( '.apg-withdrawal-selectwoo' );
		var wrapper           = root.querySelector( '.apg-withdrawal-form-wrapper' ) || document.querySelector( '.apg-withdrawal-form-wrapper' );
		if ( wrapper && root.querySelector( '.apg-withdrawal-form' ) ) {
			wrapper._apgFormHtml = wrapper.innerHTML;
		}
		var emailField        = document.getElementById( 'email' );
		var isGuestOrderInput = orderField && orderField.tagName === 'INPUT';
		var ordersNonce       = config.ordersNonce || '';
		var productsMap       = config.productsMap || {};
		var selectedProducts  = config.selectedProducts || [];
		var ajaxUrl           = config.ajaxUrl || '';
		var ordersWarning     = config.ordersWarning || {};
		var noResultsText     = config.i18n && config.i18n.noResults ? config.i18n.noResults : '';
		var noOrdersForEmailText = config.i18n && config.i18n.noOrdersForEmail ? config.i18n.noOrdersForEmail : '';
		var chooseProductsText = config.i18n && config.i18n.chooseProducts ? config.i18n.chooseProducts : '';
		var warningMessages   = config.warningMessages || {};

		/** @returns {HTMLElement|null} Lazily-created error span inside #order_id_field. */
		function getOrderErrorEl() {
			var fieldContainer = document.getElementById( 'order_id_field' );
			if ( ! fieldContainer ) { return null; }
			var el = fieldContainer.querySelector( '.apg-withdrawal-order-error' );
			if ( ! el ) {
				el = document.createElement( 'span' );
				el.className = 'apg-withdrawal-order-error';
				el.style.cssText = 'display:none;color:#c0392b;font-size:0.875em;margin-top:4px;display:none;';
				fieldContainer.appendChild( el );
			}
			return el;
		}

		/** @param {string} emailVal Email that returned no orders. */
		function showOrderError( emailVal ) {
			var el = getOrderErrorEl();
			if ( el ) {
				el.textContent = noOrdersForEmailText.replace( '%s', emailVal );
				el.style.display = '';
			}
		}

		/** Hides and clears the order-not-found error. */
		function hideOrderError() {
			var el = getOrderErrorEl();
			if ( el ) {
				el.style.display = 'none';
				el.textContent = '';
			}
		}

		/** @param {string} emailVal Email that returned no orders. */
		function downgradeOrderFieldToInput( emailVal ) {
			var currentOrderField = document.getElementById( 'order_id' );
			if ( currentOrderField && currentOrderField.tagName === 'SELECT' ) {
				var inputWrapper = currentOrderField.closest
					? currentOrderField.closest( '.woocommerce-input-wrapper' )
					: currentOrderField.parentNode;
				if ( window.jQuery ) {
					try {
						var $sel = window.jQuery( currentOrderField );
						if ( $sel.hasClass( 'select2-hidden-accessible' ) ) {
							$sel.selectWoo( 'destroy' );
						}
					} catch ( e ) { /* ignore SelectWoo errors during downgrade */ }
				}
				var input = document.createElement( 'input' );
				input.type = 'text';
				input.id = 'order_id';
				input.name = 'order_id';
				input.className = 'input-text';
				input.setAttribute( 'aria-required', 'true' );
				input.required = true;
				if ( inputWrapper ) {
					inputWrapper.innerHTML = '';
					inputWrapper.appendChild( input );
				}
			} else if ( currentOrderField ) {
				currentOrderField.disabled = false;
				currentOrderField.value = '';
			}
			showOrderError( emailVal );
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

		function submitAjax( form, actionName ) {
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
					if ( payload && payload.data && payload.data.message ) {
						wrapper.insertAdjacentHTML( 'afterbegin', payload.data.message );
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
						var snap = {
							name:     getVal( 'apg_withdrawal_name' ),
							email:    getVal( 'apg_withdrawal_email' ),
							phone:    getVal( 'apg_withdrawal_phone' ),
							order:    getVal( 'apg_withdrawal_order' ),
							scope:    getVal( 'apg_withdrawal_scope' ),
							details:  getVal( 'apg_withdrawal_details' ),
							products: getVals( 'apg_withdrawal_products[]' )
						};
						wrapper.innerHTML = wrapper._apgFormHtml;
						// For guest forms the order INPUT must carry the value before init so
						// fetchOrdersByEmail fires and upgradeOrderFieldToSelect pre-selects it.
						var preOrdEl     = document.getElementById( 'order_id' );
						var isGuestInput = preOrdEl && preOrdEl.tagName === 'INPUT';
						var preEmailEl   = document.getElementById( 'email' );
						if ( preEmailEl )                              { preEmailEl.value = snap.email; }
						if ( isGuestInput && snap.order )              { preOrdEl.value   = snap.order; }
						if ( isGuestInput && snap.products.length )    { wrapper._apgPendingProducts = snap.products; }
						window.apgWithdrawalInitForm( wrapper );
						var nameEl    = document.getElementById( 'name' );
						var emailEl   = document.getElementById( 'email' );
						var phoneEl   = document.getElementById( 'phone' );
						var detailsEl = document.getElementById( 'details' );
						var ordEl     = document.getElementById( 'order_id' );
						var scpEl     = document.getElementById( 'scope' );
						var prdEl     = document.getElementById( 'products' );
						if ( nameEl )    { nameEl.value    = snap.name; }
						if ( emailEl )   { emailEl.value   = snap.email; }
						if ( phoneEl )   { phoneEl.value   = snap.phone; }
						if ( detailsEl ) { detailsEl.value = snap.details; }
						if ( window.jQuery ) {
							if ( scpEl && snap.scope ) { window.jQuery( scpEl ).val( snap.scope ).trigger( 'change' ); }
							// Logged-in path: order already SELECT after init — set and restore products synchronously.
							if ( ordEl && ordEl.tagName === 'SELECT' && snap.order ) {
								window.jQuery( ordEl ).val( snap.order ).trigger( 'change' );
								if ( prdEl && snap.products.length ) { window.jQuery( prdEl ).val( snap.products ).trigger( 'change' ); }
							}
						} else {
							if ( scpEl ) { scpEl.value = snap.scope; }
							if ( ordEl && ordEl.tagName === 'SELECT' ) { ordEl.value = snap.order; }
						}
					} );
				}
				window.apgWithdrawalInitForm( wrapper );
			} ).catch( function () {
				form.submit();
			} );
		}

		function syncProducts() {
			var currentOrderField = document.getElementById( 'order_id' );
			if ( ! currentOrderField || ! scopeField || ! productsRow || ! productsField ) {
				return;
			}

			var orderId = currentOrderField.value;
			var items = productsMap[ orderId ] || {};
			var itemCount = Object.keys( items ).length;

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

			productsField.innerHTML = '';

			Object.keys( items ).forEach( function ( itemId ) {
				var option = document.createElement( 'option' );
				option.value = itemId;
				option.textContent = items[ itemId ];
				if ( selectedProducts.indexOf( itemId ) !== -1 ) {
					option.selected = true;
				}
				productsField.appendChild( option );
			} );

			productsRow.style.display = isPartial ? '' : 'none';

			initSelectWoo( productsField, chooseProductsText, false, 0 );

			var warningDiv = root.querySelector ? root.querySelector( '.apg-withdrawal-product-warning' ) : null;
			if ( warningDiv ) {
				var warningType = ordersWarning[ orderId ];
				if ( warningType && warningMessages[ warningType ] ) {
					warningDiv.textContent = warningMessages[ warningType ];
					warningDiv.style.display = '';
				} else {
					warningDiv.style.display = 'none';
					warningDiv.textContent = '';
				}
			}
		}

		function upgradeOrderFieldToSelect( data ) {
			var currentOrderField = document.getElementById( 'order_id' );
			if ( ! currentOrderField ) {
				return;
			}
			if ( ! data.orders || ! data.orders.length ) {
				return;
			}

			hideOrderError();
			productsMap = data.productsMap || {};
			ordersWarning = data.warningMap || {};

			var prefilledValue = currentOrderField.value;

			var inputWrapper = currentOrderField.closest
				? currentOrderField.closest( '.woocommerce-input-wrapper' )
				: currentOrderField.parentNode;

			if ( currentOrderField.tagName === 'SELECT' && window.jQuery ) {
				try {
					var $sel = window.jQuery( currentOrderField );
					if ( $sel.hasClass( 'select2-hidden-accessible' ) ) {
						$sel.selectWoo( 'destroy' );
					}
				} catch ( e ) { /* ignore SelectWoo errors */ }
			}

			var select = document.createElement( 'select' );
			select.id = 'order_id';
			select.name = 'order_id';
			select.className = 'apg-withdrawal-selectwoo wc-enhanced-select';
			select.setAttribute( 'aria-required', 'true' );
			select.required = true;

			var placeholder = document.createElement( 'option' );
			placeholder.value = '';
			placeholder.textContent = data.placeholder || '';
			select.appendChild( placeholder );

			data.orders.forEach( function ( order ) {
				var option = document.createElement( 'option' );
				option.value = order.id;
				option.textContent = order.label;
				if ( prefilledValue && String( order.id ) === String( prefilledValue ) ) {
					option.selected = true;
				}
				select.appendChild( option );
			} );

			if ( inputWrapper ) {
				inputWrapper.innerHTML = '';
				inputWrapper.appendChild( select );
			}

			initSelectWoo( select, data.placeholder || '', true, 0 );

			if ( window.jQuery ) {
				window.jQuery( select ).on( 'change select2:select select2:clear', syncProducts );
			} else {
				select.addEventListener( 'change', syncProducts );
			}

			syncProducts();

			if ( wrapper && wrapper._apgPendingProducts && wrapper._apgPendingProducts.length ) {
				var prd = document.getElementById( 'products' );
				if ( prd && window.jQuery ) {
					window.jQuery( prd ).val( wrapper._apgPendingProducts ).trigger( 'change' );
				}
				wrapper._apgPendingProducts = null;
			}
		}

		function fetchOrdersByEmail() {
			if ( ! isGuestOrderInput || ! emailField ) {
				return;
			}
			var emailVal = emailField.value.trim();
			if ( ! emailVal ) {
				return;
			}

			var orderInput = document.getElementById( 'order_id' );
			if ( orderInput && orderInput.tagName === 'INPUT' ) {
				orderInput.disabled = true;
			}

			var formData = new window.FormData();
			formData.append( 'action', 'apg_withdrawal_get_guest_orders' );
			formData.append( 'nonce', ordersNonce );
			formData.append( 'email', emailVal );

			window.fetch( ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( payload ) {
				if ( payload && payload.success && payload.data && payload.data.orders && payload.data.orders.length ) {
					upgradeOrderFieldToSelect( payload.data );
				} else {
					downgradeOrderFieldToInput( emailVal );
				}
			} ).catch( function () {
				downgradeOrderFieldToInput( emailVal );
			} );
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

		if ( isGuestOrderInput && emailField ) {
			emailField.addEventListener( 'blur', fetchOrdersByEmail );
			emailField.addEventListener( 'input', function () {
				hideOrderError();
				var inp = document.getElementById( 'order_id' );
				if ( inp && inp.tagName === 'INPUT' ) {
					inp.disabled = ! emailField.value.trim();
				}
			} );
			if ( emailField.value.trim() ) {
				fetchOrdersByEmail();
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
