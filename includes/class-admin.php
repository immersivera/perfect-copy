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
        add_action( 'wp_ajax_sitesync_cloner_quick_export', array( $this, 'handle_quick_export_ajax' ) );
        add_action( 'wp_ajax_sitesync_cloner_clone_post', array( $this, 'handle_clone_post_ajax' ) );
        
        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // Add action links to post list tables
        $this->register_post_actions();
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
            'handle_media' => 1, // Media handling ON by default
            'preserve_dates' => 0, // Original post dates OFF by default
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
        
        // Post types - ensure at least post and page are included
        $sanitized['post_types'] = isset($input['post_types']) && is_array($input['post_types']) ? $input['post_types'] : array();
        
        // Always include core post types
        if (!in_array('post', $sanitized['post_types'])) {
            $sanitized['post_types'][] = 'post';
        }
        
        if (!in_array('page', $sanitized['post_types'])) {
            $sanitized['post_types'][] = 'page';
        }
        
        // Filter to only valid public post types
        $public_post_types = get_post_types(array('public' => true));
        $sanitized['post_types'] = array_intersect($sanitized['post_types'], $public_post_types);
        
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
                    'quickExportTitle'  => __( 'SiteSync Quick Export', 'sitesync-cloner' ),
                    'exportGenerating'  => __( 'Generating export...', 'sitesync-cloner' ),
                    'copyExport'        => __( 'Copy to Clipboard', 'sitesync-cloner' ),
                    'saveExport'        => __( 'Save to File', 'sitesync-cloner' ),
                    'copied'            => __( 'Copied!', 'sitesync-cloner' ),
                    'exportContent'     => __( 'Export Content', 'sitesync-cloner' ),
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
                        <select id="sitesync-cloner-post-type" class="sitesync-cloner-select">
                            <?php
                            // Get enabled post types from settings
                            $settings = get_option('sitesync_cloner_settings', array());
                            $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
                            
                            // Get all public post types
                            $post_types = get_post_types( array( 'public' => true ), 'objects' );
                            $first_type = true; // Track first type to make it selected by default
                            
                            foreach ( $post_types as $post_type ) :
                                // Skip attachments
                                if ( $post_type->name === 'attachment' ) :
                                    continue;
                                endif;
                                
                                // Only show enabled post types
                                if ( in_array($post_type->name, $enabled_post_types) ) :
                                    $selected = $first_type ? 'selected="selected"' : '';
                                    $first_type = false;
                            ?>
                                <option value="<?php echo esc_attr( $post_type->name ); ?>" <?php echo $selected; ?>>
                                    <?php echo esc_html( $post_type->labels->name ); ?>
                                </option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </select>
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
                'handle_media' => 1, // Media handling ON by default
                'preserve_dates' => 0, // Original post dates OFF by default
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
                                <?php 
                                // Get all public post types
                                $post_types = get_post_types(array('public' => true), 'objects');
                                $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
                                
                                // Core post types that must be enabled
                                $core_types = array('post', 'page');
                                
                                foreach ($post_types as $post_type) :
                                    // Skip media attachments
                                    if ($post_type->name === 'attachment') {
                                        continue;
                                    }
                                    
                                    $is_core = in_array($post_type->name, $core_types);
                                    $checked = in_array($post_type->name, $enabled_post_types) ? 'checked' : '';
                                    $disabled = $is_core ? 'disabled' : '';
                                ?>
                                <label>
                                    <input type="checkbox" name="sitesync_cloner_settings[post_types][]" value="<?php echo esc_attr($post_type->name); ?>"
                                           <?php echo $checked; ?> <?php echo $disabled; ?>>
                                    <?php echo esc_html($post_type->labels->name); ?>
                                    <?php if ($is_core) : ?>
                                        <span class="required">(<?php esc_html_e('Required', 'sitesync-cloner'); ?>)</span>
                                    <?php endif; ?>
                                </label><br>
                                <?php endforeach; ?>
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

        // Get enabled post types from settings
        $settings = get_option('sitesync_cloner_settings', array());
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
        
        // Verify the requested post type is enabled
        if (!in_array($post_type, $enabled_post_types)) {
            wp_send_json_error( array( 'message' => __( 'This post type is not enabled in SiteSync Cloner settings.', 'sitesync-cloner' ) ) );
        }

        // Get page number for pagination
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        
        // Set up query args
        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => 10, // Limit to 10 posts per page as requested
            'paged'          => $page,
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
        
        // Calculate pagination info
        $total_posts = $query->found_posts;
        $total_pages = ceil($total_posts / $args['posts_per_page']);

        $response = array(
            'posts' => $posts,
            'pagination' => array(
                'total_posts' => $total_posts,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'per_page' => $args['posts_per_page']
            )
        );

        wp_send_json_success( $response );
    }

    /**
     * Register post action links and buttons for quick exporting
     */
    private function register_post_actions() {
        // Get enabled post types from settings
        $settings = get_option('sitesync_cloner_settings', array());
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
        
        // Add action links to post list tables
        foreach ($enabled_post_types as $post_type) {
            // Add Export link to row actions
            add_filter("post_row_actions", array($this, 'add_export_row_action'), 10, 2);
            
            // Also add to page row actions if it's a page
            if ($post_type === 'page') {
                add_filter("page_row_actions", array($this, 'add_export_row_action'), 10, 2);
            }
            
            // For custom post types
            if ($post_type !== 'post' && $post_type !== 'page') {
                add_filter("{$post_type}_row_actions", array($this, 'add_export_row_action'), 10, 2);
            }
            
            // Add meta box to post edit screens
            add_action("add_meta_boxes_{$post_type}", array($this, 'add_export_meta_box'));
        }
    }
    
    /**
     * Add Quick Export and Clone links to row actions
     */
    public function add_export_row_action($actions, $post) {
        // Check if post type is enabled
        $settings = get_option('sitesync_cloner_settings', array());
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
        
        if (in_array($post->post_type, $enabled_post_types)) {
            // Create nonce for security
            $nonce = wp_create_nonce('sitesync_cloner_quick_export_' . $post->ID);
            
            // Create direct download export link
            $export_url = admin_url('admin-ajax.php?action=sitesync_cloner_quick_export&post_id=' . $post->ID . '&nonce=' . $nonce . '&mode=download');
            
            // Add export action link
            $actions['sitesync_export'] = sprintf(
                '<a href="%s" class="sitesync-quick-export-download">%s</a>',
                esc_url($export_url),
                __('Export', 'sitesync-cloner')
            );
            
            // Add clone action link
            $clone_nonce = wp_create_nonce('sitesync_cloner_clone_' . $post->ID);
            $clone_url = admin_url('admin-ajax.php?action=sitesync_cloner_clone_post&post_id=' . $post->ID . '&nonce=' . $clone_nonce);
            
            $actions['sitesync_clone'] = sprintf(
                '<a href="%s" class="sitesync-clone-post">%s</a>',
                esc_url($clone_url),
                __('Clone', 'sitesync-cloner')
            );
        }
        
        return $actions;
    }
    
    /**
     * Add Export meta box to post edit screens
     */
    public function add_export_meta_box($post) {
        add_meta_box(
            'sitesync_cloner_export_box',
            __('SiteSync Cloner', 'sitesync-cloner'),
            array($this, 'render_export_meta_box'),
            null,
            'side',
            'default'
        );
    }
    
    /**
     * Render the export meta box
     */
    public function render_export_meta_box($post) {
        // Create nonce for security
        $nonce = wp_create_nonce('sitesync_cloner_quick_export_' . $post->ID);
        
        // Create export URLs for different modes
        $export_url = admin_url('admin-ajax.php?action=sitesync_cloner_quick_export&post_id=' . $post->ID . '&nonce=' . $nonce);
        $download_url = admin_url('admin-ajax.php?action=sitesync_cloner_quick_export&post_id=' . $post->ID . '&nonce=' . $nonce . '&mode=download');
        
        // Create clone URL
        $clone_nonce = wp_create_nonce('sitesync_cloner_clone_' . $post->ID);
        $clone_url = admin_url('admin-ajax.php?action=sitesync_cloner_clone_post&post_id=' . $post->ID . '&nonce=' . $clone_nonce);
        
        // Output meta box content
        ?>
        <p><?php esc_html_e('SiteSync Cloner tools for this content.', 'sitesync-cloner'); ?></p>
        
        <div class="sitesync-meta-actions">
            <p><strong><?php esc_html_e('Export Options:', 'sitesync-cloner'); ?></strong></p>
            <a href="<?php echo esc_url($download_url); ?>" class="button sitesync-download-button">
                <?php esc_html_e('Download Export File', 'sitesync-cloner'); ?>
            </a>
            
            <p style="margin-top:15px;"><strong><?php esc_html_e('Clone Options:', 'sitesync-cloner'); ?></strong></p>
            <a href="<?php echo esc_url($clone_url); ?>" class="button button-primary sitesync-clone-button">
                <?php echo esc_html(get_post_type($post) === 'page' ? __('Clone This Page', 'sitesync-cloner') : __('Clone This Post', 'sitesync-cloner')); ?>
            </a>
            <p class="description"><?php esc_html_e('Creates a duplicate copy of this post as a draft.', 'sitesync-cloner'); ?></p>
        </div>
        
        <div class="sitesync-export-result" style="display:none;margin-top:15px;">
            <h4><?php esc_html_e('Export Result:', 'sitesync-cloner'); ?></h4>
        </div>
        <?php
    }
    
    /**
     * Handle quick export AJAX request
     */
    public function handle_quick_export_ajax() {
        // Check if post ID is provided
        if (!isset($_REQUEST['post_id'])) {
            wp_send_json_error(array('message' => __('No post ID provided.', 'sitesync-cloner')));
        }
        
        // Get post ID and mode (download or json)
        $post_id = intval($_REQUEST['post_id']);
        $mode = isset($_REQUEST['mode']) ? sanitize_text_field($_REQUEST['mode']) : 'json';
        
        // Verify nonce
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sitesync_cloner_quick_export_' . $post_id)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'sitesync-cloner')));
        }
        
        // Get post
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found.', 'sitesync-cloner')));
        }
        
        // Check if post type is enabled
        $settings = get_option('sitesync_cloner_settings', array());
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
        if (!in_array($post->post_type, $enabled_post_types)) {
            wp_send_json_error(array('message' => __('This post type is not enabled for export.', 'sitesync-cloner')));
        }
        
        // Generate export data
        $exporter = new SiteSync_Cloner_Exporter();
        $export_data = $exporter->export_post($post_id);
        
        if (is_wp_error($export_data)) {
            wp_send_json_error(array('message' => $export_data->get_error_message()));
        }
        
        // If mode is download, send as file download
        if ($mode === 'download') {
            $post_title = get_the_title($post_id);
            $filename = 'sitesync-export-' . sanitize_title($post_title) . '.json';
            
            // Set headers for file download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            // Output the JSON data and exit
            echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
            exit;
        }
        
        // Otherwise, send as JSON response
        wp_send_json_success(array(
            'data' => $export_data,
            'title' => get_the_title($post_id),
            'message' => __('Export generated successfully!', 'sitesync-cloner')
        ));
    }

    /**
     * Handle clone post AJAX request
     */
    public function handle_clone_post_ajax() {
        // Check if post ID is provided
        if (!isset($_REQUEST['post_id'])) {
            wp_die(__('No post ID provided.', 'sitesync-cloner'));
        }
        
        // Get post ID
        $post_id = intval($_REQUEST['post_id']);
        
        // Verify nonce
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'sitesync_cloner_clone_' . $post_id)) {
            wp_die(__('Security check failed.', 'sitesync-cloner'));
        }
        
        // Get post
        $post = get_post($post_id);
        if (!$post) {
            wp_die(__('Post not found.', 'sitesync-cloner'));
        }
        
        // Check if post type is enabled
        $settings = get_option('sitesync_cloner_settings', array());
        $enabled_post_types = isset($settings['post_types']) ? $settings['post_types'] : array('post', 'page');
        if (!in_array($post->post_type, $enabled_post_types)) {
            wp_die(__('This post type is not enabled for cloning.', 'sitesync-cloner'));
        }
        
        // Get the post data for cloning
        $post_data = array(
            'post_title'     => $post->post_title . ' ' . __('(Clone)', 'sitesync-cloner'),
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_status'    => 'draft', // Always set cloned posts to draft initially
            'post_type'      => $post->post_type,
            'post_author'    => $post->post_author,
            'ping_status'    => $post->ping_status,
            'comment_status' => $post->comment_status,
            'post_password'  => $post->post_password,
            'menu_order'     => $post->menu_order,
            'to_ping'        => $post->to_ping,
        );
        
        // Check if we should preserve dates
        $preserve_dates = isset($settings['preserve_dates']) ? $settings['preserve_dates'] : false;
        if ($preserve_dates) {
            $post_data['post_date'] = $post->post_date;
            $post_data['post_date_gmt'] = $post->post_date_gmt;
        }
        
        // Insert the new post
        $new_post_id = wp_insert_post($post_data);
        
        if (is_wp_error($new_post_id)) {
            wp_die(__('Error cloning post: ', 'sitesync-cloner') . $new_post_id->get_error_message());
        }
        
        // Copy post meta
        $post_meta = get_post_meta($post_id);
        if ($post_meta) {
            foreach ($post_meta as $meta_key => $meta_values) {
                // Skip internal WordPress meta keys
                if (in_array($meta_key, array('_edit_lock', '_edit_last', '_wp_old_slug'))) {
                    continue;
                }
                
                foreach ($meta_values as $meta_value) {
                    add_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
                }
            }
        }
        
        // Copy taxonomies/terms
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
            if (!empty($terms) && !is_wp_error($terms)) {
                wp_set_object_terms($new_post_id, $terms, $taxonomy);
            }
        }
        
        // Handle media if enabled in settings
        $handle_media = isset($settings['handle_media']) ? $settings['handle_media'] : true;
        if ($handle_media) {
            // Copy featured image if it exists
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if ($thumbnail_id) {
                set_post_thumbnail($new_post_id, $thumbnail_id);
            }
            
            // Let's also handle images in the content
            if (class_exists('SiteSync_Cloner_Media_Handler')) {
                // Find attachment IDs in the post content
                preg_match_all('/wp-image-(\d+)/i', $post->post_content, $matches);
                
                // If attachments are found, make sure they're associated with the new post
                if (isset($matches[1]) && !empty($matches[1])) {
                    // Set their new post parent
                    foreach ($matches[1] as $attachment_id) {
                        // This doesn't copy the attachment, just ensures it's associated in the media library
                        wp_update_post(array(
                            'ID' => $attachment_id,
                            'post_parent' => $new_post_id,
                        ));
                    }
                }
            }
        }
        
        // Redirect to the edit screen for the new post
        wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
        exit;
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
