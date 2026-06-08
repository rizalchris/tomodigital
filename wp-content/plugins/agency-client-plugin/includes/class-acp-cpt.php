<?php
/**
 * Case Study custom post type.
 *
 * NOTE: This is half-finished. The previous dev registered the post type but it never
 * really worked in the editor and there's no way to store the "headline metric" we promised
 * the client (e.g. "+212% organic traffic"). Finishing this is Task 2.
 *
 * @package AgencyClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACP_CPT {

	/**
	 * The canonical post type slug used throughout the plugin.
	 */
	const POST_TYPE = 'acp_case_study';

	/**
	 * Meta key for the headline metric on each case study (e.g. "+212% organic traffic").
	 * The seeded sample data uses this key. Task 2 should read/write it (or your own, but
	 * the demo content lives here).
	 */
	const META_HEADLINE = 'acp_headline_metric';

	/**
	 * Shortcode tag for rendering recent case studies.
	 */
	const SHORTCODE = 'acp_case_studies';

	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_case_study_meta' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'clear_case_study_caches' ) );
		add_action( 'before_delete_post', array( $this, 'maybe_clear_case_study_caches' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'render_case_studies' ) );
	}

	public function register_post_type() {
		$labels = array(
			'name'                  => __( 'Case Studies', 'agency-client' ),
			'singular_name'         => __( 'Case Study', 'agency-client' ),
			'menu_name'             => __( 'Case Studies', 'agency-client' ),
			'name_admin_bar'        => __( 'Case Study', 'agency-client' ),
			'add_new'               => __( 'Add New', 'agency-client' ),
			'add_new_item'          => __( 'Add New Case Study', 'agency-client' ),
			'edit_item'             => __( 'Edit Case Study', 'agency-client' ),
			'new_item'              => __( 'New Case Study', 'agency-client' ),
			'view_item'             => __( 'View Case Study', 'agency-client' ),
			'view_items'            => __( 'View Case Studies', 'agency-client' ),
			'search_items'          => __( 'Search Case Studies', 'agency-client' ),
			'not_found'             => __( 'No case studies found.', 'agency-client' ),
			'not_found_in_trash'    => __( 'No case studies found in Trash.', 'agency-client' ),
			'all_items'             => __( 'All Case Studies', 'agency-client' ),
			'archives'              => __( 'Case Study Archives', 'agency-client' ),
			'attributes'            => __( 'Case Study Attributes', 'agency-client' ),
			'featured_image'        => __( 'Featured image', 'agency-client' ),
			'set_featured_image'    => __( 'Set featured image', 'agency-client' ),
			'remove_featured_image' => __( 'Remove featured image', 'agency-client' ),
			'use_featured_image'    => __( 'Use as featured image', 'agency-client' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'has_archive'        => true,
			'menu_icon'          => 'dashicons-portfolio',
			'supports'           => array(
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'revisions',
			),
			'rewrite'            => array(
				'slug' => 'case-studies',
			),
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register headline metric meta for REST and meta handling.
	 */
	public function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			self::META_HEADLINE,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Add a meta box for the headline metric field.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'acp-headline-metric',
			__( 'Headline Metric', 'agency-client' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the headline metric meta box.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public function render_meta_box( $post ) {
		$value = get_post_meta( $post->ID, self::META_HEADLINE, true );

		wp_nonce_field( 'acp_save_case_study_metric', 'acp_case_study_metric_nonce' );
		?>
		<p>
			<label for="acp-headline-metric-field"><?php esc_html_e( 'Short outcome statement, for example "+212% organic traffic".', 'agency-client' ); ?></label>
		</p>
		<input
			type="text"
			class="widefat"
			id="acp-headline-metric-field"
			name="acp_headline_metric"
			value="<?php echo esc_attr( $value ); ?>"
		/>
		<?php
	}

	/**
	 * Save the case study headline metric.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_case_study_meta( $post_id ) {
		if ( ! isset( $_POST['acp_case_study_metric_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['acp_case_study_metric_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'acp_save_case_study_metric' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$value = isset( $_POST['acp_headline_metric'] )
			? sanitize_text_field( wp_unslash( $_POST['acp_headline_metric'] ) )
			: '';

		if ( '' === $value ) {
			delete_post_meta( $post_id, self::META_HEADLINE );
			return;
		}

		update_post_meta( $post_id, self::META_HEADLINE, $value );
	}

	/**
	 * Clear cached aggregates that depend on case study content.
	 *
	 * @param int $post_id Post ID.
	 */
	public function clear_case_study_caches( $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		delete_transient( 'acp_partner_mentions' );
	}

	/**
	 * Clear caches when a case study is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function maybe_clear_case_study_caches( $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$this->clear_case_study_caches( $post_id );
	}

	/**
	 * Helper you may want for Task 2: fetch published case studies.
	 *
	 * @return WP_Post[]
	 */
	public function get_case_studies( $limit = 10 ) {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return $query->posts;
	}

	/**
	 * Render the case studies shortcode.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_case_studies( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit' => 10,
			),
			$atts,
			self::SHORTCODE
		);

		$posts = $this->get_case_studies( (int) $atts['limit'] );

		if ( empty( $posts ) ) {
			return '<p>' . esc_html__( 'No case studies are available yet.', 'agency-client' ) . '</p>';
		}

		ob_start();
		?>
		<div class="acp-case-studies">
			<?php foreach ( $posts as $post ) : ?>
				<?php
				$metric    = get_post_meta( $post->ID, self::META_HEADLINE, true );
				$excerpt   = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 32 );
				$permalink = get_permalink( $post );
				?>
				<article class="acp-case-study">
					<h3 class="acp-case-study__title">
						<a href="<?php echo esc_url( $permalink ); ?>">
							<?php echo esc_html( get_the_title( $post ) ); ?>
						</a>
					</h3>
					<?php if ( $metric ) : ?>
						<p class="acp-case-study__metric"><?php echo esc_html( $metric ); ?></p>
					<?php endif; ?>
					<?php if ( $excerpt ) : ?>
						<div class="acp-case-study__excerpt"><?php echo wp_kses_post( wpautop( $excerpt ) ); ?></div>
					<?php endif; ?>
					<p class="acp-case-study__link">
						<a href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'Read case study', 'agency-client' ); ?></a>
					</p>
				</article>
			<?php endforeach; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
