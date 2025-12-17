<?php
/**
 * Plugin Name: WP Background Remover
 * Plugin URI: https://github.com/yourusername/wp-background-remover
 * Description: AI-powered background removal using BRIA-RMBG-1.4 model. Client-side processing with WebGPU/WASM. FREE: Single image processing. PREMIUM: Bulk processing and advanced features (account required).
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
	 * Account management instance
	 *
	 * @var WP_BG_Remover_Account
	 */
	public $account;

	/**
	 * Settings instance
	 *
	 * @var WP_BG_Remover_Settings
	 */
	public $settings;

	/**
	 * Admin instance
	 *
	 * @var WP_BG_Remover_Admin
	 */
	public $admin;

	/**
	 * Media integration instance
	 *
	 * @var WP_BG_Remover_Media_Integration
	 */
	public $media_integration;

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
		require_once WP_BG_REMOVER_PLUGIN_DIR . 'includes/class-account.php';
		require_once WP_BG_REMOVER_PLUGIN_DIR . 'includes/class-settings.php';
		require_once WP_BG_REMOVER_PLUGIN_DIR . 'includes/class-admin.php';
		require_once WP_BG_REMOVER_PLUGIN_DIR . 'includes/class-media-integration.php';
		require_once WP_BG_REMOVER_PLUGIN_DIR . 'includes/class-rest-api.php';
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Initialize classes
		add_action( 'plugins_loaded', array( $this, 'init_classes' ) );

		// Load text domain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Enqueue scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Register Gutenberg block
		add_action( 'init', array( $this, 'register_gutenberg_block' ) );

		// Activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialize plugin classes
	 */
	public function init_classes() {
		$this->account           = new WP_BG_Remover_Account();
		$this->settings          = new WP_BG_Remover_Settings();
		$this->admin             = new WP_BG_Remover_Admin();
		$this->media_integration = new WP_BG_Remover_Media_Integration();
		$this->rest_api          = new WP_BG_Remover_REST_API();
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
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on plugin pages and media pages
		$plugin_pages = array(
			'toplevel_page_wp-bg-remover',
			'wp-bg-remover_page_wp-bg-remover-settings',
			'wp-bg-remover_page_wp-bg-remover-premium',
			'upload.php',
			'post.php',
			'post-new.php',
		);

		if ( ! in_array( $hook, $plugin_pages, true ) ) {
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

		// Enqueue admin script
		wp_enqueue_script(
			'wp-bg-remover-admin',
			WP_BG_REMOVER_BUILD_URL . 'admin.js',
			array( 'wp-bg-remover-core', 'jquery' ),
			WP_BG_REMOVER_VERSION,
			true
		);

		// Enqueue admin styles
		wp_enqueue_style(
			'wp-bg-remover-admin',
			WP_BG_REMOVER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WP_BG_REMOVER_VERSION
		);

		// Pass configuration to JavaScript
		wp_localize_script(
			'wp-bg-remover-admin',
			'wpBgRemoverConfig',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'restUrl'     => rest_url( 'bg-remover/v1' ),
				'nonce'       => wp_create_nonce( 'wp_bg_remover_nonce' ),
				'isPremium'   => wp_bg_remover_is_premium(),
				'upgradeUrl'  => admin_url( 'admin.php?page=wp-bg-remover-premium' ),
				'settings'    => $this->settings->get_all_settings(),
			)
		);
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Only load if Gutenberg block is used
		if ( has_block( 'wp-bg-remover/background-remover' ) ) {
			wp_enqueue_script(
				'wp-bg-remover-core',
				WP_BG_REMOVER_BUILD_URL . 'background-remover.js',
				array(),
				WP_BG_REMOVER_VERSION,
				true
			);

			wp_enqueue_style(
				'wp-bg-remover-frontend',
				WP_BG_REMOVER_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				WP_BG_REMOVER_VERSION
			);
		}
	}

	/**
	 * Register Gutenberg block
	 */
	public function register_gutenberg_block() {
		// Check if Gutenberg is available
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register block script
		wp_register_script(
			'wp-bg-remover-block',
			WP_BG_REMOVER_BUILD_URL . 'gutenberg-block.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-bg-remover-core',
			),
			WP_BG_REMOVER_VERSION,
			true
		);

		// Register block style
		wp_register_style(
			'wp-bg-remover-block-editor',
			WP_BG_REMOVER_PLUGIN_URL . 'blocks/background-remover/style.css',
			array( 'wp-edit-blocks' ),
			WP_BG_REMOVER_VERSION
		);

		// Register block
		register_block_type(
			'wp-bg-remover/background-remover',
			array(
				'editor_script'   => 'wp-bg-remover-block',
				'editor_style'    => 'wp-bg-remover-block-editor',
				'style'           => 'wp-bg-remover-block-editor',
			)
		);
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Set default options
		$default_settings = array(
			'model_precision'        => 'q8',
			'device_preference'      => 'auto',
			'default_bg_color'       => 'transparent',
			'max_image_dimensions'   => 4000,
			'output_quality'         => 95,
			'media_library_enabled'  => true,
			'gutenberg_block_enabled' => true,
		);

		foreach ( $default_settings as $key => $value ) {
			if ( false === get_option( "wp_bg_remover_{$key}" ) ) {
				add_option( "wp_bg_remover_{$key}", $value );
			}
		}

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
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
 * Helper function to check if user has premium account
 *
 * @return bool
 */
function wp_bg_remover_is_premium() {
	$account = WP_Background_Remover::instance()->account;
	return $account ? $account->is_premium() : false;
}

/**
 * Helper function to check if user can access a specific feature
 *
 * @param string $feature Feature name to check.
 * @return bool
 */
function wp_bg_remover_can_access( $feature ) {
	$account = WP_Background_Remover::instance()->account;
	return $account ? $account->can_access_feature( $feature ) : false;
}

/**
 * License Notice
 *
 * This plugin uses the BRIA-RMBG-1.4 model which is free for non-commercial use.
 * Commercial use requires a license from BRIA AI.
 * Model License: https://huggingface.co/briaai/RMBG-1.4
 *
 * Plugin Licensing:
 * - FREE: Single image processing (no account required)
 * - PREMIUM: Bulk processing and advanced features (account required)
 */
