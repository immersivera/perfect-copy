<?php
/**
 * Admin class for Clone & Export.
 *
 * @package Clone_N_Export
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class.
 */
class Clone_N_Export_Admin {

    /**
     * Initialize the admin class.
     */
    public function __construct() {
        // Add admin menu.
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        // Register admin scripts and styles.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // Register AJAX handlers.
        add_action( 'wp_ajax_clone_n_export_export', array( $this, 'handle_export_ajax' ) );
        add_action( 'wp_ajax_clone_n_export_validate_import', array( $this, 'handle_validate_import_ajax' ) );
        add_action( 'wp_ajax_clone_n_export_import', array( $this, 'handle_import_ajax' ) );
    }

    /**
     * Add admin menu items.
     */
    public function add_admin_menu() {
        // Add export page.
        add_submenu_page(
            'tools.php',
            __( 'Clone & Export - Export', 'clone-n-export' ),
            __( 'Clone & Export', 'clone-n-export' ),
            'edit_posts',
            'clone-n-export-export',
            array( $this, 'render_export_page' )
        );

        // Add import page.
        add_submenu_page(
            'tools.php',
            __( 'Clone & Export - Import', 'clone-n-export' ),
            __( 'Clone & Export Import', 'clone-n-export' ),
            'edit_posts',
            'clone-n-export-import',
            array( $this, 'render_import_page' )
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'tools_page_clone-n-export-export' !== $hook && 'tools_page_clone-n-export-import' !== $hook ) {
            return;
        }

        // Enqueue CSS.
        wp_enqueue_style(
            'clone-n-export-admin',
            CLONE_N_EXPORT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CLONE_N_EXPORT_VERSION
        );

        // Enqueue JS.
        wp_enqueue_script(
            'clone-n-export-admin',
            CLONE_N_EXPORT_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            CLONE_N_EXPORT_VERSION,
            true
        );

        // Localize script.
        wp_localize_script(
            'clone-n-export-admin',
            'cloneNExportAdmin',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'clone-n-export-nonce' ),
                'i18n'      => array(
                    'exportSuccess'     => __( 'Export generated successfully!', 'clone-n-export' ),
                    'exportError'       => __( 'Error generating export.', 'clone-n-export' ),
                    'copySuccess'       => __( 'Export code copied to clipboard!', 'clone-n-export' ),
                    'copyError'         => __( 'Error copying to clipboard.', 'clone-n-export' ),
                    'validationSuccess' => __( 'JSON validated successfully!', 'clone-n-export' ),
                    'validationError'   => __( 'Invalid JSON format or missing required fields.', 'clone-n-export' ),
                    'importSuccess'     => __( 'Content imported successfully!', 'clone-n-export' ),
                    'importError'       => __( 'Error importing content.', 'clone-n-export' ),
                    'downloadingMedia'  => __( 'Downloading media files...', 'clone-n-export' ),
                    'processingContent' => __( 'Processing content...', 'clone-n-export' ),
                ),
            )
        );
    }

    /**
     * Render the export page.
     */
    public function render_export_page() {
        ?>
        <div class="wrap clone-n-export-wrap">
            <h1><?php esc_html_e( 'Clone & Export - Export', 'clone-n-export' ); ?></h1>
            
            <div class="clone-n-export-card">
                <h2><?php esc_html_e( 'Export Content', 'clone-n-export' ); ?></h2>
                <p><?php esc_html_e( 'Select a post or page to export and generate a JSON code that can be imported into another WordPress site.', 'clone-n-export' ); ?></p>
                
                <div class="wp-content-porter-form">
                    <?php wp_nonce_field( 'clone-n-export-export', 'clone_n_export_export_nonce' ); ?>
                    
                    <div class="clone-n-export-form-row">
                        <label for="clone-n-export-post-select"><?php esc_html_e( 'Select Post/Page:', 'clone-n-export' ); ?></label>
                        <select id="clone-n-export-post-select" name="post_id" required>
                            <option value=""><?php esc_html_e( 'Select a post or page', 'clone-n-export' ); ?></option>
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
                    
                    <div class="clone-n-export-form-row">
                        <button id="clone-n-export-generate-export" class="button button-primary"><?php esc_html_e( 'Generate Export Code', 'clone-n-export' ); ?></button>
                    </div>
                </div>
                
                <div id="clone-n-export-export-result" class="clone-n-export-result" style="display: none;">
                    <h3><?php esc_html_e( 'Export Code', 'clone-n-export' ); ?></h3>
                    <p><?php esc_html_e( 'Copy this code and paste it into the import field on your target WordPress site.', 'clone-n-export' ); ?></p>
                    
                    <div class="clone-n-export-form-row">
                        <textarea id="clone-n-export-export-code" readonly rows="10"></textarea>
                    </div>
                    
                    <div class="clone-n-export-form-row">
                        <button id="clone-n-export-copy-export" class="button button-secondary"><?php esc_html_e( 'Copy to Clipboard', 'clone-n-export' ); ?></button>
                    </div>
                </div>
                
                <div id="clone-n-export-export-notice" class="notice" style="display: none;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the import page.
     */
    public function render_import_page() {
        ?>
        <div class="wrap clone-n-export-wrap">
            <h1><?php esc_html_e( 'Clone & Export - Import', 'clone-n-export' ); ?></h1>
            
            <div class="clone-n-export-card">
                <h2><?php esc_html_e( 'Import Content', 'clone-n-export' ); ?></h2>
                <p><?php esc_html_e( 'Paste the export code generated by Clone & Export and import it into this site.', 'clone-n-export' ); ?></p>
                
                <div class="clone-n-export-form">
                    <?php wp_nonce_field( 'clone-n-export-import', 'clone_n_export_import_nonce' ); ?>
                    
                    <div class="clone-n-export-form-row">
                        <label for="clone-n-export-import-code"><?php esc_html_e( 'Paste Export Code:', 'clone-n-export' ); ?></label>
                        <textarea id="clone-n-export-import-code" name="import_code" rows="10" required></textarea>
                    </div>
                    
                    <div class="clone-n-export-form-row">
                        <button id="clone-n-export-validate-import" class="button button-secondary"><?php esc_html_e( 'Validate', 'clone-n-export' ); ?></button>
                    </div>
                </div>
                
                <div id="clone-n-export-import-preview" class="clone-n-export-preview" style="display: none;">
                    <h3><?php esc_html_e( 'Import Preview', 'clone-n-export' ); ?></h3>
                    
                    <div class="clone-n-export-preview-content">
                        <p><strong><?php esc_html_e( 'Title:', 'clone-n-export' ); ?></strong> <span id="clone-n-export-preview-title"></span></p>
                        <p><strong><?php esc_html_e( 'Type:', 'clone-n-export' ); ?></strong> <span id="clone-n-export-preview-type"></span></p>
                        <p><strong><?php esc_html_e( 'Media Count:', 'clone-n-export' ); ?></strong> <span id="clone-n-export-preview-media-count"></span></p>
                    </div>
                    
                    <div class="clone-n-export-form-row">
                        <button id="clone-n-export-import-now" class="button button-primary"><?php esc_html_e( 'Import Now', 'clone-n-export' ); ?></button>
                    </div>
                </div>
                
                <div id="clone-n-export-import-progress" class="clone-n-export-progress" style="display: none;">
                    <h3><?php esc_html_e( 'Import Progress', 'clone-n-export' ); ?></h3>
                    
                    <div class="clone-n-export-progress-bar-container">
                        <div id="clone-n-export-progress-bar" class="clone-n-export-progress-bar"></div>
                    </div>
                    
                    <p id="clone-n-export-progress-message"><?php esc_html_e( 'Importing...', 'clone-n-export' ); ?></p>
                </div>
                
                <div id="clone-n-export-import-result" class="clone-n-export-result" style="display: none;">
                    <h3><?php esc_html_e( 'Import Complete', 'clone-n-export' ); ?></h3>
                    
                    <p><?php esc_html_e( 'Content imported successfully as a draft!', 'clone-n-export' ); ?></p>
                    
                    <p><a id="clone-n-export-view-imported" href="#" class="button button-primary"><?php esc_html_e( 'Edit Imported Content', 'clone-n-export' ); ?></a></p>
                </div>
                
                <div id="clone-n-export-import-notice" class="notice" style="display: none;"></div>
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
            wp_send_json_error( array( 'message' => __( 'No post selected.', 'clone-n-export' ) ) );
        }

        $post_id = intval( $_POST['post_id'] );

        // Initialize exporter.
        $exporter = new Clone_N_Export_Exporter();
        
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
        $validator = new Clone_N_Export_Importer();
        $validation = $validator->validate_import_data( $import_data );

        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
        }

        // Get media count.
        $media_handler = new Clone_N_Export_Media_Handler();
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
        $importer = new Clone_N_Export_Importer();
        
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
