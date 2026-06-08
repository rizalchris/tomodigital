<?php
/**
 * Partner content feed.
 *
 * Renders a list of partner sites with a freshly-fetched "authority score" for each.
 * The client says the pages this appears on are slow. (Task 3.)
 *
 * Usage: [acp_partner_feed]
 *
 * @package AgencyClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACP_Market_Widget {

	/**
	 * Cache TTL for remote feed and mention counts.
	 */
	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	public function register() {
		add_shortcode( 'acp_partner_feed', array( $this, 'render' ) );
	}

	/**
	 * The partner sites we surface. In the real plugin this comes from an option;
	 * hard-coded here to keep the exercise self-contained.
	 *
	 * @return string[]
	 */
	private function partner_domains() {
		return array(
			'searchengineland.com',
			'moz.com',
			'ahrefs.com',
			'semrush.com',
			'backlinko.com',
			'searchenginejournal.com',
		);
	}

	public function render() {
		$rows = '';

		// The external authority service. Overridable per-environment via the ACP_AUTHORITY_API
		// constant (local dev points it at the bundled mock); defaults to the real API.
		$api_base = defined( 'ACP_AUTHORITY_API' ) ? ACP_AUTHORITY_API : 'https://api.example.com/authority';

		$mentions_by_domain = $this->get_cached_mentions();

		foreach ( $this->partner_domains() as $domain ) {
			$score    = $this->get_cached_authority_score( $api_base, $domain );
			$mentions = isset( $mentions_by_domain[ $domain ] ) ? (int) $mentions_by_domain[ $domain ] : 0;

			$rows .= sprintf(
				'<tr><td>%s</td><td class="acp-score">%s</td><td>%d mentions</td></tr>',
				esc_html( $domain ),
				esc_html( (string) $score ),
				$mentions
			);
		}

		return '<table class="acp-partner-feed"><thead><tr><th>Partner</th><th>Authority</th><th>Case studies</th></tr></thead><tbody>'
			. $rows
			. '</tbody></table>';
	}

	/**
	 * Cache authority score per partner domain.
	 *
	 * @param string $api_base API endpoint base URL.
	 * @param string $domain   Partner domain.
	 * @return int|string
	 */
	private function get_cached_authority_score( $api_base, $domain ) {
		$cache_key = 'acp_partner_score_' . md5( $domain );
		$score     = get_transient( $cache_key );

		if ( false !== $score ) {
			return $score;
		}

		$response = wp_remote_get(
			add_query_arg( 'domain', rawurlencode( $domain ), $api_base ),
			array(
				'timeout' => 3,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return 'n/a';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || ! isset( $body['score'] ) ) {
			return 'n/a';
		}

		$score = (int) $body['score'];
		set_transient( $cache_key, $score, self::CACHE_TTL );

		return $score;
	}

	/**
	 * Cache the partner mention counts so render does not re-query for each row.
	 *
	 * @return array<string, int>
	 */
	private function get_cached_mentions() {
		$cache_key = 'acp_partner_mentions';
		$mentions  = get_transient( $cache_key );

		if ( is_array( $mentions ) ) {
			return $mentions;
		}

		$mentions = array_fill_keys( $this->partner_domains(), 0 );
		$posts    = get_posts(
			array(
				'post_type'      => ACP_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		foreach ( $posts as $post ) {
			$haystack = strtolower(
				implode(
					' ',
					array(
						(string) $post->post_title,
						(string) $post->post_excerpt,
						(string) $post->post_content,
					)
				)
			);

			foreach ( array_keys( $mentions ) as $domain ) {
				if ( false !== strpos( $haystack, strtolower( $domain ) ) ) {
					++$mentions[ $domain ];
				}
			}
		}

		set_transient( $cache_key, $mentions, self::CACHE_TTL );

		return $mentions;
	}
}
