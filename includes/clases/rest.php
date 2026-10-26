<?php
/**
 * REST API for withdrawal requests, under the `apg-withdrawal/v1` namespace.
 *
 * Two audiences are served with deliberately different permissions:
 *
 *   - Back-office consumers (ERP connectors, dashboards, automations) read and
 *     move requests through the workflow. They need `manage_woocommerce`.
 *   - Headless and decoupled storefronts register the consumer's declaration.
 *     `POST /requests` is therefore public, exactly like the public form, and
 *     goes through the very same processor: the order must match the e-mail,
 *     the rate limiter applies, and the guest e-mail verification kicks in when
 *     the merchant enabled it. No path bypasses those checks.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST namespace served by the plugin.
 */
const APG_WITHDRAWAL_REST_NAMESPACE = 'apg-withdrawal/v1';

/**
 * Permission callback for the back-office routes.
 *
 * @return bool|WP_Error True when allowed, WP_Error otherwise.
 */
function apg_withdrawal_rest_can_manage() {
	if ( current_user_can( 'manage_woocommerce' ) ) {
		return true;
	}

	return new WP_Error(
		'apg_withdrawal_forbidden',
		__( 'You do not have permission to manage withdrawal requests.', 'apg-withdrawal-for-woocommerce' ),
		array( 'status' => rest_authorization_required_code() )
	);
}

/**
 * Builds the public representation of a withdrawal request.
 *
 * The IBAN is only exposed to a caller that can manage WooCommerce; the create
 * endpoint, which any storefront can reach, never echoes it back.
 *
 * @param WP_Post $post      Withdrawal request post.
 * @param bool    $sensitive Whether to include the refund account.
 * @return array Response payload.
 */
function apg_withdrawal_rest_prepare_request( $post, $sensitive = true ) {
	$post_id    = $post->ID;
	$status     = (string) get_post_meta( $post_id, '_apg_withdrawal_status', true );
	$scope      = (string) get_post_meta( $post_id, '_apg_withdrawal_scope', true );
	$order_id   = absint( get_post_meta( $post_id, '_apg_withdrawal_wc_order_id', true ) );
	$items      = (array) get_post_meta( $post_id, '_apg_withdrawal_products', true );
	$quantities = apg_withdrawal_get_request_quantities( $post_id );

	$lines = array();

	foreach ( $items as $item_id ) {
		$item_id = (string) $item_id;
		$lines[] = array(
			'item_id'  => (int) $item_id,
			'quantity' => isset( $quantities[ $item_id ] ) ? (int) $quantities[ $item_id ] : null,
		);
	}

	$payload = array(
		'id'               => $post_id,
		'date_gmt'         => (string) $post->post_date_gmt,
		'status'           => $status ? $status : 'pending',
		'scope'            => 'partial' === $scope ? 'partial' : 'full',
		'customer_name'    => (string) get_post_meta( $post_id, '_apg_withdrawal_name', true ),
		'customer_email'   => (string) get_post_meta( $post_id, '_apg_withdrawal_email', true ),
		'customer_phone'   => (string) get_post_meta( $post_id, '_apg_withdrawal_phone', true ),
		'order_reference'  => (string) get_post_meta( $post_id, '_apg_withdrawal_order', true ),
		'order_id'         => $order_id,
		'items'            => $lines,
		'details'          => (string) $post->post_content,
		'expired_warning'  => (bool) get_post_meta( $post_id, '_apg_withdrawal_expired_warning', true ),
		'email_verified'   => (string) get_post_meta( $post_id, '_apg_withdrawal_email_verified', true ),
		'receipt_hash'     => (string) get_post_meta( $post_id, '_apg_withdrawal_receipt_hash', true ),
		'receipt_hash_gmt' => (string) get_post_meta( $post_id, '_apg_withdrawal_receipt_hash_timestamp', true ),
	);

	if ( $sensitive ) {
		$payload['refund_iban'] = (string) get_post_meta( $post_id, '_apg_withdrawal_iban', true );
	}

	/**
	 * Filters the REST representation of a withdrawal request.
	 *
	 * @param array   $payload Response payload.
	 * @param WP_Post $post    Withdrawal request post.
	 */
	return (array) apply_filters( 'apg_withdrawal_rest_request', $payload, $post );
}

