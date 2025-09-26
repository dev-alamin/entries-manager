<?php
/**
 * Plugin Name: EntryDashboard – Forms Entries Manager
 * Plugin URI:  https://entriesmanager.com/
 * Description: A centralized dashboard to manage, search, and sync form submissions from WPForms, Contact Form 7, Elementor, and more. Transform your WordPress into a mini-CRM.
 * Version:     1.0.0
 * Author:      EntriesManager
 * Author URI:  https://entriesmanager.com/
 * Text Domain: entrydashboard
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

if( ! defined( 'FEM_VERSION' ) ) {
    define( 'FEM_VERSION', WP_DEBUG_LOG ? time() : '1.0.0' );
}

/**
 * Define the path for the plugin.
 * 
 * This is used to include files and for various plugin functionalities.
 */
if( ! defined( 'FEM_PATH' ) ) {
    define( 'FEM_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * Define the URL for the plugin.
 * 
 * This is used to load assets and for various plugin functionalities.
 */
if( ! defined( 'FEM_URL' ) ) {
    define( 'FEM_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Define the assets URL for the plugin.
 * 
 * This is used to load CSS, JS, and other assets from the plugin directory.
 */
if( ! defined( 'FEM_ASSETS_URL' ) ) {
    define( 'FEM_ASSETS_URL', FEM_URL . 'assets/' );
}

/**
 * Define the prefix for generic usage without i18n.
 * 
 * This is used to ensure that the plugin's prefix is consistent across the plugin.
 */
if( ! defined( 'FEM_PREFIX' ) ) {
    define( 'FEM_PREFIX', 'FEM_' );
}

/**
 * Define the table name without prefix for storing WPForms entries.
 * 
 * This is used to ensure the table name is consistent across the plugin.
 * It is also used in the database handler to create and access the custom entries table.
 */
if( ! defined( 'FEM_TABLE_NAME' ) ) {
    define( 'FEM_TABLE_NAME', 'fem_entries_manager' );
}

/**
 * Keep track of the database schema version.
 * 
 * This is used to manage database migrations and updates.
 * It should be incremented whenever there are changes to the database schema.
 */
if( ! defined( 'FEM_DB_VERSION' ) ) {
    define( 'FEM_DB_VERSION', '1.0.0' );
}

/**
 * Define 3rd Party Proxy Server URL
 * 
 * This is where the proxy server of google authorization will be handled
 */
if( ! defined( 'FEM_PROXY_BASE_URL' ) ) {
    define( 'FEM_PROXY_BASE_URL', trailingslashit( 'https://backend.entriesmanager.com' ) );
}

if( ! defined( 'FEM_GOOGLE_PROXY_URL' ) ) {
    define( 'FEM_GOOGLE_PROXY_URL', 'https://backend.entriesmanager.com/oauth/init' );
}

/**
 * Define the plugin base path for use in various functionalities.
 * 
 * This is used to ensure that the plugin's base path is consistent across the plugin.
 * It is also used in the plugin's main file to ensure that the plugin is loaded correctly
 */
define( 'FEM_PLUGIN_BASE', plugin_basename( __FILE__ ) );

define( 'FEM_PLUGIN_BASE_FILE', __FILE__ );

use Amin\FormsEntriesManager\Plugin;

/**
 * Initialize the plugin.
 *
 * This function is called to start the plugin and set up necessary components.
 */
function fem_init() {
    return Plugin::init();
}

// Kick-off the plugin initialization
fem_init();

add_filter( 'wpcf7_verify_nonce', '__return_true' );