<?php
/**
 * Admin-side logic for the withdrawal request list and editor.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the withdrawal request statuses as slug => translated label, in the
 * order they follow in the workflow. Single source of truth for the list table,
 * the metaboxes, the bulk actions, the CSV export and the REST API.
 *
 * @return array<string,string>
 */
function apg_withdrawal_get_statuses() {
	return array(
		'pending'   => __( 'Pending', 'apg-withdrawal-for-woocommerce' ),
		'accepted'  => __( 'Accepted', 'apg-withdrawal-for-woocommerce' ),
		'rejected'  => __( 'Rejected', 'apg-withdrawal-for-woocommerce' ),
		'completed' => __( 'Completed', 'apg-withdrawal-for-woocommerce' ),
	);
}

/**
 * Counts published withdrawal requests grouped by status in a single query, so
 * the status views can show their counts without one query per status.
 *
 * Requests created before the status meta existed, or whose meta is empty, are
 * counted as `pending` — which is what the list table already displays for them.
 *
 * @return array<string,int> Counts keyed by status slug, plus `all`.
 */
function apg_withdrawal_count_requests_by_status() {
	global $wpdb;

	$counts = array_fill_keys( array_keys( apg_withdrawal_get_statuses() ), 0 );

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate over a custom meta key; no core API covers it, and the result feeds a single admin screen.
		"SELECT COALESCE( NULLIF( pm.meta_value, '' ), 'pending' ) AS status, COUNT( p.ID ) AS total
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_apg_withdrawal_status'
		WHERE p.post_type = 'apg_withdrawal' AND p.post_status = 'publish'
		GROUP BY status",
		ARRAY_A
	);

	foreach ( (array) $rows as $row ) {
		$status = isset( $row['status'] ) ? (string) $row['status'] : 'pending';

		if ( ! isset( $counts[ $status ] ) ) {
			$status = 'pending';
		}

		$counts[ $status ] += (int) $row['total'];
	}

	$counts['all'] = array_sum( $counts );

	return $counts;
}

/**
 * Reads the status currently selected in the list table, if any.
 *
 * @return string Status slug, or empty string when no filter is active.
 */
function apg_withdrawal_get_current_status_filter() {
	$status = isset( $_GET['apg_withdrawal_status'] ) ? sanitize_key( wp_unslash( $_GET['apg_withdrawal_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter; no state change.

	return isset( apg_withdrawal_get_statuses()[ $status ] ) ? $status : '';
}

/**
 * Replaces the post-status views of the list table ("All | Published | …", which
 * carry no meaning for this post type) with one link per withdrawal status,
 * each showing its count.
 *
 * @param array<string,string> $views Default views.
 * @return array<string,string> Withdrawal status views.
 */
function apg_withdrawal_status_views( $views ) {
	$counts  = apg_withdrawal_count_requests_by_status();
	$current = apg_withdrawal_get_current_status_filter();
	$base    = admin_url( 'edit.php?post_type=apg_withdrawal' );
	$links   = array();

	$links['all'] = sprintf(
		'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
		esc_url( $base ),
		'' === $current ? ' class="current" aria-current="page"' : '',
		esc_html__( 'All', 'apg-withdrawal-for-woocommerce' ),
		esc_html( number_format_i18n( $counts['all'] ) )
	);

	foreach ( apg_withdrawal_get_statuses() as $slug => $label ) {
		if ( empty( $counts[ $slug ] ) ) {
			continue;
		}

		$links[ $slug ] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
			esc_url( add_query_arg( 'apg_withdrawal_status', $slug, $base ) ),
			$current === $slug ? ' class="current" aria-current="page"' : '',
			esc_html( $label ),
			esc_html( number_format_i18n( $counts[ $slug ] ) )
		);
	}

	return $links;
}
add_filter( 'views_edit-apg_withdrawal', 'apg_withdrawal_status_views' );

/**
 * Applies the status filter and the status ordering to the list table query.
 *
 * `pending` is matched with a `NOT EXISTS` clause as well, so requests stored
 * before the status meta was introduced — and any whose meta was emptied — show
 * up under the status the list table displays for them.
 *
 * @param WP_Query $query Current admin query.
 * @return void
 */
function apg_withdrawal_filter_admin_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'apg_withdrawal' !== $query->get( 'post_type' ) ) {
		return;
	}

	$status = apg_withdrawal_get_current_status_filter();

	if ( $status ) {
		$meta_query = array(
			array(
				'key'     => '_apg_withdrawal_status',
				'value'   => $status,
				'compare' => '=',
			),
		);

		if ( 'pending' === $status ) {
			$meta_query['relation'] = 'OR';
			$meta_query[]           = array(
				'key'     => '_apg_withdrawal_status',
				'compare' => 'NOT EXISTS',
			);
			$meta_query[]           = array(
				'key'     => '_apg_withdrawal_status',
				'value'   => '',
				'compare' => '=',
			);
		}

		$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin list table filter; no alternative without meta_query.
	}

	if ( 'apg_withdrawal_status' === $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', '_apg_withdrawal_status' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required to sort by the status meta.
		$query->set( 'orderby', 'meta_value' );
	}
}
add_action( 'pre_get_posts', 'apg_withdrawal_filter_admin_query' );

