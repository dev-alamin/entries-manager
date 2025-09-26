<?php
/**
 * Submit_Entry Class
 *
 * Captures and saves WPForms form entries into a custom database table
 * for further processing, display, or external integration.
 *
 * This class hooks into the `wpforms_process_entry_save` action to extract
 * submitted form data, serialize it, and insert it into a custom table managed
 * by the plugin.
 *
 * @package    Save_WPForms_Entries
 * @subpackage Entry_Storage
 * @author     Al Amin
 * @since      1.0.0
 */
namespace Amin\FormsEntriesManager\Core;

defined( 'ABSPATH' ) || exit;

use Amin\FormsEntriesManager\Utility\Helper;
use Amin\FormsEntriesManager\GoogleSheet\Send_Data;
use WPCF7_Submission;
use Amin\FormsEntriesManager\Logger\FileLogger;
use WPCF7_ContactForm;
use Amin\FormsEntriesManager\Core\DB_Schema;
use Amin\FormsEntriesManager\Utility\FileHandler;

/**
 * Class Submit_Entry
 *
 * Handles saving form entries to custom database tables.
 */
class Submit_Entry {
	protected $logger;

	public function __construct() {
		$this->logger = new FileLogger( 'submit_entry.log' );
		// WPForms Hook
		add_action( 'wpforms_process_entry_save', array( $this, 'save_entry_from_wpforms' ), 10, 3 );
		// Contact Form 7 Hook
		if ( class_exists( 'WPCF7_Submission' ) ) {
			add_action( 'wpcf7_before_send_mail', array( $this, 'save_entry_from_cf7' ), 10, 1 );
		}

		// Elementor Forms Hook
		add_action( 'elementor_pro/forms/new_record', array( $this, 'save_entry_from_elementor' ), 10, 2 );
	}

	/**
	 * Handles WPForms entries.
	 *
	 * @param array $fields The fields submitted in the form.
	 * @param array $entry The entry data from WPForms.
	 * @param int   $form_id The ID of the form being submitted.
	 */
	public function save_entry_from_wpforms( $fields, $entry, $form_id ) {
		global $wpdb;

		$submissions_table = DB_Schema::submissions_table();
		$entries_table     = DB_Schema::entries_table();
		$name              = '';
		$email             = '';

		// Extract name and email for the submissions table.
		foreach ( $fields as $field_id => $field_data ) {
			if ( ! empty( $field_data['type'] ) ) {
				if ( $field_data['type'] === 'name' ) {
					$first = $field_data['first'] ?? '';
					$last  = $field_data['last'] ?? '';
					$name  = trim( $first . ' ' . $last );
				} elseif ( $field_data['type'] === 'email' ) {
					$email = $field_data['value'] ?? '';
				}
			}
		}

		// Insert into the submissions table first.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$submissions_table,
			array(
				'form_id'    => absint( $form_id ),
				'name'       => sanitize_text_field( $name ),
				'email'      => sanitize_email( $email ),
				'status'     => 'unread',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		$submission_id = $wpdb->insert_id;

		if ( ! $submission_id ) {
			$this->logger->log( 'Failed to insert into submissions table.', 'error' );
			return;
		}

		// Insert individual fields into the entries table.
		foreach ( $fields as $field_id => $field_data ) {
			$field_key = isset( $field_data['name'] ) ? $field_data['name'] : 'field_' . $field_id;

			// Check if the field is a file upload.
			if ( $field_data['type'] === 'file-upload' && ! empty( $field_data['value'] ) ) {
				$uploaded_files = maybe_unserialize( $field_data['value'] );

				// Handle multiple files if they exist.
				if ( is_array( $uploaded_files ) ) {
					foreach ( $uploaded_files as $file ) {
						// Save filename entry.
						$wpdb->insert(
							$entries_table,
							array(
								'submission_id' => $submission_id,
								'field_key'     => $field_key,
								'field_value'   => 'files: ' . sanitize_file_name( basename( $file ) ),
								'created_at'    => current_time( 'mysql' ),
							),
							array( '%d', '%s', '%s', '%s' )
						);

						// Save URL entry.
						$wpdb->insert(
							$entries_table,
							array(
								'submission_id' => $submission_id,
								'field_key'     => $field_key . '_link',
								'field_value'   => esc_url_raw( $file ),
								'created_at'    => current_time( 'mysql' ),
							),
							array( '%d', '%s', '%s', '%s' )
						);
					}
				}
			} else {
				// It's a regular field, save it normally.
				$field_value = is_array( $field_data['value'] ) ? implode( ', ', array_map( 'sanitize_text_field', $field_data['value'] ) ) : sanitize_text_field( $field_data['value'] );

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$entries_table,
					array(
						'submission_id' => $submission_id,
						'field_key'     => $field_key,
						'field_value'   => $field_value,
						'created_at'    => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s' )
				);
			}
		}

		// Send data to Google Sheets if enabled.
		$send_data = new Send_Data();
		$send_data->process_single_entry( array( 'entry_id' => $submission_id ) );

		// Invalidate cached form fields and forms list.
		Helper::delete_option( 'forms_cache_' );
	}

	/**
	 * Handles CF7 entries.
	 *
	 * @param WPCF7_ContactForm $contact_form The CF7 form instance.
	 */
	public function save_entry_from_cf7( WPCF7_ContactForm $contact_form ) {
		global $wpdb;

		$submissions_table = DB_Schema::submissions_table();
		$entries_table     = DB_Schema::entries_table();
		$submission        = WPCF7_Submission::get_instance();

		if ( ! $submission ) {
			$this->logger->log( 'No submission instance found.', 'error' );
			return;
		}

		$posted_data = $submission->get_posted_data();

		// Security check.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'wp_rest' ) ) {
			$this->logger->log( 'Invalid nonce in CF7 submission.', 'error' );
			return;
		}

		$form_id = absint( $contact_form->id() );
		$name    = '';
		$email   = '';

		// Get the list of file fields so we can exclude them from the main loop.
		$uploaded_files   = $submission->uploaded_files();
		$file_field_names = array_keys( $uploaded_files );

		// Extract name and email for the submissions table.
		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( 'text' === $tag->basetype && str_contains( $tag->name, 'name' ) ) {
				$name = ! empty( $posted_data[ $tag->name ] ) ? sanitize_text_field( $posted_data[ $tag->name ] ) : '';
			}
			if ( 'email' === $tag->basetype ) {
				$email = ! empty( $posted_data[ $tag->name ] ) ? sanitize_email( $posted_data[ $tag->name ] ) : '';
			}
		}

