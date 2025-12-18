<?php
/**
 * Plugin Name: WP Background Remover
 * Plugin URI: https://github.com/yourusername/wp-background-remover
 * Description: AI-powered background removal for your visitors. Use shortcode [bg_remover] to add the tool to any page. Processing happens locally in the user's browser using BRIA-RMBG-1.4 model.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-background-remover
 * Domain Path: /languages
 *
 * @package WP_Background_Remover
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'WP_BG_REMOVER_VERSION', '1.0.0' );
define( 'WP_BG_REMOVER_PLUGIN_FILE', __FILE__ );
define( 'WP_BG_REMOVER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_BG_REMOVER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_BG_REMOVER_BUILD_DIR', WP_BG_REMOVER_PLUGIN_DIR . 'build/' );
define( 'WP_BG_REMOVER_BUILD_URL', WP_BG_REMOVER_PLUGIN_URL . 'build/' );

/**
 * Main plugin class
 */
class WP_Background_Remover {
	/**
	 * Plugin instance
	 *
	 * @var WP_Background_Remover
	 */
	private static $instance = null;

	/**
	 * REST API instance
	 *
	 * @var WP_BG_Remover_REST_API
	 */
	public $rest_api;

	/**
	 * Get plugin instance
	 *
	 * @return WP_Background_Remover
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files
	 */
	private function load_dependencies() {
		require_once WP_BG_REMOVER_PLUGIN_DIR . 'includes/class-rest-api.php';
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Initialize REST API
		add_action( 'plugins_loaded', array( $this, 'init_classes' ) );

		// Load text domain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Register shortcode
		add_shortcode( 'bg_remover', array( $this, 'render_shortcode' ) );

		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Activation hook
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
	}

	/**
	 * Initialize plugin classes
	 */
	public function init_classes() {
		$this->rest_api = new WP_BG_Remover_REST_API();
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wp-background-remover',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Only load if shortcode is present
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'bg_remover' ) ) {
			return;
		}

		// Enqueue background remover core
		wp_enqueue_script(
			'wp-bg-remover-core',
			WP_BG_REMOVER_BUILD_URL . 'background-remover.js',
			array(),
			WP_BG_REMOVER_VERSION,
			true
		);

		// Enqueue app script
		wp_enqueue_script(
			'wp-bg-remover-app',
			WP_BG_REMOVER_BUILD_URL . 'app.js',
			array( 'wp-bg-remover-core' ),
			WP_BG_REMOVER_VERSION,
			true
		);

		// Enqueue styles
		wp_enqueue_style(
			'wp-bg-remover-frontend',
			WP_BG_REMOVER_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			WP_BG_REMOVER_VERSION
		);

		// Pass configuration to JavaScript
		wp_localize_script(
			'wp-bg-remover-app',
			'wpBgRemoverConfig',
			array(
				'restUrl' => rest_url( 'bg-remover/v1' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Render shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode HTML output
	 */
	public function render_shortcode( $atts ) {
		// Parse attributes
		$atts = shortcode_atts(
			array(
				'title'      => __( 'AI Background Remover', 'wp-background-remover' ),
				'max_images' => 50,
			),
			$atts,
			'bg_remover'
		);

		ob_start();
		?>
		<div class="wp-bg-remover" data-max-images="<?php echo esc_attr( $atts['max_images'] ); ?>">
			<div class="wp-bg-remover-header">
				<h2><?php echo esc_html( $atts['title'] ); ?></h2>
				<p><?php esc_html_e( 'Remove backgrounds from images using AI. All processing happens locally in your browser!', 'wp-background-remover' ); ?></p>
			</div>

			<!-- Upload Area -->
			<div class="wp-bg-remover-upload" id="upload-area">
				<div class="upload-box" id="upload-box">
					<div class="upload-icon">📁</div>
					<h3><?php esc_html_e( 'Drop your images here or click to upload', 'wp-background-remover' ); ?></h3>
					<p><?php esc_html_e( 'Supports JPG, PNG, WebP • Process up to 50 images at once', 'wp-background-remover' ); ?></p>
					<input type="file" id="image-upload" accept="image/jpeg,image/png,image/webp" multiple style="display: none;">
					<button type="button" class="wp-bg-remover-button primary" id="select-files-btn">
						<?php esc_html_e( 'Select Images', 'wp-background-remover' ); ?>
					</button>
				</div>
			</div>

			<!-- Processing Area -->
			<div class="wp-bg-remover-processing" id="processing-area" style="display: none;">
				<div class="processing-header">
					<h3><?php esc_html_e( 'Processing Images', 'wp-background-remover' ); ?></h3>
					<div class="processing-controls">
						<button type="button" class="wp-bg-remover-button" id="pause-btn">
							<?php esc_html_e( 'Pause', 'wp-background-remover' ); ?>
						</button>
						<button type="button" class="wp-bg-remover-button" id="cancel-btn">
							<?php esc_html_e( 'Cancel', 'wp-background-remover' ); ?>
						</button>
					</div>
				</div>

				<div class="progress-container">
					<div class="progress-bar">
						<div class="progress-fill" id="progress-fill"></div>
					</div>
					<p class="progress-text" id="progress-text"><?php esc_html_e( 'Initializing...', 'wp-background-remover' ); ?></p>
				</div>

				<div class="image-queue" id="image-queue"></div>
			</div>

			<!-- Results Area -->
			<div class="wp-bg-remover-results" id="results-area" style="display: none;">
				<div class="results-header">
					<h3><?php esc_html_e( 'Processed Images', 'wp-background-remover' ); ?></h3>
					<div class="results-actions">
						<button type="button" class="wp-bg-remover-button primary" id="download-all-btn">
							<?php esc_html_e( 'Download All', 'wp-background-remover' ); ?>
						</button>
						<button type="button" class="wp-bg-remover-button" id="process-more-btn">
							<?php esc_html_e( 'Process More Images', 'wp-background-remover' ); ?>
						</button>
					</div>
				</div>

				<div class="results-grid" id="results-grid"></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}
}

/**
 * Initialize the plugin
 */
function wp_background_remover() {
	return WP_Background_Remover::instance();
}

// Start the plugin
wp_background_remover();

/**
 * License Notice
 *
 * This plugin uses the BRIA-RMBG-1.4 model which is free for non-commercial use.
 * Commercial use requires a license from BRIA AI.
 * Model License: https://huggingface.co/briaai/RMBG-1.4
 */
