<?php

namespace Amin\FormsEntriesManager\Admin;

use Amin\FormsEntriesManager\Utility\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Class Options
 *
 * Handles all admin-related functionalities including
 * menu registration, settings registration, asset enqueuing,
 * and admin UI rendering for WPForms Entries plugin.
 */
class Options {


	/**
	 * Constructor.
	 *
	 * Hooks into WordPress admin actions to initialize the admin menu,
	 * enqueue assets, register settings, and hide update notices on plugin pages.
	 */
	public function __construct() {

		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'wp_ajax_entr_mgr_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Save settings via AJAX.
	 *
	 * Handles saving plugin settings through an AJAX request.
	 * Validates nonce and updates options accordingly.
	 */
	public function save_settings() {
		check_ajax_referer( 'wp_rest' );

		Helper::update_option( 'export_limit', absint( $_POST['entr_mgr_export_limit'] ?? 100 ) );
		Helper::update_option( 'entries_per_page', absint( $_POST['entr_mgr_entries_per_page'] ?? 20 ) );

		if ( ! empty( $_POST['entr_mgr_custom_columns'] ) && is_array( $_POST['entr_mgr_custom_columns'] ) ) {
			$sanitized = array();

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			foreach ( $_POST['entr_mgr_custom_columns'] as $form_id => $fields ) {
				$sanitized[ absint( $form_id ) ] = array_map( 'sanitize_text_field', (array) $fields );
			}

			Helper::update_option( 'cusom_form_columns_settings', wp_json_encode( $sanitized ) );
		} else {
			Helper::update_option( 'cusom_form_columns_settings', array() );
		}

		wp_send_json_success( array( 'message' => 'Saved' ) );
	}

	/**
	 * Register plugin settings.
	 *
	 * Registers settings for the plugin, including OAuth credentials
	 * and new custom options for Google Sheets integration.
	 */
	public function register_settings() {
		// OAuth credentials
		register_setting(
			'entr_mgr_google_settings',
			'entr_mgr_google_sheet_tab',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// New custom options
		register_setting(
			'entr_mgr_google_settings',
			'entr_mgr_entries_per_page',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 25,
			)
		);

		register_setting(
			'entr_mgr_google_settings',
			'entr_mgr_google_sheet_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'entr_mgr_google_settings',
			'entr_mgr_google_sheet_auto_sync',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => function ( $val ) {
					return $val === '1' || $val === 1;
				},
				'default'           => true,
			)
		);
	}
}