		// Insert into the submissions table first.
		$wpdb->insert(
			$submissions_table,
			array(
				'form_id'    => $form_id,
				'name'       => $name,
				'email'      => $email,
				'status'     => 'unread',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		$submission_id = $wpdb->insert_id;

		if ( ! $submission_id ) {
			$this->logger->log( 'Failed to insert into submissions table.', 'error' );
			return;
		}

		// Insert individual fields into the entries table.
		foreach ( $posted_data as $key => $value ) {
			// Exclude system fields and file fields from this loop.
			if ( strpos( $key, '_wpcf7' ) === false && $key !== '_wpnonce' && ! in_array( $key, $file_field_names ) ) {
				$field_value = is_array( $value ) ? implode( ', ', array_map( 'sanitize_text_field', $value ) ) : sanitize_text_field( $value );

				$wpdb->insert(
					$entries_table,
					array(
						'submission_id' => $submission_id,
						'field_key'     => $key,
						'field_value'   => $field_value,
						'created_at'    => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s' )
				);
			}
		}

		// Handle file uploads by calling the dedicated method.
		$this->handle_uploaded_files( $submission_id, $uploaded_files, $entries_table, $wpdb );

				// Send data to Google Sheets if enabled.
		$send_data = new Send_Data();
		$send_data->process_single_entry( array( 'entry_id' => $submission_id ) );

		// Invalidate cached form fields and forms list.
		Helper::delete_option( 'forms_cache_' );
	}

	/**
	 * Handles the saving and processing of uploaded files.
	 *
	 * @param int    $submission_id The ID of the submission.
	 * @param array  $uploaded_files The array of uploaded files from WPCF7_Submission.
	 * @param string $entries_table The name of the entries table.
	 * @param object $wpdb The WordPress database object.
	 */
	private function handle_uploaded_files( $submission_id, $uploaded_files, $entries_table, $wpdb ) {
		if ( empty( $uploaded_files ) ) {
			return;
		}

		// Get the base upload directory URL for creating the final link.
		$upload_dir      = wp_upload_dir();
		$private_dir_url = trailingslashit( $upload_dir['baseurl'] ) . 'fem-cf7-uploads';

		foreach ( $uploaded_files as $field_name => $file_paths ) {
			// The FileHandler class should return just the filename(s).
			$file_handler = new FileHandler( $this->logger );
			$file_list    = $file_handler->process_files( $file_paths );

			if ( ! empty( $file_list ) ) {
				// For simplicity, we'll handle the first file in the list.
				// You may need to adjust for multiple file uploads.
				$filename = reset( $file_list );
				$file_url = trailingslashit( $private_dir_url ) . $filename;

				// Insert the filename into the database with the "files:" prefix.
				$wpdb->insert(
					$entries_table,
					array(
						'submission_id' => $submission_id,
						'field_key'     => $field_name,
						'field_value'   => 'files: ' . $filename,
						'created_at'    => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s' )
				);

				// Now, insert the file URL as a separate, new entry.
				$wpdb->insert(
					$entries_table,
					array(
						'submission_id' => $submission_id,
						'field_key'     => $field_name . '_link', // Use a new key, like "file-623_link"
						'field_value'   => esc_url_raw( $file_url ),
						'created_at'    => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s' )
				);
			}
		}
	}

	/**
	 * Handles Elementor Forms entries.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record The form record instance.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $handler The Ajax handler instance.
	 */
	public function save_entry_from_elementor( $record, $handler ) {
		global $wpdb;

		$submissions_table = Helper::get_submission_table();
		$entries_table     = Helper::get_data_table();
		$name              = '';
		$email             = '';
		$form_id           = $record->get_form_settings( 'id' );
		$raw_fields        = $record->get_formatted_data( false );

		// Extract name and email for the submissions table.
		foreach ( $raw_fields as $field ) {
			if ( str_contains( $field['type'], 'text' ) && str_contains( strtolower( $field['label'] ), 'name' ) ) {
				$name = $field['value'] ?? '';
			} elseif ( $field['type'] === 'email' ) {
				$email = $field['value'] ?? '';
			}
		}

		// Insert into the submissions table first.
		$wpdb->insert(
			$submissions_table,
			array(
				'form_id'    => absint( $form_id ),
				'name'       => sanitize_text_field( $name ),
				'email'      => sanitize_email( $email ),
				'status'     => 'unread',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		$submission_id = $wpdb->insert_id;

		if ( ! $submission_id ) {
			$this->logger->log( 'Failed to insert into submissions table.', 'error' );
			return;
		}

		// Insert individual fields into the entries table.
		foreach ( $raw_fields as $field ) {
			$field_key   = $field['id'];
			$field_type  = $field['type'];
			$field_value = $field['value'];

			// Check for file uploads.
			if ( $field_type === 'upload' && ! empty( $field_value ) ) {
				// Elementor's upload value is a link, but can be a comma-separated string for multiple files.
				$uploaded_files = explode( ', ', $field_value );

				foreach ( $uploaded_files as $file_url ) {
					$filename = basename( wp_parse_url( $file_url, PHP_URL_PATH ) );

					// Save filename entry.
					$wpdb->insert(
						$entries_table,
						array(
							'submission_id' => $submission_id,
							'field_key'     => $field_key,
							'field_value'   => 'files: ' . sanitize_file_name( $filename ),
							'created_at'    => current_time( 'mysql' ),
						),
						array( '%d', '%s', '%s', '%s' )
					);

					// Save URL entry.
					$wpdb->insert(
						$entries_table,
						array(
							'submission_id' => $submission_id,
							'field_key'     => $field_key . '_link',
							'field_value'   => esc_url_raw( $file_url ),
							'created_at'    => current_time( 'mysql' ),
						),
						array( '%d', '%s', '%s', '%s' )
					);
				}
			} else {
				// It's a regular field, save it normally.
				$sanitized_value = is_array( $field_value ) ? implode( ', ', array_map( 'sanitize_text_field', $field_value ) ) : sanitize_text_field( $field_value );

				$wpdb->insert(
					$entries_table,
					array(
						'submission_id' => $submission_id,
						'field_key'     => $field_key,
						'field_value'   => $sanitized_value,
						'created_at'    => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s' )
				);
			}
		}

		// Send data to Google Sheets.
		$send_data = new Send_Data();
		$send_data->process_single_entry( array( 'entry_id' => $submission_id ) );

		// Invalidate caches.
		Helper::delete_option( 'forms_cache_' );
	}
}
