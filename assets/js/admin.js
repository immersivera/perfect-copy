/**
 * Perfect Copy Admin JavaScript
 */
jQuery(document).ready(function($) {
    // Export functionality
    const $generateExportBtn = $('#perfectcopy-generate-export');
    const $exportResult = $('#perfectcopy-export-result');
    const $exportCode = $('#perfectcopy-export-code');
    const $copyExportBtn = $('#perfectcopy-copy-export');
    const $saveExportBtn = $('#perfectcopy-save-export');
    const $exportNotice = $('#perfectcopy-export-notice');
    const $postTypeSelect = $('#perfectcopy-post-type');
    const $searchInput = $('#perfectcopy-search');
    const $contentGrid = $('#perfectcopy-content-grid');
    const $loadingIndicator = $('#perfectcopy-loading');
    const $selectedCount = $('#perfectcopy-selected-count');
    
    // Pagination tracking
    let currentPage = 1;
    let totalPages = 0;
    
    // Track selected content items
    let selectedItems = [];
    
    // Import functionality
    const $validateImportBtn = $('#perfectcopy-validate-import');
    const $importCode = $('#perfectcopy-import-code');
    const $importFile = $('#perfectcopy-import-file');
    const $importPreview = $('#perfectcopy-import-preview');
    const $previewTitle = $('#perfectcopy-preview-title');
    const $previewType = $('#perfectcopy-preview-type');
    const $previewMediaCount = $('#perfectcopy-preview-media-count');
    const $importNowBtn = $('#perfectcopy-import-now');
    const $importProgress = $('#perfectcopy-import-progress');
    const $progressBar = $('#perfectcopy-progress-bar');
    const $progressMessage = $('#perfectcopy-progress-message');
    const $importResult = $('#perfectcopy-import-result');
    const $viewImportedBtn = $('#perfectcopy-view-imported');
    const $importNotice = $('#perfectcopy-import-notice');
    
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
     * Handle post type dropdown change
     */
    $postTypeSelect.on('change', function() {
        // Get selected post type
        const postType = $(this).val();
        
        // Clear search input
        $searchInput.val('');
        
        // Reset to page 1 when changing post type
        currentPage = 1;
        
        // Load posts for this post type
        loadPosts(postType, '', currentPage);
    });
    
    /**
     * Handle search input
     */
    let searchTimer;
    $searchInput.on('input', function() {
        const searchTerm = $(this).val();
        
        // Get current post type from dropdown
        const postType = $postTypeSelect.val();
        
        // Clear previous timer
        clearTimeout(searchTimer);
        
        // Reset to page 1 when searching
        currentPage = 1;
        
        // Set new timer for debouncing
        searchTimer = setTimeout(function() {
            loadPosts(postType, searchTerm, currentPage);
        }, 300); // 300ms delay for debouncing
    });
    
    /**
     * Load posts for a post type with optional search and pagination
     */
    function loadPosts(postType, searchTerm = '', page = 1) {
        // Clear current content grid
        $contentGrid.empty();
        
        // Remember the current page
        currentPage = page;
        
        // Reset selected items if changing page or filters
        selectedItems = [];
        updateSelectedCount();
        
        // Show loading indicator
        $loadingIndicator.show();
        
        $.ajax({
            url: perfectcopyAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'perfectcopy_load_posts',
                post_type: postType,
                search: searchTerm,
                page: page,
                nonce: perfectcopyAdmin.nonce
            },
            success: function(response) {
                $loadingIndicator.hide();
                
                if (response.success && response.data) {
                    const data = response.data;
                    
                    // Handle no results
                    if (!data.posts || data.posts.length === 0) {
                        const emptyMessage = searchTerm 
                            ? 'No content found matching "' + searchTerm + '"' 
                            : 'No content found for this post type';
                            
                        $contentGrid.html('<div class="perfectcopy-empty-message">' + emptyMessage + '</div>');
                        showNotice($exportNotice, emptyMessage, 'info');
                    } else {
                        // Add content cards to grid
                        $.each(data.posts, function(index, post) {
                            const cardHtml = `
                                <div class="perfectcopy-content-card" data-id="${post.id}">
                                    <input type="checkbox" class="perfectcopy-card-checkbox" id="content-${post.id}" value="${post.id}">
                                    <div class="perfectcopy-card-info">
                                        <div class="perfectcopy-card-title">${post.title}</div>
                                        <div class="perfectcopy-card-meta">${post.meta || ''}</div>
                                    </div>
                                </div>
                            `;
                            $contentGrid.append(cardHtml);
                        });
                        
                        // Save pagination info
                        if (data.pagination) {
                            totalPages = data.pagination.total_pages;
                            currentPage = data.pagination.current_page;
                            
                            // Add pagination controls if more than one page
                            if (totalPages > 1) {
                                addPaginationControls(data.pagination, postType, searchTerm);
                            }
                        }
                        
                        // Add click handlers to cards
                        initializeCardHandlers();
                    }
                } else {
                    const errorMsg = response.data ? response.data.message : 'Error loading content.';
                    $contentGrid.html('<div class="perfectcopy-empty-message">' + errorMsg + '</div>');
                    showNotice($exportNotice, errorMsg, 'error');
                }
            },
            error: function() {
                $loadingIndicator.hide();
                $contentGrid.html('<div class="perfectcopy-empty-message">Error loading content.</div>');
                showNotice($exportNotice, 'Error loading content.', 'error');
            }
        });
    }
    
    /**
     * Add pagination controls to the content grid
     */
    function addPaginationControls(pagination, postType, searchTerm) {
        const currentPage = pagination.current_page;
        const totalPages = pagination.total_pages;
        const totalPosts = pagination.total_posts;
        
        // Create pagination container
        const paginationHtml = `
            <div class="perfectcopy-pagination">
                <div class="perfectcopy-pagination-info">
                    Showing page ${currentPage} of ${totalPages} (${totalPosts} total items)
                </div>
                <div class="perfectcopy-pagination-buttons">
                    ${currentPage > 1 ? '<button class="button perfectcopy-prev-page">Previous</button>' : ''}
                    ${currentPage < totalPages ? '<button class="button perfectcopy-next-page">Next</button>' : ''}
                </div>
            </div>
        `;
        
        $contentGrid.append(paginationHtml);
        
        // Add click handlers for pagination buttons
        $('.perfectcopy-prev-page').on('click', function() {
            if (currentPage > 1) {
                loadPosts(postType, searchTerm, currentPage - 1);
            }
        });
        
        $('.perfectcopy-next-page').on('click', function() {
            if (currentPage < totalPages) {
                loadPosts(postType, searchTerm, currentPage + 1);
            }
        });
    }
    
    /**
     * Initialize click handlers for content cards
     */
    function initializeCardHandlers() {
        // Handle checkbox clicks
        $('.perfectcopy-card-checkbox').on('change', function(e) {
            e.stopPropagation();
            const $card = $(this).closest('.perfectcopy-content-card');
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
        $('.perfectcopy-content-card').on('click', function(e) {
            if (!$(e.target).is('input')) {
                const $checkbox = $(this).find('.perfectcopy-card-checkbox');
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
    
    // Initialize the default tab if present
    if ($postTypeSelect.length > 0 && $contentGrid.length > 0) {
        loadPosts($postTypeSelect.val(), '', 1);
    }
    
    /**
     * Handle quick export links in post list table
     */
    $(document).on('click', '.perfectcopy-quick-export', function(e) {
        e.preventDefault();
        
        const url = $(this).attr('href');
        const postId = $(this).data('id');
        
        // Create modal dialog
        const $modal = $('<div class="perfectcopy-modal">' +
            '<div class="perfectcopy-modal-content">' +
                '<div class="perfectcopy-modal-header">' +
                    '<h3>' + perfectcopyAdmin.i18n.quickExportTitle + '</h3>' +
                    '<span class="perfectcopy-modal-close">&times;</span>' +
                '</div>' +
                '<div class="perfectcopy-modal-body">' +
                    '<p class="perfectcopy-modal-message">' + perfectcopyAdmin.i18n.exportGenerating + '</p>' +
                    '<div class="perfectcopy-modal-result" style="display:none;">' +
                        '<textarea readonly rows="8" class="perfectcopy-modal-code"></textarea>' +
                        '<div class="perfectcopy-modal-actions">' +
                            '<button class="button perfectcopy-modal-copy">' + perfectcopyAdmin.i18n.copyExport + '</button>' +
                            '<button class="button perfectcopy-modal-download">' + perfectcopyAdmin.i18n.saveExport + '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>');
        
        // Add modal to body
        $('body').append($modal);
        $modal.fadeIn(200);
        
        // Close modal on click
        $modal.find('.perfectcopy-modal-close').on('click', function() {
            $modal.fadeOut(200, function() {
                $modal.remove();
            });
        });
        
        // Make AJAX request to generate export
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Show success message
                    $modal.find('.perfectcopy-modal-message').text(data.message);
                    
                    // Fill the textarea with export code
                    $modal.find('.perfectcopy-modal-code').val(JSON.stringify(data.data, null, 2));
                    
                    // Show result container
                    $modal.find('.perfectcopy-modal-result').show();
                    
                    // Handle copy button
                    $modal.find('.perfectcopy-modal-copy').on('click', function() {
                        const $code = $modal.find('.perfectcopy-modal-code');
                        $code.select();
                        document.execCommand('copy');
                        $(this).text(perfectcopyAdmin.i18n.copied);
                        setTimeout(() => {
                            $(this).text(perfectcopyAdmin.i18n.copyExport);
                        }, 2000);
                    });
                    
                    // Handle download button
                    $modal.find('.perfectcopy-modal-download').on('click', function() {
                        const filename = 'perfectcopy-export-' + data.title.toLowerCase().replace(/[^a-z0-9]/g, '-') + '.json';
                        const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                        const link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = filename;
                        link.click();
                    });
                } else {
                    // Show error message
                    $modal.find('.perfectcopy-modal-message').text(response.data.message).addClass('error');
                }
            },
            error: function() {
                // Show error message
                $modal.find('.perfectcopy-modal-message').text(perfectcopyAdmin.i18n.exportError).addClass('error');
            }
        });
    });
    
    /**
     * Handle quick export button in post edit meta box
     */
    $(document).on('click', '.perfectcopy-quick-export-button', function() {
        const $button = $(this);
        const $result = $button.siblings('.perfectcopy-export-result');
        const url = $button.data('url');
        
        // Disable button and show loading
        $button.prop('disabled', true).text(perfectcopyAdmin.i18n.exportGenerating);
        
        // Make AJAX request
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Create export result content
                    $result.html(
                        '<p class="success">' + data.message + '</p>' +
                        '<textarea readonly rows="4" class="perfectcopy-export-code">' + JSON.stringify(data.data) + '</textarea>' +
                        '<button type="button" class="button button-small perfectcopy-copy-export-code">' + perfectcopyAdmin.i18n.copyExport + '</button>'
                    ).show();
                    
                    // Handle copy button click
                    $result.find('.perfectcopy-copy-export-code').on('click', function() {
                        const $code = $result.find('.perfectcopy-export-code');
                        $code.select();
                        document.execCommand('copy');
                        $(this).text(perfectcopyAdmin.i18n.copied);
                        setTimeout(() => {
                            $(this).text(perfectcopyAdmin.i18n.copyExport);
                        }, 2000);
                    });
                } else {
                    // Show error message
                    $result.html('<p class="error">' + response.data.message + '</p>').show();
                }
                
                // Re-enable button
                $button.prop('disabled', false).text(perfectcopyAdmin.i18n.exportContent);
            },
            error: function() {
                // Show error message
                $result.html('<p class="error">' + perfectcopyAdmin.i18n.exportError + '</p>').show();
                
                // Re-enable button
                $button.prop('disabled', false).text(perfectcopyAdmin.i18n.exportContent);
            }
        });
    });

    $generateExportBtn.on('click', function(e) {
        e.preventDefault();
        
        if (selectedItems.length === 0) {
            showNotice($exportNotice, perfectcopyAdmin.i18n.exportError + ' ' + 
                       'Please select content to export.', 'error');
            return;
        }
        
        // Disable button and show loading state
        $generateExportBtn.prop('disabled', true).text('Generating...');
        
        // Prepare data for batch or single export
        let ajaxData = {
            action: 'perfectcopy_export',
            nonce: perfectcopyAdmin.nonce
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
            url: perfectcopyAdmin.ajaxUrl,
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
                        showNotice($exportNotice, response.data.summary || perfectcopyAdmin.i18n.batchExportSuccess.replace('{count}', response.data.count), 'success');
                    } else {
                        showNotice($exportNotice, perfectcopyAdmin.i18n.exportSuccess, 'success');
                    }
                    
                    // Scroll to export code
                    $('html, body').animate({
                        scrollTop: $exportResult.offset().top - 100
                    }, 300);
                } else {
                    const errorMsg = response.data ? response.data.message : perfectcopyAdmin.i18n.exportError;
                    showNotice($exportNotice, errorMsg, 'error');
                }
            },
            error: function() {
                $generateExportBtn.prop('disabled', false).text('Generate Export Code');
                showNotice($exportNotice, perfectcopyAdmin.i18n.exportError, 'error');
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
            showNotice($exportNotice, perfectcopyAdmin.i18n.copySuccess, 'success');
        } catch (err) {
            showNotice($exportNotice, perfectcopyAdmin.i18n.copyError, 'error');
        }
    });
    
    /**
     * Save export code to file
     */
    $saveExportBtn.on('click', function(e) {
        e.preventDefault();
        
        const exportCode = $exportCode.val();
        
        if (!exportCode) {
            showNotice($exportNotice, perfectcopyAdmin.i18n.saveError, 'error');
            return;
        }
        
        // Get post title for filename
        const postTitle = $('#perfectcopy-post-select option:selected').text() || 'perfectcopy-export';
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
        
        showNotice($exportNotice, perfectcopyAdmin.i18n.saveSuccess, 'success');
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
            showNotice($importNotice, perfectcopyAdmin.i18n.fileReadError, 'error');
            return;
        }
        
        const reader = new FileReader();
        
        reader.onload = function(event) {
            try {
                // Try to parse JSON to validate format
                JSON.parse(event.target.result);
                
                // Set the textarea value
                $importCode.val(event.target.result);
                
                showNotice($importNotice, perfectcopyAdmin.i18n.fileReadSuccess, 'success');
            } catch (err) {
                showNotice($importNotice, perfectcopyAdmin.i18n.fileReadError, 'error');
            }
        };
        
        reader.onerror = function() {
            showNotice($importNotice, perfectcopyAdmin.i18n.fileReadError, 'error');
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
            url: perfectcopyAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'perfectcopy_validate_import',
                import_code: importCode,
                nonce: perfectcopyAdmin.nonce
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
                        showNotice($importNotice, perfectcopyAdmin.i18n.validationSuccess, 'success');
                    }
                    
                    // Scroll to preview
                    $('html, body').animate({
                        scrollTop: $importPreview.offset().top - 100
                    }, 300);
                } else {
                    const errorMsg = response.data ? response.data.message : perfectcopyAdmin.i18n.validationError;
                    showNotice($importNotice, errorMsg, 'error');
                    $importPreview.hide();
                }
            },
            error: function() {
                $validateImportBtn.prop('disabled', false).text('Validate');
                showNotice($importNotice, perfectcopyAdmin.i18n.validationError, 'error');
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
        $progressMessage.text(perfectcopyAdmin.i18n.processingContent);
        
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
            url: perfectcopyAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'perfectcopy_import',
                import_code: importCode,
                nonce: perfectcopyAdmin.nonce
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
                        showNotice($importNotice, perfectcopyAdmin.i18n.importSuccess, 'success');
                    }
                    
                    checkIfReadyToShowResult();
                } else {
                    const errorMsg = response.data ? response.data.message : perfectcopyAdmin.i18n.importError;
                    showNotice($importNotice, errorMsg, 'error');
                    $importProgress.hide();
                    $importPreview.show();
                }
            },
            error: function() {
                $importNowBtn.prop('disabled', false).text('Import Now');
                serverResponseReceived = true;
                showNotice($importNotice, perfectcopyAdmin.i18n.importError, 'error');
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
                    
                    $('#perfectcopy-import-result h3').text('Batch Import Complete');
                    $('#perfectcopy-import-result p').first().text(importCompletionMsg);
                    
                    // If we have errors, display them
                    if (responseData.error_count && responseData.error_count > 0) {
                        $('#perfectcopy-import-result').append('<p class="import-errors">' + 
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
                            $viewImportedBtn.attr('href', perfectcopyAdmin.adminUrl + 'edit.php?post_type=page');
                        } else {
                            $viewImportedBtn.attr('href', perfectcopyAdmin.adminUrl + 'edit.php');
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
            { percent: 25, message: perfectcopyAdmin.i18n.processingContent },
            { percent: 50, message: perfectcopyAdmin.i18n.downloadingMedia },
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
