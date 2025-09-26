<?php

namespace Amin\FormsEntriesManager;

use Amin\FormsEntriesManager\Utility\Helper;

defined( 'ABSPATH' ) || exit;
/**
 * Class Assets
 *
 * Handles the registration and enqueueing of admin assets.
 */
class Assets {

	/**
	 * Constructor: Hook into admin asset loading.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Get all admin scripts.
	 *
	 * @return array
	 */
	public function get_scripts() {
		$version = defined( 'FEM_VERSION' ) ? FEM_VERSION : time();

		return array(
			'fem-tailwind-css' => array(
				'src'       => FEM_ASSETS_URL . 'admin/tailwind.min.js',
				'deps'      => array(),
				'version'   => $version,
				'in_footer' => false,
			),
			'fem-admin-js'     => array(
				'src'       => FEM_ASSETS_URL . 'admin/admin.js',
				'deps'      => array(),
				'version'   => filemtime( FEM_PATH . 'assets/admin/admin.js' ),
				'in_footer' => true,
			),
			'fem-collapse'     => array(
				'src'       => FEM_ASSETS_URL . 'admin/collapse.js',
				'deps'      => array(),
				'version'   => null,
				'in_footer' => true,
			),
			'fem-alpine'       => array(
				'src'       => FEM_ASSETS_URL . 'admin/alpine.min.js',
				'deps'      => array( 'fem-collapse' ),
				'version'   => null,
				'in_footer' => true,
			),
			'fem-lottie'       => array(
				'src'       => FEM_ASSETS_URL . 'admin/lottie-player.js',
				'deps'      => array(),
				'version'   => '5.12.0',
				'in_footer' => true,
			),
		);
	}

	/**
	 * Get all admin styles.
	 *
	 * @return array
	 */
	public function get_styles() {
		$version = defined( 'FEM_VERSION' ) ? FEM_VERSION : time();

		return array(
			'fem-admin-css' => array(
				'src'     => FEM_ASSETS_URL . 'admin/admin.css',
				'deps'    => array(),
				'version' => filemtime( FEM_PATH . 'assets/admin/admin.css' ),
			),
		);
	}

	/**
	 * Register and enqueue assets on specific admin pages.
	 *
	 * @param string $hook
	 * @return void
	 */
	public function register_assets( $hook ) {
		if ( ! in_array(
			$hook,
			array(
				'toplevel_page_entrydashboard',
				'forms-entries_page_entrydashboard-settings',
				'forms-entries_page_entrydashboard-migration',
			),
			true
		) ) {
			return;
		}

		// Register styles
		foreach ( $this->get_styles() as $handle => $style ) {
			wp_register_style(
				$handle,
				$style['src'],
				$style['deps'] ?? array(),
				$style['version'] ?? false,
			);

			wp_enqueue_style( $handle );
		}

		// Register scripts
		foreach ( $this->get_scripts() as $handle => $script ) {
			wp_register_script(
				$handle,
				$script['src'],
				$script['deps'] ?? array(),
				$script['version'] ?? false,
				$script['in_footer'] ?? true
			);

			wp_enqueue_script( $handle );
		}

		wp_enqueue_script( 'lodash.min.js' );
		// Get the existing custom columns from the database.
		$initial_columns = Helper::get_option( 'cusom_form_columns_settings', array() );
		// Localize main admin JS
		wp_localize_script(
			'fem-admin-js',
			'femSettings',
			array(
				'restUrl'        => esc_url_raw( rest_url() ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'perPage'        => Helper::get_option( 'entries_per_page', 20 ),
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'initialColumns' => $initial_columns ? json_decode( $initial_columns ) : array(),
			)
		);

		// Pass dynamic data from PHP to your script
		wp_localize_script(
			'fem-admin-js',
			'femReviewData',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'fem-dismiss-review-notice-nonce' ),
			)
		);

		wp_localize_script(
			'fem-admin-js',
			'femMigrationNotice',
			array(
				'title'      => __( 'Migrate from WPFormsDB', 'entrydashboard' ),
				'message'    => __( 'We found data in the legacy', 'entrydashboard' ) . ' <code>wpforms_db</code> ' . __( 'table. You can migrate all your entries into our advanced manager in just a few clicks.', 'entrydashboard' ),
				'start'      => __( 'Start Migration', 'entrydashboard' ),
				'dismissAlt' => __( 'Dismiss', 'entrydashboard' ),
			)
		);