/**
 * Marks the status and order columns as sortable.
 *
 * @param array<string,string> $columns Sortable columns.
 * @return array<string,string>
 */
function apg_withdrawal_sortable_columns( $columns ) {
	$columns['apg_withdrawal_status'] = 'apg_withdrawal_status';

	return $columns;
}
add_filter( 'manage_edit-apg_withdrawal_sortable_columns', 'apg_withdrawal_sortable_columns' );

/**
 * Adds one bulk action per withdrawal status so a batch of requests can be moved
 * through the workflow without opening them one by one.
 *
 * @param array<string,string> $actions Registered bulk actions.
 * @return array<string,string>
 */
function apg_withdrawal_bulk_actions( $actions ) {
	unset( $actions['edit'] );

	foreach ( apg_withdrawal_get_statuses() as $slug => $label ) {
		$actions[ 'apg_withdrawal_mark_' . $slug ] = sprintf(
			/* translators: %s: withdrawal status label. */
			__( 'Mark as %s', 'apg-withdrawal-for-woocommerce' ),
			$label
		);
	}

	return $actions;
}
add_filter( 'bulk_actions-edit-apg_withdrawal', 'apg_withdrawal_bulk_actions' );

/**
 * Applies a status bulk action. Goes through
 * {@see apg_withdrawal_change_status()} so each request gets its status-log
 * entry and, when the merchant enabled it, its customer notification — exactly
 * as if the status had been changed from the request screen.
 *
 * @param string    $redirect_to URL the list table will redirect to.
 * @param string    $action      Bulk action being applied.
 * @param array<int> $post_ids   Selected request IDs.
 * @return string Redirect URL carrying the result count.
 */
function apg_withdrawal_handle_bulk_actions( $redirect_to, $action, $post_ids ) {
	if ( 0 !== strpos( $action, 'apg_withdrawal_mark_' ) ) {
		return $redirect_to;
	}

	$status = substr( $action, strlen( 'apg_withdrawal_mark_' ) );

	if ( ! isset( apg_withdrawal_get_statuses()[ $status ] ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return $redirect_to;
	}

	$changed = 0;

	foreach ( (array) $post_ids as $post_id ) {
		if ( current_user_can( 'edit_post', $post_id ) && apg_withdrawal_change_status( absint( $post_id ), $status ) ) {
			++$changed;
		}
	}

	// The notice that reads these arguments back verifies this nonce before
	// trusting them, so the count and the label cannot be dictated by a crafted
	// link handed to a logged-in merchant.
	return add_query_arg(
		array(
			'apg_withdrawal_bulk_changed' => $changed,
			'apg_withdrawal_bulk_status'  => $status,
			'apg_withdrawal_bulk_nonce'   => wp_create_nonce( 'apg_withdrawal_bulk_notice' ),
		),
		$redirect_to
	);
}
add_filter( 'handle_bulk_actions-edit-apg_withdrawal', 'apg_withdrawal_handle_bulk_actions', 10, 3 );

/**
 * Reports the outcome of a status bulk action.
 *
 * @return void
 */
function apg_withdrawal_bulk_action_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'edit-apg_withdrawal' !== $screen->id ) {
		return;
	}

	$nonce = isset( $_GET['apg_withdrawal_bulk_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['apg_withdrawal_bulk_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'apg_withdrawal_bulk_notice' ) || ! isset( $_GET['apg_withdrawal_bulk_changed'] ) ) {
		return;
	}

	$changed  = absint( wp_unslash( $_GET['apg_withdrawal_bulk_changed'] ) );
	$status   = isset( $_GET['apg_withdrawal_bulk_status'] ) ? sanitize_key( wp_unslash( $_GET['apg_withdrawal_bulk_status'] ) ) : '';
	$statuses = apg_withdrawal_get_statuses();

	if ( ! isset( $statuses[ $status ] ) ) {
		return;
	}

	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: number of requests updated, 2: withdrawal status label. */
				_n( '%1$d withdrawal request marked as %2$s.', '%1$d withdrawal requests marked as %2$s.', $changed, 'apg-withdrawal-for-woocommerce' ),
				$changed,
				$statuses[ $status ]
			)
		)
	);
}
add_action( 'admin_notices', 'apg_withdrawal_bulk_action_notice' );

