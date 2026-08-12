<?php
/**
 * Plugin Name:       Mlýn Flexible Slider
 * Description:       Reusable ordered image, video, and linked-content sliders for shortcodes and templates.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Velký mlýn
 * License:           GPL-2.0-or-later
 * Text Domain:       mlyn-flexible-slider
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MFS_VERSION', '1.1.0' );
define( 'MFS_FILE', __FILE__ );
define( 'MFS_DIR', plugin_dir_path( __FILE__ ) );

require_once MFS_DIR . 'src/class-plugin.php';

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'edit.php?post_type=mlyn_slider' ) ),
				esc_html__( 'Sliders', 'mlyn-flexible-slider' )
			)
		);
		return $links;
	}
);

register_activation_hook( __FILE__, array( 'MFS\\Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		MFS\Plugin::instance();
	}
);

/**
 * Render a slider from a theme or another plugin.
 *
 * @param int|string $id Slider post ID or slug.
 * @param array      $attributes Optional shortcode-style overrides.
 */
if ( ! function_exists( 'mlyn_render_slider' ) ) {
	function mlyn_render_slider( $id, array $attributes = array() ): string {
		$attributes['id'] = $id;
		return MFS\Plugin::instance()->render_shortcode( $attributes );
	}
}
