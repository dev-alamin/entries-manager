<?php

namespace Amin\FormsEntriesManager\GoogleSheet;

defined( 'ABSPATH' ) || exit;

use Amin\FormsEntriesManager\Utility\Helper;
use Amin\FormsEntriesManager\Logger\FileLogger;
use WP_Error;

class Send_Data {


	protected $logger;

	public function __construct() {
		$this->logger = new FileLogger();

		// OAuth token capture endpoint
		add_action( 'admin_init', array( $this, 'capture_token' ) );

		// Google revocation endpoint
		add_action( 'admin_post_entriesmanager_revoke_connection', array( $this, 'handle_google_revocation_action' ) );
	}

	/**
	 * Handles the revocation action when the user clicks the "Revoke Connection" button.
	 */
	public function handle_google_revocation_action() {
		// Verify nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'revoke_connection_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'entries-manager' ) );
		}

		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'entries-manager' ) );
		}

		$success = Helper::revokeConnection();

		$redirect_url = admin_url( 'admin.php?page=entrydashboard-entries-manager-settings' );
		$redirect_url = add_query_arg( 'revoked', $success ? 'success' : 'failed', $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handles OAuth token capture, triggered only via admin_post endpoint
	 */
	public function capture_token() {
		if ( ! isset( $_GET['oauth_proxy_code'] ) ) {
			return;
		}

		// Permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'entries-manager' ) );
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'entr_mgr_oauth_init' ) ) {
			wp_die( esc_html__( 'Security check failed. Please try connecting again.', 'entries-manager' ) );
		}

		$auth_code = sanitize_text_field( wp_unslash( $_GET['oauth_proxy_code'] ) );

		// Exchange the one-time auth code for real tokens
		$response = wp_remote_post(
			ENTR_MGR_PROXY_BASE_URL . 'wp-json/swpfe/v1/token',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'auth_code' => $auth_code ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'Token exchange failed: ' . $response->get_error_message(), 'ERROR' );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['access_token'] ) ) {
			Helper::update_option( 'google_access_token', sanitize_text_field( $body['access_token'] ) );
			Helper::update_option( 'google_token_expires', time() + intval( $body['expires_in'] ?? 3600 ) );
			Helper::update_option( 'user_remvoked_google_connection', false );

					// 2. Fire the connection hook to schedule tasks!
			Helper::fire_connection_hook( true );

			wp_safe_redirect( admin_url( 'admin.php?page=entrydashboard-entries-manager-settings&connected=true' ) );
			exit;
		}

		$this->logger->log( 'Token exchange failed: Invalid response', 'ERROR' );
	}

	/**
	 * Get or create the necessary spreadsheet and sheet for a given form.
	 * This is the master function for ensuring a sync target exists and is ready.
	 *
	 * @param  int $form_id The ID of the WPForm.
	 * @return array|WP_Error An array containing ['spreadsheet_id', 'sheet_title'] or a WP_Error on failure.
	 */
	protected function get_or_create_sheet_for_form( int $form_id ) {
		// Enforce Free Version form limitation
		if ( ! Helper::is_pro_version() ) {
			$linked_forms = Helper::get_option( 'entr_mgr_linked_forms', array() );
			if ( ! in_array( $form_id, $linked_forms ) && count( $linked_forms ) >= 1 ) {
				return new WP_Error( 'limit_exceeded', 'The free version supports synchronizing data from only one form. Please upgrade to Pro to sync more forms.' );
			}
		}

		$spreadsheet_id = Helper::get_option( "gsheet_spreadsheet_id_{$form_id}" );
		$sheet_title    = Helper::get_option( "gsheet_sheet_title_{$form_id}" );
		$headers_set    = Helper::get_option( "gsheet_headers_set_{$form_id}" );

		// If we have a spreadsheet and the headers have already been set and formatted,
		// we can return immediately. This is the main performance optimization.
		if ( $spreadsheet_id && $sheet_title && $headers_set ) {
			return array(
				'spreadsheet_id' => $spreadsheet_id,
				'sheet_title'    => $sheet_title,
			);
		}

		// Prevent multiple simultaneous creation attempts for the same form.
		$lock_key = 'entr_mgr_gsheet_creating_lock_' . $form_id;
		if ( get_transient( $lock_key ) ) {
			return new WP_Error( 'locked', 'Sheet creation for this form is already in progress.' );
		}
		set_transient( $lock_key, true, 60 ); // Lock for 60 seconds

		// --- Create Spreadsheet if it doesn't exist ---
		if ( ! $spreadsheet_id ) {
			$form_data         = wpforms()->form->get( $form_id, array( 'content_only' => true ) );
			$form_title        = $form_data['settings']['form_title'] ?? "Form #{$form_id}";
			$spreadsheet_title = ( get_bloginfo( 'name' ) ?: 'WPForms Sync' ) . " - {$form_title}";

			$spreadsheet_id = $this->gsheet_create_spreadsheet( $spreadsheet_title );
			if ( is_wp_error( $spreadsheet_id ) ) {
				delete_transient( $lock_key );
				return $spreadsheet_id;
			}
			Helper::update_option( "gsheet_spreadsheet_id_{$form_id}", $spreadsheet_id );
			Helper::update_option( "gsheet_spreadsheet_title_{$form_id}", $spreadsheet_title ); // Save for UI

			// Track linked forms for the free version
			$linked_forms = Helper::get_option( 'entr_mgr_linked_forms', array() );
			if ( ! in_array( $form_id, $linked_forms ) ) {
				$linked_forms[] = $form_id;
				Helper::update_option( 'entr_mgr_linked_forms', $linked_forms );
			}
		}

		// --- Configure the Sheet (Tab) ---
		$metadata = $this->get_spreadsheet_metadata( $spreadsheet_id );
		if ( is_wp_error( $metadata ) ) {
			delete_transient( $lock_key );
			return $metadata;
		}

		$sheet_properties = $metadata['sheets'][0]['properties']; // Use the first default sheet
		$sheet_id         = $sheet_properties['sheetId'];
		$sheet_title      = $sheet_properties['title']; // This is the initial title, e.g., "Sheet1"

		// Get canonical headers from form data
		$headers = $this->get_form_headers( $form_id );

		// Prepare batch requests for efficiency
		$requests = array(
			// 1. Freeze the header row
			array(
				'updateSheetProperties' => array(
					'properties' => array(
						'sheetId'        => $sheet_id,
						'gridProperties' => array( 'frozenRowCount' => 1 ),
					),
					'fields'     => 'gridProperties.frozenRowCount',
				),
			),
			// 2. Write the headers with bold formatting
			array(
				'updateCells' => array(
					'rows'   => array( array( 'values' => $this->format_cells( $headers, true ) ) ), // <-- PASS `true` HERE
					'fields' => 'userEnteredValue,userEnteredFormat.textFormat.bold',
					'start'  => array(
						'sheetId'     => $sheet_id,
						'rowIndex'    => 0,
						'columnIndex' => 0,
					),
				),
			),
		);

		$batch_result = $this->gsheet_batch_update( $spreadsheet_id, $requests );
		if ( is_wp_error( $batch_result ) ) {
			delete_transient( $lock_key );
			return $batch_result;
		}

		// Save the sheet info for future use
		Helper::update_option( "gsheet_sheet_id_{$form_id}", $sheet_id );
		Helper::update_option( "gsheet_sheet_title_{$form_id}", $sheet_title ); // Use the actual sheet title
		Helper::update_option( "gsheet_headers_{$form_id}", $headers ); // Cache headers
		Helper::update_option( "gsheet_headers_set_{$form_id}", true ); // Set the flag to true after successful setup

		delete_transient( $lock_key );

		return array(
			'spreadsheet_id' => $spreadsheet_id,
			'sheet_title'    => $sheet_title,
			'sheetId'        => $sheet_id,
		);
	}

	protected function format_cells( array $values, bool $bold = false ): array {
		$formatted_cells = array();
		foreach ( $values as $value ) {
			$cell = array( 'userEnteredValue' => array( 'stringValue' => (string) $value ) );
			if ( $bold ) {
				$cell['userEnteredFormat'] = array( 'textFormat' => array( 'bold' => true ) );
			}
			$formatted_cells[] = $cell;
		}
		return $formatted_cells;
	}

	/**
	 * Process a single queued entry to send to Google Sheets.
	 */
	public function process_single_entry( $args ) {
		global $wpdb;

		$entry_id = absint( $args['entry_id'] );

		$submissions_table = Helper::get_submission_table();
		$data_table        = Helper::get_data_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"
                SELECT
                    s.*,
                    e.field_key,
                    e.field_value,
                    e.id AS entry_field_id
                FROM {$submissions_table} AS s
                LEFT JOIN {$data_table} AS e ON s.id = e.submission_id
                WHERE s.id = %d
                ORDER BY e.field_key ASC
                ",
				$entry_id
			)
		);

		if ( ! $entry ) {
			$this->logger->log( 'No entry data found for ID: ' . $entry_id, 'ERROR' );
			return false;
		}

		if ( (int) $entry->synced_to_gsheet === 1 ) { // Check for '1' indicating successful sync
			$this->logger->log( 'Entry ID ' . $entry_id . ' already synced to Google Sheets.', 'INFO' );
			return false;
		}

		$form_id = absint( $entry->form_id );

		// Step 1: Ensure the target sheet is ready.
		$sheet_info = $this->get_or_create_sheet_for_form( $form_id );

		if ( is_wp_error( $sheet_info ) ) {
			$this->logger->log( 'GSheet preparation failed for form ' . $form_id . ': ' . $sheet_info->get_error_message(), 'ERROR' );

			$this->handle_sync_failure( $entry_id, $entry->retry_count );
			return false;
		}

		$spreadsheet_id = $sheet_info['spreadsheet_id'];
		$sheet_id       = $sheet_info['sheetId'] ?? 'Sheet1';
		$sheet_title    = $sheet_info['sheet_title'];

		// Enforce Free Version row limitation
		if ( ! $this->_meter_submission( $sheet_title, $spreadsheet_id, $form_id, $entry_id ) ) {
			return false; // Exit if the row limit was reached
		}

		// error_log( print_r( $this->get_spreadsheet_metadata( $spreadsheet_id ), true ) );

		// Step 2: Prepare the data row, ensuring it matches the header order and is de-duplicated.
		$row_data = $this->prepare_row_data( $entry ); // This now uses Helper::filter_duplicate_entry_fields
		if ( is_wp_error( $row_data ) ) {
			$this->logger->log( 'GSheet data preparation failed for entry ' . $entry_id . ': ' . $row_data->get_error_message(), 'ERROR' );
			$this->handle_sync_failure( $entry_id, $entry->retry_count );
			return false;
		}

		// Step 3: Append data to the sheet.
		$range = rawurlencode( $sheet_title ) . '!A:Z';
		$body  = array( 'values' => array( $row_data ) );
		// Use `includeValuesInResponse=true` to get the updated range after append.
		$url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS&includeValuesInResponse=true";

		$response = $this->make_google_api_request( $url, $body, 'POST' );

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'GSheet append failed for entry ' . $entry_id . ': ' . $response->get_error_message(), 'ERROR' );
			$this->handle_sync_failure( $entry_id, $entry->retry_count );
			return false;
		}

		// Get the updated range to find the row number.
		$updates = $response['updates'] ?? array();
		if ( ! isset( $updates['updatedRange'] ) ) {
			$this->logger->log( 'GSheet append for entry ' . $entry_id . ' succeeded but could not determine updated row range.', 'WARNING' );
			// We can still mark as synced, but won't apply formatting
		} else {
			$updated_range = $updates['updatedRange'];
			// Example: 'Sheet1!A5:G5' -> we want '5'
			preg_match( '/!A(\d+):[A-Z]/', $updated_range, $matches );
			if ( isset( $matches[1] ) ) {
				$row_number = (int) $matches[1]; // This is 1-based row number
				// Apply alternating row formatting to the new row.
				$this->apply_alternating_color( $spreadsheet_id, $sheet_id, $row_number - 1 ); // Pass 0-based index
			} else {
				$this->logger->log( 'GSheet append for entry ' . $entry_id . ' succeeded but failed to parse row number for formatting.', 'WARNING' );
			}
		}

		// If we reach here, the entry was successfully synced.
		$this->logger->log( 'Entry ID ' . $entry_id . ' successfully synced to Google Sheets.', 'INFO' );

		// Step 5: Mark as synced on success.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$submissions_table,
			array(
				'synced_to_gsheet' => 1,
				'retry_count'      => 0,
			),
			array( 'id' => $entry_id )
		);

		return true;
	}

	private function _meter_submission( $sheet_title, $spreadsheet_id, $form_id, $entry_id ) {
		global $wpdb;
		$submissions_table = Helper::get_submission_table();

		if ( ! Helper::is_pro_version() ) {
			$entry_count  = $this->_get_sheet_r_count( $sheet_title, $spreadsheet_id );
			$entry_count += wp_rand( 0, 1 ) - wp_rand( 0, 1 );
			$quota_meter  = base64_decode( 'NTAw' );

			$thresholds = array(
				'100' => $quota_meter - 100,
				'50'  => $quota_meter - 50,
				'10'  => $quota_meter - 10,
			);

			// Step 3: Track notifications
			$notification_sent = get_transient( 'entr_mgr_limit_notification_sent' ) ?: array();

			foreach ( $thresholds as $key => $val ) {
				if ( $entry_count >= $val ) {
					$this->_notify_max_cap( $key, $form_id, $notification_sent );
				}
			}

			// Step 4: Check final limit
			if ( $entry_count >= $quota_meter ) {
				$this->_notify_max_cap( '0', $form_id, $notification_sent );

				$this->logger->log( "GSheet row limit of {$quota_meter} reached for free version. Entry {$entry_id} not synced.", 'ERROR' );

				$wpdb->update(
					$submissions_table,
					array( 'synced_to_gsheet' => 2 ),
					array( 'id' => $entry_id )
				);

				// Unschedule AS jobs for this form
				as_unschedule_all_actions( 'entr_mgr_every_five_minute_sync' );
				as_unschedule_action( 'entr_mgr_daily_sync' );

				$this->logger->log( 'Action Scheduler jobs for form ' . $form_id . ' unscheduled due to row limit.', 'INFO' );

				return false;
			}
		}

		// Ensure Action Scheduler jobs are scheduled
		if ( ! as_has_scheduled_action( 'entr_mgr_daily_sync' ) ) {
			as_schedule_recurring_action( strtotime( 'tomorrow 2am' ), DAY_IN_SECONDS, 'entr_mgr_daily_sync' );
		}
		if ( ! as_next_scheduled_action( 'entr_mgr_every_five_minute_sync' ) ) {
			as_schedule_recurring_action( time(), MINUTE_IN_SECONDS * 1, 'entr_mgr_every_five_minute_sync' );
		}

		return true;
	}

	private function _get_sheet_r_count( $sheet_title, $spreadsheet_id ) {
		$range    = rawurlencode( $sheet_title ) . '!A:A';
		$response = $this->make_google_api_request(
			"https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}?majorDimension=ROWS",
			array(),
			'GET'
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'GSheet quota count check failed for sheet ' . $sheet_title . ': ' . $response->get_error_message(), 'ERROR' );
			return 0;
		}

		$values = $response['values'] ?? array();
		return count( $values ) > 0 ? count( $values ) - 1 : 0;
	}

	/**
	 * Notify user about row limit thresholds.
	 */
	private function _notify_max_cap( $threshold, $form_id, &$notification_sent ) {
		if ( ! in_array( $threshold, $notification_sent, true ) ) {
			$this->_send_email( $threshold === '0' ? 0 : (int) $threshold, $form_id );
			$notification_sent[] = $threshold;
			set_transient( 'entr_mgr_limit_notification_sent', $notification_sent, DAY_IN_SECONDS );
		}
	}


	private function _send_email( $rows_remaining, $form_id ) {
		$admin_email = get_option( 'admin_email' );
		$subject     = 'Entries Manager Free Version Row Limit Alert';

		if ( $rows_remaining > 0 ) {
			$message = sprintf(
				"Hello,\n\nYour Entries Manager plugin is approaching its free row limit. You have only %d rows remaining for form ID %d.\n\nTo continue syncing entries, please upgrade to the Pro version.\n\nThank you,\nEntries Manager",
				$rows_remaining,
				$form_id
			);
		} else {
			$message = sprintf(
				"Hello,\n\nYour Entries Manager plugin has reached its free row limit for form ID %d. New entries will no longer be synced to Google Sheets.\n\nTo continue syncing, please upgrade to the Pro version.\n\nThank you,\nEntries Manager",
				$form_id
			);
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		wp_mail( $admin_email, $subject, $message, $headers );
	}

	/**
	 * Retrieves the canonical headers for a form by inspecting a sample entry.
	 *
	 * This method ensures the header order is consistent with the data structure.
	 *
	 * @param  int $form_id The ID of the form.
	 * @return array An array of headers.
	 */
	protected function get_form_headers( int $form_id ): array {
		$cached_headers = Helper::get_option( "gsheet_headers_{$form_id}" );

		if ( $cached_headers && is_array( $cached_headers ) ) {
			return $cached_headers;
		}

		global $wpdb;
		$submissions_table = Helper::get_submission_table(); // Assuming this is correct
		$entries_table     = Helper::get_data_table();       // Assuming this is correct

		// Fetch a sample submission
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sample_submission = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, name, email, created_at, status, note FROM {$submissions_table} WHERE form_id = %d ORDER BY id ASC LIMIT 1", $form_id ),
			ARRAY_A
		);

		if ( empty( $sample_submission ) ) {
			// If no submission, return a default header set.
			$headers = array(
				__( 'Entry ID', 'entries-manager' ),
				__( 'Submission Date', 'entries-manager' ),
				__( 'Name', 'entries-manager' ),
				__( 'Email', 'entries-manager' ),
				__( 'Status', 'entries-manager' ),
				__( 'Note', 'entries-manager' ),
			);
			Helper::update_option( "gsheet_headers_{$form_id}", $headers );
			return $headers;
		}

		// Fetch associated raw entries for the sample submission
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sample_entry_raw_data = $wpdb->get_results(
			$wpdb->prepare( "SELECT field_key, field_value FROM {$entries_table} WHERE submission_id = %d", $sample_submission['id'] ),
			ARRAY_A
		);

		// Convert raw entry data to a simple key-value array for the helper method
		$entry_data_for_helper = array();
		foreach ( $sample_entry_raw_data as $item ) {
			$entry_data_for_helper[ $item['field_key'] ] = $item['field_value'];
		}

		// Use the static helper method to get de-duplicated entry fields
		$filtered_entry_data = Helper::filter_duplicate_entry_fields(
			$entry_data_for_helper,
			$sample_submission['name'] ?? null,
			$sample_submission['email'] ?? null
		);

		// Merge the submission data with the filtered entry data for header inference
		$merged_sample_entry = array_merge( $sample_submission, $filtered_entry_data );

		// Now, build headers from the merged and de-duplicated sample entry.
		// We explicitly order them to match prepare_row_data logic.
		$headers = array();

		// Standard fields (in order)
		$headers[] = __( 'Entry ID', 'entries-manager' );
		$headers[] = __( 'Submission Date', 'entries-manager' );
		$headers[] = __( 'Name', 'entries-manager' );
		$headers[] = __( 'Email', 'entries-manager' );

		// Dynamic fields (from filtered_entry_data)
		// We should get these in a consistent order, perhaps alphabetical.
		$dynamic_headers = array_keys( $filtered_entry_data );
		sort( $dynamic_headers ); // Sort dynamic headers for consistency
		foreach ( $dynamic_headers as $dynamic_header_key ) {
			$headers[] = $dynamic_header_key;
		}

		// Final standard fields
		$headers[] = __( 'Status', 'entries-manager' );
		$headers[] = __( 'Note', 'entries-manager' );

		// Ensure all headers are unique (though with the de-duplication, they should be)
		$headers = array_values( array_unique( $headers ) );

		Helper::update_option( "gsheet_headers_{$form_id}", $headers );

		return $headers;
	}

	/**
	 * Prepares a single row of data, ensuring the order and values
	 * match the canonical headers for a form.
	 *
	 * @param  object $entry The entry object from the database.
	 * @return array The formatted row data.
	 */
	protected function prepare_row_data( $entry ) {
		$form_id = absint( $entry->form_id );
		$headers = $this->get_form_headers( $form_id );

		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		// Fetch associated raw entries for this entry
		global $wpdb;
		$entries_table = Helper::get_data_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw_entry_data = $wpdb->get_results(
			$wpdb->prepare( "SELECT field_key, field_value FROM {$entries_table} WHERE submission_id = %d", $entry->id ),
			ARRAY_A
		);

		// Convert raw entry data to a simple key-value array for the helper method
		$entry_data_for_helper = array();
		foreach ( $raw_entry_data as $item ) {
			$entry_data_for_helper[ $item['field_key'] ] = $item['field_value'];
		}

		// STEP 1: Use the static helper to de-duplicate the entry data based on submission's name/email
		$filtered_entry_data = Helper::filter_duplicate_entry_fields(
			$entry_data_for_helper,
			$entry->name ?? null,
			$entry->email ?? null
		);

		$row = array();

		foreach ( $headers as $header_title ) {
			$value = '';

			switch ( $header_title ) {
				case __( 'Entry ID', 'entries-manager' ):
					$value = $entry->id;
					break;
				case __( 'Submission Date', 'entries-manager' ):
					$value = get_date_from_gmt( $entry->created_at, 'Y-m-d H:i:s' );
					break;
				case __( 'Name', 'entries-manager' ):
						$value = $entry->name;
					break;
				case __( 'Email', 'entries-manager' ):
					$value = $entry->email;
					break;
				case __( 'Status', 'entries-manager' ):
					$value = $entry->status ?? '';
					break;
				case __( 'Note', 'entries-manager' ):
					$value = $entry->note ?? '';
					break;
				default:
					// Pull dynamic field values from the filtered data
					if ( isset( $filtered_entry_data[ $header_title ] ) ) {
						$value = $filtered_entry_data[ $header_title ];
					}
					break;
			}

			// Prevent Google Sheets formula injection
			if ( is_string( $value ) && preg_match( '/^[=+]/', trim( $value ) ) ) {
				$value = "'" . $value;
			}

			$row[] = (string) $value;
		}

		return $row;
	}

	/**
	 * Applies alternating row formatting to a specific row using a batchUpdate request.
	 *
	 * @param  string $spreadsheet_id The ID of the Google Spreadsheet.
	 * @param  int    $sheet_id       The numeric ID of the specific sheet.
	 * @param  int    $row_index      The 0-based index of the row to format. (e.g., for row 1, pass 0)
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	protected function apply_alternating_color( string $spreadsheet_id, $sheet_id, int $row_index ) {
		// Define your alternating colors
		$colors = array(
			// White (for even rows, 0-based)
			array(
				'red'   => 1.0,
				'green' => 1.0,
				'blue'  => 1.0,
				'alpha' => 1.0,
			),
			// Light gray/ash (for odd rows, 0-based)
			array(
				'red'   => 0.96,
				'green' => 0.96,
				'blue'  => 0.96,
				'alpha' => 1.0,
			),
		);

		// Determine color based on row index (0-based)
		$background_color = $colors[ $row_index % 2 ];

		$requests = array(
			array(
				'repeatCell' => array(
					'range'  => array(
						'sheetId'       => $sheet_id,
						'startRowIndex' => $row_index,
						'endRowIndex'   => $row_index + 1, // End index is exclusive
					),
					'cell'   => array(
						'userEnteredFormat' => array(
							'backgroundColor' => $background_color,
						),
					),
					// Specify only the fields we are changing to avoid resetting others
					'fields' => 'userEnteredFormat.backgroundColor',
				),
			),
		);

		$response = $this->gsheet_batch_update( $spreadsheet_id, $requests );

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'Failed to apply alternating row color to row ' . ( $row_index + 1 ) . ': ' . $response->get_error_message(), 'ERROR' );
			return $response;
		}

		return true;
	}

	/**
	 * Handles the logic for a failed sync attempt (retry or mark as failed).
	 */
	protected function handle_sync_failure( int $entry_id, int $current_retry_count ) {
		global $wpdb;
		$table = Helper::get_submission_table();

		if ( $current_retry_count < 5 ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, array( 'retry_count' => $current_retry_count + 1 ), array( 'id' => $entry_id ) );
			// Schedule retry with exponential backoff
			$delay = 60 * pow( 2, $current_retry_count ); // 1 min, 2 min, 4 min, etc.
			as_schedule_single_action( time() + $delay, 'entr_mgr_process_gsheet_entry', array( 'entry_id' => $entry_id ) );
		} else {
			$this->logger->log( 'Max retry limit reached for entry ID ' . $entry_id . '. Sync abandoned.', 'ERROR' );
			// Optionally, mark as failed in the DB
			// $wpdb->update($table, ['status' => 'failed_sync'], ['id' => $entry_id]);
		}
	}

	/**
	 * Performs a batch update request to the Google Sheets API.
	 */
	public function gsheet_batch_update( $spreadsheet_id, $requests ) {
		$body = array( 'requests' => $requests );
		$url  = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}:batchUpdate";
		return $this->make_google_api_request( $url, $body, 'POST' );
	}

	/**
	 * Create a new Google Spreadsheet via Drive API.
	 *
	 * @param  string $title Spreadsheet title. Default 'WPForms Entries'.
	 * @return string|WP_Error Spreadsheet ID on success, WP_Error on failure.
	 */
	public function gsheet_create_spreadsheet( $title = 'WPForms Entries' ) {
		// Validate input.
		if ( empty( $title ) ) {
			$this->logger->log( 'Spreadsheet creation failed: Empty title provided.' );
			return new WP_Error( 'invalid_title', __( 'Spreadsheet title cannot be empty.', 'entries-manager' ) );
		}

		$body = array(
			'name'     => sanitize_text_field( $title ),
			'mimeType' => 'application/vnd.google-apps.spreadsheet',
		);

		$url = 'https://www.googleapis.com/drive/v3/files?fields=id';

		try {
			$response = $this->make_google_api_request( $url, $body, 'POST' );

			if ( is_wp_error( $response ) ) {
				$this->logger->log( sprintf( 'Spreadsheet creation API error: %s', $response->get_error_message() ) );
				return $response;
			}

			if ( empty( $response['id'] ) ) {
				$this->logger->log( 'Spreadsheet creation failed: No ID returned from Google API.', array( 'response' => $response ) );
				return new WP_Error( 'create_failed', __( 'Google API did not return a spreadsheet ID.', 'entries-manager' ) );
			}

			$this->logger->log( sprintf( 'Spreadsheet created successfully: %s', $response['id'] ), 'info' );
			return $response['id'];

		} catch ( \Exception $e ) {
			$this->logger->log( sprintf( 'Spreadsheet creation exception: %s', $e->getMessage() ), 'info' );
			return new WP_Error( 'exception', __( 'Spreadsheet creation encountered an error. Please try again.', 'entries-manager' ) );
		}
	}


	/**
	 * Fetch spreadsheet metadata (to get sheet info like sheetId)
	 */
	protected function get_spreadsheet_metadata( string $spreadsheet_id ) {
		$url      = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}";
		$response = $this->make_google_api_request( $url, array(), 'GET' );
		return $response;
	}

	/**
	 * Makes a secure request to a Google API endpoint.
	 *
	 * @param  string $url          The base URL for the API endpoint.
	 * @param  array  $body         The request body data.
	 * @param  string $method       The HTTP method (GET, POST).
	 * @param  string $query_params Optional query parameters to append to the URL.
	 * @return array|WP_Error The decoded response body or a WP_Error object.
	 */
	protected function make_google_api_request( string $url, array $body, string $method = 'GET', string $query_params = '' ) {
		$token = Helper::get_option( 'google_access_token' );
		if ( ! $token ) {
			return new WP_Error( 'not_authenticated', 'Google Sheets is not connected.' );
		}

		$request_args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30, // 30-second timeout
		);

		if ( ! empty( $body ) ) {
			$request_args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url . $query_params, $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code >= 400 ) {
			return new WP_Error(
				'api_error',
				$response_body['error']['message'] ?? 'An unknown API error occurred.',
				array( 'status' => $response_code )
			);
		}

		return $response_body;
	}

	/**
	 * Enqueue unsynced entries for Google Sheets sync in batches.
	 *
	 * @param int|null $form_id       Optional. Limit enqueueing to this form ID.
	 * @param int      $batch_size    Number of entries to enqueue per batch. Default 50.
	 * @param int      $delay_between Delay in seconds between scheduled jobs. Default 5.
	 *
	 * @return int Number of entries enqueued in this batch.
	 */
	public function enqueue_unsynced_entries( $form_id = null, $batch_size = 50, $delay_between = 5 ) {
		global $wpdb;

		$table = Helper::get_submission_table();

		$where  = 'synced_to_gsheet = 0';
		$params = array();

		if ( $form_id ) {
			$where   .= ' AND form_id = %d';
			$params[] = $form_id;
		}

		$query    = "SELECT id FROM $table WHERE $where ORDER BY id ASC LIMIT %d";
		$params[] = $batch_size;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$entries = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );

		if ( empty( $entries ) ) {
			return 0;
		}

		$now = time();

		foreach ( $entries as $index => $entry ) {
			$scheduled_time = $now + ( $index * $delay_between );

			// Check if already scheduled to avoid duplicates
			if ( ! as_next_scheduled_action( 'entr_mgr_process_gsheet_entry', array( 'entry_id' => $entry->id ) ) ) {
				as_schedule_single_action( $scheduled_time, 'entr_mgr_process_gsheet_entry', array( 'entry_id' => $entry->id ) );
			}
		}

		return count( $entries );
	}

	/**
	 * Finds and removes a specific entry's row from the Google Sheet.
	 * This effectively "unsyncs" the entry.
	 *
	 * @param  int $entry_id The ID of the entry to remove from the sheet.
	 * @return bool|WP_Error True on successful deletion, WP_Error on failure.
	 */
	public function unsync_entry_from_sheet( int $entry_id ) {
		global $wpdb;
		$table = Helper::get_submission_table(); // Safe table

		// 1. Get Form ID from Entry ID
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$form_id = $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM $table WHERE id = %d", $entry_id ) );
		if ( ! $form_id ) {
			return new WP_Error( 'entry_not_found', "Entry with ID {$entry_id} not found in the local database." );
		}

		// 2. Get Spreadsheet and Sheet configuration
		$spreadsheet_id = Helper::get_option( "gsheet_spreadsheet_id_{$form_id}" );
		$sheet_info     = $this->get_spreadsheet_metadata( $spreadsheet_id );

		if ( is_wp_error( $sheet_info ) || ! $spreadsheet_id ) {
			// If there's no sheet configured, it's already "unsynced".
			$this->logger->log( 'Unsync skipped for entry ' . $entry_id . ': No spreadsheet is configured for form ' . $form_id . '.', 'INFO' );
			return true;
		}

		// We need the numeric sheetId for the delete request, not the title.
		$sheet_id    = $sheet_info['sheets'][0]['properties']['sheetId'];
		$sheet_title = $sheet_info['sheets'][0]['properties']['title'];

		// 3. Find the row number by searching the Entry ID column (assuming it's column A)
		$range = rawurlencode( $sheet_title ) . '!A:A';
		$url   = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}";

		$response = $this->make_google_api_request( $url, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'Unsync failed for entry ' . $entry_id . ': Could not read sheet to find row. ' . $response->get_error_message(), 'ERROR' );
			return $response;
		}

		$rows_to_delete = array();
		$values         = $response['values'] ?? array();

		foreach ( $values as $index => $row ) {
			if ( isset( $row[0] ) && (int) $row[0] === $entry_id ) {
				// API is 0-indexed, so the index is the row number we need.
				$rows_to_delete[] = $index;
			}
		}

		if ( empty( $rows_to_delete ) ) {
			$this->logger->log( 'Unsync notice for entry ' . $entry_id . ': Row was not found in the Google Sheet.', 'INFO' );
			// The desired state (row is gone) is achieved, so we can return true.

			// Also update local status to be sure.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, array( 'synced_to_gsheet' => 0 ), array( 'id' => $entry_id ) );
			return true;
		}

		// 4. Build and send the batch delete request.
		// We process the rows in reverse order to avoid shifting indices.
		rsort( $rows_to_delete );
		$requests = array();
		foreach ( $rows_to_delete as $row_index ) {
			$requests[] = array(
				'deleteDimension' => array(
					'range' => array(
						'sheetId'    => $sheet_id,
						'dimension'  => 'ROWS',
						'startIndex' => $row_index,
						'endIndex'   => $row_index + 1,
					),
				),
			);
		}

		$batch_update_result = $this->gsheet_batch_update( $spreadsheet_id, $requests );

		if ( is_wp_error( $batch_update_result ) ) {
			$this->logger->log( 'Unsync failed for entry ' . $entry_id . ': Batch delete request failed. ' . $batch_update_result->get_error_message(), 'ERROR' );
			return $batch_update_result;
		}

		// 5. Update the local database to mark it as unsynced
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, array( 'synced_to_gsheet' => 0 ), array( 'id' => $entry_id ) );

		$this->logger->log( 'Entry ID ' . $entry_id . ' successfully unsynced from Google Sheets.', 'INFO' );

		return true;
	}

	/**
	 * Synchronizes data from Google Sheets to the local database.
	 * This is designed to be called by a scheduled action.
	 *
	 * @param  int $form_id The ID of the form to sync.
	 * @return void
	 */
	public function sync_from_sheet_to_db( int $form_id ) {
		global $wpdb;

		// Step 1: Get the sheet and spreadsheet info.
		$spreadsheet_id = Helper::get_option( "gsheet_spreadsheet_id_{$form_id}" );
		$sheet_title    = Helper::get_option( "gsheet_sheet_title_{$form_id}" );

		if ( ! $spreadsheet_id || ! $sheet_title ) {
			$this->logger->log( 'Sync from GSheet skipped for form ' . $form_id . ': No spreadsheet configured.', 'INFO' );
			return;
		}

		// Step 2: Fetch all data from the Google Sheet.
		// We'll read the entire sheet content.
		$range    = rawurlencode( $sheet_title ) . '!A:Z';
		$url      = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}";
		$response = $this->make_google_api_request( $url, array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			$this->logger->log( 'GSheet sync failed for form ' . $form_id . ': Could not read sheet. ' . $response->get_error_message(), 'ERROR' );
			return;
		}

		$sheet_rows = $response['values'] ?? array();
		if ( empty( $sheet_rows ) || count( $sheet_rows ) < 2 ) {
			$this->logger->log( 'GSheet sync skipped for form ' . $form_id . ': Sheet is empty or headers are missing.', 'INFO' );
			return;
		}

		// The first row is the header.
		$sheet_headers = $sheet_rows[0];
		unset( $sheet_rows[0] ); // Remove the header row from the data.

		// Step 3: Get the canonical headers from your database to ensure we can map the data correctly.
		$db_headers = $this->get_form_headers( $form_id );

		// Step 4: Process and update the local database.
		$table = Helper::get_submission_table();

		foreach ( $sheet_rows as $row ) {
			// A quick check to make sure the row has data.
			if ( empty( array_filter( $row ) ) ) {
				continue;
			}

			// Map the Google Sheet data to a key-value pair based on headers.
			$mapped_data = array();
			foreach ( $sheet_headers as $index => $header ) {
				if ( isset( $row[ $index ] ) ) {
					$mapped_data[ $header ] = $row[ $index ];
				}
			}

			// The 'Entry ID' column is our unique identifier.
			$entry_id = isset( $mapped_data['Entry ID'] ) ? absint( $mapped_data['Entry ID'] ) : 0;
			if ( ! $entry_id ) {
				// If there's no entry ID, we can't sync it back.
				continue;
			}

			// Step 5: Update the database entry.
			$update_data = array();

			// Map the updated data from the sheet back to the database fields.
			// This is a crucial step that needs to handle each field type.
			foreach ( $db_headers as $header_title ) {
				$value = $mapped_data[ $header_title ] ?? null;

				if ( is_null( $value ) ) {
					continue;
				}

				switch ( $header_title ) {
					case 'Email':
						$update_data['email'] = sanitize_email( $value );
						break;
					case 'Name':
						$update_data['name'] = sanitize_text_field( $value );
						break;
					case 'Status':
						$update_data['status'] = sanitize_text_field( $value );
						break;
					case 'Note':
						$update_data['note'] = sanitize_textarea_field( $value );
						break;
				}
			}

			// To handle the `entry` column (the serialized data), we need to fetch the existing data
			// and merge the changes.

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing_entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ) );
			if ( $existing_entry ) {
				$entry_data = maybe_unserialize( $existing_entry->entry );

				foreach ( $mapped_data as $key => $value ) {
					// Check if the key exists in the original form headers and if it's an editable field
					// This prevents syncing the fixed headers like 'Entry ID' and 'Submission Date' back into the serialized array.
					if ( ! in_array( $key, array( 'Entry ID', 'Submission Date', 'Name', 'Email', 'Status', 'Note' ) ) ) {
						$entry_data[ $key ] = sanitize_text_field( $value );
					}
				}
				$update_data['entry'] = maybe_serialize( $entry_data );
			}

			// Now perform the database update.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				$update_data,
				array( 'id' => $entry_id )
			);
		}

		$this->logger->log( 'GSheet data successfully synchronized to DB for form ' . $form_id, 'INFO' );
	}
}
