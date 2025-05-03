<?php
/**
 * Plugin Name: Clone & Export
 * Plugin URI: 
 * Description: A WordPress plugin designed to simplify the process of duplicating and transferring content between WordPress sites through a JSON-based export/import system.
 * Version: 1.0.0
 * Author: 
 * Author URI: 
 * Text Domain: clone-n-export
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package Clone_N_Export
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'CLONE_N_EXPORT_VERSION', '1.0.0' );
define( 'CLONE_N_EXPORT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLONE_N_EXPORT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLONE_N_EXPORT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
class Clone_N_Export {

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
        require_once CLONE_N_EXPORT_PLUGIN_DIR . 'includes/class-admin.php';
        require_once CLONE_N_EXPORT_PLUGIN_DIR . 'includes/class-exporter.php';
        require_once CLONE_N_EXPORT_PLUGIN_DIR . 'includes/class-importer.php';
        require_once CLONE_N_EXPORT_PLUGIN_DIR . 'includes/class-media-handler.php';
        require_once CLONE_N_EXPORT_PLUGIN_DIR . 'includes/class-json-processor.php';
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
        $admin = new Clone_N_Export_Admin();
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
function clone_n_export_init() {
    return Clone_N_Export::get_instance();
}

// Start the plugin.
clone_n_export_init();
