<?php

namespace MFS;

use RuntimeException;
use WP_Error;
use WP_Post;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Transfer {
	private const FORMAT             = 'mlyn-flexible-slider-transfer';
	private const FORMAT_VERSION     = 1;
	private const NOTICE_PREFIX      = 'mfs_transfer_notice_';
	private const MEDIA_HASH_META    = '_mfs_transfer_file_hash';
	private const MAX_ARCHIVE_SIZE   = 268435456;
	private const MAX_MANIFEST_SIZE  = 2097152;
	private const MAX_EXTRACTED_SIZE = 536870912;

	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_mfs_export_sliders', array( $this, 'handle_export' ) );
		add_action( 'admin_post_mfs_import_sliders', array( $this, 'handle_import' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Plugin::POST_TYPE,
			__( 'Import / Export', 'mlyn-flexible-slider' ),
			__( 'Import / Export', 'mlyn-flexible-slider' ),
			'manage_options',
			'mfs-transfer',
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		$this->assert_access();
		$sliders = get_posts(
			array(
				'post_type'      => Plugin::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$notice = get_transient( self::NOTICE_PREFIX . get_current_user_id() );
		if ( false !== $notice ) {
			delete_transient( self::NOTICE_PREFIX . get_current_user_id() );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Slider import / export', 'mlyn-flexible-slider' ); ?></h1>
			<?php $this->render_notice( $notice ); ?>
			<div class="card" style="max-width: 820px;">
				<h2><?php esc_html_e( 'Export sliders', 'mlyn-flexible-slider' ); ?></h2>
				<p><?php esc_html_e( 'The ZIP archive contains slider settings, ordered slides, and copies of directly selected images and videos. Linked content is identified by its post type and slug rather than its database ID.', 'mlyn-flexible-slider' ); ?></p>
				<?php if ( $sliders ) : ?>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="mfs_export_sliders">
						<?php wp_nonce_field( 'mfs_export_sliders' ); ?>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Choose sliders to export', 'mlyn-flexible-slider' ); ?></legend>
							<?php foreach ( $sliders as $slider ) : ?>
								<p><label><input type="checkbox" name="slider_ids[]" value="<?php echo esc_attr( (string) $slider->ID ); ?>" checked> <strong><?php echo esc_html( $slider->post_title ); ?></strong> <code><?php echo esc_html( $slider->post_name ); ?></code> — <?php echo esc_html( get_post_status_object( $slider->post_status )->label ?? $slider->post_status ); ?></label></p>
							<?php endforeach; ?>
						</fieldset>
						<?php submit_button( __( 'Download transfer ZIP', 'mlyn-flexible-slider' ), 'primary', 'submit', false ); ?>
					</form>
				<?php else : ?>
					<p><?php esc_html_e( 'There are no sliders to export.', 'mlyn-flexible-slider' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="card" style="max-width: 820px; margin-top: 20px;">
				<h2><?php esc_html_e( 'Import sliders', 'mlyn-flexible-slider' ); ?></h2>
				<p><?php esc_html_e( 'Import a ZIP created by this plugin. Included media files are added to the Media Library. Linked posts and events are matched by post type and slug; unresolved links are reported after import.', 'mlyn-flexible-slider' ); ?></p>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="mfs_import_sliders">
					<?php wp_nonce_field( 'mfs_import_sliders' ); ?>
					<p><input type="file" name="mfs_transfer_archive" accept=".zip,application/zip" required></p>
					<fieldset>
						<legend><strong><?php esc_html_e( 'When a slider with the same slug already exists', 'mlyn-flexible-slider' ); ?></strong></legend>
						<p><label><input type="radio" name="conflict_mode" value="replace" checked> <?php esc_html_e( 'Replace its settings and slides', 'mlyn-flexible-slider' ); ?></label></p>
						<p><label><input type="radio" name="conflict_mode" value="copy"> <?php esc_html_e( 'Create a separate copy with a unique slug', 'mlyn-flexible-slider' ); ?></label></p>
					</fieldset>
					<?php submit_button( __( 'Import transfer ZIP', 'mlyn-flexible-slider' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	public function handle_export(): void {
		$this->assert_access();
		check_admin_referer( 'mfs_export_sliders' );
		$ids = isset( $_POST['slider_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['slider_ids'] ) ) ) ) ) : array();
		if ( ! $ids ) {
			$this->redirect_with_notice( 'error', __( 'Choose at least one slider to export.', 'mlyn-flexible-slider' ) );
		}

		$archive = $this->build_archive( $ids );
		if ( is_wp_error( $archive ) ) {
			$this->redirect_with_notice( 'error', $archive->get_error_message() );
		}

		$filename = 'mlyn-sliders-' . gmdate( 'Ymd-His' ) . '.zip';
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $archive ) );
		readfile( $archive ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		wp_delete_file( $archive );
		exit;
	}

	public function handle_import(): void {
		$this->assert_access();
		check_admin_referer( 'mfs_import_sliders' );
		$file = $_FILES['mfs_transfer_archive'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( $file['tmp_name'] ?? '' ) ) {
			$this->redirect_with_notice( 'error', __( 'Choose a valid slider transfer ZIP.', 'mlyn-flexible-slider' ) );
		}
		if ( (int) ( $file['size'] ?? 0 ) > self::MAX_ARCHIVE_SIZE || 'zip' !== strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) ) ) {
			$this->redirect_with_notice( 'error', __( 'The transfer archive is not a valid ZIP or is too large.', 'mlyn-flexible-slider' ) );
		}

		$mode   = isset( $_POST['conflict_mode'] ) && 'copy' === sanitize_key( wp_unslash( $_POST['conflict_mode'] ) ) ? 'copy' : 'replace';
		$result = $this->import_archive( (string) $file['tmp_name'], $mode );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message() );
		}
		$this->redirect_with_notice( 'success', $this->format_report( $result ), $result['warnings'] );
	}

	/**
	 * @return string|WP_Error
	 */
	public function build_archive( array $slider_ids ) {
		if ( ! class_exists( ZipArchive::class ) ) {
			return new WP_Error( 'mfs_zip_unavailable', __( 'The server does not provide ZIP support.', 'mlyn-flexible-slider' ) );
		}
		$path = wp_tempnam( 'mlyn-slider-transfer.zip' );
		if ( ! $path ) {
			return new WP_Error( 'mfs_temp_failed', __( 'A temporary export file could not be created.', 'mlyn-flexible-slider' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $path );
			return new WP_Error( 'mfs_zip_failed', __( 'The transfer ZIP could not be created.', 'mlyn-flexible-slider' ) );
		}

		$media    = array();
		$sliders  = array();
		$warnings = array();
		foreach ( $slider_ids as $slider_id ) {
			$slider = get_post( absint( $slider_id ) );
			if ( ! $slider instanceof WP_Post || Plugin::POST_TYPE !== $slider->post_type || ! current_user_can( 'edit_post', $slider->ID ) ) {
				continue;
			}
			$slides = array();
			foreach ( $this->plugin->get_slides( $slider->ID ) as $slide ) {
				$slides[] = $this->export_slide( $slide, $media, $zip, $warnings );
			}
			$sliders[] = array(
				'title'    => $slider->post_title,
				'slug'     => $slider->post_name,
				'status'   => $slider->post_status,
				'settings' => $this->plugin->get_settings( $slider->ID ),
				'slides'   => $slides,
			);
		}
		if ( ! $sliders ) {
			$zip->close();
			wp_delete_file( $path );
			return new WP_Error( 'mfs_no_sliders', __( 'No exportable sliders were found.', 'mlyn-flexible-slider' ) );
		}

		$manifest = array(
			'format'         => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
			'created_at'     => gmdate( 'c' ),
			'source'         => array( 'url' => home_url( '/' ), 'name' => get_bloginfo( 'name' ) ),
			'sliders'        => $sliders,
			'media'          => $media,
			'warnings'       => $warnings,
		);
		$zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$zip->close();
		return $path;
	}

	/**
	 * @return array|WP_Error
	 */
	public function import_archive( string $archive_path, string $mode = 'replace' ) {
		if ( ! class_exists( ZipArchive::class ) ) {
			return new WP_Error( 'mfs_zip_unavailable', __( 'The server does not provide ZIP support.', 'mlyn-flexible-slider' ) );
		}
		if ( ! is_readable( $archive_path ) || filesize( $archive_path ) > self::MAX_ARCHIVE_SIZE ) {
			return new WP_Error( 'mfs_invalid_zip', __( 'The transfer ZIP is unreadable or too large.', 'mlyn-flexible-slider' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $archive_path ) ) {
			return new WP_Error( 'mfs_invalid_zip', __( 'The transfer ZIP could not be opened.', 'mlyn-flexible-slider' ) );
		}
		if ( $zip->numFiles > 1000 || false === $zip->locateName( 'manifest.json' ) ) {
			$zip->close();
			return new WP_Error( 'mfs_invalid_archive', __( 'The ZIP is not a Mlýn slider transfer archive.', 'mlyn-flexible-slider' ) );
		}
		$extracted_size = 0;
		for ( $index = 0; $index < $zip->numFiles; ++$index ) {
			$name = (string) $zip->getNameIndex( $index );
			if ( '' === $name || preg_match( '#(^|/)\.\.(/|$)#', $name ) || 0 === strpos( $name, '/' ) || preg_match( '#^[A-Za-z]:#', $name ) ) {
				$zip->close();
				return new WP_Error( 'mfs_unsafe_archive', __( 'The transfer ZIP contains an unsafe file path.', 'mlyn-flexible-slider' ) );
			}
			$stat            = $zip->statIndex( $index );
			$extracted_size += (int) ( $stat['size'] ?? 0 );
			if ( $extracted_size > self::MAX_EXTRACTED_SIZE ) {
				$zip->close();
				return new WP_Error( 'mfs_archive_too_large', __( 'The uncompressed transfer archive is too large.', 'mlyn-flexible-slider' ) );
			}
			$operations = 0;
			$attributes = 0;
			if ( $zip->getExternalAttributesIndex( $index, $operations, $attributes ) && 0120000 === ( ( $attributes >> 16 ) & 0170000 ) ) {
				$zip->close();
				return new WP_Error( 'mfs_unsafe_archive', __( 'The transfer ZIP contains an unsupported symbolic link.', 'mlyn-flexible-slider' ) );
			}
		}
		$manifest_stat = $zip->statName( 'manifest.json' );
		if ( ! is_array( $manifest_stat ) || (int) ( $manifest_stat['size'] ?? 0 ) > self::MAX_MANIFEST_SIZE ) {
			$zip->close();
			return new WP_Error( 'mfs_manifest_too_large', __( 'The slider transfer manifest is too large.', 'mlyn-flexible-slider' ) );
		}
		$manifest_json = $zip->getFromName( 'manifest.json' );
		$manifest      = is_string( $manifest_json ) ? json_decode( $manifest_json, true ) : null;
		if ( ! is_array( $manifest ) || self::FORMAT !== ( $manifest['format'] ?? '' ) || self::FORMAT_VERSION !== (int) ( $manifest['format_version'] ?? 0 ) || ! is_array( $manifest['sliders'] ?? null ) ) {
			$zip->close();
			return new WP_Error( 'mfs_invalid_manifest', __( 'The slider transfer manifest is missing or unsupported.', 'mlyn-flexible-slider' ) );
		}

		$temp_dir = trailingslashit( get_temp_dir() ) . 'mfs-transfer-' . wp_generate_uuid4();
		if ( ! wp_mkdir_p( $temp_dir ) || ! $zip->extractTo( $temp_dir ) ) {
			$zip->close();
			$this->remove_directory( $temp_dir );
			return new WP_Error( 'mfs_extract_failed', __( 'The slider transfer files could not be extracted.', 'mlyn-flexible-slider' ) );
		}
		$zip->close();

		$report = array( 'created' => 0, 'updated' => 0, 'media_created' => 0, 'media_reused' => 0, 'warnings' => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $manifest['warnings'] ?? array() ) ) ) ) );
		try {
			$media_map = $this->import_media( (array) ( $manifest['media'] ?? array() ), $temp_dir, $report );
			$source_url = esc_url_raw( (string) ( $manifest['source']['url'] ?? '' ) );
			foreach ( $manifest['sliders'] as $slider_data ) {
				if ( ! is_array( $slider_data ) ) {
					continue;
				}
				$this->import_slider( $slider_data, $media_map, $mode, $source_url, $report );
			}
		} catch ( RuntimeException $exception ) {
			$this->remove_directory( $temp_dir );
			return new WP_Error( 'mfs_import_failed', $exception->getMessage() );
		}
		$this->remove_directory( $temp_dir );
		return $report;
	}

	private function export_slide( array $slide, array &$media, ZipArchive $zip, array &$warnings ): array {
		$slide = $this->plugin->normalize_transfer_slide( $slide );
		foreach ( array( 'image_id' => 'image', 'video_id' => 'video', 'poster_id' => 'poster' ) as $id_key => $transfer_key ) {
			$slide[ $transfer_key ] = $this->export_attachment( (int) $slide[ $id_key ], $media, $zip, $warnings );
			unset( $slide[ $id_key ] );
		}
		$post_id = (int) $slide['post_id'];
		unset( $slide['post_id'] );
		$linked = $post_id ? get_post( $post_id ) : null;
		$slide['linked_content'] = $linked instanceof WP_Post ? array(
			'post_type' => $linked->post_type,
			'slug'      => $linked->post_name,
			'path'      => get_page_uri( $linked ),
			'title'     => $linked->post_title,
			'url'       => get_permalink( $linked ),
		) : null;
		if ( $post_id && ! $linked ) {
			$warnings[] = sprintf( __( 'Slide “%s” refers to missing content ID %d.', 'mlyn-flexible-slider' ), $slide['title'] ?: $slide['id'], $post_id );
		}
		return $slide;
	}

	private function export_attachment( int $attachment_id, array &$media, ZipArchive $zip, array &$warnings ) {
		if ( ! $attachment_id ) {
			return null;
		}
		$attachment = get_post( $attachment_id );
		$file       = get_attached_file( $attachment_id );
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! $file || ! is_readable( $file ) ) {
			$warnings[] = sprintf( __( 'Media attachment %d could not be included in the archive.', 'mlyn-flexible-slider' ), $attachment_id );
			return null;
		}
		$hash = hash_file( 'sha256', $file );
		$key  = $hash ? 'sha256-' . $hash : 'attachment-' . $attachment_id;
		if ( isset( $media[ $key ] ) ) {
			return $key;
		}
		$filename     = sanitize_file_name( wp_basename( $file ) );
		$archive_path = 'media/' . substr( hash( 'sha256', $key ), 0, 16 ) . '-' . $filename;
		$zip->addFile( $file, $archive_path );
		$media[ $key ] = array(
			'file'        => $archive_path,
			'filename'    => $filename,
			'mime_type'   => get_post_mime_type( $attachment_id ),
			'title'       => $attachment->post_title,
			'caption'     => $attachment->post_excerpt,
			'description' => $attachment->post_content,
			'alt'         => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'source_url'  => wp_get_attachment_url( $attachment_id ),
			'sha256'      => $hash ?: '',
		);
		return $key;
	}

	private function import_media( array $media, string $temp_dir, array &$report ): array {
		$map = array();
		if ( $media && ! current_user_can( 'upload_files' ) ) {
			throw new RuntimeException( __( 'You are not allowed to import slider media.', 'mlyn-flexible-slider' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		foreach ( $media as $key => $item ) {
			if ( ! is_string( $key ) || ! is_array( $item ) ) {
				continue;
			}
			$hash = preg_match( '/^[a-f0-9]{64}$/', (string) ( $item['sha256'] ?? '' ) ) ? (string) $item['sha256'] : '';
			$relative = ltrim( str_replace( '\\', '/', (string) ( $item['file'] ?? '' ) ), '/' );
			if ( ! preg_match( '#^media/[A-Za-z0-9._-]+$#', $relative ) || ! is_readable( $temp_dir . '/' . $relative ) ) {
				$report['warnings'][] = sprintf( __( 'Media file “%s” is missing from the transfer archive.', 'mlyn-flexible-slider' ), (string) ( $item['filename'] ?? $key ) );
				continue;
			}
			$actual_hash = hash_file( 'sha256', $temp_dir . '/' . $relative );
			if ( $hash && ( ! $actual_hash || ! hash_equals( $hash, $actual_hash ) ) ) {
				$report['warnings'][] = sprintf( __( 'Media file “%s” failed its integrity check and was skipped.', 'mlyn-flexible-slider' ), (string) ( $item['filename'] ?? $key ) );
				continue;
			}
			$hash     = $actual_hash ?: $hash;
			$existing = $hash ? $this->find_attachment_by_hash( $hash, sanitize_file_name( (string) ( $item['filename'] ?? '' ) ) ) : 0;
			if ( $existing ) {
				$map[ $key ] = $existing;
				++$report['media_reused'];
				continue;
			}
			$tmp_copy = wp_tempnam( sanitize_file_name( (string) ( $item['filename'] ?? 'slider-media' ) ) );
			if ( ! $tmp_copy || ! copy( $temp_dir . '/' . $relative, $tmp_copy ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
				throw new RuntimeException( __( 'A slider media file could not be prepared for import.', 'mlyn-flexible-slider' ) );
			}
			$sideload = array(
				'name'     => sanitize_file_name( (string) ( $item['filename'] ?? wp_basename( $relative ) ) ),
				'tmp_name' => $tmp_copy,
				'error'    => 0,
				'size'     => filesize( $tmp_copy ),
			);
			$attachment_id = media_handle_sideload(
				$sideload,
				0,
				sanitize_text_field( (string) ( $item['description'] ?? '' ) ),
				array(
					'post_title'   => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
					'post_excerpt' => sanitize_textarea_field( (string) ( $item['caption'] ?? '' ) ),
				)
			);
			if ( is_wp_error( $attachment_id ) ) {
				wp_delete_file( $tmp_copy );
				$report['warnings'][] = sprintf( __( 'Media “%1$s” could not be imported: %2$s', 'mlyn-flexible-slider' ), (string) ( $item['filename'] ?? $key ), $attachment_id->get_error_message() );
				continue;
			}
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) ( $item['alt'] ?? '' ) ) );
			if ( $hash ) {
				update_post_meta( $attachment_id, self::MEDIA_HASH_META, $hash );
			}
			$map[ $key ] = (int) $attachment_id;
			++$report['media_created'];
		}
		return $map;
	}

	private function find_attachment_by_hash( string $hash, string $filename ): int {
		$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => self::MEDIA_HASH_META, 'meta_value' => $hash ) );
		if ( $existing ) {
			return (int) $existing[0];
		}
		if ( '' === $filename ) {
			return 0;
		}
		$candidates = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => '_wp_attached_file', 'value' => $filename, 'compare' => 'LIKE' ) ),
			)
		);
		foreach ( $candidates as $attachment_id ) {
			$file = get_attached_file( (int) $attachment_id );
			if ( $file && is_readable( $file ) && hash_equals( $hash, (string) hash_file( 'sha256', $file ) ) ) {
				update_post_meta( (int) $attachment_id, self::MEDIA_HASH_META, $hash );
				return (int) $attachment_id;
			}
		}
		return 0;
	}

	private function import_slider( array $data, array $media_map, string $mode, string $source_url, array &$report ): void {
		$title = sanitize_text_field( (string) ( $data['title'] ?? '' ) );
		$slug  = sanitize_title( (string) ( $data['slug'] ?? $title ) );
		if ( '' === $title || '' === $slug || ! is_array( $data['slides'] ?? null ) ) {
			$report['warnings'][] = __( 'One malformed slider record was skipped.', 'mlyn-flexible-slider' );
			return;
		}
		$existing = 'replace' === $mode ? get_page_by_path( $slug, OBJECT, Plugin::POST_TYPE ) : null;
		if ( $existing && ! current_user_can( 'edit_post', $existing->ID ) ) {
			$report['warnings'][] = sprintf( __( 'Slider “%s” could not be replaced because you cannot edit it.', 'mlyn-flexible-slider' ), $title );
			return;
		}
		$status = in_array( $data['status'] ?? '', array( 'publish', 'draft', 'pending', 'private' ), true ) ? $data['status'] : 'draft';
		if ( 'publish' === $status && ! current_user_can( get_post_type_object( Plugin::POST_TYPE )->cap->publish_posts ) ) {
			$status = 'draft';
		}
		$post_data = array( 'post_type' => Plugin::POST_TYPE, 'post_title' => $title, 'post_name' => $slug, 'post_status' => $status );
		if ( $existing ) {
			$post_data['ID'] = $existing->ID;
		}
		$slider_id = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $slider_id ) ) {
			throw new RuntimeException( $slider_id->get_error_message() );
		}
		$slides = array();
		foreach ( $data['slides'] as $slide ) {
			if ( ! is_array( $slide ) ) {
				continue;
			}
			foreach ( array( 'image' => 'image_id', 'video' => 'video_id', 'poster' => 'poster_id' ) as $transfer_key => $id_key ) {
				$media_key       = is_string( $slide[ $transfer_key ] ?? null ) ? $slide[ $transfer_key ] : '';
				$slide[ $id_key ] = $media_key && isset( $media_map[ $media_key ] ) ? $media_map[ $media_key ] : 0;
				if ( $media_key && ! isset( $media_map[ $media_key ] ) ) {
					$report['warnings'][] = sprintf( __( 'Slider “%1$s”: %2$s media for slide “%3$s” could not be mapped.', 'mlyn-flexible-slider' ), $title, $transfer_key, (string) ( $slide['title'] ?? $slide['id'] ?? '' ) );
				}
				unset( $slide[ $transfer_key ] );
			}
			$slide['post_id'] = $this->resolve_linked_content( $slide['linked_content'] ?? null, $title, $report );
			unset( $slide['linked_content'] );
			$slide['url'] = $this->remap_internal_url( (string) ( $slide['url'] ?? '' ), $source_url );
			$slides[] = $this->plugin->normalize_transfer_slide( $slide );
		}
		update_post_meta( $slider_id, Plugin::META_SETTINGS, $this->plugin->normalize_transfer_settings( is_array( $data['settings'] ?? null ) ? $data['settings'] : array() ) );
		update_post_meta( $slider_id, Plugin::META_SLIDES, $slides );
		if ( $existing ) {
			++$report['updated'];
		} else {
			++$report['created'];
		}
	}

	private function remap_internal_url( string $url, string $source_url ): string {
		$source = untrailingslashit( $source_url );
		if ( '' === $source || 0 !== strpos( $url, $source ) ) {
			return $url;
		}
		$boundary = substr( $url, strlen( $source ), 1 );
		if ( '' !== $boundary && '/' !== $boundary && '?' !== $boundary && '#' !== $boundary ) {
			return $url;
		}
		return untrailingslashit( home_url() ) . substr( $url, strlen( $source ) );
	}

	private function resolve_linked_content( $reference, string $slider_title, array &$report ): int {
		if ( ! is_array( $reference ) || empty( $reference['post_type'] ) || empty( $reference['slug'] ) ) {
			return 0;
		}
		$post_type = sanitize_key( $reference['post_type'] );
		$slug      = sanitize_title( $reference['slug'] );
		$path      = implode( '/', array_filter( array_map( 'sanitize_title', explode( '/', (string) ( $reference['path'] ?? $slug ) ) ) ) );
		$post      = get_page_by_path( $path ?: $slug, OBJECT, $post_type );
		if ( ! $post && $path !== $slug ) {
			$post = get_page_by_path( $slug, OBJECT, $post_type );
		}
		if ( $post instanceof WP_Post ) {
			return $post->ID;
		}
		$report['warnings'][] = sprintf(
			__( 'Slider “%1$s”: linked %2$s “%3$s” was not found; choose a replacement in the slider editor.', 'mlyn-flexible-slider' ),
			$slider_title,
			$post_type,
			(string) ( $reference['title'] ?? $slug )
		);
		return 0;
	}

	private function format_report( array $report ): string {
		return sprintf(
			__( 'Import complete: %1$d slider(s) created, %2$d updated; %3$d media file(s) imported and %4$d reused.', 'mlyn-flexible-slider' ),
			(int) $report['created'],
			(int) $report['updated'],
			(int) $report['media_created'],
			(int) $report['media_reused']
		);
	}

	private function render_notice( $notice ): void {
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}
		$type = 'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success';
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p>';
		if ( ! empty( $notice['warnings'] ) ) {
			echo '<ul style="list-style: disc; margin-left: 20px;">';
			foreach ( (array) $notice['warnings'] as $warning ) {
				echo '<li>' . esc_html( $warning ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	private function redirect_with_notice( string $type, string $message, array $warnings = array() ): void {
		set_transient( self::NOTICE_PREFIX . get_current_user_id(), compact( 'type', 'message', 'warnings' ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Plugin::POST_TYPE . '&page=mfs-transfer' ) );
		exit;
	}

	private function assert_access(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to transfer sliders.', 'mlyn-flexible-slider' ), '', array( 'response' => 403 ) );
		}
	}

	private function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) || 0 !== strpos( wp_normalize_path( $directory ), wp_normalize_path( trailingslashit( get_temp_dir() ) . 'mfs-transfer-' ) ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : wp_delete_file( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
		rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
