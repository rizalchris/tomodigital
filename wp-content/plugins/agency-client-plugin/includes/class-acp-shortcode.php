<?php
/**
 * Newsletter sign-up.
 *
 * Renders a sign-up form and stores submissions. The client mentioned "something looks off"
 * on this form. There is at least one real security problem here. (Task 4.)
 *
 * Usage: [acp_newsletter]
 *
 * @package AgencyClient
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACP_Shortcode {

	/**
	 * Request-scoped form notice state.
	 *
	 * @var string
	 */
	private $notice = '';

	/**
	 * Notice type for CSS hooks.
	 *
	 * @var string
	 */
	private $notice_type = 'success';

	public function register() {
		add_shortcode( 'acp_newsletter', array( $this, 'render' ) );
		add_action( 'init', array( $this, 'maybe_handle_submission' ) );
	}

	public function render() {
		$ref = isset( $_GET['ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ref'] ) ) : '';

		$out  = '';
		if ( $ref ) {
			$out .= '<p class="acp-ref">Campaign: ' . esc_html( $ref ) . '</p>';
		}

		if ( $this->notice ) {
			$out .= sprintf(
				'<p class="acp-notice acp-notice--%1$s">%2$s</p>',
				esc_attr( $this->notice_type ),
				esc_html( $this->notice )
			);
		}

		$out .= '<form method="post" action="" class="acp-newsletter">';
		$out .= wp_nonce_field( 'acp_newsletter_signup', 'acp_newsletter_nonce', true, false );
		$out .= '<label>Name <input type="text" name="acp_name" required></label>';
		$out .= '<label>Email <input type="email" name="acp_email" required></label>';
		$out .= '<button type="submit" name="acp_newsletter_submit">Sign up</button>';
		$out .= '</form>';

		return $out;
	}

	public function maybe_handle_submission() {
		if ( ! isset( $_POST['acp_newsletter_submit'] ) ) {
			return;
		}

		if (
			! isset( $_POST['acp_newsletter_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['acp_newsletter_nonce'] ) ),
				'acp_newsletter_signup'
			)
		) {
			$this->set_notice( 'Security check failed. Please try again.', 'error' );
			return;
		}

		global $wpdb;

		$name  = isset( $_POST['acp_name'] ) ? sanitize_text_field( wp_unslash( $_POST['acp_name'] ) ) : '';
		$email = isset( $_POST['acp_email'] ) ? sanitize_email( wp_unslash( $_POST['acp_email'] ) ) : '';

		if ( '' === $name ) {
			$this->set_notice( 'Please enter your name.', 'error' );
			return;
		}

		if ( ! is_email( $email ) ) {
			$this->set_notice( 'Please enter a valid email address.', 'error' );
			return;
		}

		$table = $wpdb->prefix . 'acp_signups';
		$inserted = $wpdb->insert(
			$table,
			array(
				'name'  => $name,
				'email' => $email,
			),
			array(
				'%s',
				'%s',
			)
		);

		if ( false === $inserted ) {
			$this->set_notice( 'We could not save your signup right now. Please try again.', 'error' );
			return;
		}

		$this->set_notice(
			sprintf(
				/* translators: %s: subscriber name. */
				__( 'Thanks for signing up, %s!', 'agency-client' ),
				$name
			)
		);
	}

	/**
	 * Set a request-scoped form notice.
	 *
	 * @param string $message Notice text.
	 * @param string $type    success|error.
	 */
	private function set_notice( $message, $type = 'success' ) {
		$this->notice      = $message;
		$this->notice_type = $type;
	}
}