/**
 * Defines the columns for the withdrawal requests admin list table.
 *
 * @param array $columns Default columns array.
 * @return array Replacement columns array for the apg_withdrawal post type.
 */
function apg_withdrawal_admin_columns( $columns ) {
	$new_columns = array(
		'cb'                     => isset( $columns['cb'] ) ? $columns['cb'] : '',
		'title'                  => __( 'Reference', 'apg-withdrawal-for-woocommerce' ),
		'apg_withdrawal_name'    => __( 'Customer', 'apg-withdrawal-for-woocommerce' ),
		'apg_withdrawal_email'   => __( 'Email', 'apg-withdrawal-for-woocommerce' ),
		'apg_withdrawal_order'   => __( 'Order', 'apg-withdrawal-for-woocommerce' ),
		'apg_withdrawal_scope'   => __( 'Scope', 'apg-withdrawal-for-woocommerce' ),
		'apg_withdrawal_status'  => __( 'Status', 'apg-withdrawal-for-woocommerce' ),
		'date'                   => __( 'Date', 'apg-withdrawal-for-woocommerce' ),
	);

	return $new_columns;
}
add_filter( 'manage_apg_withdrawal_posts_columns', 'apg_withdrawal_admin_columns' );

/**
 * Renders the content for each custom column in the withdrawal requests list table.
 *
 * @param string $column  Column key being rendered.
 * @param int    $post_id Withdrawal request post ID.
 * @return void
 */
function apg_withdrawal_admin_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'apg_withdrawal_name':
			echo esc_html( get_post_meta( $post_id, '_apg_withdrawal_name', true ) );
			break;
		case 'apg_withdrawal_email':
			$email = get_post_meta( $post_id, '_apg_withdrawal_email', true );
			if ( $email ) {
				printf( '<a href="mailto:%1$s">%2$s</a>', esc_attr( $email ), esc_html( $email ) );
			}
			break;
		case 'apg_withdrawal_order':
			$order_ref = get_post_meta( $post_id, '_apg_withdrawal_order', true );
			$order_id  = absint( get_post_meta( $post_id, '_apg_withdrawal_wc_order_id', true ) );

			if ( $order_id ) {
				$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
				$url   = $order && is_callable( array( $order, 'get_edit_order_url' ) ) ? $order->get_edit_order_url() : admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
				printf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $order_ref ) );
			} else {
				echo esc_html( $order_ref );
			}
			break;
		case 'apg_withdrawal_scope':
			echo esc_html( 'partial' === get_post_meta( $post_id, '_apg_withdrawal_scope', true ) ? __( 'Specific products only', 'apg-withdrawal-for-woocommerce' ) : __( 'Full order', 'apg-withdrawal-for-woocommerce' ) );
			break;
		case 'apg_withdrawal_status':
			$status = get_post_meta( $post_id, '_apg_withdrawal_status', true );
			$labels = apg_withdrawal_get_statuses();
			printf( '<span class="apg-status apg-status-%1$s">%2$s</span>', esc_attr( $status ? $status : 'pending' ), esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['pending'] ) );
			break;
	}
}
add_action( 'manage_apg_withdrawal_posts_custom_column', 'apg_withdrawal_admin_column_content', 10, 2 );

