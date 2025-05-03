/**
 * SiteSync Cloner Admin JavaScript
 */
jQuery(document).ready(function($) {
    // Export functionality
    const $generateExportBtn = $('#sitesync-cloner-generate-export');
    const $exportResult = $('#sitesync-cloner-export-result');
    const $exportCode = $('#sitesync-cloner-export-code');
    const $copyExportBtn = $('#sitesync-cloner-copy-export');
    const $saveExportBtn = $('#sitesync-cloner-save-export');
    const $exportNotice = $('#sitesync-cloner-export-notice');
    
    // Import functionality
    const $validateImportBtn = $('#sitesync-cloner-validate-import');
    const $importCode = $('#sitesync-cloner-import-code');
    const $importFile = $('#sitesync-cloner-import-file');
    const $importPreview = $('#sitesync-cloner-import-preview');
    const $previewTitle = $('#sitesync-cloner-preview-title');
    const $previewType = $('#sitesync-cloner-preview-type');
    const $previewMediaCount = $('#sitesync-cloner-preview-media-count');
    const $importNowBtn = $('#sitesync-cloner-import-now');
    const $importProgress = $('#sitesync-cloner-import-progress');
    const $progressBar = $('#sitesync-cloner-progress-bar');
    const $progressMessage = $('#sitesync-cloner-progress-message');
    const $importResult = $('#sitesync-cloner-import-result');
    const $viewImportedBtn = $('#sitesync-cloner-view-imported');
    const $importNotice = $('#sitesync-cloner-import-notice');
    
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
        
        const postId = $('#sitesync-cloner-post-select').val();
        
        if (!postId) {
            showNotice($exportNotice, siteSyncClonerAdmin.i18n.exportError + ' ' + 
                       'Please select a post or page.', 'error');
            return;
        }
        
        // Disable button and show loading state
        $generateExportBtn.prop('disabled', true).text('Generating...');
        
        // AJAX request to generate export code
        $.ajax({
            url: siteSyncClonerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sitesync_cloner_export',
                post_id: postId,
                nonce: siteSyncClonerAdmin.nonce
            },
            success: function(response) {
                $generateExportBtn.prop('disabled', false).text('Generate Export Code');
                
                if (response.success && response.data.json) {
                    // Show export code
                    $exportCode.val(response.data.json);
                    $exportResult.show();
                    // Enable save and copy buttons
                    $copyExportBtn.prop('disabled', false);
                    $saveExportBtn.prop('disabled', false);
                    showNotice($exportNotice, siteSyncClonerAdmin.i18n.exportSuccess, 'success');
                    
                    // Scroll to export code
                    $('html, body').animate({
                        scrollTop: $exportResult.offset().top - 100
                    }, 300);
                } else {
                    const errorMsg = response.data ? response.data.message : siteSyncClonerAdmin.i18n.exportError;
                    showNotice($exportNotice, errorMsg, 'error');
                }
            },
            error: function() {
                $generateExportBtn.prop('disabled', false).text('Generate Export Code');
                showNotice($exportNotice, siteSyncClonerAdmin.i18n.exportError, 'error');
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
            showNotice($exportNotice, siteSyncClonerAdmin.i18n.copySuccess, 'success');
        } catch (err) {
            showNotice($exportNotice, siteSyncClonerAdmin.i18n.copyError, 'error');
        }
    });
    
    /**
     * Save export code to file
     */
    $saveExportBtn.on('click', function(e) {
        e.preventDefault();
        
        const exportCode = $exportCode.val();
        
        if (!exportCode) {
            showNotice($exportNotice, siteSyncClonerAdmin.i18n.saveError, 'error');
            return;
        }
        
        // Get post title for filename
        const postTitle = $('#sitesync-cloner-post-select option:selected').text() || 'sitesync-export';
        // Create sanitized filename
        const filename = postTitle.toLowerCase().replace(/[^a-z0-9]/g, '-') + '-' + 
                        new Date().toISOString().slice(0, 10) + '.json';
        
        // Create blob and download link
        const blob = new Blob([exportCode], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        
        // Cleanup
        setTimeout(function() {
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }, 0);
        
        showNotice($exportNotice, siteSyncClonerAdmin.i18n.saveSuccess, 'success');
    });
    
    /**
     * Handle file import
     */
    $importFile.on('change', function(e) {
        const file = e.target.files[0];
        
        if (!file) {
            return;
        }
        
        // Check if file is JSON
        if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
            showNotice($importNotice, siteSyncClonerAdmin.i18n.fileReadError, 'error');
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(event) {
            try {
                // Try to parse JSON to validate format
                JSON.parse(event.target.result);
                
                // Set the textarea value
                $importCode.val(event.target.result);
                
                showNotice($importNotice, siteSyncClonerAdmin.i18n.fileReadSuccess, 'success');
            } catch (err) {
                showNotice($importNotice, siteSyncClonerAdmin.i18n.fileReadError, 'error');
            }
        };
        
        reader.onerror = function() {
            showNotice($importNotice, siteSyncClonerAdmin.i18n.fileReadError, 'error');
        };
        
        reader.readAsText(file);
    });
    
    /**
     * Validate import code
     */
    $validateImportBtn.on('click', function(e) {
        e.preventDefault();
        
        const importCode = $importCode.val();
        
        if (!importCode) {
            showNotice($importNotice, 'Please enter import code or select a file.', 'error');
            return;
        }
        
        // Disable button and show loading state
        $validateImportBtn.prop('disabled', true).text('Validating...');
        
        // AJAX request to validate import code
        $.ajax({
            url: siteSyncClonerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sitesync_cloner_validate_import',
                import_code: importCode,
                nonce: siteSyncClonerAdmin.nonce
            },
            success: function(response) {
                $validateImportBtn.prop('disabled', false).text('Validate');
                
                if (response.success && response.data) {
                    // Show import preview
                    $previewTitle.text(response.data.title);
                    $previewType.text(response.data.type);
                    $previewMediaCount.text(response.data.media_count);
                    $importPreview.show();
                    
                    showNotice($importNotice, siteSyncClonerAdmin.i18n.validationSuccess, 'success');
                    
                    // Scroll to preview
                    $('html, body').animate({
                        scrollTop: $importPreview.offset().top - 100
                    }, 300);
                } else {
                    const errorMsg = response.data ? response.data.message : siteSyncClonerAdmin.i18n.validationError;
                    showNotice($importNotice, errorMsg, 'error');
                    $importPreview.hide();
                }
            },
            error: function() {
                $validateImportBtn.prop('disabled', false).text('Validate');
                showNotice($importNotice, siteSyncClonerAdmin.i18n.validationError, 'error');
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
        $importResult.hide(); // Ensure result is hidden before starting
        $progressBar.css('width', '10%');
        $progressMessage.text(siteSyncClonerAdmin.i18n.processingContent);
        
        // Initialize progress simulation
        let progressComplete = false;
        let serverResponseReceived = false;
        let responseData = null;
        
        // Start progress simulation
        simulateProgressUpdates(function() {
            progressComplete = true;
            checkIfReadyToShowResult();
        });
        
        // AJAX request to import content
        $.ajax({
            url: siteSyncClonerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sitesync_cloner_import',
                import_code: importCode,
                nonce: siteSyncClonerAdmin.nonce
            },
            success: function(response) {
                $importNowBtn.prop('disabled', false).text('Import Now');
                serverResponseReceived = true;
                
                if (response.success && response.data) {
                    responseData = response.data;
                    showNotice($importNotice, siteSyncClonerAdmin.i18n.importSuccess, 'success');
                    checkIfReadyToShowResult();
                } else {
                    const errorMsg = response.data ? response.data.message : siteSyncClonerAdmin.i18n.importError;
                    showNotice($importNotice, errorMsg, 'error');
                    $importProgress.hide();
                    $importPreview.show();
                }
            },
            error: function() {
                $importNowBtn.prop('disabled', false).text('Import Now');
                serverResponseReceived = true;
                showNotice($importNotice, siteSyncClonerAdmin.i18n.importError, 'error');
                $importProgress.hide();
                $importPreview.show();
            }
        });
        
        // Function to check if we can show the final result
        function checkIfReadyToShowResult() {
            if (progressComplete && serverResponseReceived && responseData) {
                // Both progress animation and server response are complete
                $importResult.show();
                
                // Use edit URL instead of view URL
                $viewImportedBtn.attr('href', responseData.edit_url);
                
                // Scroll to result
                $('html, body').animate({
                    scrollTop: $importResult.offset().top - 100
                }, 300);
            }
        }
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
    function simulateProgressUpdates(callback) {
        const steps = [
            { percent: 25, message: siteSyncClonerAdmin.i18n.processingContent },
            { percent: 50, message: siteSyncClonerAdmin.i18n.downloadingMedia },
            { percent: 75, message: 'Creating post and importing content...' },
            { percent: 90, message: 'Finalizing import...' },
            { percent: 100, message: 'Import completed!' }
        ];
        
        let currentStep = 0;
        
        const interval = setInterval(function() {
            if (currentStep < steps.length) {
                updateProgress(steps[currentStep].percent, steps[currentStep].message);
                currentStep++;
                
                // If we've reached the last step, trigger the callback after a small delay
                if (currentStep >= steps.length) {
                    setTimeout(function() {
                        clearInterval(interval);
                        if (callback && typeof callback === 'function') {
                            callback();
                        }
                    }, 800); // Small delay to let users see 100% completion
                }
            } else {
                clearInterval(interval);
            }
        }, 1500);
        
        return interval;
    }
});
