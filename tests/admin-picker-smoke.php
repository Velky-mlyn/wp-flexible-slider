<?php

// Run with: wp eval-file wp-content/plugins/mlyn-flexible-slider/tests/admin-picker-smoke.php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
if ( ! $admins ) {
	throw new RuntimeException( 'No administrator is available for the admin-picker smoke test.' );
}

wp_set_current_user( (int) $admins[0] );
$plugin = MFS\Plugin::instance();
$slider = get_post( 4177 );
if ( ! $slider || 'mlyn_slider' !== $slider->post_type ) {
	throw new RuntimeException( 'Homepage Hero slider 4177 is unavailable.' );
}

ob_start();
$plugin->render_slides_meta_box( $slider );
$html = ob_get_clean();

foreach ( array( 1113, 3416 ) as $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		throw new RuntimeException( 'Expected linked content is unavailable: ' . $post_id );
	}
	$visible_title = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
	foreach ( array( 'value="' . $post_id . '"', '#' . $post_id, $visible_title, esc_url( get_edit_post_link( $post_id, 'raw' ) ) ) as $expected ) {
		if ( false === strpos( $html, $expected ) ) {
			throw new RuntimeException( 'Rendered picker did not preserve linked content ' . $post_id . ': ' . $expected );
		}
	}
}

foreach ( array( 'id="mfs-content-modal"', 'id="mfs-content-search"', 'id="mfs-content-type"', 'id="mfs-content-results"', 'id="mfs-content-load-more"' ) as $expected ) {
	if ( false === strpos( $html, $expected ) ) {
		throw new RuntimeException( 'Rendered picker is missing: ' . $expected );
	}
}
if ( preg_match( '/<select[^>]+name="mfs_slides\[[^]]+\]\[post_id\]"/', $html ) ) {
	throw new RuntimeException( 'The legacy linked-content select is still rendered.' );
}

$types_method = new ReflectionMethod( MFS\Plugin::class, 'get_searchable_post_types' );
$types_method->setAccessible( true );
$types = $types_method->invoke( $plugin );
foreach ( array( 'post', 'page', 'tribe_events' ) as $post_type ) {
	if ( ! isset( $types[ $post_type ] ) ) {
		throw new RuntimeException( 'Expected searchable post type is missing: ' . $post_type );
	}
}
foreach ( array( 'attachment', 'mlyn_slider', 'mailpoet_page' ) as $post_type ) {
	if ( isset( $types[ $post_type ] ) ) {
		throw new RuntimeException( 'Unsupported post type is searchable: ' . $post_type );
	}
}

$past_event_query = new WP_Query(
	array(
		'post_type'                    => array( 'tribe_events' ),
		'post_status'                  => 'publish',
		'post__in'                     => array( 1113 ),
		'posts_per_page'               => 1,
		'tribe_suppress_query_filters' => true,
	)
);
if ( ! $past_event_query->have_posts() || 1113 !== (int) $past_event_query->posts[0]->ID ) {
	throw new RuntimeException( 'Past-event query suppression does not expose event 1113.' );
}

$find_content = new ReflectionMethod( MFS\Plugin::class, 'find_content' );
$find_content->setAccessible( true );
if ( false === has_action( 'wp_ajax_mfs_search_content', array( $plugin, 'search_content' ) ) ) {
	throw new RuntimeException( 'The secured admin AJAX search action is not registered.' );
}

foreach ( array( 'Anthropoid', '1113' ) as $term ) {
	$search = $find_content->invoke( $plugin, $term, array( 'tribe_events' ), 1 );
	$ids    = wp_list_pluck( $search['items'], 'id' );
	if ( ! in_array( 1113, $ids, true ) ) {
		throw new RuntimeException( 'AJAX search did not return past event 1113 for: ' . $term );
	}
	$item = $search['items'][ array_search( 1113, $ids, true ) ];
	if ( 'tribe_events' !== $item['post_type'] || empty( $item['type_label'] ) || empty( $item['status_label'] ) || empty( $item['date'] ) || empty( $item['edit_url'] ) ) {
		throw new RuntimeException( 'AJAX result metadata is incomplete for event 1113.' );
	}
}

echo "MFS admin-picker smoke test passed.\n";
