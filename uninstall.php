<?php
/**
 * Clever Forms uninstall routine.
 *
 * @package CleverForms
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! get_option( 'clever_forms_delete_data_on_uninstall', false ) ) {
	return;
}

$clever_forms_post_ids = get_posts(
	array(
		'post_type'      => array( 'clever_form', 'clever_entry' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $clever_forms_post_ids as $clever_forms_post_id ) {
	wp_delete_post( (int) $clever_forms_post_id, true );
}

$clever_forms_uploads = wp_upload_dir();
$clever_forms_private_dir = trailingslashit( $clever_forms_uploads['basedir'] ) . 'clever-forms-private';

if ( is_dir( $clever_forms_private_dir ) ) {
	$clever_forms_iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $clever_forms_private_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $clever_forms_iterator as $clever_forms_item ) {
		if ( $clever_forms_item->isDir() ) {
			@rmdir( $clever_forms_item->getPathname() );
		} else {
			wp_delete_file( $clever_forms_item->getPathname() );
		}
	}
	@rmdir( $clever_forms_private_dir );
}

delete_option( 'clever_forms_delete_data_on_uninstall' );
