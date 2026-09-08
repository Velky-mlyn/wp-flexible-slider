<?php
// Run with wp eval-file wp-content/plugins/mlyn-flexible-slider/tests/visibility-smoke.php.
$plugin = MFS\Plugin::instance();
$assert = static function ( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } };
$resolve = new ReflectionMethod( MFS\Plugin::class, 'resolve_slide' ); $resolve->setAccessible( true );
$render = new ReflectionMethod( MFS\Plugin::class, 'render_frontend_slide' ); $render->setAccessible( true );
$events = get_posts( array( 'post_type' => 'tribe_events', 'post_status' => 'publish', 'meta_key' => '_thumbnail_id', 'posts_per_page' => 1 ) );
$assert( ! empty( $events ), 'No event with an image available.' );
$base = array( 'type' => 'post', 'post_id' => $events[0]->ID, 'enabled' => true );
$legacy = $plugin->normalize_transfer_slide( $base );
$assert( $legacy['show_button'] && $legacy['show_eyebrow'], 'Legacy visibility changed.' );
foreach ( array( array( true, true ), array( true, false ), array( false, true ), array( false, false ) ) as $flags ) {
 $slide = $plugin->normalize_transfer_slide( array_merge( $base, array( 'show_eyebrow' => $flags[0], 'show_button' => $flags[1], 'eyebrow' => 'Custom label' ) ) );
 $resolved = $resolve->invoke( $plugin, $slide );
 $assert( 'Custom label' === $resolved['eyebrow'] && 'Vstupenky' === $resolved['button_label'] && get_permalink( $events[0] ) === $resolved['url'], 'Hiding lost values or altered inheritance.' );
 ob_start(); $render->invoke( $plugin, $resolved, 0, 1 ); $html = ob_get_clean();
 $assert( ( false !== strpos( $html, 'class="mfs-eyebrow ' ) ) === $flags[0], 'Eyebrow visibility failed.' );
 $assert( ( false !== strpos( $html, 'class="mfs-button ' ) ) === $flags[1], 'Button visibility failed.' );
}
$hidden = $plugin->normalize_transfer_slide( array_merge( $base, array( 'show_eyebrow' => '0', 'show_button' => '0' ) ) );
$resolved = $resolve->invoke( $plugin, $hidden );
$tags = get_the_tags( $events[0]->ID );
$assert( $resolved['eyebrow'] === ( $tags ? $tags[0]->name : '' ), 'Event tag inheritance changed.' );
ob_start(); $render->invoke( $plugin, $resolved, 0, 1 ); $html = ob_get_clean();
$assert( false === strpos( $html, 'class="mfs-eyebrow ' ) && false === strpos( $html, 'class="mfs-button ' ), 'Inherited content was not hidden.' );
echo "Slide visibility combinations, legacy defaults, preserved values and event inheritance passed.\n";
