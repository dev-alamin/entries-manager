<?php

namespace Amin\FormsEntriesManager\Admin;

defined( 'ABSPATH' ) || exit;

use Amin\FormsEntriesManager\Utility\Helper;
use Amin\FormsEntriesManager\Admin\Logs\LogViewerPage;

/**
 * Class Menu
 *
 * Handles the admin menu registration, settings page, and asset enqueuing
 * for the Advanced Entries Manager for WPForms plugin.
 */
class Menu {

	/**
	 * LogViewer instance.
	 *
	 * @var Assets
	 */
	protected $log_viewer_page;

	/**
	 * Constructor.
	 *
	 * Hooks into WordPress admin actions to initialize the admin menu,
	 * enqueue assets, register settings, and hide update notices on plugin pages.
	 */
	public function __construct() {
		$this->log_viewer_page = new LogViewerPage();
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Add admin menu pages.
	 *
	 * Adds a top-level menu for WPForms Entries and a submenu for
	 * plugin settings. Both are accessible only to users with
	 * 'manage_options' capability.
	 *
	 * @return void
	 */
	public function add_menu() {
		$legacy_table_exists = Helper::table_exists( 'wpforms_db' );

		add_menu_page(
			__( 'Forms Entries', 'entrydashboard' ),
			__( 'Forms Entries', 'entrydashboard' ),
			'manage_options',
			'entrydashboard',
			array( $this, 'render_page' ),
			'dashicons-feedback',
			25
		);

		add_submenu_page(
			'entrydashboard',
			__( 'WPForms Entry Sync Settings', 'entrydashboard' ),
			__( 'Settings', 'entrydashboard' ),
			'manage_options',
			'entrydashboard-settings',
			array( $this, 'render_settings_page' ),
			65
		);

		add_submenu_page(
			'entrydashboard',
			__( 'Logs', 'entrydashboard' ),
			__( 'Logs', 'entrydashboard' ),
			'manage_options',
			'entrydashboard-logs',
			array( $this->log_viewer_page, 'render_page' )
		);

		if ( $legacy_table_exists
		&& ! Helper::get_option( 'migration_complete' )
		&& Helper::is_pro_version()
		) :
			add_submenu_page(
				'entrydashboard',
				__( 'Migration', 'entrydashboard' ),
				__( 'Migration', 'entrydashboard' ),
				'manage_options',
				'entrydashboard-migration',
				array( $this, 'render_migration_page' )
			);
		endif;
	}

	/**
	 * Render main entries page.
	 *
	 * Includes the main admin page view file.
	 *
	 * @return void
	 */
	public function render_page() {
		include __DIR__ . '/views/view-entries.php';
	}

	/**
	 * Render settings page.
	 *
	 * Includes the settings page view file.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		include __DIR__ . '/views/settings-page.php';
	}

	/**
	 * Render migration page.
	 *
	 * Includes the migration page view file.
	 *
	 * @return void
	 */
	public function render_migration_page() {
		include __DIR__ . '/views/migration-page.php';
	}
}
