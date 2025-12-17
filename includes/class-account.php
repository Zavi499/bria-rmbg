<?php
/**
 * Account Management Class
 *
 * Handles premium account verification and feature access control
 *
 * @package WP_Background_Remover
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Account management class
 */
class WP_BG_Remover_Account {
	/**
	 * Account data option name
	 *
	 * @var string
	 */
	const ACCOUNT_DATA_OPTION = 'wp_bg_remover_account_data';

	/**
	 * Account verification cache duration (in seconds)
	 *
	 * @var int
	 */
	const CACHE_DURATION = 3600; // 1 hour

	/**
	 * Premium features list
	 *
	 * @var array
	 */
	private $premium_features = array(
		'bulk_processing',
		'advanced_settings',
		'api_access',
		'woocommerce_integration',
		'priority_support',
		'export_zip',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize hooks
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Add AJAX handlers for account verification
		add_action( 'wp_ajax_wp_bg_remover_verify_account', array( $this, 'ajax_verify_account' ) );
		add_action( 'wp_ajax_wp_bg_remover_logout_account', array( $this, 'ajax_logout_account' ) );
	}

	/**
	 * Check if current user has premium account
	 *
	 * @return bool
	 */
	public function is_premium() {
		$account_data = $this->get_account_data();

		// No account data means free user
		if ( ! $account_data ) {
			return false;
		}

		// Check if account is active
		if ( empty( $account_data['status'] ) || 'active' !== $account_data['status'] ) {
			return false;
		}

		// Check if verification is still valid (not expired)
		if ( ! empty( $account_data['verified_at'] ) ) {
			$verified_time = intval( $account_data['verified_at'] );
			$current_time  = time();

			// If verification is older than cache duration, re-verify
			if ( ( $current_time - $verified_time ) > self::CACHE_DURATION ) {
				// Verification expired, need to re-verify
				// For now, we'll keep the status but flag for re-verification
				// You can implement automatic re-verification here
				return ! empty( $account_data['is_premium'] );
			}
		}

		return ! empty( $account_data['is_premium'] );
	}

	/**
	 * Verify account with external API
	 *
	 * @param string $email User email.
	 * @param string $api_token API token for verification.
	 * @return array|WP_Error Verification result or error
	 */
	public function verify_account( $email, $api_token ) {
		// Validate inputs
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'wp-background-remover' ) );
		}

		if ( empty( $api_token ) ) {
			return new WP_Error( 'invalid_token', __( 'Please provide an API token.', 'wp-background-remover' ) );
		}

		// Get API endpoint from settings
		$api_endpoint = get_option( 'wp_bg_remover_api_endpoint', '' );

		if ( empty( $api_endpoint ) ) {
			return new WP_Error(
				'no_api_endpoint',
				__( 'API endpoint not configured. Please configure it in settings.', 'wp-background-remover' )
			);
		}

		// Make API request
		$response = wp_remote_post(
			trailingslashit( $api_endpoint ) . 'verify',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
				),
				'body'    => wp_json_encode(
					array(
						'email' => $email,
					)
				),
			)
		);

		// Check for errors
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'api_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to verify account: %s', 'wp-background-remover' ),
					$response->get_error_message()
				)
			);
		}

		// Parse response
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		// Check status code
		if ( 200 !== $status_code ) {
			$error_message = ! empty( $data['message'] )
				? $data['message']
				: __( 'Account verification failed.', 'wp-background-remover' );

			return new WP_Error( 'verification_failed', $error_message );
		}

		// Verify response data
		if ( empty( $data['success'] ) ) {
			return new WP_Error(
				'verification_failed',
				__( 'Account verification failed.', 'wp-background-remover' )
			);
		}

		// Save account data
		$account_data = array(
			'email'       => $email,
			'api_token'   => $api_token,
			'is_premium'  => ! empty( $data['is_premium'] ),
			'status'      => ! empty( $data['status'] ) ? $data['status'] : 'active',
			'verified_at' => time(),
			'user_data'   => ! empty( $data['user_data'] ) ? $data['user_data'] : array(),
		);

		$this->save_account_data( $account_data );

		return array(
			'success'    => true,
			'is_premium' => $account_data['is_premium'],
			'message'    => __( 'Account verified successfully!', 'wp-background-remover' ),
		);
	}

	/**
	 * Get account status
	 *
	 * @return array Account status information
	 */
	public function get_account_status() {
		$account_data = $this->get_account_data();

		if ( ! $account_data ) {
			return array(
				'logged_in'  => false,
				'is_premium' => false,
				'email'      => null,
			);
		}

		return array(
			'logged_in'  => true,
			'is_premium' => $this->is_premium(),
			'email'      => ! empty( $account_data['email'] ) ? $account_data['email'] : null,
			'status'     => ! empty( $account_data['status'] ) ? $account_data['status'] : 'unknown',
		);
	}

	/**
	 * Check if user can access a specific feature
	 *
	 * @param string $feature Feature name to check.
	 * @return bool
	 */
	public function can_access_feature( $feature ) {
		// Check if feature is premium
		if ( in_array( $feature, $this->premium_features, true ) ) {
			return $this->is_premium();
		}

		// Non-premium features are accessible to everyone
		return true;
	}

	/**
	 * Save account data
	 *
	 * @param array $data Account data to save.
	 * @return bool
	 */
	public function save_account_data( $data ) {
		return update_option( self::ACCOUNT_DATA_OPTION, $data );
	}

	/**
	 * Get account data
	 *
	 * @return array|false Account data or false if not found
	 */
	public function get_account_data() {
		return get_option( self::ACCOUNT_DATA_OPTION, false );
	}

	/**
	 * Logout account (clear account data)
	 *
	 * @return bool
	 */
	public function logout_account() {
		return delete_option( self::ACCOUNT_DATA_OPTION );
	}

	/**
	 * AJAX handler for account verification
	 */
	public function ajax_verify_account() {
		// Verify nonce
		check_ajax_referer( 'wp_bg_remover_nonce', 'nonce' );

		// Check user capability
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'wp-background-remover' ),
				)
			);
		}

		// Get email and token from request
		$email     = ! empty( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$api_token = ! empty( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';

		// Verify account
		$result = $this->verify_account( $email, $api_token );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler for account logout
	 */
	public function ajax_logout_account() {
		// Verify nonce
		check_ajax_referer( 'wp_bg_remover_nonce', 'nonce' );

		// Check user capability
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'wp-background-remover' ),
				)
			);
		}

		// Logout account
		$success = $this->logout_account();

		if ( $success ) {
			wp_send_json_success(
				array(
					'message' => __( 'Account disconnected successfully.', 'wp-background-remover' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to disconnect account.', 'wp-background-remover' ),
				)
			);
		}
	}
}