		wp_localize_script(
			'fem-admin-js',
			'searchDropdownString',
			array(
				'emailLabel'   => esc_html__( 'Email', 'entrydashboard' ),
				'nameLabel'    => esc_html__( 'Name', 'entrydashboard' ),
				'entryIdLabel' => esc_html__( 'Entry ID', 'entrydashboard' ),
			),
		);

		wp_localize_script(
			'fem-admin-js',
			'femStrings',
			array(
				'title'                => __( 'Migrate from WPFormsDB', 'entrydashboard' ),
				'message'              => __( 'We found data in the legacy', 'entrydashboard' ) . ' <code>wpforms_db</code> ' . __( 'table. You can migrate all your entries into our advanced manager in just a few clicks.', 'entrydashboard' ),
				'start'                => __( 'Start Migration', 'entrydashboard' ),
				'dismissAlt'           => __( 'Dismiss', 'entrydashboard' ),
				// General Messages
				'csvExportedSuccess'   => __( '✅ CSV exported successfully!', 'entrydashboard' ),
				'changesSavedSuccess'  => __( '✅ Saved changes successfully!', 'entrydashboard' ),
				'settingsSavedSuccess' => __( '✅ Settings saved successfully!', 'entrydashboard' ),

				// Time ago
				'timeAgoJustNow'       => __( 'just now', 'entrydashboard' ),
				/* translators: %d is the number of minutes ago */
				'timeAgoMinutes'       => _n( '%d minute ago', '%d minutes ago', 0, 'entrydashboard' ),
				/* translators: %d is the number of hours ago */
				'timeAgoHours'         => _n( '%d hour ago', '%d hours ago', 0, 'entrydashboard' ),
				'timeAgoYesterday'     => __( 'Yesterday', 'entrydashboard' ),

				// Errors & Warnings
				'noteTooLong'          => __( 'Note is too long. Please limit to 1000 characters.', 'entrydashboard' ),
				'deleteFailedUnknown'  => __( 'Failed to delete entry: Unknown error', 'entrydashboard' ),
				'deleteRequestFailed'  => __( 'Delete request failed. Check console for details.', 'entrydashboard' ),
				'networkError'         => __( 'A network error occurred. Please try again.', 'entrydashboard' ),
				'entryNotFound'        => __( '❌ Entry not found in the list.', 'entrydashboard' ),
				'bulkActionFailed'     => __( 'Bulk action failed:', 'entrydashboard' ),
				'exportFailed'         => __( 'Failed to start export.', 'entrydashboard' ),
				'exportProgressFailed' => __( 'Failed to fetch export progress.', 'entrydashboard' ),
				'exportSelectForm'     => __( 'Please select a form before exporting.', 'entrydashboard' ),
				'exportInvalidCSV'     => __( 'Invalid CSV content.', 'entrydashboard' ),
				'exportComplete'       => __( 'Export complete! Your download should start shortly.', 'entrydashboard' ),
				'fetchFormsError'      => __( 'Failed to fetch forms:', 'entrydashboard' ),
				'fetchEntriesError'    => __( 'Failed to fetch entries:', 'entrydashboard' ),
				'fetchFieldsError'     => __( 'Failed to fetch form fields. Please try again.', 'entrydashboard' ),
				'unexpectedError'      => __( '❌ Unexpected error occurred.', 'entrydashboard' ),
				'syncDone'             => __( 'Entry synchronization Done!', 'entrydashboard' ),
				'syncFailed'           => __( '❌ Synchronization failed.', 'entrydashboard' ),
				'saveFailed'           => __( '❌ Save failed.', 'entrydashboard' ),
				/* translators: %s is the text for id, name or email */
				'searchPlaceholder'    => esc_html__( '🔍 Search by %s...', 'entrydashboard' ),
				'emailLabel'           => esc_html__( 'Email', 'entrydashboard' ),
				'nameLabel'            => esc_html__( 'Name', 'entrydashboard' ),
				'entryIdLabel'         => esc_html__( 'Entry ID', 'entrydashboard' ),
				'copyToClipboard'      => esc_html__( 'Copy Entry', 'entrydashboard' ),
				'copiedMessage'        => esc_html__( 'Copied!', 'entrydashboard' ),
				'copyTitle'            => esc_attr__( 'Copy all to clipboard', 'entrydashboard' ),
				'copyFailed'           => __( 'Copy Failed', 'entrydashboard' ),
				'selectFormExport'     => __( 'Please select a form before exporting.', 'entrydashboard' ),
				'invalidCSVContent'    => __( 'Invalid CSV content.', 'entrydashboard' ),
				'cannotDownloadCSV'    => __( 'Cannot download file: Export Job ID is missing.', 'entrydashboard' ),
			)
		);
	}
}
