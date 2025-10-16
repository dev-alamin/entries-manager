<?php

namespace Amin\FormsEntriesManager\Admin\Logs;

use Amin\FormsEntriesManager\Logger\FileLogger;
use Amin\FormsEntriesManager\Utility\FileSystem;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the admin page for viewing and downloading log files.
 */
class LogViewerPage {

	/**
	 * @var FileLogger The logger instance.
	 */
	protected $logger;

	/**
	 * @var FileSystem The filesystem instance.
	 */
	protected $fs;

	const LOG_PAGE_SLUG = 'entrydashboard-entries-manager-logs';

	/**
	 * Constructor to initialize the class properties.
	 */
	public function __construct() {
		$this->logger = new FileLogger();
		$this->fs     = new FileSystem();
	}

	/**
	 * Renders the content of the admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) { // Use your required capability
			return;
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			if ( check_admin_referer( 'entr_mgr_log_clear' ) === false ) { // Changed key to new prefix
				printf(
					'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
					esc_html__( 'Security check failed. Please try again.', 'entries-manager' )
				);
				return;
			}
		}

		if ( isset( $_GET['action'], $_GET['file'] ) && $_GET['action'] === 'view_log' ) {

			$nonce_result = wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ),
				'entr_mgr_view_logs' // Use a specific action nonce key
			);

			if ( ! $nonce_result ) {
				printf(
					'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
					esc_html__( 'Security check failed while viewing log.', 'entries-manager' )
				);
				$this->render_log_list(); // Default back to the list
				return;
			}

			// If the nonce is valid, proceed to render the log view
			$this->render_single_log_view();
			return;
		}

		// Default: render log list (This is safe as it requires no user intent/action)
		$this->render_log_list();
	}

	/**
	 * Renders the log file list view.
	 */
	private function render_log_list() {
		// Nonce already verified in parent method.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$log_files    = $this->get_log_files();
		$message      = '';
		$message_type = '';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['message'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message      = sanitize_text_field( wp_unslash( $_GET['message'] ) );
			$message_type = 'success';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'EntryDashboard Logs', 'entries-manager' ); ?></h1>
			
			<?php if ( $message ) : ?>
				<div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
			<?php endif; ?>

			<div class="">
				<h2><?php esc_html_e( 'Log Files', 'entries-manager' ); ?></h2>
				<p><?php esc_html_e( 'View and download log files. Logs are cleaned up automatically after 30 days.', 'entries-manager' ); ?></p>
				
				<?php
				// We use Log_List_Table to render the actual list.
				$log_list_table = new \Amin\FormsEntriesManager\Admin\Logs\Log_List_Table( $log_files );
				$log_list_table->prepare_items();
				$log_list_table->display();
				?>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Clear Old Logs', 'entries-manager' ); ?></h2>
				<p><?php esc_html_e( 'You can manually trigger the log cleanup process.', 'entries-manager' ); ?></p>

				<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'action'   => 'entr_mgr_clear_logs',
							'_wpnonce' => wp_create_nonce( 'entr_mgr_log_clear' ),
						),
						admin_url( 'admin-post.php' ) // 👈 important: always specify base URL
					)
				);
				?>
				" class="button button-primary">
					<?php esc_html_e( 'Clear Logs Now', 'entries-manager' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a single log file's content.
	 */
	private function render_single_log_view() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['file'] ) ) {
			return;
		}

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$file_name = sanitize_file_name( wp_unslash( $_GET['file'] ) );
		$log_dir   = $this->get_log_directory();
		$file_path = trailingslashit( $log_dir ) . $file_name;

		// Security check
		if ( $this->fs->exists( $file_path ) && strpos( realpath( $file_path ), realpath( $log_dir ) ) === 0 ) {
			$content = $this->fs->read( $file_path );
		} else {
			$content = __( 'File not found or invalid.', 'entries-manager' );
		}
		?>
		<div class="wrap">
			<h1>
			<?php
			/* translators: %s is the log file name */
			printf( esc_html__( 'Viewing Log File: %s', 'entries-manager' ), esc_html( $file_name ) );
			?>
			</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::LOG_PAGE_SLUG ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Back to Logs', 'entries-manager' ); ?></a>
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'action'   => 'entr_mgr_download_log',
						'file'     => $file_name,
						'_wpnonce' => wp_create_nonce( 'entrydashboard-download' ),
					),
					admin_url( 'admin-post.php' ) // 👈 important: always specify base URL
				)
			);
			?>
			" class="button button-primary">
				<?php esc_html_e( 'Download Log', 'entries-manager' ); ?>
			</a>

			<div class="card" style="margin-top: 20px; max-width:fit-content;">
				<pre style="white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html( $content ); ?></pre>
			</div>
		</div>
		<?php
	}

	/**
	 * Gets a list of log files from the log directory.
	 *
	 * @return array An associative array of log files.
	 */
	private function get_log_files() {
		$log_dir = $this->get_log_directory();
		$files   = $this->fs->dirlist( $log_dir, false, false );

		if ( empty( $files ) ) {
			return array();
		}

		// Sort files by last modification date, descending.
		usort(
			$files,
			function ( $a, $b ) {
				return $b['lastmodunix'] <=> $a['lastmodunix'];
			}
		);

		// Filter to only include .log files.
		$log_files = array();
		foreach ( $files as $file => $details ) {
			if ( $details['type'] === 'f' && substr( $file, -4 ) === '.log' ) {
				$log_files[ $file ] = $details;
			}
		}
		return $log_files;
	}

	/**
	 * Gets the full path to the log directory.
	 *
	 * @return string The log directory path.
	 */
	protected function get_log_directory() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'entrydashboard-logs';
	}
}