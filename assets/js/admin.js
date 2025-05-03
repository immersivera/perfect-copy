/**
 * WP Content Porter Admin JavaScript
 */
jQuery(document).ready(function($) {
    // Export functionality
    const $generateExportBtn = $('#wp-content-porter-generate-export');
    const $exportResult = $('#wp-content-porter-export-result');
    const $exportCode = $('#wp-content-porter-export-code');
    const $copyExportBtn = $('#wp-content-porter-copy-export');
    const $exportNotice = $('#wp-content-porter-export-notice');
    
    // Import functionality
    const $validateImportBtn = $('#wp-content-porter-validate-import');
    const $importCode = $('#wp-content-porter-import-code');
    const $importPreview = $('#wp-content-porter-import-preview');
    const $previewTitle = $('#wp-content-porter-preview-title');
    const $previewType = $('#wp-content-porter-preview-type');
    const $previewMediaCount = $('#wp-content-porter-preview-media-count');
    const $importNowBtn = $('#wp-content-porter-import-now');
    const $importProgress = $('#wp-content-porter-import-progress');
    const $progressBar = $('#wp-content-porter-progress-bar');
    const $progressMessage = $('#wp-content-porter-progress-message');
    const $importResult = $('#wp-content-porter-import-result');
    const $viewImportedBtn = $('#wp-content-porter-view-imported');
    const $importNotice = $('#wp-content-porter-import-notice');
    
    /**
     * Display notice message
     */
    function showNotice(container, message, type = 'info') {
        container.html(message)
            .removeClass('success error info')
            .addClass(type)
            .show();
        
        // Scroll to notice
        $('html, body').animate({
            scrollTop: container.offset().top - 100
        }, 300);
        
        // Auto-hide after 8 seconds
        setTimeout(() => {
            container.fadeOut(500);
        }, 8000);
    }
    
    /**
     * Generate export code
     */
    $generateExportBtn.on('click', function(e) {
        e.preventDefault();
        
        const postId = $('#wp-content-porter-post-select').val();
        
        if (!postId) {
            showNotice($exportNotice, wpContentPorterAdmin.i18n.exportError + ' ' + 
                       'Please select a post or page.', 'error');
            return;
        }
        
        // Disable button and show loading state
        $generateExportBtn.prop('disabled', true).text('Generating...');
        
        // AJAX request to generate export code
        $.ajax({
            url: wpContentPorterAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_content_porter_export',
                post_id: postId,
                nonce: wpContentPorterAdmin.nonce
            },
            success: function(response) {
                $generateExportBtn.prop('disabled', false).text('Generate Export Code');
                
                if (response.success && response.data.json) {
                    // Show export code
                    $exportCode.val(response.data.json);
                    $exportResult.show();
                    showNotice($exportNotice, wpContentPorterAdmin.i18n.exportSuccess, 'success');
                    
                    // Scroll to export code
                    $('html, body').animate({
                        scrollTop: $exportResult.offset().top - 100
                    }, 300);
                } else {
                    const errorMsg = response.data ? response.data.message : wpContentPorterAdmin.i18n.exportError;
                    showNotice($exportNotice, errorMsg, 'error');
                }
            },
            error: function() {
                $generateExportBtn.prop('disabled', false).text('Generate Export Code');
                showNotice($exportNotice, wpContentPorterAdmin.i18n.exportError, 'error');
            }
        });
    });
    
    /**
     * Copy export code to clipboard
     */
    $copyExportBtn.on('click', function(e) {
        e.preventDefault();
        
        $exportCode.select();
        
        try {
            // Copy to clipboard
            document.execCommand('copy');
            showNotice($exportNotice, wpContentPorterAdmin.i18n.copySuccess, 'success');
        } catch (err) {
            showNotice($exportNotice, wpContentPorterAdmin.i18n.copyError, 'error');
        }
    });
    
    /**
     * Validate import code
     */
    $validateImportBtn.on('click', function(e) {
        e.preventDefault();
        
        const importCode = $importCode.val();
        
        if (!importCode) {
            showNotice($importNotice, 'Please paste an export code.', 'error');
            return;
        }
        
        // Disable button and show loading state
        $validateImportBtn.prop('disabled', true).text('Validating...');
        
        // AJAX request to validate import code
        $.ajax({
            url: wpContentPorterAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_content_porter_validate_import',
                import_code: importCode,
                nonce: wpContentPorterAdmin.nonce
            },
            success: function(response) {
                $validateImportBtn.prop('disabled', false).text('Validate');
                
                if (response.success && response.data) {
                    // Show import preview
                    $previewTitle.text(response.data.title);
                    $previewType.text(response.data.type);
                    $previewMediaCount.text(response.data.media_count);
                    $importPreview.show();
                    
                    showNotice($importNotice, wpContentPorterAdmin.i18n.validationSuccess, 'success');
                    
                    // Scroll to preview
                    $('html, body').animate({
                        scrollTop: $importPreview.offset().top - 100
                    }, 300);
                } else {
                    const errorMsg = response.data ? response.data.message : wpContentPorterAdmin.i18n.validationError;
                    showNotice($importNotice, errorMsg, 'error');
                    $importPreview.hide();
                }
            },
            error: function() {
                $validateImportBtn.prop('disabled', false).text('Validate');
                showNotice($importNotice, wpContentPorterAdmin.i18n.validationError, 'error');
                $importPreview.hide();
            }
        });
    });
    
    /**
     * Import content
     */
    $importNowBtn.on('click', function(e) {
        e.preventDefault();
        
        const importCode = $importCode.val();
        
        if (!importCode) {
            showNotice($importNotice, 'Please paste an export code.', 'error');
            return;
        }
        
        // Disable button and show loading state
        $importNowBtn.prop('disabled', true).text('Importing...');
        
        // Show progress
        $importPreview.hide();
        $importProgress.show();
        $progressBar.css('width', '10%');
        $progressMessage.text(wpContentPorterAdmin.i18n.processingContent);
        
        // AJAX request to import content
        $.ajax({
            url: wpContentPorterAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wp_content_porter_import',
                import_code: importCode,
                nonce: wpContentPorterAdmin.nonce
            },
            success: function(response) {
                $importNowBtn.prop('disabled', false).text('Import Now');
                
                if (response.success && response.data) {
                    // Update progress
                    updateProgress(100, 'Import completed!');
                    
                    // Show result
                    $importResult.show();
                    $viewImportedBtn.attr('href', response.data.post_url);
                    
                    showNotice($importNotice, wpContentPorterAdmin.i18n.importSuccess, 'success');
                    
                    // Scroll to result
                    $('html, body').animate({
                        scrollTop: $importResult.offset().top - 100
                    }, 300);
                } else {
                    const errorMsg = response.data ? response.data.message : wpContentPorterAdmin.i18n.importError;
                    showNotice($importNotice, errorMsg, 'error');
                    $importProgress.hide();
                    $importPreview.show();
                }
            },
            error: function() {
                $importNowBtn.prop('disabled', false).text('Import Now');
                showNotice($importNotice, wpContentPorterAdmin.i18n.importError, 'error');
                $importProgress.hide();
                $importPreview.show();
            }
        });
    });
    
    /**
     * Update progress bar
     */
    function updateProgress(percent, message) {
        $progressBar.css('width', percent + '%');
        
        if (message) {
            $progressMessage.text(message);
        }
    }
    
    // Simulate progress updates
    function simulateProgressUpdates() {
        const steps = [
            { percent: 25, message: wpContentPorterAdmin.i18n.processingContent },
            { percent: 50, message: wpContentPorterAdmin.i18n.downloadingMedia },
            { percent: 75, message: 'Creating post and importing content...' },
            { percent: 90, message: 'Finalizing import...' }
        ];
        
        let currentStep = 0;
        
        const interval = setInterval(function() {
            if (currentStep < steps.length) {
                updateProgress(steps[currentStep].percent, steps[currentStep].message);
                currentStep++;
            } else {
                clearInterval(interval);
            }
        }, 1500);
    }
    
    // Start simulating progress updates when import begins
    $importNowBtn.on('click', function() {
        simulateProgressUpdates();
    });
});
