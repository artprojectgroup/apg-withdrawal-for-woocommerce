<?php
/**
 * Admin-side logic for the withdrawal request list and editor.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

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
			$labels = array(
				'pending'   => __( 'Pending', 'apg-withdrawal-for-woocommerce' ),
				'accepted'  => __( 'Accepted', 'apg-withdrawal-for-woocommerce' ),
				'rejected'  => __( 'Rejected', 'apg-withdrawal-for-woocommerce' ),
				'completed' => __( 'Completed', 'apg-withdrawal-for-woocommerce' ),
			);
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
		'_apg_withdrawal_deadline_source' => __( 'Deadline source', 'apg-withdrawal-for-woocommerce' ),
		'_apg_withdrawal_deadline_date'   => __( 'Base date', 'apg-withdrawal-for-woocommerce' ),
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
	$product_labels    = is_array( $selected_products ) ? apg_withdrawal_get_selected_product_labels( $order, $selected_products ) : array();

	if ( $product_labels ) {
		echo '<h4>' . esc_html__( 'Selected products', 'apg-withdrawal-for-woocommerce' ) . '</h4>';
		echo '<div class="apg-withdrawal-details-box">' . esc_html( implode( ', ', $product_labels ) ) . '</div>';
	}

	$log = get_post_meta( $post->ID, '_apg_withdrawal_status_log', true );

	if ( is_array( $log ) && ! empty( $log ) ) {
		echo '<h4>' . esc_html__( 'Status history', 'apg-withdrawal-for-woocommerce' ) . '</h4>';
		echo '<table class="apg-withdrawal-meta-table apg-withdrawal-log-table"><thead>';
		echo '<tr><th>' . esc_html__( 'Date', 'apg-withdrawal-for-woocommerce' ) . '</th><th>' . esc_html__( 'User', 'apg-withdrawal-for-woocommerce' ) . '</th><th>' . esc_html__( 'From', 'apg-withdrawal-for-woocommerce' ) . '</th><th>' . esc_html__( 'To', 'apg-withdrawal-for-woocommerce' ) . '</th></tr>';
		echo '</thead><tbody>';

		$status_labels = array(
			'pending'   => __( 'Pending', 'apg-withdrawal-for-woocommerce' ),
			'accepted'  => __( 'Accepted', 'apg-withdrawal-for-woocommerce' ),
			'rejected'  => __( 'Rejected', 'apg-withdrawal-for-woocommerce' ),
			'completed' => __( 'Completed', 'apg-withdrawal-for-woocommerce' ),
		);

		foreach ( $log as $entry ) {
			$user      = isset( $entry['user_id'] ) ? get_userdata( absint( $entry['user_id'] ) ) : false;
			$user_name = $user ? $user->display_name : __( 'System', 'apg-withdrawal-for-woocommerce' );
			$from      = isset( $entry['from'] ) && isset( $status_labels[ $entry['from'] ] ) ? $status_labels[ $entry['from'] ] : esc_html( $entry['from'] ?? '' );
			$to        = isset( $entry['to'] ) && isset( $status_labels[ $entry['to'] ] ) ? $status_labels[ $entry['to'] ] : esc_html( $entry['to'] ?? '' );
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td></tr>',
				esc_html( $entry['date'] ?? '' ),
				esc_html( $user_name ),
				esc_html( $from ),
				esc_html( $to )
			);
		}

		echo '</tbody></table>';
	}
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
	$statuses       = array(
		'pending'   => __( 'Pending', 'apg-withdrawal-for-woocommerce' ),
		'accepted'  => __( 'Accepted', 'apg-withdrawal-for-woocommerce' ),
		'rejected'  => __( 'Rejected', 'apg-withdrawal-for-woocommerce' ),
		'completed' => __( 'Completed', 'apg-withdrawal-for-woocommerce' ),
	);

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
function apg_withdrawal_export_csv() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to export withdrawal requests.', 'apg-withdrawal-for-woocommerce' ) );
	}

	if ( ! isset( $_GET['apg_withdrawal_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['apg_withdrawal_export_nonce'] ) ), 'apg_withdrawal_export' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'apg-withdrawal-for-woocommerce' ) );
	}

	$posts = get_posts(
		array(
			'post_type'      => 'apg_withdrawal',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	$status_labels = array(
		'pending'   => __( 'Pending', 'apg-withdrawal-for-woocommerce' ),
		'accepted'  => __( 'Accepted', 'apg-withdrawal-for-woocommerce' ),
		'rejected'  => __( 'Rejected', 'apg-withdrawal-for-woocommerce' ),
		'completed' => __( 'Completed', 'apg-withdrawal-for-woocommerce' ),
	);

	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="withdrawal-requests-' . gmdate( 'Y-m-d' ) . '.csv"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$output = fopen( 'php://output', 'w' );

	fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

	fputcsv(
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
		),
		',',
		'"',
		'\\'
	);

	foreach ( $posts as $post ) {
		$status  = get_post_meta( $post->ID, '_apg_withdrawal_status', true );
		$scope   = get_post_meta( $post->ID, '_apg_withdrawal_scope', true );
		$expired = get_post_meta( $post->ID, '_apg_withdrawal_expired_warning', true );

		$products_label = '';
		if ( 'partial' === $scope && function_exists( 'wc_get_order' ) ) {
			$stored_items = get_post_meta( $post->ID, '_apg_withdrawal_products', true );
			$wc_order_id  = absint( get_post_meta( $post->ID, '_apg_withdrawal_wc_order_id', true ) );

			if ( $wc_order_id && ! empty( $stored_items ) && is_array( $stored_items ) ) {
				$wc_order = wc_get_order( $wc_order_id );

				if ( $wc_order ) {
					$labels = array();

					foreach ( $wc_order->get_items() as $item_id => $item ) {
						if ( in_array( (string) $item_id, array_map( 'strval', $stored_items ), true ) ) {
							$labels[] = sprintf(
								/* translators: 1: product name, 2: quantity. */
								__( '%1$s x %2$d', 'apg-withdrawal-for-woocommerce' ),
								$item->get_name(),
								$item->get_quantity()
							);
						}
					}

					$products_label = implode( '; ', $labels );
				}
			}
		}

		fputcsv(
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
			),
			',',
			'"',
			'\\'
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

	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=apg_withdrawal_export_csv' ),
		'apg_withdrawal_export',
		'apg_withdrawal_export_nonce'
	);

	printf(
		'<div class="apg-withdrawal-export-wrap"><a href="%1$s" class="button">%2$s</a></div>',
		esc_url( $url ),
		esc_html__( 'Export CSV', 'apg-withdrawal-for-woocommerce' )
	);
}
add_action( 'manage_posts_extra_tablenav', 'apg_withdrawal_list_export_link' );
