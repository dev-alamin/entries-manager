<?php
/**
 * Plugin Name: EntryDashboard – Entry Manager for Forms
 * Plugin URI:  https://entriesmanager.com/
 * Description: A centralized dashboard to manage, search, and sync form submissions from WPForms, Contact Form 7, Elementor, and more. Transform your WordPress into a mini-CRM.
 * Version:     1.0.0
 * Author:      EntriesManager
 * Text Domain: entries-manager
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * Requires at least: 5.4
 * Requires PHP: 7.0
 *
 * @package     EntryDashboard
 * @author      EntriesManager
 * @copyright   EntriesManager
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 *
 * Prefix:     edb
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Define the version of the plugin.
 * 
 * This is used to manage cache busting for assets and for version control.
 * If WP_DEBUG_LOG is enabled, it uses the current timestamp; otherwise, it uses a fixed version number.
 */

if( ! defined( 'ENTR_MGR_VERSION' ) ) {
    define( 'ENTR_MGR_VERSION', WP_DEBUG_LOG ? time() : '1.0.0' );
}

/**
 * Define the path for the plugin.
 * 
 * This is used to include files and for various plugin functionalities.
 */
if( ! defined( 'ENTR_MGR_PATH' ) ) {
    define( 'ENTR_MGR_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * Define the URL for the plugin.
 * 
 * This is used to load assets and for various plugin functionalities.
 */
if( ! defined( 'ENTR_MGR_URL' ) ) {
    define( 'ENTR_MGR_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Define the assets URL for the plugin.
 * 
 * This is used to load CSS, JS, and other assets from the plugin directory.
 */
if( ! defined( 'ENTR_MGR_ASSETS_URL' ) ) {
    define( 'ENTR_MGR_ASSETS_URL', ENTR_MGR_URL . 'assets/' );
}

/**
 * Define the prefix for generic usage without i18n.
 * 
 * This is used to ensure that the plugin's prefix is consistent across the plugin.
 */
if( ! defined( 'ENTR_MGR_PREFIX' ) ) {
    define( 'ENTR_MGR_PREFIX', 'ENTR_MGR_' );
}

/**
 * Define the table name without prefix for storing WPForms entries.
 * 
 * This is used to ensure the table name is consistent across the plugin.
 * It is also used in the database handler to create and access the custom entries table.
 */
if( ! defined( 'ENTR_MGR_TABLE_NAME' ) ) {
    define( 'ENTR_MGR_TABLE_NAME', 'entr_mgr_entries_manager' );
}

/**
 * Keep track of the database schema version.
 * 
 * This is used to manage database migrations and updates.
 * It should be incremented whenever there are changes to the database schema.
 */
if( ! defined( 'ENTR_MGR_DB_VERSION' ) ) {
    define( 'ENTR_MGR_DB_VERSION', '1.0.0' );
}

/**
 * Define 3rd Party Proxy Server URL
 * 
 * This is where the proxy server of google authorization will be handled
 */
if( ! defined( 'ENTR_MGR_PROXY_BASE_URL' ) ) {
    define( 'ENTR_MGR_PROXY_BASE_URL', trailingslashit( 'https://backend.entriesmanager.com' ) );
}

if( ! defined( 'ENTR_MGR_GOOGLE_PROXY_URL' ) ) {
    define( 'ENTR_MGR_GOOGLE_PROXY_URL', 'https://backend.entriesmanager.com/oauth/init' );
}

/**
 * Define the plugin base path for use in various functionalities.
 * 
 * This is used to ensure that the plugin's base path is consistent across the plugin.
 * It is also used in the plugin's main file to ensure that the plugin is loaded correctly
 */
define( 'ENTR_MGR_PLUGIN_BASE', plugin_basename( __FILE__ ) );

define( 'ENTR_MGR_PLUGIN_BASE_FILE', __FILE__ );

use Amin\FormsEntriesManager\Plugin;

/**
 * Initialize the plugin.
 *
 * This function is called to start the plugin and set up necessary components.
 */
function entr_mgr_init() {
    return Plugin::init();
}

// Kick-off the plugin initialization
entr_mgr_init();

add_filter( 'wpcf7_verify_nonce', '__return_true' );