/**
 * Registers the details and status metaboxes on the withdrawal request edit screen.
 *
 * @return void
 */
function apg_withdrawal_add_metaboxes() {
	add_meta_box( 'apg_withdrawal_details', __( 'Withdrawal details', 'apg-withdrawal-for-woocommerce' ), 'apg_withdrawal_render_details_metabox', 'apg_withdrawal', 'normal', 'high' );
	add_meta_box( 'apg_withdrawal_status', __( 'Status', 'apg-withdrawal-for-woocommerce' ), 'apg_withdrawal_render_status_metabox', 'apg_withdrawal', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'apg_withdrawal_add_metaboxes' );

/**
 * Renders the withdrawal request details metabox content.
 *
 * @param WP_Post $post The current withdrawal request post object.
 * @return void
 */
function apg_withdrawal_render_details_metabox( $post ) {
	$fields = array(
		'_apg_withdrawal_name'            => __( 'Customer', 'apg-withdrawal-for-woocommerce' ),
		'_apg_withdrawal_email'           => __( 'Email', 'apg-withdrawal-for-woocommerce' ),
		'_apg_withdrawal_phone'           => __( 'Phone', 'apg-withdrawal-for-woocommerce' ),
		'_apg_withdrawal_order'           => __( 'Order', 'apg-withdrawal-for-woocommerce' ),
		'_apg_withdrawal_scope'           => __( 'Scope', 'apg-withdrawal-for-woocommerce' ),
		'_apg_withdrawal_iban'            => __( 'Account for the refund', 'apg-withdrawal-for-woocommerce' ),
		'_apg_withdrawal_deadline_source' => __( 'Deadline source', 'apg-withdrawal-for-woocommerce' ),
		'_apg_withdrawal_deadline_date'   => __( 'Base date', 'apg-withdrawal-for-woocommerce' ),
		'_apg_withdrawal_email_verified'  => __( 'Email confirmed at (UTC)', 'apg-withdrawal-for-woocommerce' ),
		'_apg_withdrawal_ip'              => __( 'IP', 'apg-withdrawal-for-woocommerce' ),
		'_apg_withdrawal_user_agent'      => __( 'User agent', 'apg-withdrawal-for-woocommerce' ),
	);

	$expired_warning = get_post_meta( $post->ID, '_apg_withdrawal_expired_warning', true );

	if ( $expired_warning ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'This request was submitted after the ordinary withdrawal period had expired.', 'apg-withdrawal-for-woocommerce' ) . '</p></div>';
	}

	echo '<table class="apg-withdrawal-meta-table"><tbody>';

	foreach ( $fields as $meta_key => $label ) {
		$value = get_post_meta( $post->ID, $meta_key, true );

		if ( '_apg_withdrawal_scope' === $meta_key ) {
			$value = 'partial' === $value ? __( 'Specific products only', 'apg-withdrawal-for-woocommerce' ) : __( 'Full order', 'apg-withdrawal-for-woocommerce' );
		}

		// Optional fields: skip the row entirely when the request carries no value,
		// so the table does not grow a column of blanks for every install that
		// leaves the IBAN or the guest verification turned off.
		if ( in_array( $meta_key, array( '_apg_withdrawal_iban', '_apg_withdrawal_email_verified', '_apg_withdrawal_phone' ), true ) && '' === (string) $value ) {
			continue;
		}

		if ( '_apg_withdrawal_iban' === $meta_key ) {
			$value = apg_withdrawal_format_iban( $value );
		}

		printf( '<tr><th>%1$s</th><td>%2$s</td></tr>', esc_html( $label ), esc_html( $value ) );
	}

	echo '</tbody></table>';

	if ( $post->post_content ) {
		echo '<h4>' . esc_html__( 'Additional details', 'apg-withdrawal-for-woocommerce' ) . '</h4>';
		echo '<div class="apg-withdrawal-details-box">' . wp_kses_post( wpautop( $post->post_content ) ) . '</div>';
	}

	$selected_products = get_post_meta( $post->ID, '_apg_withdrawal_products', true );
	$order_id          = absint( get_post_meta( $post->ID, '_apg_withdrawal_wc_order_id', true ) );
	$order             = $order_id ? apg_withdrawal_get_order( $order_id ) : false;
	$product_labels    = is_array( $selected_products ) ? apg_withdrawal_get_selected_product_labels( $order, $selected_products, apg_withdrawal_get_request_quantities( $post->ID ) ) : array();

	if ( $product_labels ) {
		echo '<h4>' . esc_html__( 'Selected products', 'apg-withdrawal-for-woocommerce' ) . '</h4>';
		echo '<div class="apg-withdrawal-details-box">' . esc_html( implode( ', ', $product_labels ) ) . '</div>';
	}

	$initial_delivery = get_post_meta( $post->ID, '_apg_withdrawal_initial_email_delivery', true );
	if ( is_array( $initial_delivery ) && ! empty( $initial_delivery ) ) {
		echo '<h4>' . esc_html__( 'Acknowledgement email', 'apg-withdrawal-for-woocommerce' ) . '</h4>';
		echo '<div class="apg-withdrawal-details-box">';
		echo wp_kses_post( apg_withdrawal_render_delivery_summary( $initial_delivery ) );
		echo '</div>';
	}

	$log = get_post_meta( $post->ID, '_apg_withdrawal_status_log', true );

	if ( is_array( $log ) && ! empty( $log ) ) {
		echo '<h4>' . esc_html__( 'Status history', 'apg-withdrawal-for-woocommerce' ) . '</h4>';
		echo '<table class="apg-withdrawal-meta-table apg-withdrawal-log-table"><thead>';
		echo '<tr><th>' . esc_html__( 'Date', 'apg-withdrawal-for-woocommerce' ) . '</th><th>' . esc_html__( 'User', 'apg-withdrawal-for-woocommerce' ) . '</th><th>' . esc_html__( 'From', 'apg-withdrawal-for-woocommerce' ) . '</th><th>' . esc_html__( 'To', 'apg-withdrawal-for-woocommerce' ) . '</th><th>' . esc_html__( 'Email delivery', 'apg-withdrawal-for-woocommerce' ) . '</th></tr>';
		echo '</thead><tbody>';

		$status_labels = apg_withdrawal_get_statuses();

		foreach ( $log as $entry ) {
			$user      = isset( $entry['user_id'] ) ? get_userdata( absint( $entry['user_id'] ) ) : false;
			$user_name = $user ? $user->display_name : __( 'System', 'apg-withdrawal-for-woocommerce' );
			$from      = isset( $entry['from'] ) && isset( $status_labels[ $entry['from'] ] ) ? $status_labels[ $entry['from'] ] : esc_html( $entry['from'] ?? '' );
			$to        = isset( $entry['to'] ) && isset( $status_labels[ $entry['to'] ] ) ? $status_labels[ $entry['to'] ] : esc_html( $entry['to'] ?? '' );
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td></tr>',
				esc_html( $entry['date'] ?? '' ),
				esc_html( $user_name ),
				esc_html( $from ),
				esc_html( $to ),
				wp_kses_post( apg_withdrawal_render_delivery_summary( $entry ) )
			);
		}

		echo '</tbody></table>';
	}
}

