<?php
/**
 * REST API Class
 *
 * Handles all REST API endpoints for the plugin
 *
 * @package WP_Background_Remover
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API class
 */
class WP_BG_Remover_REST_API {
	/**
	 * API namespace
	 *
	 * @var string
	 */
	const NAMESPACE = 'bg-remover/v1';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Save processed media to library
		register_rest_route(
			self::NAMESPACE,
			'/save-media',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_media' ),
				'permission_callback' => array( $this, 'check_upload_permission' ),
				'args'                => array(
					'image_data' => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => __( 'Base64 encoded image data', 'wp-background-remover' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'filename'   => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => __( 'Original filename', 'wp-background-remover' ),
						'sanitize_callback' => 'sanitize_file_name',
					),
					'title'      => array(
						'required'          => false,
						'type'              => 'string',
						'description'       => __( 'Image title', 'wp-background-remover' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get settings
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_upload_permission' ),
			)
		);

		// Update settings
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => array( $this, 'check_manage_options_permission' ),
			)
		);

		// Get account status
		register_rest_route(
			self::NAMESPACE,
			'/account-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_account_status' ),
				'permission_callback' => array( $this, 'check_upload_permission' ),
			)
		);

		// Check feature access
		register_rest_route(
			self::NAMESPACE,
			'/check-feature/(?P<feature>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'check_feature_access' ),
				'permission_callback' => array( $this, 'check_upload_permission' ),
				'args'                => array(
					'feature' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Verify premium account
		register_rest_route(
			self::NAMESPACE,
			'/verify-account',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'verify_account' ),
				'permission_callback' => array( $this, 'check_upload_permission' ),
				'args'                => array(
					'email'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'api_token' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Save processed media to library
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_media( $request ) {
		$image_data = $request->get_param( 'image_data' );
		$filename   = $request->get_param( 'filename' );
		$title      = $request->get_param( 'title' );

		// Decode base64 image
		if ( preg_match( '/^data:image\/(\w+);base64,/', $image_data, $type ) ) {
			$image_data = substr( $image_data, strpos( $image_data, ',' ) + 1 );
			$type       = strtolower( $type[1] ); // jpg, png, gif, etc.

			$image_data = base64_decode( $image_data );

			if ( false === $image_data ) {
				return new WP_Error(
					'invalid_image',
					__( 'Failed to decode image data.', 'wp-background-remover' ),
					array( 'status' => 400 )
				);
			}
		} else {
			return new WP_Error(
				'invalid_format',
				__( 'Invalid image data format.', 'wp-background-remover' ),
				array( 'status' => 400 )
			);
		}

		// Generate unique filename
		$upload_dir = wp_upload_dir();
		$filename   = wp_unique_filename( $upload_dir['path'], $filename );
		$filepath   = $upload_dir['path'] . '/' . $filename;

		// Save file
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $filepath, $image_data ) ) {
			return new WP_Error(
				'save_failed',
				__( 'Failed to save image file.', 'wp-background-remover' ),
				array( 'status' => 500 )
			);
		}

		// Create attachment
		$attachment = array(
			'post_mime_type' => 'image/png',
			'post_title'     => $title ? $title : sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $filepath );

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error(
				'attachment_failed',
				__( 'Failed to create attachment.', 'wp-background-remover' ),
				array( 'status' => 500 )
			);
		}

		// Generate metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $filepath );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		return rest_ensure_response(
			array(
				'success'       => true,
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
				'message'       => __( 'Image saved to media library successfully.', 'wp-background-remover' ),
			)
		);
	}

	/**
	 * Get plugin settings
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_settings( $request ) {
		$settings = WP_Background_Remover::instance()->settings->get_all_settings();

		return rest_ensure_response(
			array(
				'success'  => true,
				'settings' => $settings,
			)
		);
	}

	/**
	 * Update plugin settings
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( $request ) {
		$params = $request->get_json_params();

		if ( empty( $params ) || ! is_array( $params ) ) {
			return new WP_Error(
				'invalid_data',
				__( 'Invalid settings data.', 'wp-background-remover' ),
				array( 'status' => 400 )
			);
		}

		$settings_instance = WP_Background_Remover::instance()->settings;
		$updated           = array();

		foreach ( $params as $key => $value ) {
			if ( $settings_instance->update_setting( $key, $value ) ) {
				$updated[] = $key;
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'updated' => $updated,
				'message' => __( 'Settings updated successfully.', 'wp-background-remover' ),
			)
		);
	}

	/**
	 * Get account status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_account_status( $request ) {
		$account = WP_Background_Remover::instance()->account;
		$status  = $account->get_account_status();

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => $status,
			)
		);
	}

	/**
	 * Check feature access
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_feature_access( $request ) {
		$feature = $request->get_param( 'feature' );
		$account = WP_Background_Remover::instance()->account;
		$can_access = $account->can_access_feature( $feature );

		return rest_ensure_response(
			array(
				'success'    => true,
				'feature'    => $feature,
				'can_access' => $can_access,
				'is_premium' => $account->is_premium(),
			)
		);
	}

	/**
	 * Verify premium account
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function verify_account( $request ) {
		$email     = $request->get_param( 'email' );
		$api_token = $request->get_param( 'api_token' );

		$account = WP_Background_Remover::instance()->account;
		$result  = $account->verify_account( $email, $api_token );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Check if user has upload permission
	 *
	 * @return bool
	 */
	public function check_upload_permission() {
		return current_user_can( 'upload_files' );
	}

	/**
	 * Check if user has manage options permission
	 *
	 * @return bool
	 */
	public function check_manage_options_permission() {
		return current_user_can( 'manage_options' );
	}
}
