<?php

namespace Amin\FormsEntriesManager\Scheduler\Actions;

defined( 'ABSPATH' ) || exit;

use Amin\FormsEntriesManager\GoogleSheet\Send_Data;
use Amin\FormsEntriesManager\Utility\Helper;

class Sync_Google_Sheet_Action {



	protected $send_data;

	public function __construct( Send_Data $send_data ) {
		$this->send_data = $send_data;

		// Corrected: Direct hook to the class method is the cleaner and intended way.
		// add_action('entr_mgr_process_gsheet_entry', [$this->send_data, 'process_single_entry']);
		add_action(
			'entr_mgr_process_gsheet_entry',
			function ( $entry_id ) {
				$this->send_data->process_single_entry( array( 'entry_id' => $entry_id ) );
			}
		);

		// Hook the task.
		add_action( 'entr_mgr_every_five_minute_sync', array( $this->send_data, 'enqueue_unsynced_entries' ) );

        // Keep alive google token
        add_action(
            'entr_mgr_refresh_google_token', 
            array( 
                '\\Amin\\FormsEntriesManager\\Utility\\Helper',
                'refresh_access_token_proactively' 
            ) 
        );
	}
}
