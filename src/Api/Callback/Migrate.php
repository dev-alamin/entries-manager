<?php

namespace Amin\FormsEntriesManager\Api\Callback;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Amin\FormsEntriesManager\Utility\Helper;

class Migrate {

	const SOURCE_TABLE    = 'wpforms_db';
	const OPTION_LAST_ID  = ENTR_MGR_PREFIX . 'migration_last_id';
	const OPTION_COMPLETE = ENTR_MGR_PREFIX . 'migration_complete';
	const BATCH_SIZE      = 500;
	const ACTION_HOOK     = ENTR_MGR_PREFIX . 'migrate_batch';
	const SCHEDULE_GROUP  = ENTR_MGR_PREFIX . 'migration';

	/**
	 * Trigger the migration process.
	 *
	 * @return array|WP_Error
	 */
	public function trigger_migration() {
		if ( ! class_exists( 'ActionScheduler' ) ) {
			return new WP_Error( 'missing_scheduler', __( 'Action Scheduler not available', 'entries-manager' ) );
		}

		// Prevent triggering if migration already running and not complete
		if ( Helper::get_option( 'migration_started_at' ) && ! Helper::get_option( self::OPTION_COMPLETE ) ) {
			return new WP_Error( 'migration_already_running', __( 'Migration is already in progress.', 'entries-manager' ) );
		}

		// Now safe to reset progress and start fresh
		Helper::update_option( self::OPTION_LAST_ID, 0 );
		Helper::delete_option( self::OPTION_COMPLETE );

		if ( ! Helper::get_option( 'migration_started_at' ) ) {
			Helper::update_option( 'migration_started_at', time() );
		}

		// Clear any pending/reserved duplicate actions
		as_unschedule_all_actions( self::ACTION_HOOK, array(), self::SCHEDULE_GROUP );

		// Schedule the first batch
		as_schedule_single_action(
			time(),
			self::ACTION_HOOK,
			array( 'batch_size' => self::BATCH_SIZE ),
			self::SCHEDULE_GROUP
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Migration started in background.', 'entries-manager' ),
				'code'    => 'entr_mgr_migration_started',
			)
		);
	}

	/**
	 * Process one batch of entries.
	 *
	 * @param  int $batch_size
	 * @return void
	 */
	public function migrate_from_wpformsdb_plugin( int $batch_size = self::BATCH_SIZE ): void {
		$logger = new \Amin\FormsEntriesManager\Logger\FileLogger();
		$logger->log( 'migrate_from_wpformsdb_plugin called, batch size: ' . $batch_size, 'INFO' );

		global $wpdb;

		$last_id           = absint( Helper::get_option( self::OPTION_LAST_ID, 0 ) );
		$source_table      = $wpdb->prefix . self::SOURCE_TABLE;
		$submissions_table = Helper::get_submission_table();
		$data_table        = Helper::get_data_table();

		if ( ! Helper::table_exists( $source_table ) ) {
			$logger->log( 'Source table missing: ' . $source_table, 'ERROR' );
			return;
		}

		if ( ! Helper::table_exists( $submissions_table ) || ! Helper::table_exists( $data_table ) ) {
			$logger->log( 'Target tables missing.', 'ERROR' );
			return;
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$source_table} WHERE form_id > %d ORDER BY form_id ASC LIMIT %d",
				$last_id,
				$batch_size
			),
			ARRAY_A
		);

		// Save total entries count to option for progress tracking
		if ( $last_id === 0 ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_entries = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$source_table}" );
			Helper::update_option( 'migration_total_entries', $total_entries );
		}

		if ( empty( $entries ) ) {
			Helper::update_option( self::OPTION_COMPLETE, true );
			return;
		}

		$new_last_id = $last_id;

		foreach ( $entries as $entry ) {
			$source_id  = intval( $entry['form_id'] );
			$form_id    = absint( $entry['form_post_id'] ?? 0 );
			$form_value = $entry['form_value'] ?? '';

			$data_array = maybe_unserialize( $form_value );
			unset( $data_array['WPFormsDB_status'] );

			// The old data is already a flattened array, so we don't need to do complex processing.
			$email = '';
			$name  = '';
			if ( is_array( $data_array ) ) {
				$data_array = array_change_key_case( $data_array, CASE_LOWER );
				$email      = isset( $data_array['email'] ) ? sanitize_email( $data_array['email'] ) : '';
				$name       = isset( $data_array['name'] ) ? sanitize_text_field( $data_array['name'] ) : '';
			}

			$form_date = sanitize_text_field( $entry['form_date'] ?? current_time( 'mysql' ) );

			// Step 1: Insert into the new submissions table.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert(
				$submissions_table,
				array(
					'form_id'     => $form_id,
					'name'        => $name,
					'email'       => $email,
					'created_at'  => $form_date,
					'status'      => 'unread',
					'is_favorite' => 0,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d' )
			);

			if ( $result !== false ) {
				$submission_id = $wpdb->insert_id;

				// Step 2: Insert individual fields into the new data table.
				if ( is_array( $data_array ) ) {
					$data_to_insert = array();
					$time           = current_time( 'mysql', 1 );

					// Exclude columns that are now in the submissions table.
					$excluded_keys = array( 'name', 'email', 'form_date' );

					foreach ( $data_array as $key => $value ) {
						if ( ! in_array( strtolower( $key ), $excluded_keys, true ) ) {
							$data_to_insert[] = $wpdb->prepare(
								'(%d, %s, %s, %s, %s)',
								$submission_id,
								sanitize_text_field( $key ),
								sanitize_text_field( $value ),
								$time,
								$time
							);
						}
					}

					if ( ! empty( $data_to_insert ) ) {
						// The result is a fully safe string like: "('1', 'key1', 'val1', 'time', 'time'), ('1', 'key2', 'val2', 'time', 'time')"
						$values = implode( ', ', $data_to_insert );

						// Since $values is composed of pre-prepared, quoted strings, this is safe.
						// The table name ($data_table) should be whitelisted/validated elsewhere.
						$wpdb->query( "INSERT INTO `{$data_table}` (`submission_id`, `field_key`, `field_value`, `created_at`, `updated_at`) VALUES {$values}" );
					}
				}

				$new_last_id = max( $new_last_id, $source_id );
			}
		}

		Helper::update_option( self::OPTION_LAST_ID, $new_last_id );

		if ( count( $entries ) === $batch_size ) {
			as_schedule_single_action(
				time() + 5,
				self::ACTION_HOOK,
				array( 'batch_size' => $batch_size ),
				self::SCHEDULE_GROUP
			);
		} else {
			Helper::update_option( self::OPTION_COMPLETE, true );
		}
	}

	public function wpformsdb_data( WP_REST_Request $request ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpforms_db';

		// Safety: check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return new WP_Error( 'table_missing', 'Source table does not exist', array( 'status' => 404 ) );
		}

		// Query counts grouped by form_post_id
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"
            SELECT form_post_id AS form_id, COUNT(*) AS entry_count
            FROM {$table}
            GROUP BY form_post_id
            ORDER BY entry_count DESC
            LIMIT 100
        ",
			ARRAY_A
		);

		return rest_ensure_response( $results );
	}

	public function get_migration_progress() {
		$total    = (int) Helper::get_option( 'migration_total_entries', 0 );
		$migrated = (int) Helper::get_option( 'migration_last_id', 0 ); // Or count rows if needed

		if ( $total === 0 ) {
			return rest_ensure_response(
				array(
					'progress' => 100,
					'complete' => true,
					'migrated' => $migrated,
					'total'    => $total,
					'eta'      => null,
				)
			);
		}

		$progress = ( $migrated / $total ) * 100;
		$progress = min( 100, round( $progress, 2 ) );

		$complete = (bool) Helper::get_option( 'migration_complete', false );

		$start = (int) Helper::get_option( 'entr_mgr_migration_started_at', 0 );
		$eta   = null;

		if ( $start > 0 && $migrated > 0 && ! $complete ) {
			$elapsed = time() - $start;
			$eta     = ( ( $total - $migrated ) / $migrated ) * $elapsed;
			$eta     = max( 0, (int) $eta ); // ensure it's not negative
		}

		return rest_ensure_response(
			array(
				'progress' => $progress,
				'complete' => $complete,
				'migrated' => $migrated,
				'total'    => $total,
				'eta'      => $eta,
			)
		);
	}
}
