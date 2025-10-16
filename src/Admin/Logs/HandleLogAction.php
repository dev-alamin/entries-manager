<?php

namespace Amin\FormsEntriesManager\Admin\Logs;

use Amin\FormsEntriesManager\Logger\FileLogger;
use Amin\FormsEntriesManager\Utility\FileSystem;

defined( 'ABSPATH' ) || exit;

/**
 * Handles log-related actions outside of the page rendering loop,
 * primarily using admin-post.php hooks for robustness.
 */
class HandleLogAction {

    /**
     * @var FileSystem
     */
    protected $fs;

    /**
     * @var FileLogger
     */
    protected $logger;

    const LOG_PAGE_SLUG = 'entrydashboard-entries-manager-logs';

    public function __construct() {
        $this->fs     = new FileSystem();
        $this->logger = new FileLogger();
        
        // Use admin_post hook for POST and GET actions targeting admin-post.php
        // These hooks run only when admin-post.php is hit with the 'action' query param.
        add_action( 'admin_post_entr_mgr_download_log', array( $this, 'handle_download' ) );
        add_action( 'admin_post_entr_mgr_clear_logs', array( $this, 'handle_clear' ) );
        
        // We no longer need the complicated conditional checks inside admin_init
        // as admin_post hooks only fire for their specific action.
    }
    
    // The handle_log_actions is now obsolete and removed.

    /**
     * Handles the file download request.
     * This method is triggered via admin_post_entr_mgr_download_log.
     */
    public function handle_download() {
        
        // 1. Capability check
        if ( ! current_user_can( 'can_manage_entr_mgr_entries' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to download logs.', 'entries-manager' ) );
        }

        // 2. Nonce verification and file check
        if ( ! isset( $_GET['file'], $_GET['_wpnonce'] ) ) {
             wp_die( esc_html__( 'Missing file or security token.', 'entries-manager' ) );
        }
        
        if ( check_admin_referer( 'entrydashboard-download' ) === false ) {
            wp_die( esc_html__( 'Security check failed. Invalid nonce!', 'entries-manager' ) );
        }

        $file_name = sanitize_file_name( wp_unslash( $_GET['file'] ) );
        $log_dir   = $this->logger->get_log_directory();
        $file_path = trailingslashit( $log_dir ) . $file_name;

        // Security check: ensure file exists and path is contained within the log directory
        if ( $this->fs->exists( $file_path ) && strpos( realpath( $file_path ), realpath( $log_dir ) ) === 0 ) {
            $file_content = $this->fs->read( $file_path );

            // Ensure headers are sent correctly for download
            header( 'Content-Description: File Transfer' );
            header( 'Content-Type: text/plain' );
            header( 'Content-Disposition: attachment; filename="' . esc_attr( $file_name ) . '"' );
            header( 'Expires: 0' );
            header( 'Cache-Control: must-revalidate' );
            header( 'Pragma: public' );
            header( 'Content-Length: ' . strlen( $file_content ) );

            echo $file_content; // Output raw content
            exit;
        } else {
            // Redirect with an error message.
            $redirect_url = add_query_arg(
                array(
                    'message' => __( 'File not found or invalid.', 'entries-manager' ),
                    'type'    => 'error' // Added type for visual feedback
                ),
                admin_url( 'admin.php?page' . self::LOG_PAGE_SLUG )
            );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    /**
     * Handles the log clear request.
     * This method is triggered via admin_post_entr_mgr_clear_logs.
     */
    public function handle_clear() {
        
        // 1. Capability check
        if ( ! current_user_can( 'can_manage_entr_mgr_entries' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to clear logs.', 'entries-manager' ) );
        }

        // 2. Nonce verification
        if ( check_admin_referer( 'entr_mgr_log_clear' ) === false ) { // Using the nonce key from the form
            wp_die( esc_html__( 'Security check failed. Invalid nonce!', 'entries-manager' ) );
        }

        // 3. Perform action
        $this->logger->clear_old_logs( 0 ); // Pass 0 to clear all logs.
        
        // 4. Redirect with success message
        $redirect_url = add_query_arg(
            array(
                'message' => __( 'All logs have been cleared.', 'entries-manager' ),
                'type'    => 'success' // Added type for visual feedback
            ),
            admin_url( 'admin.php?page=' . self::LOG_PAGE_SLUG )
        );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}