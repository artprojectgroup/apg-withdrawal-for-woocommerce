<?php
/**
 * Limpieza al desinstalar el plugin.
 *
 * @package WC_APG_Withdrawal
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_transient( 'apg_withdrawal_plugin' );

$apg_withdrawal_settings = get_option( 'apg_withdrawal_settings', array() );

if ( '1' !== ( isset( $apg_withdrawal_settings['delete_data_on_uninstall'] ) ? $apg_withdrawal_settings['delete_data_on_uninstall'] : '1' ) ) {
	return;
}

$apg_withdrawal_post_ids = get_posts(
	array(
		'post_type'      => 'apg_withdrawal',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

foreach ( $apg_withdrawal_post_ids as $post_id ) {
	wp_delete_post( absint( $post_id ), true );
}

delete_option( 'apg_withdrawal_settings' );
