<?php
/**
 * Integration with the WooCommerce orders list and order edit screens.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns all withdrawal request posts linked to a given WooCommerce order.
 *
 * @param int $order_id WooCommerce order ID.
 * @return WP_Post[] Array of withdrawal request post objects.
 */
function apg_withdrawal_get_order_linked_withdrawals( $order_id ) {
	return get_posts(
		array(
			'post_type'      => 'apg_withdrawal',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- No alternative without meta_query
				array(
					'key'     => '_apg_withdrawal_wc_order_id',
					'value'   => absint( $order_id ),
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
}

/**
 * Returns the IDs of all WooCommerce orders that have at least one withdrawal request.
 *
 * @return int[] Array of WooCommerce order IDs.
 */
function apg_withdrawal_get_order_ids_with_withdrawals() {
	global $wpdb;
	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance-critical batch query, no suitable WP API
		$wpdb->prepare(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
			WHERE meta_key = %s
			AND meta_value != ''
			AND meta_value != '0'",
			'_apg_withdrawal_wc_order_id'
		)
	);
	return array_map( 'absint', $ids );
}

// ——————————————————————————————————
// COLUMNA EN LA LISTA DE PEDIDOS
// ——————————————————————————————————

/**
 * Adds a Withdrawal column to the WooCommerce orders list table.
 *
 * @param array $columns Existing columns array.
 * @return array Modified columns array with the Withdrawal column inserted after order_status.
 */
function apg_withdrawal_add_orders_column( $columns ) {
	$new = array();

	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'order_status' === $key ) {
			$new['apg_withdrawal'] = __( 'Withdrawal', 'apg-withdrawal-for-woocommerce' );
		}
	}

	if ( ! isset( $new['apg_withdrawal'] ) ) {
		$new['apg_withdrawal'] = __( 'Withdrawal', 'apg-withdrawal-for-woocommerce' );
	}

	return $new;
}

/**
 * Renders the content of the Withdrawal column for a given order row.
 *
 * @param string         $column      Column key being rendered.
 * @param WC_Order|int   $order_or_id WooCommerce order object or order ID.
 * @return void
 */
function apg_withdrawal_render_orders_column( $column, $order_or_id ) {
	if ( 'apg_withdrawal' !== $column ) {
		return;
	}

	if ( is_object( $order_or_id ) && is_callable( array( $order_or_id, 'get_id' ) ) ) {
		$order_id = $order_or_id->get_id();
	} else {
		$order_id = absint( $order_or_id );
	}

	$withdrawals = apg_withdrawal_get_order_linked_withdrawals( $order_id );

	if ( empty( $withdrawals ) ) {
		echo '&mdash;';
		return;
	}

	$status_labels = array(
		'pending'   => __( 'Pending', 'apg-withdrawal-for-woocommerce' ),
		'accepted'  => __( 'Accepted', 'apg-withdrawal-for-woocommerce' ),
		'rejected'  => __( 'Rejected', 'apg-withdrawal-for-woocommerce' ),
		'completed' => __( 'Completed', 'apg-withdrawal-for-woocommerce' ),
	);

	foreach ( $withdrawals as $withdrawal ) {
		$status = get_post_meta( $withdrawal->ID, '_apg_withdrawal_status', true );
		$status = $status ? $status : 'pending';
		$label  = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
		$url    = get_edit_post_link( $withdrawal->ID );
		printf(
			'<a href="%1$s"><span class="apg-status apg-status-%2$s">%3$s</span></a>',
			esc_url( $url ),
			esc_attr( $status ),
			esc_html( $label )
		);
	}
}

// HPOS
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'apg_withdrawal_add_orders_column' );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'apg_withdrawal_render_orders_column', 10, 2 );

// Legacy post-based orders
add_filter( 'manage_edit-shop_order_columns', 'apg_withdrawal_add_orders_column' );
add_action( 'manage_shop_order_posts_custom_column', 'apg_withdrawal_render_orders_column', 10, 2 );

// ——————————————————————————————————
// FILTRO EN LA LISTA DE PEDIDOS
// ——————————————————————————————————

/**
 * Renders the withdrawal status filter dropdown above the orders list table.
 *
 * @return void
 */
