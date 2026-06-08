<?php
/**
 * REST API surface for case studies.
 *
 * Stub only. The bonus task (Task 6) is to turn this into a proper read endpoint and consume
 * it from a small React/Vue widget in assets/widget/. Skip this unless you have time to spare.
 *
 * @package AgencyClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACP_Rest {

	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_shortcode( 'acp_case_studies_widget', array( $this, 'render_widget_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_widget_assets' ) );
	}

	public function register_routes() {
		register_rest_route(
			'acp/v1',
			'/case-studies',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_case_studies' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_case_studies( $request ) {
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = min( 20, max( 1, absint( $request->get_param( 'per_page' ) ) ) );

		$query = new WP_Query(
			array(
				'post_type'      => ACP_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'      => (int) $post->ID,
				'title'   => get_the_title( $post ),
				'link'    => get_permalink( $post ),
				'metric'  => (string) get_post_meta( $post->ID, ACP_CPT::META_HEADLINE, true ),
				'excerpt' => has_excerpt( $post )
					? $post->post_excerpt
					: wp_trim_words( wp_strip_all_tags( $post->post_content ), 32 ),
			);
		}

		return rest_ensure_response(
			array(
				'items'       => $items,
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
			)
		);
	}

	/**
	 * Render a mount point for the REST-driven widget.
	 *
	 * @return string
	 */
	public function render_widget_shortcode() {
		return '<div class="acp-case-studies-widget" data-endpoint="' . esc_url( rest_url( 'acp/v1/case-studies' ) ) . '"></div>';
	}

	/**
	 * Enqueue the widget assets when the shortcode is present.
	 */
	public function maybe_enqueue_widget_assets() {
		if ( ! is_singular() ) {
			return;
		}

		global $post;

		if ( ! $post || ! has_shortcode( $post->post_content, 'acp_case_studies_widget' ) ) {
			return;
		}

		wp_enqueue_style(
			'acp-case-studies-widget',
			ACP_URL . 'assets/widget/widget.css',
			array(),
			ACP_VERSION
		);

		wp_enqueue_script(
			'acp-case-studies-widget',
			ACP_URL . 'assets/widget/widget.js',
			array( 'wp-element' ),
			ACP_VERSION,
			true
		);
	}
}
