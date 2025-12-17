<?php
/**
 * Settings Management Class
 *
 * Handles plugin settings and configuration
 *
 * @package WP_Background_Remover
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings management class
 */
class WP_BG_Remover_Settings {
	/**
	 * Settings option prefix
	 *
	 * @var string
	 */
	const OPTION_PREFIX = 'wp_bg_remover_';

	/**
	 * Available settings with defaults
	 *
	 * @var array
	 */
	private $default_settings = array(
		// General Settings (Free & Premium)
		'model_precision'         => 'q8',
		'device_preference'       => 'auto',
		'default_bg_color'        => 'transparent',
		'max_image_dimensions'    => 4000,
		'output_quality'          => 95,
		'media_library_enabled'   => true,
		'gutenberg_block_enabled' => true,

		// Premium Settings
		'bulk_limit'              => 25,
		'auto_process_upload'     => false,
		'api_access_enabled'      => false,
		'custom_watermark'        => false,

		// API Configuration
		'api_endpoint'            => '',
	);

	/**
	 * Premium-only settings
	 *
	 * @var array
	 */
	private $premium_settings = array(
		'bulk_limit',
		'auto_process_upload',
		'api_access_enabled',
		'custom_watermark',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register WordPress settings
	 */
	public function register_settings() {
		foreach ( $this->default_settings as $key => $default ) {
			register_setting(
				'wp_bg_remover_settings',
				self::OPTION_PREFIX . $key,
				array(
					'type'              => $this->get_setting_type( $key ),
					'sanitize_callback' => array( $this, 'sanitize_setting' ),
					'default'           => $default,
				)
			);
		}
	}

	/**
	 * Get all settings
	 *
	 * @return array All settings with current values
	 */
	public function get_all_settings() {
		$settings   = array();
		$is_premium = wp_bg_remover_is_premium();

		foreach ( $this->default_settings as $key => $default ) {
			// Skip premium settings if not premium
			if ( ! $is_premium && in_array( $key, $this->premium_settings, true ) ) {
				continue;
			}

			$settings[ $key ] = $this->get_setting( $key );
		}

		return $settings;
	}

	/**
	 * Get a single setting
	 *
	 * @param string $key Setting key.
	 * @return mixed Setting value
	 */
	public function get_setting( $key ) {
		// Check if setting exists
		if ( ! array_key_exists( $key, $this->default_settings ) ) {
			return null;
		}

		// Check if premium setting without premium access
		if ( ! wp_bg_remover_is_premium() && in_array( $key, $this->premium_settings, true ) ) {
			return $this->default_settings[ $key ];
		}

		return get_option( self::OPTION_PREFIX . $key, $this->default_settings[ $key ] );
	}

	/**
	 * Update a single setting
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True on success, false on failure
	 */
	public function update_setting( $key, $value ) {
		// Check if setting exists
		if ( ! array_key_exists( $key, $this->default_settings ) ) {
			return false;
		}

		// Check if premium setting without premium access
		if ( ! wp_bg_remover_is_premium() && in_array( $key, $this->premium_settings, true ) ) {
			return false;
		}

		// Sanitize value
		$value = $this->sanitize_setting( $value, $key );

		return update_option( self::OPTION_PREFIX . $key, $value );
	}

	/**
	 * Reset all settings to defaults
	 *
	 * @return bool
	 */
	public function reset_to_defaults() {
		$success = true;

		foreach ( $this->default_settings as $key => $default ) {
			if ( ! update_option( self::OPTION_PREFIX . $key, $default ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Get setting type for registration
	 *
	 * @param string $key Setting key.
	 * @return string Setting type
	 */
	private function get_setting_type( $key ) {
		$value = $this->default_settings[ $key ];

		if ( is_bool( $value ) ) {
			return 'boolean';
		} elseif ( is_int( $value ) ) {
			return 'integer';
		} elseif ( is_float( $value ) ) {
			return 'number';
		} else {
			return 'string';
		}
	}

	/**
	 * Sanitize setting value
	 *
	 * @param mixed  $value Setting value.
	 * @param string $key Setting key (optional).
	 * @return mixed Sanitized value
	 */
	public function sanitize_setting( $value, $key = '' ) {
		// Handle specific settings
		switch ( $key ) {
			case 'model_precision':
				$allowed = array( 'q4', 'q8', 'fp16' );
				return in_array( $value, $allowed, true ) ? $value : 'q8';

			case 'device_preference':
				$allowed = array( 'auto', 'webgpu', 'wasm' );
				return in_array( $value, $allowed, true ) ? $value : 'auto';

			case 'default_bg_color':
				// Validate color (hex or transparent)
				if ( 'transparent' === $value ) {
					return $value;
				}
				return sanitize_hex_color( $value ) ? sanitize_hex_color( $value ) : 'transparent';

			case 'max_image_dimensions':
				$value = absint( $value );
				return ( $value >= 1000 && $value <= 10000 ) ? $value : 4000;

			case 'output_quality':
				$value = absint( $value );
				return ( $value >= 1 && $value <= 100 ) ? $value : 95;

			case 'bulk_limit':
				$value = absint( $value );
				return ( $value >= 1 && $value <= 100 ) ? $value : 25;

			case 'media_library_enabled':
			case 'gutenberg_block_enabled':
			case 'auto_process_upload':
			case 'api_access_enabled':
			case 'custom_watermark':
				return (bool) $value;

			case 'api_endpoint':
				return esc_url_raw( $value );

			default:
				// Generic sanitization
				if ( is_bool( $value ) ) {
					return (bool) $value;
				} elseif ( is_numeric( $value ) ) {
					return is_float( $value ) ? floatval( $value ) : intval( $value );
				} else {
					return sanitize_text_field( $value );
				}
		}
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-background-remover' ) );
		}

		$is_premium = wp_bg_remover_is_premium();
		?>
		<div class="wrap wp-bg-remover-settings">
			<h1><?php esc_html_e( 'Background Remover Settings', 'wp-background-remover' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'wp_bg_remover_settings' );
				?>

				<h2><?php esc_html_e( 'General Settings', 'wp-background-remover' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="model_precision"><?php esc_html_e( 'Model Precision', 'wp-background-remover' ); ?></label>
						</th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_PREFIX . 'model_precision' ); ?>" id="model_precision">
								<option value="q4" <?php selected( $this->get_setting( 'model_precision' ), 'q4' ); ?>>q4 (Fastest)</option>
								<option value="q8" <?php selected( $this->get_setting( 'model_precision' ), 'q8' ); ?>>q8 (Balanced)</option>
								<option value="fp16" <?php selected( $this->get_setting( 'model_precision' ), 'fp16' ); ?>>fp16 (Best Quality)</option>
							</select>
							<p class="description"><?php esc_html_e( 'Higher precision = better quality but slower processing.', 'wp-background-remover' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="device_preference"><?php esc_html_e( 'Device Preference', 'wp-background-remover' ); ?></label>
						</th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_PREFIX . 'device_preference' ); ?>" id="device_preference">
								<option value="auto" <?php selected( $this->get_setting( 'device_preference' ), 'auto' ); ?>><?php esc_html_e( 'Auto', 'wp-background-remover' ); ?></option>
								<option value="webgpu" <?php selected( $this->get_setting( 'device_preference' ), 'webgpu' ); ?>><?php esc_html_e( 'WebGPU', 'wp-background-remover' ); ?></option>
								<option value="wasm" <?php selected( $this->get_setting( 'device_preference' ), 'wasm' ); ?>><?php esc_html_e( 'WASM', 'wp-background-remover' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'WebGPU is faster but may not be supported on all browsers.', 'wp-background-remover' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="default_bg_color"><?php esc_html_e( 'Default Background Color', 'wp-background-remover' ); ?></label>
						</th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_PREFIX . 'default_bg_color' ); ?>" id="default_bg_color" value="<?php echo esc_attr( $this->get_setting( 'default_bg_color' ) ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Default background color (hex color or "transparent").', 'wp-background-remover' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="max_image_dimensions"><?php esc_html_e( 'Max Image Dimensions', 'wp-background-remover' ); ?></label>
						</th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION_PREFIX . 'max_image_dimensions' ); ?>" id="max_image_dimensions" value="<?php echo esc_attr( $this->get_setting( 'max_image_dimensions' ) ); ?>" min="1000" max="10000" step="100">
							<p class="description"><?php esc_html_e( 'Maximum image dimensions in pixels (1000-10000).', 'wp-background-remover' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="output_quality"><?php esc_html_e( 'Output Quality', 'wp-background-remover' ); ?></label>
						</th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION_PREFIX . 'output_quality' ); ?>" id="output_quality" value="<?php echo esc_attr( $this->get_setting( 'output_quality' ) ); ?>" min="1" max="100">
							<p class="description"><?php esc_html_e( 'Image output quality (1-100).', 'wp-background-remover' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Features', 'wp-background-remover' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_PREFIX . 'media_library_enabled' ); ?>" value="1" <?php checked( $this->get_setting( 'media_library_enabled' ), true ); ?>>
								<?php esc_html_e( 'Enable Media Library Integration', 'wp-background-remover' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_PREFIX . 'gutenberg_block_enabled' ); ?>" value="1" <?php checked( $this->get_setting( 'gutenberg_block_enabled' ), true ); ?>>
								<?php esc_html_e( 'Enable Gutenberg Block', 'wp-background-remover' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php if ( $is_premium ) : ?>
					<h2><?php esc_html_e( 'Premium Settings', 'wp-background-remover' ); ?> <span class="premium-badge"><?php esc_html_e( 'Premium', 'wp-background-remover' ); ?></span></h2>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="bulk_limit"><?php esc_html_e( 'Bulk Processing Limit', 'wp-background-remover' ); ?></label>
							</th>
							<td>
								<select name="<?php echo esc_attr( self::OPTION_PREFIX . 'bulk_limit' ); ?>" id="bulk_limit">
									<option value="10" <?php selected( $this->get_setting( 'bulk_limit' ), 10 ); ?>>10</option>
									<option value="25" <?php selected( $this->get_setting( 'bulk_limit' ), 25 ); ?>>25</option>
									<option value="50" <?php selected( $this->get_setting( 'bulk_limit' ), 50 ); ?>>50</option>
									<option value="100" <?php selected( $this->get_setting( 'bulk_limit' ), 100 ); ?>>100</option>
								</select>
								<p class="description"><?php esc_html_e( 'Maximum number of images to process at once.', 'wp-background-remover' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Advanced Features', 'wp-background-remover' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_PREFIX . 'auto_process_upload' ); ?>" value="1" <?php checked( $this->get_setting( 'auto_process_upload' ), true ); ?>>
									<?php esc_html_e( 'Auto-process on media upload', 'wp-background-remover' ); ?>
								</label>
								<br>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_PREFIX . 'api_access_enabled' ); ?>" value="1" <?php checked( $this->get_setting( 'api_access_enabled' ), true ); ?>>
									<?php esc_html_e( 'Enable API access', 'wp-background-remover' ); ?>
								</label>
								<br>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_PREFIX . 'custom_watermark' ); ?>" value="1" <?php checked( $this->get_setting( 'custom_watermark' ), true ); ?>>
									<?php esc_html_e( 'Enable custom watermark', 'wp-background-remover' ); ?>
								</label>
							</td>
						</tr>
					</table>
				<?php endif; ?>

				<h2><?php esc_html_e( 'API Configuration', 'wp-background-remover' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="api_endpoint"><?php esc_html_e( 'API Endpoint URL', 'wp-background-remover' ); ?></label>
						</th>
						<td>
							<input type="url" name="<?php echo esc_attr( self::OPTION_PREFIX . 'api_endpoint' ); ?>" id="api_endpoint" value="<?php echo esc_url( $this->get_setting( 'api_endpoint' ) ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Your backend API endpoint for account verification.', 'wp-background-remover' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