/**
 * Renders a short human-readable summary of an email delivery record (initial
 * acknowledgement or status-change log entry). Used both in the admin detail
 * view and as a building block for richer summaries.
 *
 * @param array $entry Delivery info or status-log entry with the email fields.
 * @return string Inline HTML summary.
 */
function apg_withdrawal_render_delivery_summary( $entry ) {
	$attempted = ! empty( $entry['email_attempted'] ) || ! empty( $entry['attempted'] );
	$accepted  = isset( $entry['email_accepted'] ) ? $entry['email_accepted'] : ( $entry['accepted'] ?? null );
	$when      = isset( $entry['email_accepted_at'] ) ? (string) $entry['email_accepted_at'] : (string) ( $entry['accepted_at'] ?? '' );
	$error     = isset( $entry['email_error'] ) ? (string) $entry['email_error'] : (string) ( $entry['error'] ?? '' );

	if ( ! $attempted ) {
		return esc_html__( '— (not sent)', 'apg-withdrawal-for-woocommerce' );
	}

	if ( true === (bool) $accepted && '' === $error ) {
		return sprintf(
			'✓ %s %s',
			esc_html__( 'Accepted by the mailer at', 'apg-withdrawal-for-woocommerce' ),
			esc_html( $when . ' UTC' )
		);
	}

	return sprintf(
		'✗ %s%s',
		esc_html__( 'Failed', 'apg-withdrawal-for-woocommerce' ),
		'' !== $error ? ': ' . esc_html( $error ) : ''
	);
}