/**
 * `GET /requests` — paginated list, optionally narrowed by status or order.
 *
 * @param WP_REST_Request $request Incoming request.
 * @return WP_REST_Response
 */
function apg_withdrawal_rest_get_requests( $request ) {
	$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
	$page     = max( 1, (int) $request->get_param( 'page' ) );
	$status   = sanitize_key( (string) $request->get_param( 'status' ) );
	$order_id = absint( $request->get_param( 'order_id' ) );

	$args = array(
		'post_type'      => 'apg_withdrawal',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $page,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	$meta_query = array();

	if ( $status && isset( apg_withdrawal_get_statuses()[ $status ] ) ) {
		$meta_query[] = array(
			'key'     => '_apg_withdrawal_status',
			'value'   => $status,
			'compare' => '=',
		);
	}

	if ( $order_id ) {
		$meta_query[] = array(
			'key'     => '_apg_withdrawal_wc_order_id',
			'value'   => $order_id,
			'compare' => '=',
			'type'    => 'NUMERIC',
		);
	}

	if ( $meta_query ) {
		$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Authenticated REST query over plugin meta; no alternative.
	}

	$query = new WP_Query( $args );
	$items = array();

	foreach ( $query->posts as $post ) {
		$items[] = apg_withdrawal_rest_prepare_request( $post );
	}

	$response = new WP_REST_Response( $items );
	$response->header( 'X-WP-Total', (int) $query->found_posts );
	$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

	return $response;
}

/**
 * Loads a withdrawal request by ID, or an error when it does not exist.
 *
 * @param int $id Request ID.
 * @return WP_Post|WP_Error
 */
function apg_withdrawal_rest_get_post( $id ) {
	$post = get_post( absint( $id ) );

	if ( ! $post || 'apg_withdrawal' !== $post->post_type ) {
		return new WP_Error(
			'apg_withdrawal_not_found',
			__( 'Withdrawal request not found.', 'apg-withdrawal-for-woocommerce' ),
			array( 'status' => 404 )
		);
	}

	return $post;
}

/**
 * `GET /requests/<id>` — a single request.
 *
 * @param WP_REST_Request $request Incoming request.
 * @return WP_REST_Response|WP_Error
 */
function apg_withdrawal_rest_get_request( $request ) {
	$post = apg_withdrawal_rest_get_post( $request->get_param( 'id' ) );

	if ( is_wp_error( $post ) ) {
		return $post;
	}

	return new WP_REST_Response( apg_withdrawal_rest_prepare_request( $post ) );
}

/**
 * `PUT|PATCH /requests/<id>` — moves a request through the workflow. Delegates
 * to {@see apg_withdrawal_change_status()} so the status log and the customer
 * notification behave exactly as they do in the admin screens.
 *
 * @param WP_REST_Request $request Incoming request.
 * @return WP_REST_Response|WP_Error
 */
function apg_withdrawal_rest_update_request( $request ) {
	$post = apg_withdrawal_rest_get_post( $request->get_param( 'id' ) );

	if ( is_wp_error( $post ) ) {
		return $post;
	}

	$status = sanitize_key( (string) $request->get_param( 'status' ) );

	if ( ! isset( apg_withdrawal_get_statuses()[ $status ] ) ) {
		return new WP_Error(
			'apg_withdrawal_invalid_status',
			__( 'Unknown withdrawal status.', 'apg-withdrawal-for-woocommerce' ),
			array( 'status' => 400 )
		);
	}

	apg_withdrawal_change_status( $post->ID, $status );

	return new WP_REST_Response( apg_withdrawal_rest_prepare_request( get_post( $post->ID ) ) );
}

/**
 * `POST /requests` — registers a declaration coming from a headless storefront.
 *
 * Runs through the same processor as the public form, so every validation, the
 * rate limiter and the optional guest e-mail verification apply unchanged. A
 * request that only got parked pending e-mail confirmation answers `202` with
 * `status: pending_verification` and no ID, because nothing has been stored yet.
 *
 * @param WP_REST_Request $request Incoming request.
 * @return WP_REST_Response|WP_Error
 */
function apg_withdrawal_rest_create_request( $request ) {
	$items      = (array) $request->get_param( 'items' );
	$products   = array();
	$quantities = array();

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) || empty( $item['item_id'] ) ) {
			continue;
		}

		$item_id                = (string) absint( $item['item_id'] );
		$products[]             = $item_id;
		$quantities[ $item_id ] = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;
	}

	// Capture the ID the processor assigns instead of guessing it from "the most
	// recent request", which would hand back somebody else's declaration under
	// concurrent submissions.
	$created_id = 0;
	$capture    = function ( $post_id ) use ( &$created_id ) {
		$created_id = absint( $post_id );
	};

	add_action( 'apg_withdrawal_request_registered', $capture );

	$result = apg_withdrawal_process_submission_data(
		array(
			'order'      => $request->get_param( 'order_id' ),
			'name'       => $request->get_param( 'customer_name' ),
			'email'      => $request->get_param( 'customer_email' ),
			'phone'      => $request->get_param( 'customer_phone' ),
			'scope'      => $request->get_param( 'scope' ),
			'details'    => $request->get_param( 'details' ),
			'iban'       => $request->get_param( 'refund_iban' ),
			'products'   => $products,
			'quantities' => $quantities,
		)
	);

	remove_action( 'apg_withdrawal_request_registered', $capture );

	if ( 'verify_sent' === $result ) {
		return new WP_REST_Response(
			array(
				'status'  => 'pending_verification',
				'message' => apg_withdrawal_get_notice_message( 'verify_sent' ),
			),
			202
		);
	}

	if ( 'success' !== $result ) {
		$codes = array(
			'throttle'  => 429,
			// The declaration is already on file: nothing is wrong with the
			// request, it just conflicts with the state of the resource.
			'duplicate' => 409,
		);

		return new WP_Error(
			'apg_withdrawal_' . $result,
			apg_withdrawal_get_notice_message( $result ),
			array( 'status' => isset( $codes[ $result ] ) ? $codes[ $result ] : 400 )
		);
	}

	$created = $created_id ? get_post( $created_id ) : null;

	if ( ! $created ) {
		return new WP_Error(
			'apg_withdrawal_general',
			apg_withdrawal_get_notice_message( 'general' ),
			array( 'status' => 500 )
		);
	}

	return new WP_REST_Response( apg_withdrawal_rest_prepare_request( $created, false ), 201 );
}

