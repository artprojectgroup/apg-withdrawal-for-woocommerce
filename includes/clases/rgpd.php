<?php
/**
 * GDPR integration: personal-data exporter and eraser registered with the
 * native WordPress privacy tools (`Tools → Export Personal Data` and
 * `Tools → Erase Personal Data`).
 *
 * The eraser **anonymises** withdrawal requests rather than deleting them, so
 * the record remains available as legal evidence under Article 16 bis(8) of
 * Directive 2011/83/EU while removing the customer's personal data. The
 * `_apg_withdrawal_wc_order_id` meta is intentionally preserved because it is
 * a contract reference (not a personal identifier) and the merchant needs it
 * to keep linking the request to the underlying order.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the list of `apg_withdrawal` post IDs for a given customer email.
 *
 * @param string $email_address Email address to look up.
 * @return int[]
 */
function apg_withdrawal_find_requests_by_email( $email_address ) {
	$email_address = sanitize_email( $email_address );

	if ( ! $email_address ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'apg_withdrawal',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Triggered only from privacy admin tools, low volume.
				array(
					'key'     => '_apg_withdrawal_email',
					'value'   => $email_address,
					'compare' => '=',
				),
			),
		)
	);

	return array_map( 'absint', $query->posts );
}

/**
 * Registers the plugin's personal-data exporter with WordPress.
 *
 * @param array $exporters Existing exporters.
 * @return array
 */
function apg_withdrawal_register_data_exporter( $exporters ) {
	$exporters['apg-withdrawal-for-woocommerce'] = array(
		'exporter_friendly_name' => __( 'APG Withdrawal for WooCommerce — withdrawal requests', 'apg-withdrawal-for-woocommerce' ),
		'callback'               => 'apg_withdrawal_data_exporter',
	);

	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'apg_withdrawal_register_data_exporter' );

/**
 * Exporter callback: returns all withdrawal-request data associated with the
 * provided email address. Pagination is unused because withdrawal request
 * volumes are low per customer.
 *
 * @param string $email_address Email address being exported.
 * @param int    $page          Page number (unused).
 * @return array{data: array, done: bool}
 */
function apg_withdrawal_data_exporter( $email_address, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress privacy callback signature.
	$post_ids = apg_withdrawal_find_requests_by_email( $email_address );
	$exports  = array();

	foreach ( $post_ids as $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			continue;
		}

		$fields = array(
			array(
				'name'  => __( 'Request number', 'apg-withdrawal-for-woocommerce' ),
				'value' => '#' . $post_id,
			),
			array(
				'name'  => __( 'Date', 'apg-withdrawal-for-woocommerce' ),
				'value' => $post->post_date,
			),
			array(
				'name'  => __( 'Customer', 'apg-withdrawal-for-woocommerce' ),
				'value' => (string) get_post_meta( $post_id, '_apg_withdrawal_name', true ),
			),
			array(
				'name'  => __( 'Email', 'apg-withdrawal-for-woocommerce' ),
				'value' => (string) get_post_meta( $post_id, '_apg_withdrawal_email', true ),
			),
			array(
				'name'  => __( 'Phone', 'apg-withdrawal-for-woocommerce' ),
				'value' => (string) get_post_meta( $post_id, '_apg_withdrawal_phone', true ),
			),
			array(
				'name'  => __( 'Order', 'apg-withdrawal-for-woocommerce' ),
				'value' => (string) get_post_meta( $post_id, '_apg_withdrawal_order', true ),
			),
			array(
				'name'  => __( 'Scope', 'apg-withdrawal-for-woocommerce' ),
				'value' => (string) get_post_meta( $post_id, '_apg_withdrawal_scope', true ),
			),
			array(
				'name'  => __( 'Status', 'apg-withdrawal-for-woocommerce' ),
				'value' => (string) get_post_meta( $post_id, '_apg_withdrawal_status', true ),
			),
			array(
				'name'  => __( 'IP', 'apg-withdrawal-for-woocommerce' ),
				'value' => (string) get_post_meta( $post_id, '_apg_withdrawal_ip', true ),
			),
			array(
				'name'  => __( 'User agent', 'apg-withdrawal-for-woocommerce' ),
				'value' => (string) get_post_meta( $post_id, '_apg_withdrawal_user_agent', true ),
			),
			array(
				'name'  => __( 'Additional details', 'apg-withdrawal-for-woocommerce' ),
				'value' => (string) $post->post_content,
			),
			array(
				'name'  => __( 'Receipt SHA-256', 'apg-withdrawal-for-woocommerce' ),
				'value' => (string) get_post_meta( $post_id, '_apg_withdrawal_receipt_hash', true ),
			),
		);

		$exports[] = array(
			'group_id'    => 'apg-withdrawal-requests',
			'group_label' => __( 'Withdrawal requests', 'apg-withdrawal-for-woocommerce' ),
			'item_id'     => 'apg-withdrawal-' . $post_id,
			'data'        => array_filter(
				$fields,
				function ( $field ) {
					return '' !== ( $field['value'] ?? '' );
				}
			),
		);
	}

	return array(
		'data' => $exports,
		'done' => true,
	);
}