/**
 * Renders the withdrawal request status metabox with a status change dropdown.
 *
 * @param WP_Post $post The current withdrawal request post object.
 * @return void
 */
function apg_withdrawal_render_status_metabox( $post ) {
	wp_nonce_field( 'apg_withdrawal_save_status', 'apg_withdrawal_status_nonce' );

	$current_status = get_post_meta( $post->ID, '_apg_withdrawal_status', true );
	$statuses       = apg_withdrawal_get_statuses();

	echo '<p><label for="apg_withdrawal_status_field"><strong>' . esc_html__( 'Set status', 'apg-withdrawal-for-woocommerce' ) . '</strong></label></p>';
	echo '<select id="apg_withdrawal_status_field" name="apg_withdrawal_status_field" style="width:100%;">';

	foreach ( $statuses as $key => $label ) {
		printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $current_status ? $current_status : 'pending', $key, false ), esc_html( $label ) );
	}

	echo '</select>';
}

/**
 * Saves the withdrawal request status when the post is saved from the admin screen.
 *
 * @param int $post_id Withdrawal request post ID.
 * @return void
 */
function apg_withdrawal_save_status( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['apg_withdrawal_status_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apg_withdrawal_status_nonce'] ) ), 'apg_withdrawal_save_status' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) || 'apg_withdrawal' !== get_post_type( $post_id ) ) {
		return;
	}

	if ( isset( $_POST['apg_withdrawal_status_field'] ) ) {
		$new_status = sanitize_key( wp_unslash( $_POST['apg_withdrawal_status_field'] ) );

		if ( function_exists( 'apg_withdrawal_change_status' ) ) {
			apg_withdrawal_change_status( $post_id, $new_status );
		} elseif ( in_array( $new_status, array( 'pending', 'accepted', 'rejected', 'completed' ), true ) ) {
			update_post_meta( $post_id, '_apg_withdrawal_status', $new_status );
		}
	}
}
add_action( 'save_post_apg_withdrawal', 'apg_withdrawal_save_status' );

/**
 * Generates and streams a CSV export of all withdrawal requests to the browser.
 *
 * @return void
 */
/**
 * Writes a CSV row to the given stream after applying OWASP-recommended
 * defences against CSV / spreadsheet formula injection.
 *
 * Cells whose first character is one of `=`, `+`, `-`, `@`, tab or carriage
 * return can be interpreted as a formula by spreadsheet software like Excel
 * or Numbers. To prevent that, those values are prefixed with a leading
 * apostrophe (`'`) which is then stripped on display by the spreadsheet but
 * neutralises the formula evaluation.
 *
 * @param resource           $handle Open file pointer (php://output is fine).
 * @param array<int,scalar>  $fields Row values to be serialised.
 * @return void
 */
function apg_withdrawal_fputcsv_safe( $handle, array $fields ) {
	$dangerous_prefixes = array( '=', '+', '-', '@', "\t", "\r" );

	$safe = array_map(
		function ( $value ) use ( $dangerous_prefixes ) {
			$string = (string) $value;
			if ( '' === $string ) {
				return $string;
			}
			if ( in_array( $string[0], $dangerous_prefixes, true ) ) {
				return "'" . $string;
			}
			return $string;
		},
		$fields
	);

	fputcsv( $handle, $safe, ',', '"', '\\' );
}