/**
 * Registers every route of the plugin.
 *
 * @return void
 */
function apg_withdrawal_register_rest_routes() {
	register_rest_route(
		APG_WITHDRAWAL_REST_NAMESPACE,
		'/requests',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'apg_withdrawal_rest_get_requests',
				'permission_callback' => 'apg_withdrawal_rest_can_manage',
				'args'                => array(
					'status'   => array(
						'type'    => 'string',
						'enum'    => array_keys( apg_withdrawal_get_statuses() ),
					),
					'order_id' => array( 'type' => 'integer' ),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'apg_withdrawal_rest_create_request',
				'permission_callback' => '__return_true',
				'args'                => array(
					'order_id'       => array(
						'type'     => 'string',
						'required' => true,
					),
					'customer_name'  => array(
						'type'     => 'string',
						'required' => true,
					),
					'customer_email' => array(
						'type'     => 'string',
						'format'   => 'email',
						'required' => true,
					),
					'customer_phone' => array( 'type' => 'string' ),
					'scope'          => array(
						'type'    => 'string',
						'enum'    => array( 'full', 'partial' ),
						'default' => 'full',
					),
					'details'        => array( 'type' => 'string' ),
					'refund_iban'    => array( 'type' => 'string' ),
					'items'          => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'item_id'  => array( 'type' => 'integer' ),
								'quantity' => array( 'type' => 'integer' ),
							),
						),
					),
				),
			),
		)
	);

	register_rest_route(
		APG_WITHDRAWAL_REST_NAMESPACE,
		'/requests/(?P<id>\d+)',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'apg_withdrawal_rest_get_request',
				'permission_callback' => 'apg_withdrawal_rest_can_manage',
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'apg_withdrawal_rest_update_request',
				'permission_callback' => 'apg_withdrawal_rest_can_manage',
				'args'                => array(
					'id'     => array(
						'type'     => 'integer',
						'required' => true,
					),
					'status' => array(
						'type'     => 'string',
						'enum'     => array_keys( apg_withdrawal_get_statuses() ),
						'required' => true,
					),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'apg_withdrawal_register_rest_routes' );
