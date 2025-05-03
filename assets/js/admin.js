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
    const $postTypeTab = $('.sitesync-cloner-tab');
    const $searchInput = $('#sitesync-cloner-search');
    const $contentGrid = $('#sitesync-cloner-content-grid');
    const $loadingIndicator = $('#sitesync-cloner-loading');
    const $selectedCount = $('#sitesync-cloner-selected-count');
    
    // Track selected content items
    let selectedItems = [];
    
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
     * Handle post type tab clicks
     */
    $postTypeTab.on('click', function(e) {
        e.preventDefault();
        
        // Update active tab
        $postTypeTab.removeClass('active');
        $(this).addClass('active');
        
        // Get selected post type
        const postType = $(this).data('post-type');
        
        // Clear search input
        $searchInput.val('');
        
        // Load posts for this post type
        loadPosts(postType, '');
    });
    
    /**
     * Handle search input
     */
    let searchTimer;
    $searchInput.on('input', function() {
        const searchTerm = $(this).val();
        
        // Get current post type
        const postType = $('.sitesync-cloner-tab.active').data('post-type');
        
        // Clear previous timer
        clearTimeout(searchTimer);
        
        // Set new timer for debouncing
        searchTimer = setTimeout(function() {
            loadPosts(postType, searchTerm);
        }, 300); // 300ms delay for debouncing
    });
    
    /**
     * Load posts for a post type with optional search
     */
    function loadPosts(postType, searchTerm = '') {
        // Clear current content grid
        $contentGrid.empty();
        
        // Reset selected items
        selectedItems = [];
        updateSelectedCount();
        
        // Show loading indicator
        $loadingIndicator.show();
        
        $.ajax({
            url: siteSyncClonerAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sitesync_cloner_load_posts',
                post_type: postType,
                search: searchTerm,
                nonce: siteSyncClonerAdmin.nonce
            },
            success: function(response) {
                $loadingIndicator.hide();
                
                if (response.success && response.data) {
                    // Handle no results
                    if (response.data.length === 0) {
                        const emptyMessage = searchTerm 
                            ? 'No content found matching "' + searchTerm + '"' 
                            : 'No content found for this post type';
                            
                        $contentGrid.html('<div class="sitesync-cloner-empty-message">' + emptyMessage + '</div>');
                        showNotice($exportNotice, emptyMessage, 'info');
                    } else {
                        // Add content cards to grid
                        $.each(response.data, function(index, post) {
                            const cardHtml = `
                                <div class="sitesync-cloner-content-card" data-id="${post.id}">
                                    <input type="checkbox" class="sitesync-cloner-card-checkbox" id="content-${post.id}" value="${post.id}">
                                    <div class="sitesync-cloner-card-info">
                                        <div class="sitesync-cloner-card-title">${post.title}</div>
                                        <div class="sitesync-cloner-card-meta">${post.meta || ''}</div>
                                    </div>
                                </div>
                            `;
                            $contentGrid.append(cardHtml);
                        });
                        
                        // Add click handlers to cards
                        initializeCardHandlers();
                    }
                } else {
                    const errorMsg = response.data ? response.data.message : 'Error loading content.';
                    $contentGrid.html('<div class="sitesync-cloner-empty-message">' + errorMsg + '</div>');
                    showNotice($exportNotice, errorMsg, 'error');
                }
            },
            error: function() {
                $loadingIndicator.hide();
                $contentGrid.html('<div class="sitesync-cloner-empty-message">Error loading content.</div>');
                showNotice($exportNotice, 'Error loading content.', 'error');
            }
        });
    }
    
    /**
     * Initialize click handlers for content cards
     */
    function initializeCardHandlers() {
        // Handle checkbox clicks
        $('.sitesync-cloner-card-checkbox').on('change', function(e) {
            e.stopPropagation();
            const $card = $(this).closest('.sitesync-cloner-content-card');
            const contentId = $card.data('id');
            
            if ($(this).is(':checked')) {
                $card.addClass('selected');
                addSelectedItem(contentId);
            } else {
                $card.removeClass('selected');
                removeSelectedItem(contentId);
            }
        });
        
        // Handle card clicks (except on the checkbox)
        $('.sitesync-cloner-content-card').on('click', function(e) {
            if (!$(e.target).is('input')) {
                const $checkbox = $(this).find('.sitesync-cloner-card-checkbox');
                $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
            }
        });
    }
    
    /**
     * Add an item to the selected items array
     */
    function addSelectedItem(id) {
        if (!selectedItems.includes(id)) {
            selectedItems.push(id);
            updateSelectedCount();
        }
    }
    
    /**
     * Remove an item from the selected items array
     */
    function removeSelectedItem(id) {
        const index = selectedItems.indexOf(id);
        if (index !== -1) {
            selectedItems.splice(index, 1);
            updateSelectedCount();
        }
    }
    
    /**
     * Update the selected items count display
     */
    function updateSelectedCount() {
        $selectedCount.text(selectedItems.length);
        
        // Enable/disable generate button based on selection
        if (selectedItems.length > 0) {
            $generateExportBtn.prop('disabled', false);
        } else {
            $generateExportBtn.prop('disabled', true);
        }
    }
    
    // Initialize with the first post type (posts)
    loadPosts('post', '');
    
    /**
     * Generate export code
     */
    $generateExportBtn.on('click', function(e) {
        e.preventDefault();
        
        if (selectedItems.length === 0) {
            showNotice($exportNotice, siteSyncClonerAdmin.i18n.exportError + ' ' + 
                       'Please select content to export.', 'error');
            return;
        }
        
        // Disable button and show loading state
        $generateExportBtn.prop('disabled', true).text('Generating...');
        
        // Prepare data for batch or single export
        let ajaxData = {
            action: 'sitesync_cloner_export',
            nonce: siteSyncClonerAdmin.nonce
        };
        
        if (selectedItems.length > 1) {
            // Use batch export
            ajaxData.post_ids = selectedItems;
        } else {
            // Use single export for backward compatibility
            ajaxData.post_id = selectedItems[0];
        }
        
        // AJAX request to generate export code
        $.ajax({
            url: siteSyncClonerAdmin.ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                $generateExportBtn.prop('disabled', false).text('Generate Export Code');
                
                if (response.success && response.data.json) {
                    // Show export code
                    $exportCode.val(response.data.json);
                    $exportResult.show();
                    // Enable save and copy buttons
                    $copyExportBtn.prop('disabled', false);
                    $saveExportBtn.prop('disabled', false);
                    
                    // Show appropriate success message
                    if (response.data.is_batch && response.data.count > 1) {
                        showNotice($exportNotice, response.data.summary || siteSyncClonerAdmin.i18n.batchExportSuccess.replace('{count}', response.data.count), 'success');
                    } else {
                        showNotice($exportNotice, siteSyncClonerAdmin.i18n.exportSuccess, 'success');
                    }
                    
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
                    // Check if it's a batch import
                    if (response.data.is_batch) {
                        // Show batch preview
                        $previewTitle.html('<strong>' + response.data.count + '</strong> items: ' + 
                                          response.data.titles.slice(0, 3).join(', ') + 
                                          (response.data.count > 3 ? '...' : ''));
                        $previewType.text(response.data.types.join(', '));
                        $previewMediaCount.text(response.data.media_count);
                        $importPreview.show();
                        showNotice($importNotice, response.data.summary, 'success');
                    } else {
                        // Show single item preview
                        $previewTitle.text(response.data.title);
                        $previewType.text(response.data.type);
                        $previewMediaCount.text(response.data.media_count);
                        $importPreview.show();
                        showNotice($importNotice, siteSyncClonerAdmin.i18n.validationSuccess, 'success');
                    }
                    
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
                    
                    // Display appropriate success message
                    if (response.data.is_batch) {
                        showNotice($importNotice, response.data.summary, 'success');
                    } else {
                        showNotice($importNotice, siteSyncClonerAdmin.i18n.importSuccess, 'success');
                    }
                    
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
                
                // Update result view based on whether it's a batch or single import
                if (responseData.is_batch) {
                    // Show batch import results
                    const successCount = responseData.success_count || 0;
                    const importCompletionMsg = successCount > 1 
                        ? 'Successfully imported ' + successCount + ' items!'
                        : 'Content imported successfully!';
                    
                    $('#sitesync-cloner-import-result h3').text('Batch Import Complete');
                    $('#sitesync-cloner-import-result p').first().text(importCompletionMsg);
                    
                    // If we have errors, display them
                    if (responseData.error_count && responseData.error_count > 0) {
                        $('#sitesync-cloner-import-result').append('<p class="import-errors">' + 
                                                                 responseData.error_count + ' items failed to import</p>');
                    }
                }
                
                // Set button URL and text based on import type
                if (responseData.is_batch) {
                    // For batch imports, use the view_url if available or fallback to admin posts/pages list
                    if (responseData.view_url) {
                        $viewImportedBtn.attr('href', responseData.view_url);
                    } else {
                        // Default to the post type listing in admin
                        const firstSuccessItem = responseData.success && responseData.success.length > 0 ? responseData.success[0] : null;
                        if (firstSuccessItem && firstSuccessItem.post_type === 'page') {
                            $viewImportedBtn.attr('href', siteSyncClonerAdmin.adminUrl + 'edit.php?post_type=page');
                        } else {
                            $viewImportedBtn.attr('href', siteSyncClonerAdmin.adminUrl + 'edit.php');
                        }
                    }
                    $viewImportedBtn.text('View Imported Content');
                } else {
                    // For single imports, use post_url (frontend) if available, otherwise edit_url
                    if (responseData.post_url) {
                        $viewImportedBtn.attr('href', responseData.post_url);
                        $viewImportedBtn.text('View Imported Content');
                    } else if (responseData.edit_url) {
                        $viewImportedBtn.attr('href', responseData.edit_url);
                        $viewImportedBtn.text('Edit Imported Content');
                    } else {
                        $viewImportedBtn.hide();
                    }
                }
                
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