/**
 * Streams the full withdrawal request log as a CSV download. Hooked on
 * `admin_post_apg_withdrawal_export_csv` and triggered from the admin toolbar.
 * Sets the appropriate HTTP headers, writes a UTF-8 BOM so spreadsheet
 * software opens the file with the correct encoding, and emits each row via
 * `apg_withdrawal_fputcsv_safe()` so cells starting with formula-trigger
 * characters cannot be evaluated by Excel / Numbers / LibreOffice.
 *
 * @return void
 */
function apg_withdrawal_export_csv() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to export withdrawal requests.', 'apg-withdrawal-for-woocommerce' ) );
	}

	if ( ! isset( $_GET['apg_withdrawal_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['apg_withdrawal_export_nonce'] ) ), 'apg_withdrawal_export' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'apg-withdrawal-for-woocommerce' ) );
	}

	$status_labels = apg_withdrawal_get_statuses();
	$status_filter = isset( $_GET['apg_withdrawal_status'] ) ? sanitize_key( wp_unslash( $_GET['apg_withdrawal_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Export nonce verified above.
	$status_filter = isset( $status_labels[ $status_filter ] ) ? $status_filter : '';

	$query_args = array(
		'post_type'      => 'apg_withdrawal',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	// Mirror the status the merchant is looking at in the list table, so the
	// button exports what is on screen rather than always the whole log.
	if ( $status_filter ) {
		$meta_query = array(
			array(
				'key'     => '_apg_withdrawal_status',
				'value'   => $status_filter,
				'compare' => '=',
			),
		);

		if ( 'pending' === $status_filter ) {
			$meta_query['relation'] = 'OR';
			$meta_query[]           = array(
				'key'     => '_apg_withdrawal_status',
				'compare' => 'NOT EXISTS',
			);
			$meta_query[]           = array(
				'key'     => '_apg_withdrawal_status',
				'value'   => '',
				'compare' => '=',
			);
		}

		$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-triggered export; no alternative without meta_query.
	}

	$posts = get_posts( $query_args );

	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="withdrawal-requests-' . ( $status_filter ? $status_filter . '-' : '' ) . gmdate( 'Y-m-d' ) . '.csv"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$output = fopen( 'php://output', 'w' );

	fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

	apg_withdrawal_fputcsv_safe(
		$output,
		array(
			__( 'ID', 'apg-withdrawal-for-woocommerce' ),
			__( 'Date', 'apg-withdrawal-for-woocommerce' ),
			__( 'Customer', 'apg-withdrawal-for-woocommerce' ),
			__( 'Email', 'apg-withdrawal-for-woocommerce' ),
			__( 'Phone', 'apg-withdrawal-for-woocommerce' ),
			__( 'Order', 'apg-withdrawal-for-woocommerce' ),
			__( 'Scope', 'apg-withdrawal-for-woocommerce' ),
			__( 'Status', 'apg-withdrawal-for-woocommerce' ),
			__( 'Expired warning', 'apg-withdrawal-for-woocommerce' ),
			__( 'IP', 'apg-withdrawal-for-woocommerce' ),
			__( 'User agent', 'apg-withdrawal-for-woocommerce' ),
			__( 'Details', 'apg-withdrawal-for-woocommerce' ),
			__( 'Products', 'apg-withdrawal-for-woocommerce' ),
			__( 'Account for the refund', 'apg-withdrawal-for-woocommerce' ),
			__( 'Email confirmed at (UTC)', 'apg-withdrawal-for-woocommerce' ),
			__( 'Receipt SHA-256', 'apg-withdrawal-for-woocommerce' ),
			__( 'Acknowledgement email accepted', 'apg-withdrawal-for-woocommerce' ),
			__( 'Acknowledgement email accepted at (UTC)', 'apg-withdrawal-for-woocommerce' ),
		)
	);

	foreach ( $posts as $post ) {
		$status  = get_post_meta( $post->ID, '_apg_withdrawal_status', true );
		$scope   = get_post_meta( $post->ID, '_apg_withdrawal_scope', true );
		$expired = get_post_meta( $post->ID, '_apg_withdrawal_expired_warning', true );

		$products_label = '';
		if ( 'partial' === $scope ) {
			$stored_items = get_post_meta( $post->ID, '_apg_withdrawal_products', true );
			$wc_order_id  = absint( get_post_meta( $post->ID, '_apg_withdrawal_wc_order_id', true ) );

			if ( $wc_order_id && ! empty( $stored_items ) && is_array( $stored_items ) ) {
				$products_label = implode(
					'; ',
					apg_withdrawal_get_selected_product_labels(
						apg_withdrawal_get_order( $wc_order_id ),
						$stored_items,
						apg_withdrawal_get_request_quantities( $post->ID )
					)
				);
			}
		}

		$initial_delivery = get_post_meta( $post->ID, '_apg_withdrawal_initial_email_delivery', true );
		$delivery_accepted = '';
		$delivery_when     = '';
		if ( is_array( $initial_delivery ) ) {
			$delivery_when = isset( $initial_delivery['accepted_at'] ) ? (string) $initial_delivery['accepted_at'] : '';
			if ( ! empty( $initial_delivery['attempted'] ) ) {
				$delivery_accepted = ! empty( $initial_delivery['accepted'] )
					? __( 'Yes', 'apg-withdrawal-for-woocommerce' )
					: __( 'No', 'apg-withdrawal-for-woocommerce' );
			}
		}

		apg_withdrawal_fputcsv_safe(
			$output,
			array(
				$post->ID,
				$post->post_date,
				get_post_meta( $post->ID, '_apg_withdrawal_name', true ),
				get_post_meta( $post->ID, '_apg_withdrawal_email', true ),
				get_post_meta( $post->ID, '_apg_withdrawal_phone', true ),
				get_post_meta( $post->ID, '_apg_withdrawal_order', true ),
				'partial' === $scope ? __( 'Specific products only', 'apg-withdrawal-for-woocommerce' ) : __( 'Full order', 'apg-withdrawal-for-woocommerce' ),
				isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : $status,
				$expired ? __( 'Yes', 'apg-withdrawal-for-woocommerce' ) : __( 'No', 'apg-withdrawal-for-woocommerce' ),
				get_post_meta( $post->ID, '_apg_withdrawal_ip', true ),
				get_post_meta( $post->ID, '_apg_withdrawal_user_agent', true ),
				$post->post_content,
				$products_label,
				apg_withdrawal_format_iban( get_post_meta( $post->ID, '_apg_withdrawal_iban', true ) ),
				get_post_meta( $post->ID, '_apg_withdrawal_email_verified', true ),
				get_post_meta( $post->ID, '_apg_withdrawal_receipt_hash', true ),
				$delivery_accepted,
				$delivery_when,
			)
		);
	}

	fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming to php://output; WP_Filesystem does not support output stream wrappers
	exit;
}
add_action( 'admin_post_apg_withdrawal_export_csv', 'apg_withdrawal_export_csv' );

/**
 * Renders the Export CSV button in the withdrawal requests list table navigation area.
 *
 * @return void
 */
function apg_withdrawal_list_export_link() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'edit-apg_withdrawal' !== $screen->id ) {
		return;
	}

	$status = apg_withdrawal_get_current_status_filter();
	$target = admin_url( 'admin-post.php?action=apg_withdrawal_export_csv' );

	if ( $status ) {
		$target = add_query_arg( 'apg_withdrawal_status', $status, $target );
	}

	$url = wp_nonce_url( $target, 'apg_withdrawal_export', 'apg_withdrawal_export_nonce' );

	printf(
		'<div class="apg-withdrawal-export-wrap"><a href="%1$s" class="button">%2$s</a></div>',
		esc_url( $url ),
		$status
			? esc_html(
				sprintf(
					/* translators: %s: withdrawal status label. */
					__( 'Export CSV (%s)', 'apg-withdrawal-for-woocommerce' ),
					apg_withdrawal_get_statuses()[ $status ]
				)
			)
			: esc_html__( 'Export CSV', 'apg-withdrawal-for-woocommerce' )
	);
}
add_action( 'manage_posts_extra_tablenav', 'apg_withdrawal_list_export_link' );
