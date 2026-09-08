<?php
// Run with wp eval-file wp-content/plugins/mlyn-flexible-slider/tests/focal-point-smoke.php.
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
wp_set_current_user( (int) $admins[0] );
$plugin = MFS\Plugin::instance();
$assert = static function ( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } };
$old = $plugin->normalize_transfer_slide( array() );
$assert( 50 === $old['focal_x'] && 35 === $old['focal_y'], 'Legacy crop changed.' );
$invalid = $plugin->normalize_transfer_slide( array( 'focal_x' => array(), 'focal_y' => 'invalid' ) );
$assert( 50 === $invalid['focal_x'] && 35 === $invalid['focal_y'], 'Invalid crop did not use defaults.' );
$clamped = $plugin->normalize_transfer_slide( array( 'focal_x' => -20, 'focal_y' => 125 ) );
$assert( 0 === $clamped['focal_x'] && 100 === $clamped['focal_y'], 'Crop was not clamped.' );
$images = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'posts_per_page' => 1, 'fields' => 'ids' ) );
$assert( ! empty( $images ), 'No image available.' );
$id = wp_insert_post( array( 'post_type' => MFS\Plugin::POST_TYPE, 'post_status' => 'publish', 'post_title' => 'Disposable focal point test' ) );
$linked = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Disposable focal linked post' ) );
$imported = 0;
$archive = '';
try {
 set_post_thumbnail( $linked, $images[0] );
 $slides = array(
  $plugin->normalize_transfer_slide( array( 'type' => 'image', 'image_id' => $images[0], 'focal_x' => 22, 'focal_y' => 78 ) ),
  $plugin->normalize_transfer_slide( array( 'type' => 'post', 'post_id' => $linked, 'focal_x' => 81, 'focal_y' => 19, 'show_eyebrow' => false, 'show_button' => false ) ),
 );
 update_post_meta( $id, MFS\Plugin::META_SLIDES, $slides );
 $html = mlyn_render_slider( $id );
 $assert( false !== strpos( $html, 'background-position:22% 78%;' ), 'Image crop missing.' );
 $assert( false !== strpos( $html, 'background-position:81% 19%;' ), 'Linked image crop missing.' );
 ob_start(); $plugin->render_slides_meta_box( get_post( $id ) ); $editor = ob_get_clean();
 $assert( false !== strpos( $editor, 'mfs-focal-x' ) && false !== strpos( $editor, 'value="78"' ), 'Editor lost saved crop.' );
 // Transfer a linked-image slide, which requires no copied Media Library files.
 update_post_meta( $id, MFS\Plugin::META_SLIDES, array( $slides[1] ) );
 $archive = $plugin->transfer()->build_archive( array( $id ) );
 $assert( ! is_wp_error( $archive ), is_wp_error( $archive ) ? $archive->get_error_message() : 'Could not export focal point.' );
 $result = $plugin->transfer()->import_archive( $archive, 'copy' );
 $assert( ! is_wp_error( $result ) && 1 === $result['created'], 'Could not import focal point.' );
 $copies = get_posts( array( 'post_type' => MFS\Plugin::POST_TYPE, 'post_status' => 'any', 's' => 'Disposable focal point test', 'post__not_in' => array( $id ), 'orderby' => 'ID', 'order' => 'DESC', 'posts_per_page' => 1 ) );
 $imported = $copies[0]->ID;
 $copy = $plugin->get_slides( $imported )[0];
 $assert( 81 === $copy['focal_x'] && 19 === $copy['focal_y'], 'Transfer lost crop coordinates.' );
 $assert( false === $copy['show_eyebrow'] && false === $copy['show_button'], 'Transfer lost visibility settings.' );
 echo "Focal-point normalization, persistence, rendering and transfer passed.\n";
} finally {
 if ( $imported ) { wp_delete_post( $imported, true ); }
 if ( is_string( $archive ) && $archive ) { wp_delete_file( $archive ); }
 wp_delete_post( $id, true );
 wp_delete_post( $linked, true );
}
