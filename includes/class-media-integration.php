<?php
/**
 * Media Library Integration Class
 *
 * Handles integration with WordPress media library
 *
 * @package WP_Background_Remover
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media integration class
 */
class WP_BG_Remover_Media_Integration {
	/**
	 * Constructor
	 */
	public function __construct() {
		// Check if media library integration is enabled
		if ( ! get_option( 'wp_bg_remover_media_library_enabled', true ) ) {
			return;
		}

		add_filter( 'media_row_actions', array( $this, 'add_media_row_actions' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_scripts' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_field' ), 10, 2 );
	}

	/**
	 * Add "Remove Background" action to media library
	 *
	 * @param array   $actions Current actions.
	 * @param WP_Post $post Attachment post object.
	 * @return array Modified actions
	 */
	public function add_media_row_actions( $actions, $post ) {
		// Only add for images
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $actions;
		}

		$actions['remove_background'] = sprintf(
			'<a href="#" class="wp-bg-remover-media-action" data-attachment-id="%d">%s</a>',
			$post->ID,
			__( 'Remove Background', 'wp-background-remover' )
		);

		return $actions;
	}

	/**
	 * Add field to attachment edit screen
	 *
	 * @param array   $form_fields Form fields.
	 * @param WP_Post $post Attachment post object.
	 * @return array Modified form fields
	 */
	public function add_attachment_field( $form_fields, $post ) {
		// Only add for images
		if ( ! wp_attachment_is_image( $post->ID ) ) {
			return $form_fields;
		}

		$form_fields['remove_background'] = array(
			'label'         => __( 'Background Removal', 'wp-background-remover' ),
			'input'         => 'html',
			'html'          => sprintf(
				'<button type="button" class="button wp-bg-remover-attachment-btn" data-attachment-id="%d">%s</button>',
				$post->ID,
				__( 'Remove Background', 'wp-background-remover' )
			),
			'show_in_edit'  => true,
			'show_in_modal' => true,
		);

		return $form_fields;
	}

	/**
	 * Enqueue media library scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_media_scripts( $hook ) {
		// Only load on media pages
		if ( 'upload.php' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
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

		// Enqueue media modal script
		wp_enqueue_script(
			'wp-bg-remover-media-modal',
			WP_BG_REMOVER_BUILD_URL . 'media-modal.js',
			array( 'wp-bg-remover-core', 'jquery' ),
			WP_BG_REMOVER_VERSION,
			true
		);

		// Enqueue styles
		wp_enqueue_style(
			'wp-bg-remover-media',
			WP_BG_REMOVER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WP_BG_REMOVER_VERSION
		);

		// Pass configuration to JavaScript
		wp_localize_script(
			'wp-bg-remover-media-modal',
			'wpBgRemoverMedia',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'restUrl'  => rest_url( 'bg-remover/v1' ),
				'nonce'    => wp_create_nonce( 'wp_bg_remover_nonce' ),
				'settings' => WP_Background_Remover::instance()->settings->get_all_settings(),
				'strings'  => array(
					'modalTitle'       => __( 'Remove Background', 'wp-background-remover' ),
					'processing'       => __( 'Processing...', 'wp-background-remover' ),
					'complete'         => __( 'Complete!', 'wp-background-remover' ),
					'error'            => __( 'Error', 'wp-background-remover' ),
					'saveAsNew'        => __( 'Save as New', 'wp-background-remover' ),
					'replaceOriginal'  => __( 'Replace Original', 'wp-background-remover' ),
					'cancel'           => __( 'Cancel', 'wp-background-remover' ),
					'backgroundColor'  => __( 'Background Color', 'wp-background-remover' ),
					'transparent'      => __( 'Transparent', 'wp-background-remover' ),
					'downloadFailed'   => __( 'Failed to download image', 'wp-background-remover' ),
					'processingFailed' => __( 'Failed to process image', 'wp-background-remover' ),
					'saveFailed'       => __( 'Failed to save image', 'wp-background-remover' ),
					'saveSuccess'      => __( 'Image saved successfully!', 'wp-background-remover' ),
				),
			)
		);
	}
}
