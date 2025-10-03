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
		$version = defined( 'ENTR_MGR_VERSION' ) ? ENTR_MGR_VERSION : time();

		return array(
			'entr-mgr-tailwind-css' => array(
				'src'       => ENTR_MGR_ASSETS_URL . 'admin/tailwind.min.js',
				'deps'      => array(),
				'version'   => $version,
				'in_footer' => false,
			),
			'entr-mgr-admin-js'     => array(
				'src'       => ENTR_MGR_ASSETS_URL . 'admin/admin.js',
				'deps'      => array(),
				'version'   => filemtime( ENTR_MGR_PATH . 'assets/admin/admin.js' ),
				'in_footer' => true,
			),
			'entr-mgr-collapse'     => array(
				'src'       => ENTR_MGR_ASSETS_URL . 'admin/collapse.js',
				'deps'      => array(),
				'version'   => null,
				'in_footer' => true,
			),
			'entr-mgr-alpine'       => array(
				'src'       => ENTR_MGR_ASSETS_URL . 'admin/alpine.min.js',
				'deps'      => array( 'entr-mgr-collapse' ),
				'version'   => null,
				'in_footer' => true,
			),
			'entr-mgr-lottie'       => array(
				'src'       => ENTR_MGR_ASSETS_URL . 'admin/lottie-player.js',
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
		$version = defined( 'ENTR_MGR_VERSION' ) ? ENTR_MGR_VERSION : time();

		return array(
			'entr-mgr-admin-css' => array(
				'src'     => ENTR_MGR_ASSETS_URL . 'admin/admin.css',
				'deps'    => array(),
				'version' => filemtime( ENTR_MGR_PATH . 'assets/admin/admin.css' ),
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
				'toplevel_page_entrydashboard-entries-manager',
				'forms-entries_page_entrydashboard-entries-manager-settings',
				'forms-entries_page_entrydashboard-entries-manager-migration',
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
			'entr-mgr-admin-js',
			'entrMgrSettings',
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
			'entr-mgr-admin-js',
			'entrMgrReviewData',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'entr-mgr-dismiss-review-notice-nonce' ),
			)
		);

		wp_localize_script(
			'entr-mgr-admin-js',
			'entrMgrMigrationNotice',
			array(
				'title'      => __( 'Migrate from WPFormsDB', 'entries-manager' ),
				'message'    => __( 'We found data in the legacy', 'entries-manager' ) . ' <code>wpforms_db</code> ' . __( 'table. You can migrate all your entries into our advanced manager in just a few clicks.', 'entries-manager' ),
				'start'      => __( 'Start Migration', 'entries-manager' ),
				'dismissAlt' => __( 'Dismiss', 'entries-manager' ),
			)
		);

		wp_localize_script(
			'entr-mgr-admin-js',
			'searchDropdownString',
			array(
				'emailLabel'   => esc_html__( 'Email', 'entries-manager' ),
				'nameLabel'    => esc_html__( 'Name', 'entries-manager' ),
				'entryIdLabel' => esc_html__( 'Entry ID', 'entries-manager' ),
			),
		);

		wp_localize_script(
			'entr-mgr-admin-js',
			'entrMgrStrings',
			array(
				'title'                => __( 'Migrate from WPFormsDB', 'entries-manager' ),
				'message'              => __( 'We found data in the legacy', 'entries-manager' ) . ' <code>wpforms_db</code> ' . __( 'table. You can migrate all your entries into our advanced manager in just a few clicks.', 'entries-manager' ),
				'start'                => __( 'Start Migration', 'entries-manager' ),
				'dismissAlt'           => __( 'Dismiss', 'entries-manager' ),
				// General Messages
				'csvExportedSuccess'   => __( '✅ CSV exported successfully!', 'entries-manager' ),
				'changesSavedSuccess'  => __( '✅ Saved changes successfully!', 'entries-manager' ),
				'settingsSavedSuccess' => __( '✅ Settings saved successfully!', 'entries-manager' ),

				// Time ago
				'timeAgoJustNow'       => __( 'just now', 'entries-manager' ),
				/* translators: %d is the number of minutes ago */
				'timeAgoMinutes'       => _n( '%d minute ago', '%d minutes ago', 0, 'entries-manager' ),
				/* translators: %d is the number of hours ago */
				'timeAgoHours'         => _n( '%d hour ago', '%d hours ago', 0, 'entries-manager' ),
				'timeAgoYesterday'     => __( 'Yesterday', 'entries-manager' ),

				// Errors & Warnings
				'noteTooLong'          => __( 'Note is too long. Please limit to 1000 characters.', 'entries-manager' ),
				'deleteFailedUnknown'  => __( 'Failed to delete entry: Unknown error', 'entries-manager' ),
				'deleteRequestFailed'  => __( 'Delete request failed. Check console for details.', 'entries-manager' ),
				'networkError'         => __( 'A network error occurred. Please try again.', 'entries-manager' ),
				'entryNotFound'        => __( '❌ Entry not found in the list.', 'entries-manager' ),
				'bulkActionFailed'     => __( 'Bulk action failed:', 'entries-manager' ),
				'exportFailed'         => __( 'Failed to start export.', 'entries-manager' ),
				'exportProgressFailed' => __( 'Failed to fetch export progress.', 'entries-manager' ),
				'exportSelectForm'     => __( 'Please select a form before exporting.', 'entries-manager' ),
				'exportInvalidCSV'     => __( 'Invalid CSV content.', 'entries-manager' ),
				'exportComplete'       => __( 'Export complete! Your download should start shortly.', 'entries-manager' ),
				'fetchFormsError'      => __( 'Failed to fetch forms:', 'entries-manager' ),
				'fetchEntriesError'    => __( 'Failed to fetch entries:', 'entries-manager' ),
				'fetchFieldsError'     => __( 'Failed to fetch form fields. Please try again.', 'entries-manager' ),
				'unexpectedError'      => __( '❌ Unexpected error occurred.', 'entries-manager' ),
				'syncDone'             => __( 'Entry synchronization Done!', 'entries-manager' ),
				'syncFailed'           => __( '❌ Synchronization failed.', 'entries-manager' ),
				'saveFailed'           => __( '❌ Save failed.', 'entries-manager' ),
				/* translators: %s is the text for id, name or email */
				'searchPlaceholder'    => esc_html__( '🔍 Search by %s...', 'entries-manager' ),
				'emailLabel'           => esc_html__( 'Email', 'entries-manager' ),
				'nameLabel'            => esc_html__( 'Name', 'entries-manager' ),
				'entryIdLabel'         => esc_html__( 'Entry ID', 'entries-manager' ),
				'copyToClipboard'      => esc_html__( 'Copy Entry', 'entries-manager' ),
				'copiedMessage'        => esc_html__( 'Copied!', 'entries-manager' ),
				'copyTitle'            => esc_attr__( 'Copy all to clipboard', 'entries-manager' ),
				'copyFailed'           => __( 'Copy Failed', 'entries-manager' ),
				'selectFormExport'     => __( 'Please select a form before exporting.', 'entries-manager' ),
				'invalidCSVContent'    => __( 'Invalid CSV content.', 'entries-manager' ),
				'cannotDownloadCSV'    => __( 'Cannot download file: Export Job ID is missing.', 'entries-manager' ),
			)
		);
	}
}
