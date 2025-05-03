<?php
/**
 * Admin class for SiteSync Cloner.
 *
 * @package SiteSync_Cloner
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class.
 */
class SiteSync_Cloner_Admin {

    /**
     * Initialize the admin class.
     */
    public function __construct() {
        // Add admin menu.
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        // Register admin scripts and styles.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // Register AJAX handlers.
        add_action( 'wp_ajax_sitesync_cloner_export', array( $this, 'handle_export_ajax' ) );
        add_action( 'wp_ajax_sitesync_cloner_validate_import', array( $this, 'handle_validate_import_ajax' ) );
        add_action( 'wp_ajax_sitesync_cloner_import', array( $this, 'handle_import_ajax' ) );
        add_action( 'wp_ajax_sitesync_cloner_load_posts', array( $this, 'handle_load_posts_ajax' ) );
        
        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add admin menu items.
     */
    public function add_admin_menu() {
        // Add main menu page
        add_menu_page(
            __( 'SiteSync Cloner', 'sitesync-cloner' ),
            __( 'SiteSync Cloner', 'sitesync-cloner' ),
            'edit_posts',
            'sitesync-cloner',
            array( $this, 'render_export_page' ), // Default to export page
            'dashicons-backup', // Use backup icon
            30 // Position after Comments
        );

        // Add Export submenu page
        add_submenu_page(
            'sitesync-cloner',
            __( 'SiteSync Cloner - Export', 'sitesync-cloner' ),
            __( 'Export', 'sitesync-cloner' ),
            'edit_posts',
            'sitesync-cloner', // Same as parent slug to override
            array( $this, 'render_export_page' )
        );

        // Add Import submenu page
        add_submenu_page(
            'sitesync-cloner',
            __( 'SiteSync Cloner - Import', 'sitesync-cloner' ),
            __( 'Import', 'sitesync-cloner' ),
            'edit_posts',
            'sitesync-cloner-import',
            array( $this, 'render_import_page' )
        );
        
        // Add Settings submenu page
        add_submenu_page(
            'sitesync-cloner',
            __( 'SiteSync Cloner - Settings', 'sitesync-cloner' ),
            __( 'Settings', 'sitesync-cloner' ),
            'manage_options',
            'sitesync-cloner-settings',
            array( $this, 'render_settings_page' )
        );
    }
    
    /**
     * Register plugin settings.
     */
    public function register_settings() {
        // Register settings group
        register_setting(
            'sitesync_cloner_settings',
            'sitesync_cloner_settings',
            array( $this, 'sanitize_settings' )
        );
        
        // Register default settings if they don't exist
        $default_settings = array(
            'handle_media' => 1,
            'preserve_dates' => 1,
            'post_types' => array('post', 'page'),
        );
        
        if ( false === get_option( 'sitesync_cloner_settings' ) ) {
            add_option( 'sitesync_cloner_settings', $default_settings );
        }
    }
    
    /**
     * Sanitize settings before saving.
     *
     * @param array $input The settings input array.
     * @return array Sanitized settings.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        
        // Handle media checkbox - checkboxes are only submitted when checked
        $sanitized['handle_media'] = isset( $input['handle_media'] ) ? 1 : 0;
        
        // Preserve dates checkbox - checkboxes are only submitted when checked
        $sanitized['preserve_dates'] = isset( $input['preserve_dates'] ) ? 1 : 0;
        
        // Post types array (ensure at least post and page are included)
        $sanitized['post_types'] = array('post', 'page');
        
        // Add a notice
        add_settings_error(
            'sitesync_cloner_settings',
            'sitesync_settings_updated',
            __('Settings saved successfully.', 'sitesync-cloner'),
            'updated'
        );
        
        return $sanitized;
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        // Match both old and new menu hooks for backward compatibility
        $valid_hooks = array(
            'tools_page_sitesync-cloner-export',
            'tools_page_sitesync-cloner-import',
            'toplevel_page_sitesync-cloner',
            'sitesync-cloner_page_sitesync-cloner-import',
            'sitesync-cloner_page_sitesync-cloner-settings'
        );
        
        if ( !in_array($hook, $valid_hooks) ) {
            return;
        }

        // Enqueue CSS.
        wp_enqueue_style(
            'sitesync-cloner-admin',
            SITESYNC_CLONER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SITESYNC_CLONER_VERSION
        );

        // Enqueue JS.
        wp_enqueue_script(
            'sitesync-cloner-admin',
            SITESYNC_CLONER_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            SITESYNC_CLONER_VERSION,
            true
        );

        // Localize script.
        wp_localize_script(
            'sitesync-cloner-admin',
            'siteSyncClonerAdmin',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'adminUrl'  => admin_url(),
                'nonce'     => wp_create_nonce( 'sitesync-cloner-nonce' ),
                'i18n'      => array(
                    'exportSuccess'     => __( 'Export generated successfully!', 'sitesync-cloner' ),
                    'exportError'       => __( 'Error generating export.', 'sitesync-cloner' ),
                    'batchExportSuccess'=> __( 'Exported {count} items successfully!', 'sitesync-cloner' ),
                    'copySuccess'       => __( 'Export code copied to clipboard!', 'sitesync-cloner' ),
                    'copyError'         => __( 'Error copying to clipboard.', 'sitesync-cloner' ),
                    'saveSuccess'       => __( 'Export saved to file!', 'sitesync-cloner' ),
                    'saveError'         => __( 'Error saving to file.', 'sitesync-cloner' ),
                    'fileReadSuccess'   => __( 'File read successfully!', 'sitesync-cloner' ),
                    'fileReadError'     => __( 'Error reading file. Please make sure it is a valid JSON file.', 'sitesync-cloner' ),
                    'validationSuccess' => __( 'JSON validated successfully!', 'sitesync-cloner' ),
                    'validationError'   => __( 'Invalid JSON format or missing required fields.', 'sitesync-cloner' ),
                    'importSuccess'     => __( 'Content imported successfully!', 'sitesync-cloner' ),
                    'batchImportSuccess'=> __( 'Imported {count} items successfully!', 'sitesync-cloner' ),
                    'importError'       => __( 'Error importing content.', 'sitesync-cloner' ),
                    'downloadingMedia'  => __( 'Downloading media files...', 'sitesync-cloner' ),
                    'processingContent' => __( 'Processing content...', 'sitesync-cloner' ),
                ),
            )
        );
    }

    /**
     * Render the export page.
     */
    public function render_export_page() {
        ?>
        <div class="wrap sitesync-cloner-wrap">
            <h1><?php esc_html_e( 'SiteSync Cloner - Export', 'sitesync-cloner' ); ?></h1>
            
            <div class="sitesync-cloner-card">
                <h2><?php esc_html_e( 'Export Content', 'sitesync-cloner' ); ?></h2>
                <p><?php esc_html_e( 'Select a post or page to export and generate a JSON code that can be imported into another WordPress site.', 'sitesync-cloner' ); ?></p>
                
                <div class="wp-content-porter-form">
                    <?php wp_nonce_field( 'sitesync-cloner-export', 'sitesync_cloner_export_nonce' ); ?>
                    
                    <div class="sitesync-cloner-form-row">
                        <label for="sitesync-cloner-post-type"><?php esc_html_e( 'Select Content Type:', 'sitesync-cloner' ); ?></label>
                        <div class="sitesync-cloner-tabs" id="sitesync-cloner-post-type-tabs">
                            <button class="sitesync-cloner-tab active" data-post-type="post"><?php esc_html_e( 'Posts', 'sitesync-cloner' ); ?></button>
                            <button class="sitesync-cloner-tab" data-post-type="page"><?php esc_html_e( 'Pages', 'sitesync-cloner' ); ?></button>
                            <?php 
                            // Get other public post types
                            $post_types = get_post_types( array( 'public' => true ), 'objects' );
                            foreach ( $post_types as $post_type ) :
                                // Skip posts and pages as they're already included
                                if ( $post_type->name === 'post' || $post_type->name === 'page' || $post_type->name === 'attachment' ) :
                                    continue;
                                endif;
                            ?>
                                <button class="sitesync-cloner-tab" data-post-type="<?php echo esc_attr( $post_type->name ); ?>">
                                    <?php echo esc_html( $post_type->labels->name ); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="sitesync-cloner-form-row">
                        <label for="sitesync-cloner-search"><?php esc_html_e( 'Search Content:', 'sitesync-cloner' ); ?></label>
                        <input type="text" id="sitesync-cloner-search" name="search" placeholder="<?php esc_attr_e( 'Type to search...', 'sitesync-cloner' ); ?>" />
                    </div>
                    
                    <div class="sitesync-cloner-form-row">
                        <label><?php esc_html_e( 'Select Content:', 'sitesync-cloner' ); ?></label>
                        <div id="sitesync-cloner-content-grid" class="sitesync-cloner-content-grid">
                            <div class="sitesync-cloner-empty-message"><?php esc_html_e( 'No content available. Select a content type above or try a different search term.', 'sitesync-cloner' ); ?></div>
                        </div>
                        <div id="sitesync-cloner-loading" class="sitesync-cloner-loading" style="display: none;">
                            <span class="spinner is-active"></span> <?php esc_html_e( 'Loading...', 'sitesync-cloner' ); ?>
                        </div>
                        <div class="sitesync-cloner-selection-info">
                            <span id="sitesync-cloner-selected-count">0</span> <?php esc_html_e( 'items selected', 'sitesync-cloner' ); ?>
                        </div>
                    </div>
                    
                    <div class="sitesync-cloner-form-row">
                        <button id="sitesync-cloner-generate-export" class="button button-primary"><?php esc_html_e( 'Generate Export Code', 'sitesync-cloner' ); ?></button>
                    </div>
                </div>
                
                <div id="sitesync-cloner-export-result" class="sitesync-cloner-result" style="display: none;">
                    <h3><?php esc_html_e( 'Export Code', 'sitesync-cloner' ); ?></h3>
                    <p><?php esc_html_e( 'Copy this code and paste it into the import field on your target WordPress site.', 'sitesync-cloner' ); ?></p>
                    
                    <div class="sitesync-cloner-form-row">
                        <textarea id="sitesync-cloner-export-code" readonly rows="10"></textarea>
                    </div>
                    
                    <div class="sitesync-cloner-form-row">
                        <button id="sitesync-cloner-copy-export" class="button button-secondary"><?php esc_html_e( 'Copy to Clipboard', 'sitesync-cloner' ); ?></button>
                        <button id="sitesync-cloner-save-export" class="button button-secondary"><?php esc_html_e( 'Save to File', 'sitesync-cloner' ); ?></button>
                    </div>
                </div>
                
                <div id="sitesync-cloner-export-notice" class="notice" style="display: none;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the import page.
     */
    public function render_import_page() {
        ?>
        <div class="wrap sitesync-cloner-wrap">
            <h1><?php esc_html_e( 'SiteSync Cloner - Import', 'sitesync-cloner' ); ?></h1>
            
            <div class="sitesync-cloner-card">
                <h2><?php esc_html_e( 'Import Content', 'sitesync-cloner' ); ?></h2>
                <p><?php esc_html_e( 'Paste the export code generated by SiteSync Cloner and import it into this site.', 'sitesync-cloner' ); ?></p>
                
                <div class="sitesync-cloner-form">
                    <?php wp_nonce_field( 'sitesync-cloner-import', 'sitesync_cloner_import_nonce' ); ?>
                    
                    <div class="sitesync-cloner-form-row">
                        <label for="sitesync-cloner-import-code"><?php esc_html_e( 'Paste Export Code:', 'sitesync-cloner' ); ?></label>
                        <textarea id="sitesync-cloner-import-code" name="import_code" rows="10" required></textarea>
                    </div>
                    
                    <div class="sitesync-cloner-form-row">
                        <label for="sitesync-cloner-import-file"><?php esc_html_e( 'Or Import from File:', 'sitesync-cloner' ); ?></label>
                        <input type="file" id="sitesync-cloner-import-file" accept=".json,application/json" class="sitesync-cloner-file-input" />
                    </div>
                    
                    <div class="sitesync-cloner-form-row">
                        <button id="sitesync-cloner-validate-import" class="button button-secondary"><?php esc_html_e( 'Validate', 'sitesync-cloner' ); ?></button>
                    </div>
                </div>
                
                <div id="sitesync-cloner-import-preview" class="sitesync-cloner-preview" style="display: none;">
                    <h3><?php esc_html_e( 'Import Preview', 'sitesync-cloner' ); ?></h3>
                    
                    <div class="sitesync-cloner-preview-content">
                        <p><strong><?php esc_html_e( 'Title:', 'sitesync-cloner' ); ?></strong> <span id="sitesync-cloner-preview-title"></span></p>
                        <p><strong><?php esc_html_e( 'Type:', 'sitesync-cloner' ); ?></strong> <span id="sitesync-cloner-preview-type"></span></p>
                        <p><strong><?php esc_html_e( 'Media Count:', 'sitesync-cloner' ); ?></strong> <span id="sitesync-cloner-preview-media-count"></span></p>
                    </div>
                    
                    <div class="sitesync-cloner-form-row">
                        <button id="sitesync-cloner-import-now" class="button button-primary"><?php esc_html_e( 'Import Now', 'sitesync-cloner' ); ?></button>
                    </div>
                </div>
                
                <div id="sitesync-cloner-import-progress" class="sitesync-cloner-progress" style="display: none;">
                    <h3><?php esc_html_e( 'Import Progress', 'sitesync-cloner' ); ?></h3>
                    
                    <div class="sitesync-cloner-progress-bar-container">
                        <div id="sitesync-cloner-progress-bar" class="sitesync-cloner-progress-bar"></div>
                    </div>
                    
                    <p id="sitesync-cloner-progress-message"><?php esc_html_e( 'Importing...', 'sitesync-cloner' ); ?></p>
                </div>
                
                <div id="sitesync-cloner-import-result" class="sitesync-cloner-result" style="display: none;">
                    <h3><?php esc_html_e( 'Import Complete', 'sitesync-cloner' ); ?></h3>
                    
                    <p><?php esc_html_e( 'Content imported successfully as a draft!', 'sitesync-cloner' ); ?></p>
                    
                    <p><a id="sitesync-cloner-view-imported" href="#" class="button button-primary"><?php esc_html_e( 'Edit Imported Content', 'sitesync-cloner' ); ?></a></p>
                </div>
                
                <div id="sitesync-cloner-import-notice" class="notice" style="display: none;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        // Get current settings
        $settings = get_option('sitesync_cloner_settings', array());
        
        // Set defaults if settings don't exist
        if (empty($settings)) {
            $settings = array(
                'handle_media' => 1,
                'preserve_dates' => 1,
                'post_types' => array('post', 'page'),
            );
        }
        
        // Check if specific settings exist
        $handle_media = isset($settings['handle_media']) ? (bool)$settings['handle_media'] : true;
        $preserve_dates = isset($settings['preserve_dates']) ? (bool)$settings['preserve_dates'] : true;
        ?>
        <div class="wrap sitesync-cloner-wrap">
            <h1><?php esc_html_e( 'SiteSync Cloner - Settings', 'sitesync-cloner' ); ?></h1>
            
            <div class="sitesync-cloner-card">
                <h2><?php esc_html_e( 'Plugin Settings', 'sitesync-cloner' ); ?></h2>
                
                <?php 
                // Show settings updated message if settings were updated
                if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'sitesync-cloner') . '</p></div>';
                }
                ?>
                
                <form method="post" action="options.php" id="sitesync-cloner-settings-form">
                    <?php
                    settings_fields( 'sitesync_cloner_settings' );
                    do_settings_sections( 'sitesync_cloner_settings' );
                    ?>
                    
                    <div class="sitesync-cloner-form">
                        <div class="sitesync-cloner-form-row">
                            <h3><?php esc_html_e( 'Content Types', 'sitesync-cloner' ); ?></h3>
                            <p class="description"><?php esc_html_e( 'Select which content types can be exported and imported.', 'sitesync-cloner' ); ?></p>
                            
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="sitesync_cloner_settings[post_types][]" value="post" checked disabled>
                                    <?php esc_html_e( 'Posts', 'sitesync-cloner' ); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="sitesync_cloner_settings[post_types][]" value="page" checked disabled>
                                    <?php esc_html_e( 'Pages', 'sitesync-cloner' ); ?>
                                </label>
                                
                                <p class="description"><?php esc_html_e( 'Additional post type support will be added in future updates.', 'sitesync-cloner' ); ?></p>
                            </fieldset>
                        </div>
                        
                        <div class="sitesync-cloner-form-row">
                            <h3><?php esc_html_e( 'Media Handling', 'sitesync-cloner' ); ?></h3>
                            
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="sitesync_cloner_settings[handle_media]" value="1" <?php checked($handle_media); ?>>
                                    <?php esc_html_e( 'Download and import media files', 'sitesync-cloner' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'When enabled, SiteSync Cloner will attempt to download media files from the source site and add them to your media library.', 'sitesync-cloner' ); ?></p>
                            </fieldset>
                        </div>
                        
                        <div class="sitesync-cloner-form-row">
                            <h3><?php esc_html_e( 'Advanced Options', 'sitesync-cloner' ); ?></h3>
                            
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="sitesync_cloner_settings[preserve_dates]" value="1" <?php checked($preserve_dates); ?>>
                                    <?php esc_html_e( 'Preserve original post dates', 'sitesync-cloner' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'When enabled, imported content will maintain its original creation date.', 'sitesync-cloner' ); ?></p>
                            </fieldset>
                        </div>
                    </div>
                    
                    <?php submit_button( __( 'Save Settings', 'sitesync-cloner' ) ); ?>
                </form>
                
                <div id="sitesync-cloner-settings-notice" class="notice" style="display: none;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle export AJAX request.
     */
    public function handle_export_ajax() {
        // Check nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sitesync-cloner-nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'sitesync-cloner' ) ) );
        }

        // Initialize exporter.
        $exporter = new SiteSync_Cloner_Exporter();
        
        // Check if multiple post IDs are provided (batch export)
        if ( isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] ) ) {
            // Sanitize post IDs
            $post_ids = array_map( 'intval', $_POST['post_ids'] );
            
            // Generate batch export
            $export_data = $exporter->export_batch( $post_ids );
            
            if ( is_wp_error( $export_data ) ) {
                wp_send_json_error( array( 'message' => $export_data->get_error_message() ) );
            }
            
            // Process JSON with JSON processor.
            $json_processor = new SiteSync_Cloner_JSON_Processor();
            $json = $json_processor->encode( $export_data );
            
            if ( is_wp_error( $json ) ) {
                wp_send_json_error( array( 'message' => $json->get_error_message() ) );
            }
            
            $summary = sprintf(
                __( 'Exported %1$d items successfully. %2$s', 'sitesync-cloner' ),
                count( $export_data['items'] ),
                isset( $export_data['errors'] ) ? sprintf( __( 'Failed to export %d items.', 'sitesync-cloner' ), count( $export_data['errors'] ) ) : ''
            );
            
            wp_send_json_success( array( 
                'json' => $json,
                'is_batch' => true,
                'count' => count( $export_data['items'] ),
                'summary' => $summary
            ) );
        } 
        // Single post export (for backward compatibility)
        else if ( isset( $_POST['post_id'] ) ) {
            $post_id = intval( $_POST['post_id'] );
            
            // Generate export.
            $export_data = $exporter->export_post( $post_id );
    
            if ( is_wp_error( $export_data ) ) {
                wp_send_json_error( array( 'message' => $export_data->get_error_message() ) );
            }
    
            // Process JSON with JSON processor.
            $json_processor = new SiteSync_Cloner_JSON_Processor();
            $json = $json_processor->encode( $export_data );
    
            if ( is_wp_error( $json ) ) {
                wp_send_json_error( array( 'message' => $json->get_error_message() ) );
            }
    
            wp_send_json_success( array( 
                'json' => $json,
                'is_batch' => false,
                'count' => 1
            ) );
        } 
        // No post ID or IDs provided
        else {
            wp_send_json_error( array( 'message' => __( 'No post ID(s) provided.', 'sitesync-cloner' ) ) );
        }
    }

    /**
     * Handle validate import AJAX request.
     */
    public function handle_validate_import_ajax() {
        // Check nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sitesync-cloner-nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'sitesync-cloner' ) ) );
        }

        // Check if import code is provided.
        if ( ! isset( $_POST['import_code'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No import code provided.', 'sitesync-cloner' ) ) );
        }

        $import_code = wp_unslash( $_POST['import_code'] );

        // Process JSON with JSON processor.
        $json_processor = new SiteSync_Cloner_JSON_Processor();
        $import_data = $json_processor->decode( $import_code );

        if ( is_wp_error( $import_data ) ) {
            wp_send_json_error( array( 'message' => $import_data->get_error_message() ) );
        }

        // Determine if this is a batch import
        $is_batch = isset( $import_data['batch_id'] ) && isset( $import_data['items'] ) && is_array( $import_data['items'] );
        
        // Initialize validator
        $validator = new SiteSync_Cloner_Importer();
        $media_handler = new SiteSync_Cloner_Media_Handler();
        
        if ( $is_batch ) {
            // Validate the batch data
            $validation = $validator->validate_batch_data( $import_data );
            
            if ( is_wp_error( $validation ) ) {
                wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
            }
            
            // Calculate total media count
            $media_count = 0;
            $titles = array();
            $types = array();
            
            foreach ( $import_data['items'] as $item ) {
                $media_count += $media_handler->count_media_in_content( $item );
                $titles[] = $item['post_title'];
                if ( ! in_array( $item['post_type'], $types ) ) {
                    $types[] = $item['post_type'];
                }
            }
            
            wp_send_json_success( array(
                'is_batch'    => true,
                'count'       => count( $import_data['items'] ),
                'titles'      => $titles,
                'types'       => $types,
                'media_count' => $media_count,
                'summary'     => sprintf( __( 'Ready to import %d items', 'sitesync-cloner' ), count( $import_data['items'] ) ),
            ) );
        } else {
            // Handle single item import
            $validation = $validator->validate_import_data( $import_data );
            
            if ( is_wp_error( $validation ) ) {
                wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
            }
            
            // Get media count
            $media_count = $media_handler->count_media_in_content( $import_data );
            
            wp_send_json_success( array(
                'is_batch'    => false,
                'count'       => 1,
                'title'       => $import_data['post_title'],
                'type'        => $import_data['post_type'],
                'media_count' => $media_count,
            ) );
        }
    }

    /**
     * Handle load posts AJAX request.
     */
    public function handle_load_posts_ajax() {
        // Check nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sitesync-cloner-nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'sitesync-cloner' ) ) );
        }

        // Get post type and search term
        $post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : 'post';
        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

        // Check if post type exists
        if ( ! post_type_exists( $post_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post type.', 'sitesync-cloner' ) ) );
        }

        // Set up query args
        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => 50, // Limit results for performance
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        // Add search if provided
        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }

        // Get posts
        $query = new WP_Query( $args );
        $posts = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $posts[] = array(
                    'id'     => get_the_ID(),
                    'title'  => get_the_title() . ' (' . get_post_status() . ')',
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( $posts );
    }

    /**
     * Handle import AJAX request.
     */
    public function handle_import_ajax() {
        // Check nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sitesync-cloner-nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'sitesync-cloner' ) ) );
        }

        // Check if import code is provided.
        if ( ! isset( $_POST['import_code'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No import code provided.', 'sitesync-cloner' ) ) );
        }

        $import_code = wp_unslash( $_POST['import_code'] );

        // Process JSON with JSON processor.
        $json_processor = new SiteSync_Cloner_JSON_Processor();
        $import_data = $json_processor->decode( $import_code );

        if ( is_wp_error( $import_data ) ) {
            wp_send_json_error( array( 'message' => $import_data->get_error_message() ) );
        }

        // Initialize importer.
        $importer = new SiteSync_Cloner_Importer();
        
        // Determine if this is a batch import
        $is_batch = isset( $import_data['batch_id'] ) && isset( $import_data['items'] ) && is_array( $import_data['items'] );
        
        if ( $is_batch ) {
            // Perform batch import
            $results = $importer->import_batch( $import_data );
            
            if ( is_wp_error( $results ) ) {
                wp_send_json_error( array( 'message' => $results->get_error_message() ) );
            }
            
            // Calculate success summary
            $success_count = count( $results['success'] );
            $error_count = count( $results['errors'] );
            
            $summary = sprintf(
                _n(
                    'Imported %d item successfully.',
                    'Imported %d items successfully.',
                    $success_count,
                    'sitesync-cloner'
                ),
                $success_count
            );
            
            if ( $error_count > 0 ) {
                $summary .= ' ' . sprintf(
                    _n(
                        '%d item failed to import.',
                        '%d items failed to import.',
                        $error_count,
                        'sitesync-cloner'
                    ),
                    $error_count
                );
            }
            
            // Get first successful import for viewing link
            $first_import = ! empty( $results['success'] ) ? reset( $results['success'] ) : null;
            
            // Determine the post type for admin URL
            $post_type = '';
            if ($first_import && isset($first_import['post_title'])) {
                // Try to get post type from the post ID
                $post = get_post($first_import['post_id']);
                if ($post) {
                    $post_type = $post->post_type;
                }
            } else {
                // No successful imports, try to determine from first item in batch
                if (!empty($import_data['items']) && is_array($import_data['items'])) {
                    $first_item = reset($import_data['items']);
                    if (isset($first_item['post_type'])) {
                        $post_type = $first_item['post_type'];
                    }
                }
            }
            
            // Generate appropriate view URL for admin page based on post type
            $view_url = admin_url('edit.php' . ($post_type === 'page' ? '?post_type=page' : ''));
            
            wp_send_json_success( array(
                'is_batch'     => true,
                'success_count' => $success_count,
                'error_count'  => $error_count,
                'summary'      => $summary,
                'success'      => $results['success'],
                'errors'       => $results['errors'],
                'post_id'      => $first_import ? $first_import['post_id'] : null,
                'edit_url'     => $first_import ? $first_import['edit_url'] : '',
                'view_url'     => $view_url,
                'post_type'    => $post_type,
            ) );
        } else {
            // Handle single item import
            $result = $importer->import_content( $import_data );
    
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }
    
            wp_send_json_success( array(
                'is_batch'   => false,
                'post_id'    => $result,
                'post_url'   => get_permalink( $result ),
                'edit_url'   => get_edit_post_link( $result, 'raw' ),
                'summary'    => __( 'Content imported successfully!', 'sitesync-cloner' ),
            ) );
        }
    }
}
