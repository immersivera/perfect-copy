<?php
/**
 * Plugin Name: WP Content Porter
 * Plugin URI: 
 * Description: A WordPress plugin designed to simplify the process of duplicating and transferring content between WordPress sites through a JSON-based export/import system.
 * Version: 1.0.0
 * Author: 
 * Author URI: 
 * Text Domain: wp-content-porter
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package WP_Content_Porter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'WP_CONTENT_PORTER_VERSION', '1.0.0' );
define( 'WP_CONTENT_PORTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_CONTENT_PORTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_CONTENT_PORTER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
class WP_Content_Porter {

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
        require_once WP_CONTENT_PORTER_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WP_CONTENT_PORTER_PLUGIN_DIR . 'includes/class-exporter.php';
        require_once WP_CONTENT_PORTER_PLUGIN_DIR . 'includes/class-importer.php';
        require_once WP_CONTENT_PORTER_PLUGIN_DIR . 'includes/class-media-handler.php';
        require_once WP_CONTENT_PORTER_PLUGIN_DIR . 'includes/class-json-processor.php';
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
        $admin = new WP_Content_Porter_Admin();
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
function wp_content_porter_init() {
    return WP_Content_Porter::get_instance();
}

// Start the plugin.
wp_content_porter_init();
