<?php
/**
 * Admin Interface Class
 *
 * Handles admin pages and interface
 *
 * @package WP_Background_Remover
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin interface class
 */
class WP_BG_Remover_Admin {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_bulk_processor' ) );
	}

	/**
	 * Add admin menu pages
	 */
	public function add_admin_menu() {
		// Main page
		add_menu_page(
			__( 'Background Remover', 'wp-background-remover' ),
			__( 'BG Remover', 'wp-background-remover' ),
			'upload_files',
			'wp-bg-remover',
			array( $this, 'render_main_page' ),
			'dashicons-format-image',
			25
		);

		// Settings page
		add_submenu_page(
			'wp-bg-remover',
			__( 'Settings', 'wp-background-remover' ),
			__( 'Settings', 'wp-background-remover' ),
			'manage_options',
			'wp-bg-remover-settings',
			array( $this, 'render_settings_page' )
		);

		// Get Premium page
		add_submenu_page(
			'wp-bg-remover',
			__( 'Get Premium', 'wp-background-remover' ),
			__( 'Get Premium', 'wp-background-remover' ),
			'upload_files',
			'wp-bg-remover-premium',
			array( $this, 'render_premium_page' )
		);
	}

	/**
	 * Enqueue bulk processor script only for premium users
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_bulk_processor( $hook ) {
		if ( 'toplevel_page_wp-bg-remover' !== $hook ) {
			return;
		}

		// Only enqueue for premium users
		if ( wp_bg_remover_is_premium() ) {
			wp_enqueue_script(
				'wp-bg-remover-bulk',
				WP_BG_REMOVER_BUILD_URL . 'bulk-processor.js',
				array( 'wp-bg-remover-core', 'jquery' ),
				WP_BG_REMOVER_VERSION,
				true
			);
		}
	}

	/**
	 * Render main admin page
	 */
	public function render_main_page() {
		$is_premium     = wp_bg_remover_is_premium();
		$account_status = WP_Background_Remover::instance()->account->get_account_status();
		?>
		<div class="wrap wp-bg-remover-admin">
			<h1><?php esc_html_e( 'Background Remover', 'wp-background-remover' ); ?></h1>

			<!-- Account Status -->
			<div class="wp-bg-remover-account-status">
				<?php if ( $is_premium ) : ?>
					<span class="status-badge premium">
						<?php esc_html_e( 'Premium Account', 'wp-background-remover' ); ?>
					</span>
					<span class="account-email"><?php echo esc_html( $account_status['email'] ); ?></span>
				<?php else : ?>
					<span class="status-badge free">
						<?php esc_html_e( 'Free Account', 'wp-background-remover' ); ?>
					</span>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-bg-remover-premium' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Upgrade to Premium', 'wp-background-remover' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<!-- Tabs -->
			<h2 class="nav-tab-wrapper">
				<a href="#single-image" class="nav-tab nav-tab-active" data-tab="single-image">
					<?php esc_html_e( 'Single Image', 'wp-background-remover' ); ?>
				</a>
				<a href="#bulk-process" class="nav-tab <?php echo $is_premium ? '' : 'premium-feature'; ?>" data-tab="bulk-process">
					<?php esc_html_e( 'Bulk Process', 'wp-background-remover' ); ?>
					<?php if ( ! $is_premium ) : ?>
						<span class="premium-badge"><?php esc_html_e( 'Premium', 'wp-background-remover' ); ?></span>
					<?php endif; ?>
				</a>
			</h2>

			<!-- Single Image Tab -->
			<div id="single-image" class="tab-content active">
				<div class="wp-bg-remover-single">
					<div class="upload-area">
						<div class="upload-box" id="upload-box">
							<div class="upload-icon">📁</div>
							<h3><?php esc_html_e( 'Drop your image here or click to upload', 'wp-background-remover' ); ?></h3>
							<p><?php esc_html_e( 'Supports JPG, PNG, WebP', 'wp-background-remover' ); ?></p>
							<input type="file" id="image-upload" accept="image/jpeg,image/png,image/webp" style="display: none;">
							<button type="button" class="button button-primary" id="select-file-btn">
								<?php esc_html_e( 'Select Image', 'wp-background-remover' ); ?>
							</button>
						</div>
					</div>

					<div class="processing-area" id="processing-area" style="display: none;">
						<div class="progress-container">
							<div class="progress-bar">
								<div class="progress-fill" id="progress-fill"></div>
							</div>
							<p class="progress-text" id="progress-text"><?php esc_html_e( 'Initializing...', 'wp-background-remover' ); ?></p>
						</div>
					</div>

					<div class="preview-area" id="preview-area" style="display: none;">
						<div class="image-comparison">
							<div class="image-container">
								<h4><?php esc_html_e( 'Original', 'wp-background-remover' ); ?></h4>
								<img id="original-image" src="" alt="<?php esc_attr_e( 'Original', 'wp-background-remover' ); ?>">
							</div>
							<div class="image-container">
								<h4><?php esc_html_e( 'Processed', 'wp-background-remover' ); ?></h4>
								<div class="processed-image-wrapper">
									<canvas id="processed-canvas"></canvas>
								</div>
							</div>
						</div>

						<div class="controls">
							<div class="control-group">
								<label for="bg-color"><?php esc_html_e( 'Background Color:', 'wp-background-remover' ); ?></label>
								<input type="color" id="bg-color" value="#ffffff">
								<button type="button" class="button" id="transparent-btn">
									<?php esc_html_e( 'Transparent', 'wp-background-remover' ); ?>
								</button>
							</div>
						</div>

						<div class="actions">
							<button type="button" class="button button-large" id="download-btn">
								<?php esc_html_e( 'Download', 'wp-background-remover' ); ?>
							</button>
							<button type="button" class="button button-large button-primary" id="save-to-library-btn">
								<?php esc_html_e( 'Save to Media Library', 'wp-background-remover' ); ?>
							</button>
							<button type="button" class="button button-large" id="clear-btn">
								<?php esc_html_e( 'Clear', 'wp-background-remover' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Bulk Process Tab -->
			<div id="bulk-process" class="tab-content">
				<?php if ( $is_premium ) : ?>
					<div class="wp-bg-remover-bulk">
						<div class="bulk-upload-area">
							<div class="bulk-upload-box" id="bulk-upload-box">
								<div class="upload-icon">📁</div>
								<h3><?php esc_html_e( 'Drop multiple images here or click to upload', 'wp-background-remover' ); ?></h3>
								<p><?php esc_html_e( 'Process up to 50 images at once', 'wp-background-remover' ); ?></p>
								<input type="file" id="bulk-image-upload" accept="image/jpeg,image/png,image/webp" multiple style="display: none;">
								<button type="button" class="button button-primary" id="select-bulk-files-btn">
									<?php esc_html_e( 'Select Images', 'wp-background-remover' ); ?>
								</button>
							</div>
						</div>

						<div class="bulk-queue" id="bulk-queue" style="display: none;">
							<h3><?php esc_html_e( 'Processing Queue', 'wp-background-remover' ); ?></h3>
							<div class="bulk-controls">
								<button type="button" class="button" id="bulk-start-btn">
									<?php esc_html_e( 'Start Processing', 'wp-background-remover' ); ?>
								</button>
								<button type="button" class="button" id="bulk-pause-btn" disabled>
									<?php esc_html_e( 'Pause', 'wp-background-remover' ); ?>
								</button>
								<button type="button" class="button" id="bulk-clear-btn">
									<?php esc_html_e( 'Clear Queue', 'wp-background-remover' ); ?>
								</button>
							</div>
							<div class="bulk-progress">
								<div class="progress-bar">
									<div class="progress-fill" id="bulk-progress-fill"></div>
								</div>
								<p class="progress-text" id="bulk-progress-text">0 / 0</p>
							</div>
							<div class="bulk-items" id="bulk-items"></div>
						</div>

						<div class="bulk-results" id="bulk-results" style="display: none;">
							<h3><?php esc_html_e( 'Results', 'wp-background-remover' ); ?></h3>
							<div class="bulk-actions">
								<button type="button" class="button button-primary" id="download-all-btn">
									<?php esc_html_e( 'Download All as ZIP', 'wp-background-remover' ); ?>
								</button>
								<button type="button" class="button" id="save-all-to-library-btn">
									<?php esc_html_e( 'Save All to Media Library', 'wp-background-remover' ); ?>
								</button>
							</div>
							<div class="results-grid" id="results-grid"></div>
						</div>
					</div>
				<?php else : ?>
					<div class="premium-upgrade-notice">
						<div class="upgrade-content">
							<h2><?php esc_html_e( 'Unlock Bulk Processing', 'wp-background-remover' ); ?></h2>
							<p><?php esc_html_e( 'Upgrade to Premium to process up to 50 images at once!', 'wp-background-remover' ); ?></p>

							<div class="premium-features">
								<h3><?php esc_html_e( 'Premium Features:', 'wp-background-remover' ); ?></h3>
								<ul>
									<li>✅ <?php esc_html_e( 'Bulk processing (up to 50 images)', 'wp-background-remover' ); ?></li>
									<li>✅ <?php esc_html_e( 'Queue management with pause/resume', 'wp-background-remover' ); ?></li>
									<li>✅ <?php esc_html_e( 'Export results as ZIP', 'wp-background-remover' ); ?></li>
									<li>✅ <?php esc_html_e( 'Advanced settings', 'wp-background-remover' ); ?></li>
									<li>✅ <?php esc_html_e( 'API access for developers', 'wp-background-remover' ); ?></li>
									<li>✅ <?php esc_html_e( 'Priority support', 'wp-background-remover' ); ?></li>
								</ul>
							</div>

							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-bg-remover-premium' ) ); ?>" class="button button-primary button-hero">
								<?php esc_html_e( 'Get Premium Access', 'wp-background-remover' ); ?>
							</a>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		WP_Background_Remover::instance()->settings->render_settings_page();
	}

	/**
	 * Render premium/account page
	 */
	public function render_premium_page() {
		$is_premium     = wp_bg_remover_is_premium();
		$account_status = WP_Background_Remover::instance()->account->get_account_status();
		?>
		<div class="wrap wp-bg-remover-premium">
			<h1><?php esc_html_e( 'Premium Account', 'wp-background-remover' ); ?></h1>

			<?php if ( $is_premium ) : ?>
				<!-- Already Premium -->
				<div class="premium-active">
					<div class="success-message">
						<h2><?php esc_html_e( 'You have Premium access!', 'wp-background-remover' ); ?></h2>
						<p><?php esc_html_e( 'Your account is verified and you have access to all premium features.', 'wp-background-remover' ); ?></p>
					</div>

					<div class="account-info">
						<h3><?php esc_html_e( 'Account Information', 'wp-background-remover' ); ?></h3>
						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'Email:', 'wp-background-remover' ); ?></th>
								<td><?php echo esc_html( $account_status['email'] ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Status:', 'wp-background-remover' ); ?></th>
								<td><span class="status-badge premium"><?php esc_html_e( 'Active', 'wp-background-remover' ); ?></span></td>
							</tr>
						</table>

						<button type="button" class="button" id="logout-account-btn">
							<?php esc_html_e( 'Disconnect Account', 'wp-background-remover' ); ?>
						</button>
					</div>
				</div>
			<?php else : ?>
				<!-- Premium Upgrade -->
				<div class="premium-upgrade">
					<!-- Account Login -->
					<div class="account-login">
						<h2><?php esc_html_e( 'Login to Your Account', 'wp-background-remover' ); ?></h2>
						<p><?php esc_html_e( 'Enter your account credentials to unlock premium features.', 'wp-background-remover' ); ?></p>

						<form id="account-login-form" class="account-form">
							<table class="form-table">
								<tr>
									<th>
										<label for="account-email"><?php esc_html_e( 'Email:', 'wp-background-remover' ); ?></label>
									</th>
									<td>
										<input type="email" id="account-email" name="email" class="regular-text" required>
									</td>
								</tr>
								<tr>
									<th>
										<label for="account-token"><?php esc_html_e( 'API Token:', 'wp-background-remover' ); ?></label>
									</th>
									<td>
										<input type="text" id="account-token" name="api_token" class="regular-text" required>
										<p class="description"><?php esc_html_e( 'Get your API token from your account dashboard.', 'wp-background-remover' ); ?></p>
									</td>
								</tr>
							</table>

							<p class="submit">
								<button type="submit" class="button button-primary button-large">
									<?php esc_html_e( 'Verify Account', 'wp-background-remover' ); ?>
								</button>
							</p>

							<div id="account-message" class="notice" style="display: none;"></div>
						</form>
					</div>

					<!-- Feature Comparison -->
					<div class="feature-comparison">
						<h2><?php esc_html_e( 'Free vs Premium', 'wp-background-remover' ); ?></h2>

						<table class="comparison-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Feature', 'wp-background-remover' ); ?></th>
									<th><?php esc_html_e( 'Free', 'wp-background-remover' ); ?></th>
									<th><?php esc_html_e( 'Premium', 'wp-background-remover' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><?php esc_html_e( 'Single image processing', 'wp-background-remover' ); ?></td>
									<td>✅</td>
									<td>✅</td>
								</tr>
								<tr>
									<td><?php esc_html_e( 'Media library integration', 'wp-background-remover' ); ?></td>
									<td>✅</td>
									<td>✅</td>
								</tr>
								<tr>
									<td><?php esc_html_e( 'Gutenberg block', 'wp-background-remover' ); ?></td>
									<td>✅</td>
									<td>✅</td>
								</tr>
								<tr>
									<td><?php esc_html_e( 'Bulk processing', 'wp-background-remover' ); ?></td>
									<td>❌</td>
									<td>✅</td>
								</tr>
								<tr>
									<td><?php esc_html_e( 'Process up to 50 images at once', 'wp-background-remover' ); ?></td>
									<td>❌</td>
									<td>✅</td>
								</tr>
								<tr>
									<td><?php esc_html_e( 'Export as ZIP', 'wp-background-remover' ); ?></td>
									<td>❌</td>
									<td>✅</td>
								</tr>
								<tr>
									<td><?php esc_html_e( 'Advanced settings', 'wp-background-remover' ); ?></td>
									<td>❌</td>
									<td>✅</td>
								</tr>
								<tr>
									<td><?php esc_html_e( 'API access', 'wp-background-remover' ); ?></td>
									<td>❌</td>
									<td>✅</td>
								</tr>
								<tr>
									<td><?php esc_html_e( 'Priority support', 'wp-background-remover' ); ?></td>
									<td>❌</td>
									<td>✅</td>
								</tr>
							</tbody>
						</table>
					</div>

					<!-- Call to Action -->
					<div class="premium-cta">
						<h3><?php esc_html_e( 'Ready to upgrade?', 'wp-background-remover' ); ?></h3>
						<p><?php esc_html_e( 'Create an account to get started with premium features.', 'wp-background-remover' ); ?></p>
						<p><em><?php esc_html_e( 'Note: Subscription system coming soon. Account creation and verification available now.', 'wp-background-remover' ); ?></em></p>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
