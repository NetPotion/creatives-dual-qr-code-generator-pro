<?php
/**
 * Uninstall routine.
 *
 * Removes every option this plugin created, plus the per-IP rate-limit
 * transients the public form sets. Runs on delete, not on deactivate, so a
 * site can deactivate and reactivate without losing its Turnstile keys.
 *
 * @package Creatives_Dual_QR_Code_Generator_Pro
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this plugin's options and transients from the current site.
 *
 * @return void
 */
function creatives_dqrcgp_uninstall_site() {
	global $wpdb;

	$options = array(
		'creatives_dqrcgp_turnstile_site_key',
		'creatives_dqrcgp_turnstile_secret_key',
		'creatives_dqrcgp_logo_mode',
		'creatives_dqrcgp_logo_size',
		'creatives_dqrcgp_logo_attachment_id',
		'creatives_dqrcgp_terms_enabled',
		'creatives_dqrcgp_terms_company',
		'creatives_dqrcgp_terms_intro',
		'creatives_dqrcgp_terms_body',
		'creatives_dqrcgp_rate_limit_count',
		'creatives_dqrcgp_rate_limit_window',
		'creatives_dqrcgp_behind_cloudflare',
		'creatives_dqrcgp_turnstile_enabled',
		'creatives_dqrcgp_turnstile_secret_clear',
		'creatives_dqrcgp_show_credit',
		'creatives_dqrcgp_credit_text',
		'creatives_dqrcgp_migrated_legacy',
		'creatives_dqrcgp_show_migration_notice',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Rate-limit transients are keyed by a hash of the client IP, so there
	// is no fixed list of names to delete. On a persistent object cache
	// there are no rows to find and the entries expire on their own.
	// No caching or API alternative: transient names include a hash of the
	// client IP, so there is no fixed list to delete. Runs once, on delete.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_creatives_dqrcgp_limit_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_creatives_dqrcgp_limit_' ) . '%'
		)
	);

	foreach ( (array) $names as $name ) {
		delete_option( $name );
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		creatives_dqrcgp_uninstall_site();
		restore_current_blog();
	}
} else {
	creatives_dqrcgp_uninstall_site();
}
