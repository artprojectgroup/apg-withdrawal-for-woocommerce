<?php
/**
 * Category-level withdrawal classification.
 *
 * Adds a "Withdrawal type" term meta field to the WooCommerce `product_cat`
 * taxonomy with the same five values available at product level. Products that
 * do not have an explicit type set inherit from their categories at lookup time
 * via `apg_withdrawal_get_effective_withdrawal_type()` in woocommerce.php,
 * applying the "most restrictive" rule when multiple categories disagree.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the canonical list of withdrawal type slugs and their translated labels.
 *
 * @return array<string,string>
 */
function apg_withdrawal_get_type_labels() {
	return array(
		'allowed'      => __( 'Withdrawal allowed (default)', 'apg-withdrawal-for-woocommerce' ),
		'excluded'     => __( 'Withdrawal excluded', 'apg-withdrawal-for-woocommerce' ),
		'digital'      => __( 'Digital content', 'apg-withdrawal-for-woocommerce' ),
		'personalized' => __( 'Personalised product', 'apg-withdrawal-for-woocommerce' ),
		'manual'       => __( 'Manual review required', 'apg-withdrawal-for-woocommerce' ),
	);
}

/**
 * Renders the Withdrawal type selector on the "Add new category" form.
 *
 * @return void
 */
function apg_withdrawal_category_add_form_field() {
	$types = apg_withdrawal_get_type_labels();
	?>
	<div class="form-field">
		<label for="apg_withdrawal_type"><?php esc_html_e( 'Withdrawal type', 'apg-withdrawal-for-woocommerce' ); ?></label>
		<select id="apg_withdrawal_type" name="apg_withdrawal_type">
			<?php foreach ( $types as $type_slug => $type_label ) : ?>
				<option value="<?php echo esc_attr( $type_slug ); ?>"><?php echo esc_html( $type_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p><?php esc_html_e( 'Products in this category inherit this type when they do not have one of their own. If a product belongs to several categories with conflicting types, the most restrictive type wins.', 'apg-withdrawal-for-woocommerce' ); ?></p>
	</div>
	<?php
}
add_action( 'product_cat_add_form_fields', 'apg_withdrawal_category_add_form_field' );

/**
 * Renders the Withdrawal type selector on the "Edit category" form.
 *
 * @param WP_Term $term Term object being edited.
 * @return void
 */
function apg_withdrawal_category_edit_form_field( $term ) {
	$types   = apg_withdrawal_get_type_labels();
	$current = get_term_meta( $term->term_id, '_apg_withdrawal_type', true );
	$current = $current ? $current : 'allowed';
	?>
	<tr class="form-field">
		<th scope="row"><label for="apg_withdrawal_type"><?php esc_html_e( 'Withdrawal type', 'apg-withdrawal-for-woocommerce' ); ?></label></th>
		<td>
			<select id="apg_withdrawal_type" name="apg_withdrawal_type">
				<?php foreach ( $types as $type_slug => $type_label ) : ?>
					<option value="<?php echo esc_attr( $type_slug ); ?>" <?php selected( $current, $type_slug ); ?>><?php echo esc_html( $type_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Products in this category inherit this type when they do not have one of their own. If a product belongs to several categories with conflicting types, the most restrictive type wins.', 'apg-withdrawal-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'product_cat_edit_form_fields', 'apg_withdrawal_category_edit_form_field' );

/**
 * Persists the Withdrawal type term meta when a product category is created or
 * updated from the WordPress admin term editor.
 *
 * @param int $term_id Term ID being saved.
 * @return void
 */
function apg_withdrawal_save_category_type( $term_id ) {
	if ( ! current_user_can( 'manage_product_terms' ) ) {
		return;
	}

	$allowed = array_keys( apg_withdrawal_get_type_labels() );

	// Nonce is verified upstream by WordPress before firing edited/created_product_cat.
	$raw = isset( $_POST['apg_withdrawal_type'] ) ? sanitize_key( wp_unslash( $_POST['apg_withdrawal_type'] ) ) : 'allowed'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WP upstream.
	$raw = in_array( $raw, $allowed, true ) ? $raw : 'allowed';

	update_term_meta( absint( $term_id ), '_apg_withdrawal_type', $raw );
}
add_action( 'edited_product_cat', 'apg_withdrawal_save_category_type' );
add_action( 'created_product_cat', 'apg_withdrawal_save_category_type' );
