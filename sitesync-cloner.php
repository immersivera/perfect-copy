<?php
/**
 * Plugin Name: SiteSync Cloner
 * Plugin URI: https://example.com/sitesync-cloner
 * Description: A WordPress plugin designed to simplify the process of duplicating and transferring content between WordPress sites through a JSON-based export/import system.
 * Version: 1.0.0
 * Author: SiteSync Team
 * Author URI: https://example.com
 * Text Domain: sitesync-cloner
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Tested up to: 6.4
 *
 * @package SiteSync_Cloner
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'SITESYNC_CLONER_VERSION', '1.0.0' );
define( 'SITESYNC_CLONER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SITESYNC_CLONER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SITESYNC_CLONER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
class SiteSync_Cloner {

    /**
     * Instance of this class.
     *
     * @var object
     */
    private static $instance;

    /**
     * Initialize the plugin.
     */
    public function __construct() {
        // Load plugin dependencies.
        $this->load_dependencies();

        // Initialize hooks.
        $this->init_hooks();
    }

    /**
     * Get instance of this class.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load plugin dependencies.
     */
    private function load_dependencies() {
        // Include required files.
        require_once SITESYNC_CLONER_PLUGIN_DIR . 'includes/class-admin.php';
        require_once SITESYNC_CLONER_PLUGIN_DIR . 'includes/class-exporter.php';
        require_once SITESYNC_CLONER_PLUGIN_DIR . 'includes/class-importer.php';
        require_once SITESYNC_CLONER_PLUGIN_DIR . 'includes/class-media-handler.php';
        require_once SITESYNC_CLONER_PLUGIN_DIR . 'includes/class-json-processor.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Register activation hook.
        register_activation_hook( __FILE__, array( $this, 'activate' ) );

        // Register deactivation hook.
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Initialize classes.
        $admin = new SiteSync_Cloner_Admin();
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Activation code if needed.
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Deactivation code if needed.
        flush_rewrite_rules();
    }
}

// Initialize the plugin.
function sitesync_cloner_init() {
    return SiteSync_Cloner::get_instance();
}

// Start the plugin.
sitesync_cloner_init();