/**
 * Registers the plugin's personal-data eraser with WordPress.
 *
 * @param array $erasers Existing erasers.
 * @return array
 */
function apg_withdrawal_register_data_eraser( $erasers ) {
	$erasers['apg-withdrawal-for-woocommerce'] = array(
		'eraser_friendly_name' => __( 'APG Withdrawal for WooCommerce — withdrawal requests', 'apg-withdrawal-for-woocommerce' ),
		'callback'             => 'apg_withdrawal_data_eraser',
	);

	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'apg_withdrawal_register_data_eraser' );

/**
 * Eraser callback: anonymises every withdrawal request belonging to the
 * supplied email address. Personal fields are overwritten with `[redacted]`
 * (or removed entirely when relevant) while the request itself, its
 * `_apg_withdrawal_wc_order_id` reference and the status / scope metadata are
 * preserved so the merchant can still demonstrate the contract life-cycle.
 *
 * @param string $email_address Email address being erased.
 * @param int    $page          Page number (unused).
 * @return array{items_removed: int, items_retained: int, messages: array<int,string>, done: bool}
 */
function apg_withdrawal_data_eraser( $email_address, $page = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress privacy callback signature.
	$post_ids        = apg_withdrawal_find_requests_by_email( $email_address );
	$items_retained  = 0;
	$messages        = array();
	$redacted_marker = __( '[redacted]', 'apg-withdrawal-for-woocommerce' );

	foreach ( $post_ids as $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			continue;
		}

		// Personal-identifier metas: overwrite in-place with the redaction marker
		// so the record's structural integrity is preserved.
		foreach ( array( '_apg_withdrawal_name', '_apg_withdrawal_email', '_apg_withdrawal_phone', '_apg_withdrawal_ip', '_apg_withdrawal_user_agent' ) as $personal_key ) {
			$current = get_post_meta( $post_id, $personal_key, true );
			if ( '' !== (string) $current && $redacted_marker !== (string) $current ) {
				update_post_meta( $post_id, $personal_key, $redacted_marker );
			}
		}

		// Customer-supplied free text potentially contains personal data too.
		$order_ref = (string) get_post_meta( $post_id, '_apg_withdrawal_order', true );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $redacted_marker,
				'post_title'   => sprintf(
					/* translators: 1: order number, 2: redacted marker. */
					__( 'Withdrawal request %1$s - %2$s', 'apg-withdrawal-for-woocommerce' ),
					$order_ref,
					$redacted_marker
				),
			)
		);

		++$items_retained;
		$messages[] = sprintf(
			/* translators: %d withdrawal request post ID. */
			__( 'Withdrawal request #%d was anonymised and retained for legal evidence. The associated WooCommerce order reference was preserved.', 'apg-withdrawal-for-woocommerce' ),
			$post_id
		);
	}

	return array(
		'items_removed'  => 0,
		'items_retained' => $items_retained,
		'messages'       => $messages,
		'done'           => true,
	);
}

/**
 * Anonymises every withdrawal request belonging to a WordPress user that is
 * about to be deleted, regardless of who triggers the deletion (administrator
 * via *Users → Delete*, a "Delete my account" button shipped by plugins such
 * as `apg-gdpr-texts-for-forms`, a custom WooCommerce flow, etc.). Without
 * this hook the records would remain with the customer's personal data after
 * the user account itself disappears, since `wp_delete_user()` does not go
 * through the WordPress privacy eraser API.
 *
 * Reuses {@see apg_withdrawal_data_eraser()} so the anonymisation policy is
 * identical to the privacy-tools flow: name, email, phone, IP, user agent
 * and free text are replaced with `[redacted]`, while the request itself,
 * its `_apg_withdrawal_wc_order_id` reference and the status / scope metas
 * are preserved as legal evidence.
 *
 * @param int $user_id ID of the user being deleted.
 * @return void
 */
function apg_withdrawal_anonymize_on_user_delete( $user_id ) {
	$user = get_userdata( (int) $user_id );
	if ( ! $user || empty( $user->user_email ) ) {
		return;
	}

	apg_withdrawal_data_eraser( $user->user_email );
}
add_action( 'delete_user', 'apg_withdrawal_anonymize_on_user_delete' );
add_action( 'wpmu_delete_user', 'apg_withdrawal_anonymize_on_user_delete' );
