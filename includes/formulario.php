<?php
/**
 * Settings page for the APG Withdrawal for WooCommerce plugin.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

global $apg_withdrawal;

$apg_withdrawal_settings = apg_withdrawal_get_settings();

settings_errors();

$tab = 1;
?>
<div class="wrap woocommerce">
	<h2><?php esc_html_e( 'APG Withdrawal Options.', 'apg-withdrawal-for-woocommerce' ); ?></h2>
	<h3><a href="<?php echo esc_url( $apg_withdrawal['plugin_url'] ); ?>" title="Art Project Group"><?php echo esc_html( $apg_withdrawal['plugin'] ); ?></a></h3>
	<p><?php esc_html_e( 'Add to WooCommerce an online withdrawal workflow with customer form, My Account integration and admin request log.', 'apg-withdrawal-for-woocommerce' ); ?></p>
	<?php include 'cuadro-informacion.php'; ?>
	<form id="formulario" method="post" action="options.php">
		<?php settings_fields( 'apg_withdrawal_settings_group' ); ?>
		<?php // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Static plugin image does not require attachment ID ?>
		<div class="cabecera"> <a href="<?php echo esc_url( $apg_withdrawal['plugin_url'] ); ?>" title="<?php echo esc_attr( $apg_withdrawal['plugin'] ); ?>" target="_blank"><img src="<?php echo esc_url( plugins_url( 'assets/images/cabecera.jpg', apg_withdrawal_DIRECCION ) ); ?>" class="imagen" alt="<?php echo esc_attr( $apg_withdrawal['plugin'] ); ?>" /></a> </div>
		<table class="form-table apg-table">
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_withdrawal_settings[notification_email]">
						<?php esc_html_e( 'Notification email', 'apg-withdrawal-for-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp"><input id="apg_withdrawal_settings[notification_email]" name="apg_withdrawal_settings[notification_email]" type="email" value="<?php echo esc_attr( $apg_withdrawal_settings['notification_email'] ); ?>" tabindex="<?php echo esc_attr( $tab ); $tab++; ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_withdrawal_settings[page_id]">
						<?php esc_html_e( 'Withdrawal page', 'apg-withdrawal-for-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp">
					<?php
					echo wp_kses(
						wp_dropdown_pages(
							array(
								'name'              => 'apg_withdrawal_settings[page_id]',
								'selected'          => absint( $apg_withdrawal_settings['page_id'] ),
								'echo'              => 0,
								'show_option_none'  => __( 'Select a page', 'apg-withdrawal-for-woocommerce' ),
								'option_none_value' => '0',
							)
						),
						array(
							'select' => array(
								'name'  => true,
								'id'    => true,
								'class' => true,
							),
							'option' => array(
								'value'    => true,
								'selected' => true,
							),
						)
					);
					?>
					<p class="description"><?php printf( wp_kses( /* translators: %s: shortcode tag */ __( 'Page where the shortcode %s is published.', 'apg-withdrawal-for-woocommerce' ), array( 'code' => array() ) ), '<code>[apg_withdrawal_form]</code>' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_withdrawal_settings[button_text]">
						<?php esc_html_e( 'Confirmation button text', 'apg-withdrawal-for-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp"><input id="apg_withdrawal_settings[button_text]" name="apg_withdrawal_settings[button_text]" type="text" value="<?php echo esc_attr( $apg_withdrawal_settings['button_text'] ); ?>" tabindex="<?php echo esc_attr( $tab ); $tab++; ?>" /></td>
			</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="apg_withdrawal_settings[withdrawal_days]">
							<?php esc_html_e( 'Withdrawal window (days)', 'apg-withdrawal-for-woocommerce' ); ?>
						</label>
					</th>
					<td class="forminp">
						<input id="apg_withdrawal_settings[withdrawal_days]" name="apg_withdrawal_settings[withdrawal_days]" type="number" min="14" value="<?php echo esc_attr( $apg_withdrawal_settings['withdrawal_days'] ); ?>" tabindex="<?php echo esc_attr( $tab ); $tab++; ?>" />
						<p class="description"><?php esc_html_e( 'Orders older than this number of days will not show the withdrawal button. The form still accepts late requests with a legal warning.', 'apg-withdrawal-for-woocommerce' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="apg_withdrawal_settings[grace_days]">
							<?php esc_html_e( 'Extra grace days', 'apg-withdrawal-for-woocommerce' ); ?>
						</label>
					</th>
					<td class="forminp"><input id="apg_withdrawal_settings[grace_days]" name="apg_withdrawal_settings[grace_days]" type="number" min="0" value="<?php echo esc_attr( $apg_withdrawal_settings['grace_days'] ); ?>" tabindex="<?php echo esc_attr( $tab ); $tab++; ?>" /></td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="apg_withdrawal_settings[deadline_source]">
							<?php esc_html_e( 'Deadline source', 'apg-withdrawal-for-woocommerce' ); ?>
						</label>
					</th>
					<td class="forminp">
						<select id="apg_withdrawal_settings[deadline_source]" name="apg_withdrawal_settings[deadline_source]" class="wc-enhanced-select" tabindex="<?php echo esc_attr( $tab ); $tab++; ?>">
							<option value="completed" <?php selected( $apg_withdrawal_settings['deadline_source'], 'completed' ); ?>><?php esc_html_e( 'Completed date', 'apg-withdrawal-for-woocommerce' ); ?></option>
							<option value="created" <?php selected( $apg_withdrawal_settings['deadline_source'], 'created' ); ?>><?php esc_html_e( 'Order created date', 'apg-withdrawal-for-woocommerce' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Use completed date as the default legal approximation. If the order is not completed yet, the plugin will fall back automatically.', 'apg-withdrawal-for-woocommerce' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="apg_withdrawal_settings[store_ip]">
							<?php esc_html_e( 'Store IP address?', 'apg-withdrawal-for-woocommerce' ); ?>
						</label>
				</th>
				<td class="forminp"><input id="apg_withdrawal_settings[store_ip]" name="apg_withdrawal_settings[store_ip]" type="checkbox" value="1" <?php checked( $apg_withdrawal_settings['store_ip'], '1' ); ?> tabindex="<?php echo esc_attr( $tab ); $tab++; ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_withdrawal_settings[store_user_agent]">
						<?php esc_html_e( 'Store browser identifier?', 'apg-withdrawal-for-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp"><input id="apg_withdrawal_settings[store_user_agent]" name="apg_withdrawal_settings[store_user_agent]" type="checkbox" value="1" <?php checked( $apg_withdrawal_settings['store_user_agent'], '1' ); ?> tabindex="<?php echo esc_attr( $tab ); $tab++; ?>" /></td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_withdrawal_settings[delete_data_on_uninstall]">
						<?php esc_html_e( 'Delete all data on uninstall?', 'apg-withdrawal-for-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp">
					<input id="apg_withdrawal_settings[delete_data_on_uninstall]" name="apg_withdrawal_settings[delete_data_on_uninstall]" type="checkbox" value="1" <?php checked( $apg_withdrawal_settings['delete_data_on_uninstall'] ?? '1', '1' ); ?> tabindex="<?php echo esc_attr( $tab ); $tab++; ?>" />
					<p class="description"><?php esc_html_e( 'If checked, all withdrawal requests, their metadata and the plugin settings will be permanently deleted when the plugin is uninstalled. Leave unchecked to keep your data.', 'apg-withdrawal-for-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>

		<?php
		$wc_order_statuses   = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file
		$order_status_map    = isset( $apg_withdrawal_settings['order_status_map'] ) && is_array( $apg_withdrawal_settings['order_status_map'] ) ? $apg_withdrawal_settings['order_status_map'] : array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file
		$email_on_status     = isset( $apg_withdrawal_settings['email_on_status'] ) && is_array( $apg_withdrawal_settings['email_on_status'] ) ? $apg_withdrawal_settings['email_on_status'] : array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file
		$withdrawal_statuses = array( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file
			'accepted'  => __( 'Accepted', 'apg-withdrawal-for-woocommerce' ),
			'rejected'  => __( 'Rejected', 'apg-withdrawal-for-woocommerce' ),
			'completed' => __( 'Completed', 'apg-withdrawal-for-woocommerce' ),
		);
		?>

		<h3><?php esc_html_e( 'Automation', 'apg-withdrawal-for-woocommerce' ); ?></h3>
		<p><?php esc_html_e( 'Automatically update withdrawal request status when the linked WooCommerce order changes status.', 'apg-withdrawal-for-woocommerce' ); ?></p>
		<table class="form-table apg-table">
			<?php foreach ( $withdrawal_statuses as $w_status => $w_label ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file ?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<?php
					printf(
						/* translators: %s withdrawal status label. */
						esc_html__( 'Mark as %s when order becomes', 'apg-withdrawal-for-woocommerce' ),
						'<strong>' . esc_html( $w_label ) . '</strong>'
					);
					?>
				</th>
				<td class="forminp">
					<?php
					$selected = isset( $order_status_map[ $w_status ] ) ? (array) $order_status_map[ $w_status ] : array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file
					if ( ! empty( $wc_order_statuses ) ) :
					?>
					<select
						multiple="multiple"
						name="apg_withdrawal_settings[order_status_map][<?php echo esc_attr( $w_status ); ?>][]"
						data-placeholder="<?php esc_attr_e( 'Select order statuses&hellip;', 'apg-withdrawal-for-woocommerce' ); ?>"
						aria-label="<?php esc_attr_e( 'Order status', 'apg-withdrawal-for-woocommerce' ); ?>"
						class="wc-enhanced-select"
						tabindex="<?php echo esc_attr( $tab ); $tab++; ?>"
					>
						<?php foreach ( $wc_order_statuses as $wc_key => $wc_label ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file ?>
						<option value="<?php echo esc_attr( $wc_key ); ?>" <?php selected( in_array( $wc_key, $selected, true ), true ); ?>><?php echo esc_html( $wc_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php else : ?>
					<em><?php esc_html_e( 'WooCommerce order statuses not available.', 'apg-withdrawal-for-woocommerce' ); ?></em>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>

		<h3><?php esc_html_e( 'Customer email notifications', 'apg-withdrawal-for-woocommerce' ); ?></h3>
		<p>
			<?php esc_html_e( 'Send an email to the customer when the withdrawal request status changes. Configure content in', 'apg-withdrawal-for-woocommerce' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ); ?>"><?php esc_html_e( 'WooCommerce &rsaquo; Settings &rsaquo; Emails', 'apg-withdrawal-for-woocommerce' ); ?></a>.
		</p>
		<table class="form-table apg-table">
			<?php foreach ( $withdrawal_statuses as $w_status => $w_label ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file ?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_withdrawal_settings_email_<?php echo esc_attr( $w_status ); ?>">
						<?php
						printf(
							/* translators: %s withdrawal status label. */
							esc_html__( 'Email when changed to %s', 'apg-withdrawal-for-woocommerce' ),
							'<strong>' . esc_html( $w_label ) . '</strong>'
						);
						?>
					</label>
				</th>
				<td class="forminp">
					<input
						id="apg_withdrawal_settings_email_<?php echo esc_attr( $w_status ); ?>"
						name="apg_withdrawal_settings[email_on_status][<?php echo esc_attr( $w_status ); ?>]"
						type="checkbox"
						value="1"
						<?php checked( isset( $email_on_status[ $w_status ] ) && '1' === $email_on_status[ $w_status ] ); ?>
						tabindex="<?php echo esc_attr( $tab ); $tab++; ?>"
					/>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>

		<?php
		$digital_waiver_mode         = isset( $apg_withdrawal_settings['digital_waiver_mode'] ) ? $apg_withdrawal_settings['digital_waiver_mode'] : 'disabled'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file
		$digital_waiver_categories   = isset( $apg_withdrawal_settings['digital_waiver_categories'] ) ? (array) $apg_withdrawal_settings['digital_waiver_categories'] : array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file
		$digital_waiver_products     = isset( $apg_withdrawal_settings['digital_waiver_products'] ) ? (array) $apg_withdrawal_settings['digital_waiver_products'] : array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file
		$digital_waiver_custom_label = isset( $apg_withdrawal_settings['digital_waiver_custom_label'] ) ? $apg_withdrawal_settings['digital_waiver_custom_label'] : ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file
		$product_categories          = function_exists( 'get_terms' ) ? get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) : array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file
		?>

		<h3><?php esc_html_e( 'Digital content waiver', 'apg-withdrawal-for-woocommerce' ); ?></h3>
		<p><?php esc_html_e( 'Show an optional acknowledgement at checkout when the customer is buying digital content or virtual services subject to the immediate-execution waiver of the right of withdrawal.', 'apg-withdrawal-for-woocommerce' ); ?></p>
		<table class="form-table apg-table">
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_withdrawal_settings[digital_waiver_mode]"><?php esc_html_e( 'When to show the checkbox', 'apg-withdrawal-for-woocommerce' ); ?></label>
				</th>
				<td class="forminp">
					<select
						id="apg_withdrawal_settings[digital_waiver_mode]"
						name="apg_withdrawal_settings[digital_waiver_mode]"
						class="wc-enhanced-select apg-withdrawal-digital-waiver-mode"
						tabindex="<?php echo esc_attr( $tab ); $tab++; ?>"
					>
						<option value="disabled" <?php selected( $digital_waiver_mode, 'disabled' ); ?>><?php esc_html_e( 'Never (disabled)', 'apg-withdrawal-for-woocommerce' ); ?></option>
						<option value="virtual" <?php selected( $digital_waiver_mode, 'virtual' ); ?>><?php esc_html_e( 'Only on virtual products', 'apg-withdrawal-for-woocommerce' ); ?></option>
						<option value="all" <?php selected( $digital_waiver_mode, 'all' ); ?>><?php esc_html_e( 'On every order', 'apg-withdrawal-for-woocommerce' ); ?></option>
						<option value="specific" <?php selected( $digital_waiver_mode, 'specific' ); ?>><?php esc_html_e( 'On selected categories or products', 'apg-withdrawal-for-woocommerce' ); ?></option>
					</select>
				</td>
			</tr>
			<tr valign="top" class="apg-withdrawal-digital-waiver-specific-row" <?php echo 'specific' === $digital_waiver_mode ? '' : 'style="display:none;"'; ?>>
				<th scope="row" class="titledesc">
					<label for="apg_withdrawal_settings[digital_waiver_categories]"><?php esc_html_e( 'Product categories', 'apg-withdrawal-for-woocommerce' ); ?></label>
				</th>
				<td class="forminp">
					<?php if ( ! empty( $product_categories ) && ! is_wp_error( $product_categories ) ) : ?>
					<select
						multiple="multiple"
						id="apg_withdrawal_settings[digital_waiver_categories]"
						name="apg_withdrawal_settings[digital_waiver_categories][]"
						class="wc-enhanced-select"
						data-placeholder="<?php esc_attr_e( 'Select product categories&hellip;', 'apg-withdrawal-for-woocommerce' ); ?>"
						tabindex="<?php echo esc_attr( $tab ); $tab++; ?>"
					>
						<?php foreach ( $product_categories as $product_category ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file ?>
						<option value="<?php echo esc_attr( $product_category->term_id ); ?>" <?php selected( in_array( (int) $product_category->term_id, array_map( 'intval', $digital_waiver_categories ), true ), true ); ?>><?php echo esc_html( $product_category->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php else : ?>
					<em><?php esc_html_e( 'No product categories available.', 'apg-withdrawal-for-woocommerce' ); ?></em>
					<?php endif; ?>
				</td>
			</tr>
			<tr valign="top" class="apg-withdrawal-digital-waiver-specific-row" <?php echo 'specific' === $digital_waiver_mode ? '' : 'style="display:none;"'; ?>>
				<th scope="row" class="titledesc">
					<label for="apg_withdrawal_settings[digital_waiver_products]"><?php esc_html_e( 'Products', 'apg-withdrawal-for-woocommerce' ); ?></label>
				</th>
				<td class="forminp">
					<select
						multiple="multiple"
						id="apg_withdrawal_settings[digital_waiver_products]"
						name="apg_withdrawal_settings[digital_waiver_products][]"
						class="wc-product-search"
						data-placeholder="<?php esc_attr_e( 'Search for products&hellip;', 'apg-withdrawal-for-woocommerce' ); ?>"
						data-action="woocommerce_json_search_products_and_variations"
						data-multiple="true"
						tabindex="<?php echo esc_attr( $tab ); $tab++; ?>"
					>
						<?php foreach ( $digital_waiver_products as $waiver_product_id ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file ?>
							<?php
							$waiver_product = function_exists( 'wc_get_product' ) ? wc_get_product( absint( $waiver_product_id ) ) : false; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables in included file
							if ( ! $waiver_product ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( $waiver_product_id ); ?>" selected="selected"><?php echo esc_html( wp_strip_all_tags( $waiver_product->get_formatted_name() ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="apg_withdrawal_settings[digital_waiver_custom_label]"><?php esc_html_e( 'Custom checkbox text', 'apg-withdrawal-for-woocommerce' ); ?></label>
				</th>
				<td class="forminp">
					<input
						type="text"
						id="apg_withdrawal_settings[digital_waiver_custom_label]"
						name="apg_withdrawal_settings[digital_waiver_custom_label]"
						value="<?php echo esc_attr( '' !== $digital_waiver_custom_label ? $digital_waiver_custom_label : apg_withdrawal_get_digital_waiver_label() ); ?>"
						class="regular-text"
						style="width:560px;max-width:100%;"
						tabindex="<?php echo esc_attr( $tab ); $tab++; ?>"
					/>
					<p class="description"><?php esc_html_e( 'Pre-filled with the default acknowledgement text. Edit it to customise it, or clear the field to revert to the default at any time.', 'apg-withdrawal-for-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>

		<script>
			(function () {
				var modeEl = document.querySelector( '.apg-withdrawal-digital-waiver-mode' );
				if ( ! modeEl ) { return; }
				var specificRows = document.querySelectorAll( '.apg-withdrawal-digital-waiver-specific-row' );
				/** Toggle the category/product rows depending on whether the 'specific' mode is selected. */
				function syncRows() {
					var show = 'specific' === modeEl.value;
					for ( var i = 0; i < specificRows.length; i++ ) {
						specificRows[ i ].style.display = show ? '' : 'none';
					}
				}
				// SelectWoo (jQuery-based) emits the change event on the original <select>; listen on both
				// the DOM element and via jQuery when available to catch SelectWoo-driven changes.
				modeEl.addEventListener( 'change', syncRows );
				if ( window.jQuery ) {
					window.jQuery( modeEl ).on( 'change', syncRows );
				}
			})();
		</script>

		<?php submit_button(); ?>
	</form>
</div>