function apg_withdrawal_render_filter_dropdown() {
	$current = isset( $_GET['apg_withdrawal_filter'] ) ? sanitize_key( wp_unslash( $_GET['apg_withdrawal_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter read; admin list table context, no state change
	?>
	<select name="apg_withdrawal_filter" id="apg_withdrawal_filter">
		<option value=""><?php esc_html_e( 'All withdrawal statuses', 'apg-withdrawal-for-woocommerce' ); ?></option>
		<option value="has" <?php selected( $current, 'has' ); ?>><?php esc_html_e( 'Has withdrawal request', 'apg-withdrawal-for-woocommerce' ); ?></option>
		<option value="none" <?php selected( $current, 'none' ); ?>><?php esc_html_e( 'No withdrawal request', 'apg-withdrawal-for-woocommerce' ); ?></option>
	</select>
	<?php
}

// HPOS filter dropdown
add_action(
	'woocommerce_order_list_table_restrict_manage_orders',
	function () {
		apg_withdrawal_render_filter_dropdown();
	}
);

// Legacy filter dropdown
add_action(
	'restrict_manage_posts',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'edit-shop_order' !== $screen->id ) {
			return;
		}

		apg_withdrawal_render_filter_dropdown();
	}
);

// HPOS: apply filter to query args
add_filter(
	'woocommerce_order_list_table_prepare_items_query_args',
	function ( $args ) {
		$filter = isset( $_GET['apg_withdrawal_filter'] ) ? sanitize_key( wp_unslash( $_GET['apg_withdrawal_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter read; admin list table context, no state change

		if ( ! $filter ) {
			return $args;
		}

		$order_ids = apg_withdrawal_get_order_ids_with_withdrawals();

		if ( 'has' === $filter ) {
			$args['include'] = empty( $order_ids ) ? array( 0 ) : $order_ids;
		} elseif ( 'none' === $filter ) {
			$args['exclude'] = $order_ids; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Necessary for filtering orders without withdrawals
		}

		return $args;
	}
);

// Legacy: apply filter via pre_get_posts
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( ! $query->is_main_query() || ! is_admin() ) {
			return;
		}

		if ( 'shop_order' !== $query->get( 'post_type' ) ) {
			return;
		}

		$filter = isset( $_GET['apg_withdrawal_filter'] ) ? sanitize_key( wp_unslash( $_GET['apg_withdrawal_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter read; admin list table context, no state change

		if ( ! $filter ) {
			return;
		}

		$order_ids = apg_withdrawal_get_order_ids_with_withdrawals();

		if ( 'has' === $filter ) {
			$query->set( 'post__in', empty( $order_ids ) ? array( 0 ) : $order_ids );
		} elseif ( 'none' === $filter && ! empty( $order_ids ) ) {
			$query->set( 'post__not_in', $order_ids ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Required for filter exclusion
		}
	}
);

// ——————————————————————————————————
// METABOX EN LA FICHA DEL PEDIDO
// ——————————————————————————————————

/**
 * Returns the list of admin screen IDs where the order withdrawal metabox should appear.
 *
 * @return string[] Array of screen ID strings.
 */
function apg_withdrawal_get_order_edit_screens() {
	$screens = array( 'shop_order' );

	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& method_exists( '\Automattic\WooCommerce\Utilities\OrderUtil', 'get_order_admin_screen' ) ) {
		$hpos_screen = \Automattic\WooCommerce\Utilities\OrderUtil::get_order_admin_screen();

		if ( $hpos_screen && ! in_array( $hpos_screen, $screens, true ) ) {
			$screens[] = $hpos_screen;
		}
	}

	return $screens;
}

add_action( 'add_meta_boxes', 'apg_withdrawal_add_order_metabox' );

/**
 * Registers the withdrawal requests metabox on all relevant order edit screens.
 *
 * @return void
 */
function apg_withdrawal_add_order_metabox() {
	foreach ( apg_withdrawal_get_order_edit_screens() as $screen ) {
		add_meta_box(
			'apg-withdrawal-order-requests',
			__( 'Withdrawal requests', 'apg-withdrawal-for-woocommerce' ),
			'apg_withdrawal_render_order_metabox',
			$screen,
			'side'
		);
	}
}

/**
 * Renders the withdrawal requests metabox content on the order edit screen.
 *
 * @param WP_Post|WC_Order $post_or_order Post object or WooCommerce order object.
 * @return void
 */
function apg_withdrawal_render_order_metabox( $post_or_order ) {
	if ( $post_or_order instanceof WP_Post ) {
		$order_id = $post_or_order->ID;
	} elseif ( is_callable( array( $post_or_order, 'get_id' ) ) ) {
		$order_id = $post_or_order->get_id();
	} else {
		$order_id = 0;
	}

	if ( ! $order_id ) {
		return;
	}

	$withdrawals = apg_withdrawal_get_order_linked_withdrawals( $order_id );

	if ( empty( $withdrawals ) ) {
		echo '<p>' . esc_html__( 'No withdrawal requests for this order.', 'apg-withdrawal-for-woocommerce' ) . '</p>';
		return;
	}

	$status_labels = array(
		'pending'   => __( 'Pending', 'apg-withdrawal-for-woocommerce' ),
		'accepted'  => __( 'Accepted', 'apg-withdrawal-for-woocommerce' ),
		'rejected'  => __( 'Rejected', 'apg-withdrawal-for-woocommerce' ),
		'completed' => __( 'Completed', 'apg-withdrawal-for-woocommerce' ),
	);

	echo '<ul style="margin:0;padding:0;list-style:none;">';

	foreach ( $withdrawals as $withdrawal ) {
		$status = get_post_meta( $withdrawal->ID, '_apg_withdrawal_status', true );
		$status = $status ? $status : 'pending';
		$label  = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status;
		$url    = get_edit_post_link( $withdrawal->ID );
		printf(
			'<li style="margin-bottom:6px;"><a href="%1$s">#%2$d</a> &mdash; <span class="apg-status apg-status-%3$s">%4$s</span><br><small style="color:#646970;">%5$s</small></li>',
			esc_url( $url ),
			absint( $withdrawal->ID ),
			esc_attr( $status ),
			esc_html( $label ),
			esc_html( get_the_date( get_option( 'date_format' ), $withdrawal ) )
		);
	}

	echo '</ul>';
}
