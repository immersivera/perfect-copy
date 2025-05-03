<?php
/**
 * Admin class for WP Content Porter.
 *
 * @package WP_Content_Porter
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class.
 */
class WP_Content_Porter_Admin {

    /**
     * Initialize the admin class.
     */
    public function __construct() {
        // Add admin menu.
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        // Register admin scripts and styles.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // Register AJAX handlers.
        add_action( 'wp_ajax_wp_content_porter_export', array( $this, 'handle_export_ajax' ) );
        add_action( 'wp_ajax_wp_content_porter_validate_import', array( $this, 'handle_validate_import_ajax' ) );
        add_action( 'wp_ajax_wp_content_porter_import', array( $this, 'handle_import_ajax' ) );
    }

    /**
     * Add admin menu items.
     */
    public function add_admin_menu() {
        // Add export page.
        add_submenu_page(
            'tools.php',
            __( 'WP Content Porter - Export', 'wp-content-porter' ),
            __( 'WP Content Porter', 'wp-content-porter' ),
            'edit_posts',
            'wp-content-porter-export',
            array( $this, 'render_export_page' )
        );

        // Add import page.
        add_submenu_page(
            'tools.php',
            __( 'WP Content Porter - Import', 'wp-content-porter' ),
            __( 'WP Content Porter Import', 'wp-content-porter' ),
            'edit_posts',
            'wp-content-porter-import',
            array( $this, 'render_import_page' )
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'tools_page_wp-content-porter-export' !== $hook && 'tools_page_wp-content-porter-import' !== $hook ) {
            return;
        }

        // Enqueue CSS.
        wp_enqueue_style(
            'wp-content-porter-admin',
            WP_CONTENT_PORTER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_CONTENT_PORTER_VERSION
        );

        // Enqueue JS.
        wp_enqueue_script(
            'wp-content-porter-admin',
            WP_CONTENT_PORTER_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            WP_CONTENT_PORTER_VERSION,
            true
        );

        // Localize script.
        wp_localize_script(
            'wp-content-porter-admin',
            'wpContentPorterAdmin',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'wp-content-porter-nonce' ),
                'i18n'      => array(
                    'exportSuccess'     => __( 'Export generated successfully!', 'wp-content-porter' ),
                    'exportError'       => __( 'Error generating export.', 'wp-content-porter' ),
                    'copySuccess'       => __( 'Export code copied to clipboard!', 'wp-content-porter' ),
                    'copyError'         => __( 'Error copying to clipboard.', 'wp-content-porter' ),
                    'validationSuccess' => __( 'JSON validated successfully!', 'wp-content-porter' ),
                    'validationError'   => __( 'Invalid JSON format or missing required fields.', 'wp-content-porter' ),
                    'importSuccess'     => __( 'Content imported successfully!', 'wp-content-porter' ),
                    'importError'       => __( 'Error importing content.', 'wp-content-porter' ),
                    'downloadingMedia'  => __( 'Downloading media files...', 'wp-content-porter' ),
                    'processingContent' => __( 'Processing content...', 'wp-content-porter' ),
                ),
            )
        );
    }

    /**
     * Render the export page.
     */
    public function render_export_page() {
        ?>
        <div class="wrap wp-content-porter-wrap">
            <h1><?php esc_html_e( 'WP Content Porter - Export', 'wp-content-porter' ); ?></h1>
            
            <div class="wp-content-porter-card">
                <h2><?php esc_html_e( 'Export Content', 'wp-content-porter' ); ?></h2>
                <p><?php esc_html_e( 'Select a post or page to export and generate a JSON code that can be imported into another WordPress site.', 'wp-content-porter' ); ?></p>
                
                <div class="wp-content-porter-form">
                    <?php wp_nonce_field( 'wp-content-porter-export', 'wp_content_porter_export_nonce' ); ?>
                    
                    <div class="wp-content-porter-form-row">
                        <label for="wp-content-porter-post-select"><?php esc_html_e( 'Select Post/Page:', 'wp-content-porter' ); ?></label>
                        <select id="wp-content-porter-post-select" name="post_id" required>
                            <option value=""><?php esc_html_e( 'Select a post or page', 'wp-content-porter' ); ?></option>
                            <?php
                            $posts = get_posts(
                                array(
                                    'post_type'      => array( 'post', 'page' ),
                                    'posts_per_page' => -1,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                )
                            );

                            foreach ( $posts as $post ) {
                                printf(
                                    '<option value="%d">%s (%s)</option>',
                                    esc_attr( $post->ID ),
                                    esc_html( $post->post_title ),
                                    esc_html( $post->post_type )
                                );
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="wp-content-porter-form-row">
                        <button id="wp-content-porter-generate-export" class="button button-primary"><?php esc_html_e( 'Generate Export Code', 'wp-content-porter' ); ?></button>
                    </div>
                </div>
                
                <div id="wp-content-porter-export-result" class="wp-content-porter-result" style="display: none;">
                    <h3><?php esc_html_e( 'Export Code', 'wp-content-porter' ); ?></h3>
                    <p><?php esc_html_e( 'Copy this code and paste it into the import field on your target WordPress site.', 'wp-content-porter' ); ?></p>
                    
                    <div class="wp-content-porter-form-row">
                        <textarea id="wp-content-porter-export-code" readonly rows="10"></textarea>
                    </div>
                    
                    <div class="wp-content-porter-form-row">
                        <button id="wp-content-porter-copy-export" class="button button-secondary"><?php esc_html_e( 'Copy to Clipboard', 'wp-content-porter' ); ?></button>
                    </div>
                </div>
                
                <div id="wp-content-porter-export-notice" class="notice" style="display: none;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the import page.
     */
    public function render_import_page() {
        ?>
        <div class="wrap wp-content-porter-wrap">
            <h1><?php esc_html_e( 'WP Content Porter - Import', 'wp-content-porter' ); ?></h1>
            
            <div class="wp-content-porter-card">
                <h2><?php esc_html_e( 'Import Content', 'wp-content-porter' ); ?></h2>
                <p><?php esc_html_e( 'Paste the export code generated by WP Content Porter and import it into this site.', 'wp-content-porter' ); ?></p>
                
                <div class="wp-content-porter-form">
                    <?php wp_nonce_field( 'wp-content-porter-import', 'wp_content_porter_import_nonce' ); ?>
                    
                    <div class="wp-content-porter-form-row">
                        <label for="wp-content-porter-import-code"><?php esc_html_e( 'Paste Export Code:', 'wp-content-porter' ); ?></label>
                        <textarea id="wp-content-porter-import-code" name="import_code" rows="10" required></textarea>
                    </div>
                    
                    <div class="wp-content-porter-form-row">
                        <button id="wp-content-porter-validate-import" class="button button-secondary"><?php esc_html_e( 'Validate', 'wp-content-porter' ); ?></button>
                    </div>
                </div>
                
                <div id="wp-content-porter-import-preview" class="wp-content-porter-preview" style="display: none;">
                    <h3><?php esc_html_e( 'Import Preview', 'wp-content-porter' ); ?></h3>
                    
                    <div class="wp-content-porter-preview-content">
                        <p><strong><?php esc_html_e( 'Title:', 'wp-content-porter' ); ?></strong> <span id="wp-content-porter-preview-title"></span></p>
                        <p><strong><?php esc_html_e( 'Type:', 'wp-content-porter' ); ?></strong> <span id="wp-content-porter-preview-type"></span></p>
                        <p><strong><?php esc_html_e( 'Media Count:', 'wp-content-porter' ); ?></strong> <span id="wp-content-porter-preview-media-count"></span></p>
                    </div>
                    
                    <div class="wp-content-porter-form-row">
                        <button id="wp-content-porter-import-now" class="button button-primary"><?php esc_html_e( 'Import Now', 'wp-content-porter' ); ?></button>
                    </div>
                </div>
                
                <div id="wp-content-porter-import-progress" class="wp-content-porter-progress" style="display: none;">
                    <h3><?php esc_html_e( 'Import Progress', 'wp-content-porter' ); ?></h3>
                    
                    <div class="wp-content-porter-progress-bar-container">
                        <div id="wp-content-porter-progress-bar" class="wp-content-porter-progress-bar"></div>
                    </div>
                    
                    <p id="wp-content-porter-progress-message"><?php esc_html_e( 'Importing...', 'wp-content-porter' ); ?></p>
                </div>
                
                <div id="wp-content-porter-import-result" class="wp-content-porter-result" style="display: none;">
                    <h3><?php esc_html_e( 'Import Complete', 'wp-content-porter' ); ?></h3>
                    
                    <p><?php esc_html_e( 'Content imported successfully as a draft!', 'wp-content-porter' ); ?></p>
                    
                    <p><a id="wp-content-porter-view-imported" href="#" class="button button-primary"><?php esc_html_e( 'Edit Imported Content', 'wp-content-porter' ); ?></a></p>
                </div>
                
                <div id="wp-content-porter-import-notice" class="notice" style="display: none;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle export AJAX request.
     */
    public function handle_export_ajax() {
        // Check nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-content-porter-nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-content-porter' ) ) );
        }

        // Check if post ID is provided.
        if ( ! isset( $_POST['post_id'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No post selected.', 'wp-content-porter' ) ) );
        }

        $post_id = intval( $_POST['post_id'] );

        // Initialize exporter.
        $exporter = new WP_Content_Porter_Exporter();
        
        // Generate export.
        $export_data = $exporter->export_post( $post_id );

        if ( is_wp_error( $export_data ) ) {
            wp_send_json_error( array( 'message' => $export_data->get_error_message() ) );
        }

        // Process JSON with JSON processor.
        $json_processor = new WP_Content_Porter_JSON_Processor();
        $json = $json_processor->encode( $export_data );

        if ( is_wp_error( $json ) ) {
            wp_send_json_error( array( 'message' => $json->get_error_message() ) );
        }

        wp_send_json_success( array( 'json' => $json ) );
    }

    /**
     * Handle validate import AJAX request.
     */
    public function handle_validate_import_ajax() {
        // Check nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-content-porter-nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-content-porter' ) ) );
        }

        // Check if import code is provided.
        if ( ! isset( $_POST['import_code'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No import code provided.', 'wp-content-porter' ) ) );
        }

        $import_code = wp_unslash( $_POST['import_code'] );

        // Process JSON with JSON processor.
        $json_processor = new WP_Content_Porter_JSON_Processor();
        $import_data = $json_processor->decode( $import_code );

        if ( is_wp_error( $import_data ) ) {
            wp_send_json_error( array( 'message' => $import_data->get_error_message() ) );
        }

        // Validate the import data.
        $validator = new WP_Content_Porter_Importer();
        $validation = $validator->validate_import_data( $import_data );

        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
        }

        // Get media count.
        $media_handler = new WP_Content_Porter_Media_Handler();
        $media_count = $media_handler->count_media_in_content( $import_data );

        wp_send_json_success(
            array(
                'title'      => $import_data['post_title'],
                'type'       => $import_data['post_type'],
                'media_count' => $media_count,
            )
        );
    }

    /**
     * Handle import AJAX request.
     */
    public function handle_import_ajax() {
        // Check nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp-content-porter-nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-content-porter' ) ) );
        }

        // Check if import code is provided.
        if ( ! isset( $_POST['import_code'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No import code provided.', 'wp-content-porter' ) ) );
        }

        $import_code = wp_unslash( $_POST['import_code'] );

        // Process JSON with JSON processor.
        $json_processor = new WP_Content_Porter_JSON_Processor();
        $import_data = $json_processor->decode( $import_code );

        if ( is_wp_error( $import_data ) ) {
            wp_send_json_error( array( 'message' => $import_data->get_error_message() ) );
        }

        // Initialize importer.
        $importer = new WP_Content_Porter_Importer();
        
        // Import the content.
        $result = $importer->import_content( $import_data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success(
            array(
                'post_id'   => $result,
                'post_url'  => get_permalink( $result ),
                'edit_url'  => get_edit_post_link( $result, 'raw' ),
            )
        );
    }
}
