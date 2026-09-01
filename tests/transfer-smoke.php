<?php

// Run with: wp eval-file wp-content/plugins/mlyn-flexible-slider/tests/transfer-smoke.php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
$assert( ! empty( $admins ), 'No administrator is available for the transfer smoke test.' );
wp_set_current_user( (int) $admins[0] );

$plugin      = MFS\Plugin::instance();
$transfer    = $plugin->transfer();
$source      = get_page_by_path( 'homepage-hero', OBJECT, MFS\Plugin::POST_TYPE );
$archive     = '';
$imported_id = 0;
$media_before = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_mfs_transfer_file_hash',
	)
);
$source_media_ids = array();

$assert( $source instanceof WP_Post, 'The Homepage Hero source slider is unavailable.' );
foreach ( $plugin->get_slides( $source->ID ) as $source_slide ) {
	foreach ( array( 'image_id', 'video_id', 'poster_id' ) as $media_key ) {
		if ( ! empty( $source_slide[ $media_key ] ) ) {
			$source_media_ids[] = (int) $source_slide[ $media_key ];
		}
	}
}
$source_media_ids = array_values( array_unique( $source_media_ids ) );

try {
	ob_start();
	$transfer->render_page();
	$page_html = ob_get_clean();
	foreach ( array( 'Slider import / export', 'Download transfer ZIP', 'Import transfer ZIP', 'homepage-hero' ) as $expected ) {
		$assert( false !== strpos( $page_html, $expected ), 'The transfer page is missing: ' . $expected );
	}

	$archive_result = $transfer->build_archive( array( $source->ID ) );
	$assert( ! is_wp_error( $archive_result ), is_wp_error( $archive_result ) ? $archive_result->get_error_message() : 'The transfer archive was not created.' );
	$archive = (string) $archive_result;
	$assert( is_readable( $archive ), 'The transfer archive is unreadable.' );

	$zip = new ZipArchive();
	$assert( true === $zip->open( $archive ), 'The transfer archive cannot be opened.' );
	$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
	$assert( is_array( $manifest ) && 'mlyn-flexible-slider-transfer' === $manifest['format'] && 1 === (int) $manifest['format_version'], 'The transfer manifest is invalid.' );
	$assert( 1 === count( $manifest['sliders'] ) && count( $plugin->get_slides( $source->ID ) ) === count( $manifest['sliders'][0]['slides'] ), 'The exported slider or slide count is wrong.' );
	$assert( ! empty( $manifest['media'] ), 'No referenced slider media was included.' );
	foreach ( $manifest['media'] as $item ) {
		$assert( false !== $zip->locateName( $item['file'] ), 'An exported media file is missing from the ZIP.' );
	}
	foreach ( $manifest['sliders'][0]['slides'] as $slide ) {
		$assert( ! isset( $slide['post_id'], $slide['image_id'], $slide['video_id'], $slide['poster_id'] ), 'A site-specific WordPress ID leaked into the transfer manifest.' );
	}
	$zip->close();

	$result = $transfer->import_archive( $archive, 'copy' );
	$assert( ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : 'The transfer archive was not imported.' );
	$assert( 1 === $result['created'] && 0 === $result['updated'], 'Copy-mode import did not create exactly one slider.' );
	$assert( $result['media_reused'] > 0, 'Existing identical Media Library files were not reused.' );
	$copies = get_posts(
		array(
			'post_type'      => MFS\Plugin::POST_TYPE,
			'post_status'    => 'any',
			'post__not_in'   => array( $source->ID ),
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			's'              => $source->post_title,
		)
	);
	$assert( ! empty( $copies ), 'The imported slider copy cannot be found.' );
	$imported_id = (int) $copies[0]->ID;
	$assert( $plugin->get_settings( $source->ID ) === $plugin->get_settings( $imported_id ), 'Slider settings changed during transfer.' );
	$source_slides   = $plugin->get_slides( $source->ID );
	$imported_slides = $plugin->get_slides( $imported_id );
	$assert( count( $source_slides ) === count( $imported_slides ), 'Slide order or count changed during transfer.' );
	foreach ( $source_slides as $index => $source_slide ) {
		$imported_slide = $imported_slides[ $index ];
		$assert( $source_slide['type'] === $imported_slide['type'] && $source_slide['title'] === $imported_slide['title'], 'Slide data or ordering changed during transfer.' );
		if ( $source_slide['post_id'] ) {
			$assert( $source_slide['post_id'] === $imported_slide['post_id'], 'Linked content was not resolved by post type and slug.' );
		}
		foreach ( array( 'image_id', 'video_id', 'poster_id' ) as $media_key ) {
			if ( $source_slide[ $media_key ] ) {
				$assert( $imported_slide[ $media_key ] > 0 && is_readable( get_attached_file( $imported_slide[ $media_key ] ) ), 'Transferred slide media is missing.' );
				$assert( hash_file( 'sha256', get_attached_file( $source_slide[ $media_key ] ) ) === hash_file( 'sha256', get_attached_file( $imported_slide[ $media_key ] ) ), 'Transferred media content changed.' );
			}
		}
	}

	echo "MFS transfer smoke test passed.\n";
} finally {
	if ( $imported_id ) {
		wp_delete_post( $imported_id, true );
	}
	$media_after = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_mfs_transfer_file_hash',
		)
	);
	foreach ( array_diff( $media_after, $media_before ) as $attachment_id ) {
		if ( in_array( (int) $attachment_id, $source_media_ids, true ) ) {
			delete_post_meta( (int) $attachment_id, '_mfs_transfer_file_hash' );
		} else {
			wp_delete_attachment( (int) $attachment_id, true );
		}
	}
	if ( $archive ) {
		wp_delete_file( $archive );
	}
}
