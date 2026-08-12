<?php

namespace MFS;

use DateTimeImmutable;
use Exception;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private const POST_TYPE     = 'mlyn_slider';
	private const META_SETTINGS = '_mfs_settings';
	private const META_SLIDES   = '_mfs_slides';

	private static $instance;

	public static function instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate(): void {
		self::instance()->register_post_type();
		flush_rewrite_rules( false );
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_slider' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'filter_title_placeholder' ), 10, 2 );
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'               => __( 'Sliders', 'mlyn-flexible-slider' ),
					'singular_name'      => __( 'Slider', 'mlyn-flexible-slider' ),
					'add_new'            => __( 'Add slider', 'mlyn-flexible-slider' ),
					'add_new_item'       => __( 'Add new slider', 'mlyn-flexible-slider' ),
					'edit_item'          => __( 'Edit slider', 'mlyn-flexible-slider' ),
					'new_item'           => __( 'New slider', 'mlyn-flexible-slider' ),
					'view_item'          => __( 'View slider', 'mlyn-flexible-slider' ),
					'search_items'       => __( 'Search sliders', 'mlyn-flexible-slider' ),
					'not_found'          => __( 'No sliders found.', 'mlyn-flexible-slider' ),
					'menu_name'          => __( 'Sliders', 'mlyn-flexible-slider' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'menu_icon'           => 'dashicons-images-alt2',
				'supports'            => array( 'title' ),
				'map_meta_cap'        => true,
			)
		);
	}

	public function register_shortcode(): void {
		add_shortcode( 'mlyn_slider', array( $this, 'render_shortcode' ) );
	}

	public function enqueue_frontend_assets(): void {
		wp_enqueue_style(
			'mfs-slider',
			plugins_url( 'assets/frontend.css', MFS_FILE ),
			array(),
			MFS_VERSION
		);
		wp_enqueue_script(
			'mfs-slider',
			plugins_url( 'assets/frontend.js', MFS_FILE ),
			array(),
			MFS_VERSION,
			true
		);
	}

	public function register_meta_boxes(): void {
		add_meta_box(
			'mfs-slider-settings',
			__( 'Slider settings', 'mlyn-flexible-slider' ),
			array( $this, 'render_settings_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'mfs-slider-slides',
			__( 'Slides', 'mlyn-flexible-slider' ),
			array( $this, 'render_slides_meta_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
		add_meta_box(
			'mfs-slider-embed',
			__( 'Embed', 'mlyn-flexible-slider' ),
			array( $this, 'render_embed_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
		add_meta_box(
			'mfs-slider-help',
			__( 'Slide field help', 'mlyn-flexible-slider' ),
			array( $this, 'render_help_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'mfs-admin',
			plugins_url( 'assets/admin.css', MFS_FILE ),
			array(),
			MFS_VERSION
		);
		wp_enqueue_script(
			'mfs-admin',
			plugins_url( 'assets/admin.js', MFS_FILE ),
			array(),
			MFS_VERSION,
			true
		);
		wp_localize_script(
			'mfs-admin',
			'mfsAdmin',
			array(
				'confirmRemove' => __( 'Remove this slide?', 'mlyn-flexible-slider' ),
				'imageTitle'    => __( 'Choose image', 'mlyn-flexible-slider' ),
				'videoTitle'    => __( 'Choose video', 'mlyn-flexible-slider' ),
				'posterTitle'   => __( 'Choose poster image', 'mlyn-flexible-slider' ),
				'useMedia'      => __( 'Use this media', 'mlyn-flexible-slider' ),
			)
		);
	}

	public function render_settings_meta_box( WP_Post $post ): void {
		$settings = $this->get_settings( $post->ID );
		wp_nonce_field( 'mfs_save_slider', 'mfs_nonce' );
		?>
		<div class="mfs-settings-grid">
			<label>
				<span><?php esc_html_e( 'Visual variant', 'mlyn-flexible-slider' ); ?></span>
				<select name="mfs_settings[variant]">
					<option value="hero" <?php selected( $settings['variant'], 'hero' ); ?>><?php esc_html_e( 'Hero banner', 'mlyn-flexible-slider' ); ?></option>
					<option value="default" <?php selected( $settings['variant'], 'default' ); ?>><?php esc_html_e( 'Default', 'mlyn-flexible-slider' ); ?></option>
				</select>
			</label>
			<label><span><?php esc_html_e( 'Minimum height (px)', 'mlyn-flexible-slider' ); ?></span><input type="number" name="mfs_settings[height]" min="160" max="1000" value="<?php echo esc_attr( (string) $settings['height'] ); ?>"></label>
			<label><span><?php esc_html_e( 'Overlay colour', 'mlyn-flexible-slider' ); ?></span><input type="color" name="mfs_settings[overlay_color]" value="<?php echo esc_attr( $settings['overlay_color'] ); ?>"></label>
			<label><span><?php esc_html_e( 'Overlay opacity', 'mlyn-flexible-slider' ); ?></span><input type="number" name="mfs_settings[overlay_opacity]" min="0" max="1" step="0.05" value="<?php echo esc_attr( (string) $settings['overlay_opacity'] ); ?>"></label>
			<label><span><?php esc_html_e( 'Transition speed (ms)', 'mlyn-flexible-slider' ); ?></span><input type="number" name="mfs_settings[speed]" min="0" max="5000" step="50" value="<?php echo esc_attr( (string) $settings['speed'] ); ?>"></label>
			<label><span><?php esc_html_e( 'Autoplay interval (ms)', 'mlyn-flexible-slider' ); ?></span><input type="number" name="mfs_settings[interval]" min="1500" max="60000" step="500" value="<?php echo esc_attr( (string) $settings['interval'] ); ?>"></label>
		</div>
		<div class="mfs-checkbox-grid">
			<?php $this->render_checkbox( 'autoplay', __( 'Autoplay slides', 'mlyn-flexible-slider' ), $settings['autoplay'] ); ?>
			<?php $this->render_checkbox( 'loop', __( 'Loop continuously', 'mlyn-flexible-slider' ), $settings['loop'] ); ?>
			<?php $this->render_checkbox( 'arrows', __( 'Show arrows', 'mlyn-flexible-slider' ), $settings['arrows'] ); ?>
			<?php $this->render_checkbox( 'dots', __( 'Show pagination dots', 'mlyn-flexible-slider' ), $settings['dots'] ); ?>
			<?php $this->render_checkbox( 'pause_on_hover', __( 'Pause on hover/focus', 'mlyn-flexible-slider' ), $settings['pause_on_hover'] ); ?>
			<?php $this->render_checkbox( 'video_autoplay', __( 'Play active videos automatically', 'mlyn-flexible-slider' ), $settings['video_autoplay'] ); ?>
		</div>
		<?php
	}

	private function render_checkbox( string $key, string $label, bool $checked ): void {
		?>
		<label><input type="checkbox" name="mfs_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $checked ); ?>> <?php echo esc_html( $label ); ?></label>
		<?php
	}

	public function render_slides_meta_box( WP_Post $post ): void {
		$slides  = $this->get_slides( $post->ID );
		$content = $this->get_content_options();
		?>
		<p><?php esc_html_e( 'Drag slides by the handle to set their order. Linked content can inherit its title, date, image, and URL; any populated slide field overrides the inherited value.', 'mlyn-flexible-slider' ); ?></p>
		<div id="mfs-slides" class="mfs-slides">
			<?php foreach ( $slides as $index => $slide ) : ?>
				<?php $this->render_slide_editor( (string) $index, $slide, $content ); ?>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button button-primary" id="mfs-add-slide"><?php esc_html_e( 'Add slide', 'mlyn-flexible-slider' ); ?></button>
		<script type="text/html" id="mfs-slide-template">
			<?php $this->render_slide_editor( '__INDEX__', $this->slide_defaults(), $content ); ?>
		</script>
		<?php
	}

	private function render_slide_editor( string $index, array $slide, array $content ): void {
		$slide = wp_parse_args( $slide, $this->slide_defaults() );
		?>
		<article class="mfs-slide-editor" draggable="true" data-slide-index="<?php echo esc_attr( $index ); ?>">
			<header class="mfs-slide-header">
				<button type="button" class="mfs-drag-handle" aria-label="<?php esc_attr_e( 'Drag to reorder', 'mlyn-flexible-slider' ); ?>"><span class="dashicons dashicons-move"></span></button>
				<strong class="mfs-slide-summary"><?php echo esc_html( $slide['title'] ?: __( 'Untitled slide', 'mlyn-flexible-slider' ) ); ?></strong>
				<label><input type="checkbox" name="mfs_slides[<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( $slide['enabled'] ); ?>> <?php esc_html_e( 'Enabled', 'mlyn-flexible-slider' ); ?></label>
				<button type="button" class="button-link-delete mfs-remove-slide"><?php esc_html_e( 'Remove', 'mlyn-flexible-slider' ); ?></button>
			</header>
			<input type="hidden" name="mfs_slides[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $slide['id'] ); ?>">
			<div class="mfs-slide-fields">
				<label>
					<span><?php esc_html_e( 'Slide type', 'mlyn-flexible-slider' ); ?></span>
					<select class="mfs-slide-type" name="mfs_slides[<?php echo esc_attr( $index ); ?>][type]">
						<option value="image" <?php selected( $slide['type'], 'image' ); ?>><?php esc_html_e( 'Custom image', 'mlyn-flexible-slider' ); ?></option>
						<option value="video" <?php selected( $slide['type'], 'video' ); ?>><?php esc_html_e( 'Custom video', 'mlyn-flexible-slider' ); ?></option>
						<option value="post" <?php selected( $slide['type'], 'post' ); ?>><?php esc_html_e( 'Linked WordPress content', 'mlyn-flexible-slider' ); ?></option>
					</select>
				</label>
				<label class="mfs-post-field" data-types="post">
					<span><?php esc_html_e( 'Linked content', 'mlyn-flexible-slider' ); ?></span>
					<select name="mfs_slides[<?php echo esc_attr( $index ); ?>][post_id]">
						<option value="0"><?php esc_html_e( '— Select content —', 'mlyn-flexible-slider' ); ?></option>
						<?php foreach ( $content as $group_label => $posts ) : ?>
							<optgroup label="<?php echo esc_attr( $group_label ); ?>">
								<?php foreach ( $posts as $content_post ) : ?>
									<option value="<?php echo esc_attr( (string) $content_post->ID ); ?>" <?php selected( (int) $slide['post_id'], $content_post->ID ); ?>><?php echo esc_html( $content_post->post_title ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
				</label>
				<?php $this->render_media_field( $index, 'image_id', __( 'Image / image override', 'mlyn-flexible-slider' ), 'image', (int) $slide['image_id'] ); ?>
				<?php $this->render_media_field( $index, 'video_id', __( 'Video', 'mlyn-flexible-slider' ), 'video', (int) $slide['video_id'], 'video' ); ?>
				<?php $this->render_media_field( $index, 'poster_id', __( 'Video poster', 'mlyn-flexible-slider' ), 'poster', (int) $slide['poster_id'], 'video' ); ?>
				<label><span><?php esc_html_e( 'Eyebrow / label', 'mlyn-flexible-slider' ); ?></span><input type="text" name="mfs_slides[<?php echo esc_attr( $index ); ?>][eyebrow]" value="<?php echo esc_attr( $slide['eyebrow'] ); ?>"></label>
				<label><span><?php esc_html_e( 'Title', 'mlyn-flexible-slider' ); ?></span><input class="mfs-title-input" type="text" name="mfs_slides[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $slide['title'] ); ?>"></label>
				<label><span><?php esc_html_e( 'Subtitle', 'mlyn-flexible-slider' ); ?></span><input type="text" name="mfs_slides[<?php echo esc_attr( $index ); ?>][subtitle]" value="<?php echo esc_attr( $slide['subtitle'] ); ?>"></label>
				<label><span><?php esc_html_e( 'Button label', 'mlyn-flexible-slider' ); ?></span><input type="text" name="mfs_slides[<?php echo esc_attr( $index ); ?>][button_label]" value="<?php echo esc_attr( $slide['button_label'] ); ?>"></label>
				<label><span><?php esc_html_e( 'Button URL', 'mlyn-flexible-slider' ); ?></span><input type="url" name="mfs_slides[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( $slide['url'] ); ?>"></label>
				<label><span><?php esc_html_e( 'Show from', 'mlyn-flexible-slider' ); ?></span><input type="datetime-local" name="mfs_slides[<?php echo esc_attr( $index ); ?>][starts_at]" value="<?php echo esc_attr( $slide['starts_at'] ); ?>"></label>
				<label><span><?php esc_html_e( 'Show until', 'mlyn-flexible-slider' ); ?></span><input type="datetime-local" name="mfs_slides[<?php echo esc_attr( $index ); ?>][ends_at]" value="<?php echo esc_attr( $slide['ends_at'] ); ?>"></label>
			</div>
			<div class="mfs-slide-options">
				<label><input type="checkbox" name="mfs_slides[<?php echo esc_attr( $index ); ?>][new_tab]" value="1" <?php checked( $slide['new_tab'] ); ?>> <?php esc_html_e( 'Open button in a new tab', 'mlyn-flexible-slider' ); ?></label>
				<label class="mfs-post-field" data-types="post"><input type="checkbox" name="mfs_slides[<?php echo esc_attr( $index ); ?>][hide_after_event]" value="1" <?php checked( $slide['hide_after_event'] ); ?>> <?php esc_html_e( 'Hide automatically after a linked event ends', 'mlyn-flexible-slider' ); ?></label>
			</div>
		</article>
		<?php
	}

	private function render_media_field( string $index, string $key, string $label, string $kind, int $attachment_id, string $types = 'image,post' ): void {
		$url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
		?>
		<div class="mfs-media-field" data-types="<?php echo esc_attr( $types ); ?>">
			<span><?php echo esc_html( $label ); ?></span>
			<input class="mfs-media-id" type="hidden" name="mfs_slides[<?php echo esc_attr( $index ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $attachment_id ); ?>">
			<div class="mfs-media-preview" data-kind="<?php echo esc_attr( $kind ); ?>">
				<?php if ( $url && 'video' !== $kind ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php endif; ?>
				<?php if ( $url && 'video' === $kind ) : ?><code><?php echo esc_html( wp_basename( $url ) ); ?></code><?php endif; ?>
			</div>
			<button type="button" class="button mfs-choose-media" data-media-kind="<?php echo esc_attr( $kind ); ?>"><?php esc_html_e( 'Choose', 'mlyn-flexible-slider' ); ?></button>
			<button type="button" class="button-link-delete mfs-clear-media" <?php disabled( ! $attachment_id ); ?>><?php esc_html_e( 'Clear', 'mlyn-flexible-slider' ); ?></button>
		</div>
		<?php
	}

	public function render_embed_meta_box( WP_Post $post ): void {
		$slug = $post->post_name ?: sanitize_title( $post->post_title );
		?>
		<p><?php esc_html_e( 'Place this slider in any shortcode-capable area:', 'mlyn-flexible-slider' ); ?></p>
		<input type="text" class="widefat" readonly value="<?php echo esc_attr( '[mlyn_slider id="' . $slug . '"]' ); ?>" onclick="this.select();">
		<p><code>&lt;?php echo mlyn_render_slider('<?php echo esc_html( $slug ); ?>'); ?&gt;</code></p>
		<?php
	}

	public function render_help_meta_box(): void {
		?>
		<p class="mfs-help-intro">
			<strong><?php esc_html_e( 'Linked content and overrides', 'mlyn-flexible-slider' ); ?></strong><br>
			<?php esc_html_e( 'Empty text fields inherit available values from linked content. Entering a value replaces the inherited value only on this slide; it does not modify the event, post, or page.', 'mlyn-flexible-slider' ); ?>
		</p>
		<dl class="mfs-help-list">
			<dt><?php esc_html_e( 'Slide type', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Choose a custom image, a custom video, or linked WordPress content.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Linked content', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Select the event, post, or page whose content should be inherited. This appears only for the linked-content slide type.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Image / image override', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Required for a custom-image slide. For linked content, it replaces the featured image on this slide. Leave it empty to use the linked featured image.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Video', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'The Media Library video used by a custom-video slide. Active videos can autoplay when “Play active videos automatically” is enabled in Slider settings.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Video poster', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Optional still image shown before the video starts or when automatic playback is unavailable.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Eyebrow / label', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Small text above the title. A linked calendar event inherits its first tag. Enter text to override that tag.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Title', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Main slide heading. Linked content inherits its WordPress title; entered text overrides it.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Subtitle', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Secondary text below the title. A linked calendar event inherits its start date and time; entered text overrides it.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Button label', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Visible button text. A linked calendar event defaults to “Vstupenky”. The button is shown only when both its label and URL are available.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Button URL', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Button destination. Linked content inherits its permalink; an entered URL overrides it.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Show from / Show until', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Optional publication window for this slide. Leave either value empty when that boundary is not needed.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Enabled', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Temporarily show or hide the slide without deleting its configuration.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Open button in a new tab', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Opens the button destination in a separate browser tab.', 'mlyn-flexible-slider' ); ?></dd>

			<dt><?php esc_html_e( 'Hide after linked event ends', 'mlyn-flexible-slider' ); ?></dt>
			<dd><?php esc_html_e( 'Automatically removes a linked calendar event from the rendered slider after its end date and time.', 'mlyn-flexible-slider' ); ?></dd>
		</dl>
		<p class="description">
			<?php esc_html_e( 'Custom image and video slides do not inherit text or links. Supply every value you want displayed.', 'mlyn-flexible-slider' ); ?>
		</p>
		<?php
	}

	public function save_slider( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['mfs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfs_nonce'] ) ), 'mfs_save_slider' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw_settings = isset( $_POST['mfs_settings'] ) && is_array( $_POST['mfs_settings'] ) ? wp_unslash( $_POST['mfs_settings'] ) : array();
		foreach ( array( 'autoplay', 'loop', 'arrows', 'dots', 'pause_on_hover', 'video_autoplay' ) as $boolean_setting ) {
			$raw_settings[ $boolean_setting ] = ! empty( $raw_settings[ $boolean_setting ] );
		}
		$settings     = $this->sanitize_settings( $raw_settings );
		update_post_meta( $post_id, self::META_SETTINGS, $settings );

		$raw_slides = isset( $_POST['mfs_slides'] ) && is_array( $_POST['mfs_slides'] ) ? wp_unslash( $_POST['mfs_slides'] ) : array();
		$slides     = array();
		foreach ( $raw_slides as $raw_slide ) {
			if ( is_array( $raw_slide ) ) {
				$slides[] = $this->sanitize_slide( $raw_slide );
			}
		}
		update_post_meta( $post_id, self::META_SLIDES, $slides );
	}

	public function render_shortcode( $attributes = array() ): string {
		$attributes = shortcode_atts(
			array(
				'id'       => '',
				'variant'  => '',
				'class'    => '',
				'autoplay' => '',
			),
			(array) $attributes,
			'mlyn_slider'
		);

		$slider = $this->find_slider( $attributes['id'] );
		if ( ! $slider || 'publish' !== $slider->post_status ) {
			return '';
		}

		$settings = $this->get_settings( $slider->ID );
		if ( in_array( $attributes['variant'], array( 'hero', 'default' ), true ) ) {
			$settings['variant'] = $attributes['variant'];
		}
		if ( '' !== $attributes['autoplay'] ) {
			$settings['autoplay'] = filter_var( $attributes['autoplay'], FILTER_VALIDATE_BOOLEAN );
		}

		$slides = array();
		foreach ( $this->get_slides( $slider->ID ) as $slide ) {
			$resolved = $this->resolve_slide( $slide );
			if ( $resolved ) {
				$slides[] = $resolved;
			}
		}
		if ( ! $slides ) {
			return '';
		}

		$instance_id = wp_unique_id( 'mfs-slider-' );
		$classes     = array( 'mfs-slider', 'mfs-variant-' . $settings['variant'] );
		if ( $attributes['class'] ) {
			$classes = array_merge( $classes, preg_split( '/\s+/', $attributes['class'] ) );
		}
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );
		$style   = sprintf(
			'--mfs-height:%dpx;--mfs-overlay-color:%s;--mfs-overlay-opacity:%s;--mfs-speed:%dms;',
			$settings['height'],
			$settings['overlay_color'],
			$settings['overlay_opacity'],
			$settings['speed']
		);

		ob_start();
		?>
		<div id="<?php echo esc_attr( $instance_id ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( $style ); ?>" role="region" aria-roledescription="carousel" aria-label="<?php echo esc_attr( $slider->post_title ); ?>" tabindex="0" data-autoplay="<?php echo $settings['autoplay'] ? '1' : '0'; ?>" data-interval="<?php echo esc_attr( (string) $settings['interval'] ); ?>" data-loop="<?php echo $settings['loop'] ? '1' : '0'; ?>" data-pause-on-hover="<?php echo $settings['pause_on_hover'] ? '1' : '0'; ?>" data-video-autoplay="<?php echo $settings['video_autoplay'] ? '1' : '0'; ?>">
			<div class="mfs-track">
				<?php foreach ( $slides as $index => $slide ) : ?>
					<?php $this->render_frontend_slide( $slide, $index, count( $slides ) ); ?>
				<?php endforeach; ?>
			</div>
			<?php if ( count( $slides ) > 1 && $settings['arrows'] ) : ?>
				<button class="mfs-arrow mfs-previous" type="button" aria-label="<?php esc_attr_e( 'Previous slide', 'mlyn-flexible-slider' ); ?>"><span aria-hidden="true">‹</span></button>
				<button class="mfs-arrow mfs-next" type="button" aria-label="<?php esc_attr_e( 'Next slide', 'mlyn-flexible-slider' ); ?>"><span aria-hidden="true">›</span></button>
			<?php endif; ?>
			<?php if ( count( $slides ) > 1 && $settings['dots'] ) : ?>
				<div class="mfs-dots" role="group" aria-label="<?php esc_attr_e( 'Choose slide', 'mlyn-flexible-slider' ); ?>">
					<?php foreach ( $slides as $index => $unused ) : ?>
						<button type="button" data-slide="<?php echo esc_attr( (string) $index ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Show slide %d', 'mlyn-flexible-slider' ), $index + 1 ) ); ?>" <?php echo 0 === $index ? 'aria-current="true"' : ''; ?>></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function render_frontend_slide( array $slide, int $index, int $total ): void {
		$style = $slide['image_url'] ? "background-image:url('" . esc_url( $slide['image_url'] ) . "');" : '';
		?>
		<article class="mfs-slide<?php echo 0 === $index ? ' is-active' : ''; ?>" data-slide-id="<?php echo esc_attr( $slide['id'] ); ?>" style="<?php echo esc_attr( $style ); ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( sprintf( __( '%1$d of %2$d', 'mlyn-flexible-slider' ), $index + 1, $total ) ); ?>" aria-hidden="<?php echo 0 === $index ? 'false' : 'true'; ?>"<?php echo 0 === $index ? '' : ' inert'; ?>>
			<?php if ( $slide['video_url'] ) : ?>
				<video class="mfs-video" muted loop playsinline preload="metadata"<?php echo $slide['poster_url'] ? ' poster="' . esc_url( $slide['poster_url'] ) . '"' : ''; ?>>
					<source src="<?php echo esc_url( $slide['video_url'] ); ?>">
				</video>
			<?php endif; ?>
			<div class="mfs-overlay" aria-hidden="true"></div>
			<div class="mfs-content">
				<?php if ( $slide['eyebrow'] ) : ?><span class="mfs-eyebrow label inter-300"><?php echo esc_html( $slide['eyebrow'] ); ?></span><?php endif; ?>
				<?php if ( $slide['title'] ) : ?><p class="mfs-title name"><?php echo esc_html( $slide['title'] ); ?></p><?php endif; ?>
				<?php if ( $slide['subtitle'] ) : ?><p class="mfs-subtitle date mb-4 mt-2"><?php echo esc_html( $slide['subtitle'] ); ?></p><?php endif; ?>
				<?php if ( $slide['button_label'] && $slide['url'] ) : ?>
					<a class="mfs-button btn btn-primary" href="<?php echo esc_url( $slide['url'] ); ?>"<?php echo $slide['new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $slide['button_label'] ); ?></a>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	private function resolve_slide( array $slide ) {
		$slide = wp_parse_args( $slide, $this->slide_defaults() );
		if ( ! $slide['enabled'] || ! $this->is_in_schedule( $slide ) ) {
			return null;
		}

		$resolved = array(
			'id'           => $slide['id'],
			'eyebrow'     => $slide['eyebrow'],
			'title'        => $slide['title'],
			'subtitle'     => $slide['subtitle'],
			'button_label' => $slide['button_label'],
			'url'          => $slide['url'],
			'new_tab'      => $slide['new_tab'],
			'image_url'    => $slide['image_id'] ? wp_get_attachment_image_url( $slide['image_id'], 'full' ) : '',
			'video_url'    => $slide['video_id'] ? wp_get_attachment_url( $slide['video_id'] ) : '',
			'poster_url'   => $slide['poster_id'] ? wp_get_attachment_image_url( $slide['poster_id'], 'full' ) : '',
		);

		if ( 'post' === $slide['type'] ) {
			$linked = get_post( $slide['post_id'] );
			if ( ! $linked || 'publish' !== $linked->post_status ) {
				return null;
			}
			if ( 'tribe_events' === $linked->post_type && $slide['hide_after_event'] && $this->event_has_ended( $linked->ID ) ) {
				return null;
			}

			$resolved['title']     = $resolved['title'] ?: get_the_title( $linked );
			$resolved['url']       = $resolved['url'] ?: get_permalink( $linked );
			$resolved['image_url'] = $resolved['image_url'] ?: get_the_post_thumbnail_url( $linked, 'full' );
			if ( 'tribe_events' === $linked->post_type ) {
				$tags = get_the_tags( $linked->ID );
				$resolved['eyebrow'] = $resolved['eyebrow'] ?: ( $tags && ! is_wp_error( $tags ) ? $tags[0]->name : '' );
				$resolved['subtitle'] = $resolved['subtitle'] ?: $this->get_event_date( $linked->ID );
				$resolved['button_label'] = $resolved['button_label'] ?: __( 'Vstupenky', 'mlyn-flexible-slider' );
			}
		}

		if ( 'video' === $slide['type'] && ! $resolved['video_url'] ) {
			return null;
		}
		if ( 'video' !== $slide['type'] && ! $resolved['image_url'] ) {
			return null;
		}

		return apply_filters( 'mlyn_flexible_slider_resolved_slide', $resolved, $slide );
	}

	private function get_event_date( int $post_id ): string {
		if ( function_exists( 'tribe_get_start_date' ) ) {
			return (string) tribe_get_start_date( $post_id, false, 'j. n. Y \\o\\d H:i' );
		}
		$start = get_post_meta( $post_id, '_EventStartDate', true );
		return $start ? wp_date( 'j. n. Y H:i', strtotime( $start ) ) : '';
	}

	private function event_has_ended( int $post_id ): bool {
		$end = get_post_meta( $post_id, '_EventEndDate', true );
		return $end && $this->local_datetime_timestamp( $end ) < current_datetime()->getTimestamp();
	}

	private function is_in_schedule( array $slide ): bool {
		$now = current_datetime()->getTimestamp();
		if ( $slide['starts_at'] && $this->local_datetime_timestamp( $slide['starts_at'] ) > $now ) {
			return false;
		}
		if ( $slide['ends_at'] && $this->local_datetime_timestamp( $slide['ends_at'] ) < $now ) {
			return false;
		}
		return true;
	}

	private function local_datetime_timestamp( string $value ): int {
		try {
			return ( new DateTimeImmutable( $value, wp_timezone() ) )->getTimestamp();
		} catch ( Exception $exception ) {
			return 0;
		}
	}

	private function find_slider( $id ) {
		if ( is_numeric( $id ) ) {
			$post = get_post( (int) $id );
			return $post && self::POST_TYPE === $post->post_type ? $post : null;
		}
		return get_page_by_path( sanitize_title( (string) $id ), OBJECT, self::POST_TYPE );
	}

	private function get_settings( int $post_id ): array {
		$stored = get_post_meta( $post_id, self::META_SETTINGS, true );
		return $this->sanitize_settings( wp_parse_args( is_array( $stored ) ? $stored : array(), $this->settings_defaults() ) );
	}

	private function settings_defaults(): array {
		return array(
			'variant'         => 'hero',
			'height'          => 370,
			'overlay_color'   => '#47687b',
			'overlay_opacity' => 0.7,
			'speed'           => 300,
			'interval'        => 5000,
			'autoplay'        => false,
			'loop'            => true,
			'arrows'          => true,
			'dots'            => true,
			'pause_on_hover'  => true,
			'video_autoplay'  => false,
		);
	}

	private function sanitize_settings( array $settings ): array {
		$variant = isset( $settings['variant'] ) && in_array( $settings['variant'], array( 'hero', 'default' ), true ) ? $settings['variant'] : 'hero';
		$color   = isset( $settings['overlay_color'] ) ? sanitize_hex_color( $settings['overlay_color'] ) : '';
		return array(
			'variant'         => $variant,
			'height'          => max( 160, min( 1000, absint( $settings['height'] ?? 370 ) ) ),
			'overlay_color'   => $color ?: '#47687b',
			'overlay_opacity' => max( 0, min( 1, (float) ( $settings['overlay_opacity'] ?? 0.7 ) ) ),
			'speed'           => max( 0, min( 5000, absint( $settings['speed'] ?? 300 ) ) ),
			'interval'        => max( 1500, min( 60000, absint( $settings['interval'] ?? 5000 ) ) ),
			'autoplay'        => ! empty( $settings['autoplay'] ),
			'loop'            => ! empty( $settings['loop'] ),
			'arrows'          => ! empty( $settings['arrows'] ),
			'dots'            => ! empty( $settings['dots'] ),
			'pause_on_hover'  => ! empty( $settings['pause_on_hover'] ),
			'video_autoplay'  => ! empty( $settings['video_autoplay'] ),
		);
	}

	private function get_slides( int $post_id ): array {
		$slides = get_post_meta( $post_id, self::META_SLIDES, true );
		return is_array( $slides ) ? $slides : array();
	}

	private function sanitize_slide( array $slide ): array {
		$type = isset( $slide['type'] ) && in_array( $slide['type'], array( 'image', 'video', 'post' ), true ) ? $slide['type'] : 'image';
		$id   = sanitize_key( $slide['id'] ?? '' );
		return array(
			'id'               => $id ?: wp_generate_uuid4(),
			'enabled'          => ! empty( $slide['enabled'] ),
			'type'             => $type,
			'post_id'          => absint( $slide['post_id'] ?? 0 ),
			'image_id'         => absint( $slide['image_id'] ?? 0 ),
			'video_id'         => absint( $slide['video_id'] ?? 0 ),
			'poster_id'        => absint( $slide['poster_id'] ?? 0 ),
			'eyebrow'          => sanitize_text_field( $slide['eyebrow'] ?? '' ),
			'title'            => sanitize_text_field( $slide['title'] ?? '' ),
			'subtitle'         => sanitize_text_field( $slide['subtitle'] ?? '' ),
			'button_label'     => sanitize_text_field( $slide['button_label'] ?? '' ),
			'url'              => esc_url_raw( $slide['url'] ?? '' ),
			'new_tab'          => ! empty( $slide['new_tab'] ),
			'hide_after_event' => ! empty( $slide['hide_after_event'] ),
			'starts_at'        => sanitize_text_field( $slide['starts_at'] ?? '' ),
			'ends_at'          => sanitize_text_field( $slide['ends_at'] ?? '' ),
		);
	}

	private function slide_defaults(): array {
		return array(
			'id'               => wp_generate_uuid4(),
			'enabled'          => true,
			'type'             => 'image',
			'post_id'          => 0,
			'image_id'         => 0,
			'video_id'         => 0,
			'poster_id'        => 0,
			'eyebrow'          => '',
			'title'            => '',
			'subtitle'         => '',
			'button_label'     => '',
			'url'              => '',
			'new_tab'          => false,
			'hide_after_event' => false,
			'starts_at'        => '',
			'ends_at'          => '',
		);
	}

	private function get_content_options(): array {
		$options    = array();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );
		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type->name,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			if ( $posts ) {
				$options[ $post_type->labels->singular_name ] = $posts;
			}
		}
		return $options;
	}

	public function add_admin_columns( array $columns ): array {
		$columns['mfs_shortcode'] = __( 'Shortcode', 'mlyn-flexible-slider' );
		$columns['mfs_slides']    = __( 'Slides', 'mlyn-flexible-slider' );
		return $columns;
	}

	public function render_admin_column( string $column, int $post_id ): void {
		if ( 'mfs_shortcode' === $column ) {
			$post = get_post( $post_id );
			echo '<code>[mlyn_slider id="' . esc_html( $post->post_name ) . '"]</code>';
		}
		if ( 'mfs_slides' === $column ) {
			echo esc_html( (string) count( $this->get_slides( $post_id ) ) );
		}
	}

	public function filter_title_placeholder( string $placeholder, WP_Post $post ): string {
		return self::POST_TYPE === $post->post_type ? __( 'Slider name', 'mlyn-flexible-slider' ) : $placeholder;
	}
}
