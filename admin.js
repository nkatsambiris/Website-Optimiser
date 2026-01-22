// Debug function to test if script is loaded
window.testH1Modal = function() {
    console.log('Test function called - script is loaded!');
    if (typeof window.showH1AnalysisModal === 'function') {
        console.log('showH1AnalysisModal function exists');
        window.showH1AnalysisModal();
    } else {
        console.log('showH1AnalysisModal function does not exist');
    }
};

// Debug function to test AJAX setup
window.testAjaxSetup = function() {
    console.log('Testing AJAX setup...');
    console.log('meta_description_boy_data:', typeof meta_description_boy_data !== 'undefined' ? meta_description_boy_data : 'UNDEFINED');

    if (typeof meta_description_boy_data === 'undefined') {
        console.error('meta_description_boy_data is not defined!');
        return;
    }

    // Test the refresh H1 analysis endpoint
    jQuery.ajax({
        type: 'POST',
        url: meta_description_boy_data.ajax_url,
        data: {
            action: 'meta_description_boy_refresh_h1_analysis',
            nonce: meta_description_boy_data.nonce
        },
        success: function(response) {
            console.log('Refresh H1 test response (raw):', response);
            console.log('Test response type:', typeof response);

            if (typeof response === 'string') {
                try {
                    response = JSON.parse(response);
                    console.log('Test parsed response:', response);
                } catch (e) {
                    console.error('Failed to parse test JSON response:', e);
                    return;
                }
            }

            console.log('Final test response:', response);
        },
        error: function(xhr, status, error) {
            console.error('Refresh H1 test error:', {xhr: xhr, status: status, error: error});
            console.error('Response text:', xhr.responseText);
        }
    });
};

jQuery(document).ready(function($) {

    // Function to add alt text generation button to media modal
    function addAltTextButton() {
        // Check if we're in the media modal and the button doesn't already exist
        if ($('.attachment-details .setting[data-setting="alt"]').length && !$('#generate-alt-text-btn').length) {
            var altSetting = $('.attachment-details .setting[data-setting="alt"]');
            var generateBtn = '<button type="button" id="generate-alt-text-btn" class="button">Generate Alt Text with AI</button>';
            altSetting.append(generateBtn);

            // Add click handler for the new button
            $('#generate-alt-text-btn').on('click', function(e) {
                e.preventDefault();
                generateAltText();
            });
        }
    }

    // Function to generate alt text in media modal
    function generateAltText() {
        var $button = $('#generate-alt-text-btn');
        var originalButtonText = $button.text();

        // Try multiple selectors for the alt text field
        var $altTextInput = $('.setting[data-setting="alt"] input[type="text"]');
        if (!$altTextInput.length) {
            $altTextInput = $('.setting[data-setting="alt"] textarea');
        }
        if (!$altTextInput.length) {
            $altTextInput = $('input[value=""][data-setting="alt"]');
        }
        if (!$altTextInput.length) {
            $altTextInput = $('.attachment-details input[placeholder*="alt"], .attachment-details textarea[placeholder*="alt"]');
        }
        if (!$altTextInput.length) {
            $altTextInput = $('.compat-field-alt_text input, .compat-field-alt_text textarea');
        }

        // Try to get attachment ID from various sources
        var attachmentId = null;

        // Method 1: From media modal
        if (typeof wp !== 'undefined' && wp.media && wp.media.frame && wp.media.frame.state) {
            var selection = wp.media.frame.state().get('selection');
            if (selection && selection.first) {
                var model = selection.first();
                if (model && model.get) {
                    attachmentId = model.get('id');
                }
            }
        }

        // Method 2: From URL parameters
        if (!attachmentId) {
            var urlParams = new URLSearchParams(window.location.search);
            attachmentId = urlParams.get('item') || urlParams.get('attachment_id');
        }

        // Method 3: From data attributes
        if (!attachmentId) {
            attachmentId = $button.data('attachment-id') || $('.attachment-details').data('attachment-id');
        }

        // Method 4: From hidden inputs
        if (!attachmentId) {
            attachmentId = $('input[name="attachment_id"]').val();
        }

        if (!attachmentId) {
            alert('Could not determine attachment ID. Please try refreshing the page.');
            return;
        }

        // Show loading state
        $button.html('<span class="spinner is-active" style="margin: 0; float: none;"></span> Generating...');
        $button.prop('disabled', true);

        // Make AJAX request
        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_generate_alt_text',
                attachment_id: attachmentId,
                nonce: meta_description_boy_data.nonce
            },
            success: function(response) {
                // Restore button
                $button.text(originalButtonText);
                $button.prop('disabled', false);

                if (response.success) {
                    var altText = response.data.alt_text;

                    // Update the alt text field with multiple approaches
                    if ($altTextInput.length) {
                        $altTextInput.val(altText);
                        $altTextInput.trigger('change');
                        $altTextInput.trigger('input');
                        $altTextInput.trigger('keyup');

                        // Force save in WordPress media modal
                        if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                            var selection = wp.media.frame.state().get('selection');
                            if (selection && selection.first) {
                                var model = selection.first();
                                if (model && model.set) {
                                    model.set('alt', altText);
                                }
                            }
                        }
                    } else {
                        // If field not found, save directly via AJAX
                        saveAltTextDirectly(attachmentId, altText);
                    }

                    // Show success message
                    var successMsg = $('<div class="notice notice-success" style="margin: 10px 0;"><p>Alt text generated successfully!</p></div>');
                    $button.after(successMsg);
                    setTimeout(function() {
                        successMsg.fadeOut();
                    }, 3000);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                $button.text(originalButtonText);
                $button.prop('disabled', false);
                alert('Network error occurred. Please try again.');
            }
        });
    }

    // Function to save alt text directly via AJAX when field update fails
    function saveAltTextDirectly(attachmentId, altText) {
        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_save_alt_text',
                attachment_id: attachmentId,
                alt_text: altText,
                nonce: meta_description_boy_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Alt text saved directly via AJAX');
                } else {
                    console.log('Failed to save alt text directly:', response.data.message);
                }
            },
            error: function() {
                console.log('Error saving alt text directly via AJAX');
            }
        });
    }

    // Check for media modal periodically and add button if needed
    var checkInterval = setInterval(function() {
        addAltTextButton();
    }, 500);

        // Bind to WordPress media modal events if available
    if (typeof wp !== 'undefined' && wp.media) {
        // Use MutationObserver to watch for media modal changes
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes) {
                    for (var i = 0; i < mutation.addedNodes.length; i++) {
                        var node = mutation.addedNodes[i];
                        if (node.nodeType === 1) { // Element node
                            if ($(node).hasClass('media-modal') || $(node).find('.media-modal').length) {
                                setTimeout(addAltTextButton, 500);
                            }
                            if ($(node).hasClass('attachment-details') || $(node).find('.attachment-details').length) {
                                setTimeout(addAltTextButton, 100);
                            }
                        }
                    }
                }
            });
        });

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Also check when clicking on media buttons
        $(document).on('click', '.media-button, .media-menu-item', function() {
            setTimeout(addAltTextButton, 1000);
        });
    }

    // Handle alt text generation on attachment edit pages
    $(document).on('click', '#generate_attachment_alt_text', function(e) {
        e.preventDefault();

        var $button = $(this);
        var attachmentId = $button.data('attachment-id');
        var originalButtonText = $button.text();

        // Show loading state
        $button.html('<span class="spinner is-active" style="margin: 0; float: none;"></span> Generating...');
        $button.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_generate_alt_text',
                attachment_id: attachmentId,
                nonce: meta_description_boy_data.nonce
            },
            success: function(response) {
                // Restore button
                $button.text(originalButtonText);
                $button.prop('disabled', false);

                var outputDiv = $('#alt_text_output');

                if (response.success) {
                    var altText = response.data.alt_text;

                    // Update the WordPress alt text field if it exists
                    var altInput = $('input[name="attachments[' + attachmentId + '][alt]"], #attachment_alt, input[name="_wp_attachment_image_alt"]');
                    if (altInput.length) {
                        altInput.val(altText);
                    }

                    // Show the generated alt text with option to save
                    outputDiv.html(
                        '<div class="notice notice-success"><p><strong>Generated Alt Text:</strong><br>' +
                        altText + '</p></div>' +
                        '<button id="save_alt_text" class="button button-secondary" data-alt-text="' +
                        altText.replace(/"/g, '&quot;') + '" data-attachment-id="' + attachmentId + '">' +
                        'Save Alt Text</button>'
                    );

                    // Handle save button click
                    $('#save_alt_text').on('click', function(e) {
                        e.preventDefault();
                        saveAltText($(this).data('attachment-id'), $(this).data('alt-text'));
                    });

                } else {
                    outputDiv.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $button.text(originalButtonText);
                $button.prop('disabled', false);
                $('#alt_text_output').html('<div class="notice notice-error"><p>Network error occurred. Please try again.</p></div>');
            }
        });
    });

    // Function to save alt text
    function saveAltText(attachmentId, altText) {
        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_save_alt_text',
                attachment_id: attachmentId,
                alt_text: altText,
                nonce: meta_description_boy_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#alt_text_output').append('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    // Update any alt text fields on the page
                    var altInput = $('input[name="attachments[' + attachmentId + '][alt]"], #attachment_alt, input[name="_wp_attachment_image_alt"]');
                    if (altInput.length) {
                        altInput.val(altText);
                    }
                } else {
                    $('#alt_text_output').append('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $('#alt_text_output').html('<div class="notice notice-error"><p>Error saving alt text.</p></div>');
            }
        });
    }

    // Meta Description Generation - Use event delegation to handle dynamically added elements
    $(document).on('click', '#meta_description_boy_generate_meta_description', function(e) {
        e.preventDefault();

        var $this = $(this); // Reference to the button
        var originalButtonText = $this.text(); // Store the original button text

        // Check if meta_description_boy_data is available
        if (typeof meta_description_boy_data === 'undefined') {
            console.error('meta_description_boy_data is not defined');
            alert('Script configuration error. Please refresh the page and try again.');
            return;
        }

        // Display spinner inside the button and disable it
        $this.html('<span class="spinner is-active" style="margin: 0; float: none;"></span> Generating...');
        $this.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_generate_description',
                post_id: meta_description_boy_data.post_id,
                nonce: meta_description_boy_data.nonce
            },
            success: function(response) {
                // Restore the button to its original state
                $this.text(originalButtonText);
                $this.prop('disabled', false);

                var outputDiv = $('#meta_description_boy_output');

                if (response.success) {
                    // Get the meta description content
                    var metaDescription = response.data.description;

                    // Create the success notice and the Copy to Clipboard button
                    outputDiv.html('<div class="notice notice-success"><p>' + metaDescription + '</p></div><button id="copyToClipboard" class="button tagadd">Copy to Clipboard</button>');

                    // Event listener for the Copy to Clipboard button
                    $('#copyToClipboard').click(function(e) {
                        e.preventDefault();  // Prevent the default behavior of the button
                        var $copyButton = $(this); // Reference to the copy button

                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(metaDescription).then(function() {
                                $copyButton.text('Copied!'); // Update the button text
                            }).catch(function(err) {
                                console.error('Could not copy text: ', err);
                            });
                        } else {
                            // Browsers that don't support the Clipboard API
                            alert('Your browser does not support direct clipboard copy. Please manually copy the meta description.');
                        }
                    });

                } else {
                    outputDiv.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error); // Debug log

                // Restore the button to its original state in case of an AJAX error
                $this.text(originalButtonText);
                $this.prop('disabled', false);

                var outputDiv = $('#meta_description_boy_output');
                outputDiv.html('<div class="notice notice-error"><p>Network error occurred. Please try again. Error: ' + error + '</p></div>');
            }
        });
    });


    if (typeof meta_description_boy_data !== 'undefined') {
        console.log('meta_description_boy_data:', meta_description_boy_data);
    }


    // Handle H1 analysis refresh button
    $(document).on('click', '#refresh-h1-analysis', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalButtonText = $button.text();

        // Check if meta_description_boy_data is available
        if (typeof meta_description_boy_data === 'undefined') {
            console.error('meta_description_boy_data is not defined');
            alert('Script configuration error. Please refresh the page and try again.');
            return;
        }

        // Show loading state
        $button.html('<span class="spinner is-active" style="margin: 0; float: none;"></span> Refreshing...');
        $button.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_refresh_h1_analysis',
                nonce: meta_description_boy_data.nonce
            },
                        success: function(response) {
                console.log('H1 refresh response (raw):', response);
                console.log('H1 refresh response type:', typeof response);

                // Parse response if it's a string
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                        console.log('H1 refresh parsed response:', response);
                    } catch (e) {
                        console.error('Failed to parse H1 refresh JSON response:', e);
                        alert('Invalid response from server');
                        $button.text(originalButtonText);
                        $button.prop('disabled', false);
                        return;
                    }
                }

                // Restore button state first
                $button.text(originalButtonText);
                $button.prop('disabled', false);

                                                try {
                    console.log('Processing response - success:', response.success, 'has stats:', !!response.stats);

                    if (response.success && response.stats) {
                        // Update the dashboard display with new stats
                        console.log('Updating H1 display with new stats:', response.stats);
                        updateH1Display(response.stats);

                        // Show the detailed H1 analysis modal with results
                        console.log('Opening H1 analysis modal...');
                        if (typeof window.showH1AnalysisModal === 'function') {
                            // Pass the detailed results to the modal
                            window.showH1AnalysisModal(response.detailed_results || null);
                        } else {
                            console.warn('showH1AnalysisModal function not found');
                            // Show success message as fallback
                            var successMessage = response.message || 'H1 analysis completed successfully';
                            showNotification(successMessage, 'success');
                        }
                    } else {
                        console.error('Response validation failed:', response);
                        var errorMessage = response.message || (response.data && response.data.message) || 'Unknown error';
                        alert('Error: ' + errorMessage);
                    }
                } catch (e) {
                    console.error('Error in H1 refresh success handler:', e);
                    console.error('Response that caused error:', response);
                    alert('An error occurred while processing the response. Check console for details.');
                }
            },
            error: function(xhr, status, error) {
                console.error('H1 refresh error:', {xhr: xhr, status: status, error: error});
                console.error('H1 refresh response text:', xhr.responseText);
                alert('Network error occurred. Please try again. Check console for details.');

                // Restore button state
                $button.text(originalButtonText);
                $button.prop('disabled', false);
            }
        });
    });

    // Handle H1 analysis view button
    $(document).on('click', '#view-h1-analysis', function(e) {
        e.preventDefault();

        // Check if meta_description_boy_data is available
        if (typeof meta_description_boy_data === 'undefined') {
            console.error('meta_description_boy_data is not defined');
            alert('Script configuration error. Please refresh the page and try again.');
            return;
        }

        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_get_saved_h1_analysis',
                nonce: meta_description_boy_data.nonce
            },
            success: function(response) {
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        alert('Invalid response from server');
                        return;
                    }
                }

                if (!response.success || !response.data) {
                    var errorMessage = response.message || (response.data && response.data.message) || 'No saved analysis found.';
                    alert(errorMessage);
                    return;
                }

                var results = response.data.results || [];
                if (!results.length) {
                    alert('No saved analysis found. Please run the analysis first.');
                    return;
                }

                if (typeof window.showH1AnalysisModal === 'function') {
                    window.showH1AnalysisModal(results);
                } else {
                    alert('Unable to open analysis modal. Please refresh the page.');
                }
            },
            error: function() {
                alert('Network error occurred. Please try again.');
            }
        });
    });

    // Helper function to update H1 display on dashboard
    function updateH1Display(stats) {
        var $h1Section = $('.seo-stat-item').filter(function() {
            return $(this).find('h4').text().trim() === 'H1 Headings';
        });

        if ($h1Section.length === 0) {
            console.error('H1 section not found on dashboard');
            return;
        }

        // Determine status class and text
        var statusClass = '';
        var statusText = '';
        if (stats.percentage >= 100) {
            statusClass = 'status-good';
            statusText = 'All Correct';
        } else if (stats.percentage >= 80) {
            statusClass = 'status-warning';
            statusText = 'Nearly Complete';
        } else {
            statusClass = 'status-error';
            statusText = 'Issues Found';
        }

        // Update status class on the main container
        $h1Section.removeClass('status-good status-warning status-error').addClass(statusClass);

        // Update status text
        $h1Section.find('.stat-status').removeClass('status-good status-warning status-error').addClass(statusClass).text(statusText);

        // Update statistics
        $h1Section.find('.stat-number').text(stats.correct + '/' + stats.total);
        $h1Section.find('.stat-label').text(Math.round(stats.percentage * 10) / 10 + '% correct');

        // Update action buttons
        var $actionDiv = $h1Section.find('.stat-action');
        var refreshButton = '<button id="refresh-h1-analysis" class="button button-small" style="margin-bottom: 5px;">🔄 Refresh</button>';
        var viewButton = '<button id="view-h1-analysis" class="button button-small" style="margin-left: 2px;">📄 View Last Analysis</button>';

        var issueButtons = '';
        if (stats.issues > 0) {
            var adminUrl = (typeof meta_description_boy_data !== 'undefined' && meta_description_boy_data.admin_url)
                ? meta_description_boy_data.admin_url
                : '/wp-admin/';

            if (stats.no_h1 > 0) {
                issueButtons += '<a href="' + adminUrl + 'edit.php?h1_missing=1" class="button button-small" style="margin-left: 2px;">No H1 (' + stats.no_h1 + ')</a>';
            }
            if (stats.multiple_h1 > 0) {
                issueButtons += '<a href="' + adminUrl + 'edit.php?h1_multiple=1" class="button button-small" style="margin-left: 2px;">Multiple H1 (' + stats.multiple_h1 + ')</a>';
            }
        }

        $actionDiv.html(refreshButton + viewButton + issueButtons);
    }

    // Helper function to show notifications
    function showNotification(message, type) {
        type = type || 'info';

        // Create notification element
        var $notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

        // Add to top of page
        $('.wrap h1').first().after($notification);

        // Auto-remove after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);

        // Make it dismissible
        $notification.on('click', '.notice-dismiss', function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }

    // Bulk Alt Text Generation Variables
    var bulkProcessing = false;
    var imagesToProcess = [];
    var currentImageIndex = 0;
    var processedCount = 0;
    var successCount = 0;
    var errorCount = 0;

    // Handle bulk alt text generation start
    $(document).on('click', '#bulk_alt_text_generate', function(e) {
        e.preventDefault();

        if (bulkProcessing) {
            console.log('Bulk processing already in progress, ignoring click');
            return;
        }

        var $button = $(this);
        var $stopButton = $('#bulk_alt_text_stop');
        var $progressDiv = $('#bulk_progress');
        var $tableContainer = $('#bulk_status_table_container');
        var $tbody = $('#bulk_status_tbody');

        // Check if meta_description_boy_data is available
        if (typeof meta_description_boy_data === 'undefined') {
            console.error('meta_description_boy_data is not defined');
            alert('Script configuration error. Please refresh the page and try again.');
            return;
        }

        // Reset variables
        bulkProcessing = true;
        currentImageIndex = 0;
        processedCount = 0;
        successCount = 0;
        errorCount = 0;

        // Update UI
        $button.prop('disabled', true);
        $stopButton.show();
        $progressDiv.show();
        $tableContainer.show();
        $tbody.empty();

        // Get list of images without alt text
        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_get_images_without_alt_text',
                nonce: meta_description_boy_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    imagesToProcess = response.data.images;
                    var totalImages = response.data.total;

                    if (totalImages === 0) {
                        alert('No images found that need alt text generation.');
                        resetBulkInterface();
                        return;
                    }

                    $('#progress_text').text('Found ' + totalImages + ' images without alt text. Starting generation...');

                    // Add rows to status table
                    imagesToProcess.forEach(function(image) {
                        var row = '<tr id="row_' + image.id + '">' +
                            '<td><img src="' + image.thumbnail + '" style="width: 50px; height: 50px; object-fit: cover;" alt="Thumbnail"></td>' +
                            '<td>' + image.title + '</td>' +
                            '<td class="status">Pending</td>' +
                            '<td class="result">-</td>' +
                            '</tr>';
                        $tbody.append(row);
                    });

                    // Start processing
                    processNextImage();
                } else {
                    alert('Error: ' + response.data.message);
                    resetBulkInterface();
                }
            },
            error: function() {
                alert('Network error occurred. Please try again.');
                resetBulkInterface();
            }
        });
    });

    // Handle bulk alt text generation stop
    $(document).on('click', '#bulk_alt_text_stop', function(e) {
        e.preventDefault();
        bulkProcessing = false;
        resetBulkInterface();
        $('#progress_text').text('Stopped by user. Processed ' + processedCount + ' of ' + imagesToProcess.length + ' images.');
    });

    // Process next image in queue
    function processNextImage() {
        if (!bulkProcessing || currentImageIndex >= imagesToProcess.length) {
            // Processing complete
            bulkProcessing = false;
            resetBulkInterface();
            $('#progress_text').text('Complete! Processed ' + processedCount + ' images. Success: ' + successCount + ', Errors: ' + errorCount);
            return;
        }

        var image = imagesToProcess[currentImageIndex];
        var $row = $('#row_' + image.id);
        var $statusCell = $row.find('.status');
        var $resultCell = $row.find('.result');

        // Update status
        $statusCell.html('<span class="spinner is-active" style="margin: 0;"></span> Processing');

        // Update progress
        var progressPercent = Math.round((currentImageIndex / imagesToProcess.length) * 100);
        $('#progress_bar').css('width', progressPercent + '%');
        $('#progress_text').text('Processing image ' + (currentImageIndex + 1) + ' of ' + imagesToProcess.length + ' - ' + image.title);

        // Scroll to current row
        $row[0].scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Process the image
        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_bulk_process_single_image',
                attachment_id: image.id,
                nonce: meta_description_boy_data.nonce
            },
            success: function(response) {
                processedCount++;

                if (response.success) {
                    successCount++;
                    $statusCell.html('<span style="color: green;">✓ Success</span>');
                    $resultCell.text(response.data.alt_text);
                } else {
                    errorCount++;
                    $statusCell.html('<span style="color: red;">✗ Error</span>');
                    $resultCell.text(response.data.message);
                }

                // Process next image after a short delay
                currentImageIndex++;
                setTimeout(function() {
                    if (bulkProcessing) {
                        processNextImage();
                    }
                }, 1000); // 1 second delay between requests to avoid rate limiting
            },
            error: function() {
                processedCount++;
                errorCount++;
                $statusCell.html('<span style="color: red;">✗ Error</span>');
                $resultCell.text('Network error');

                // Process next image
                currentImageIndex++;
                setTimeout(function() {
                    if (bulkProcessing) {
                        processNextImage();
                    }
                }, 1000);
            }
        });
    }

    // Reset bulk processing interface
    function resetBulkInterface() {
        $('#bulk_alt_text_generate').prop('disabled', false);
        $('#bulk_alt_text_stop').hide();
        $('#progress_bar').css('width', '100%');
    }

    // H1 Analysis Modal Functions
    var h1DetailedResultsCache = null;

    function calculateH1Summary(results) {
        var summary = {
            correct: 0,
            no_h1: 0,
            multiple_h1: 0,
            total: 0
        };

        if (!results || !results.length) {
            return summary;
        }

        results.forEach(function(result) {
            if (result.is_excluded) {
                return;
            }

            summary.total++;

            if (result.h1_count === 0) {
                summary.no_h1++;
            } else if (result.h1_count === 1) {
                summary.correct++;
            } else if (result.h1_count !== null && result.h1_count !== undefined) {
                summary.multiple_h1++;
            }
        });

        return summary;
    }

    function calculateH1SummaryFromTable() {
        var summary = {
            correct: 0,
            no_h1: 0,
            multiple_h1: 0,
            total: 0
        };

        $('#h1-results-tbody tr').each(function() {
            var $row = $(this);
            var isExcluded = $row.find('.h1-exclude-checkbox').prop('checked');
            if (isExcluded) {
                return;
            }

            var status = $row.data('h1Status');
            if (!status) {
                return;
            }

            summary.total++;

            if (status === 'No H1') {
                summary.no_h1++;
            } else if (status === 'Correct') {
                summary.correct++;
            } else if (status === 'Multiple H1') {
                summary.multiple_h1++;
            }
        });

        return summary;
    }
    window.showH1AnalysisModal = function(detailedResults) {
        console.log('showH1AnalysisModal called - NEW VERSION', detailedResults ? 'with pre-calculated results' : 'without results');

        // Create modal HTML if it doesn't exist
        if (!$('#h1-analysis-modal').length) {
            console.log('Creating modal HTML...');
            var modalHTML = '<div id="h1-analysis-modal" class="h1-modal-overlay" style="display: none;">' +
                '<div class="h1-modal-content">' +
                    '<div class="h1-modal-header">' +
                        '<h2>H1 Headings Analysis</h2>' +
                        '<span class="h1-modal-close">&times;</span>' +
                    '</div>' +
                    '<div class="h1-modal-body">' +
                        '<div id="h1-analysis-progress" style="display: none;">' +
                            '<div class="h1-progress-bar-container">' +
                                '<div class="h1-progress-bar" id="h1-progress-bar"></div>' +
                            '</div>' +
                            '<p id="h1-progress-text">Analyzing posts...</p>' +
                        '</div>' +
                        '<div id="h1-analysis-results" style="display: none;">' +
                            '<div class="h1-summary"></div>' +
                            '<table class="h1-results-table">' +
                                '<thead>' +
                                    '<tr>' +
                                        '<th>Post ID</th>' +
                                        '<th>Title</th>' +
                                        '<th>H1 Count</th>' +
                                        '<th>Status</th>' +
                                        '<th>Action</th>' +
                                        '<th>Exclude</th>' +
                                    '</tr>' +
                                '</thead>' +
                                '<tbody id="h1-results-tbody">' +
                                '</tbody>' +
                            '</table>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';

            $('body').append(modalHTML);
            console.log('Modal HTML appended to body');
        }

        // Show modal and start analysis
        console.log('Showing modal...');
        $('#h1-analysis-modal').show();

        if (detailedResults) {
            // Use pre-calculated results
            console.log('Using pre-calculated results');
            $('#h1-analysis-progress').hide();
            $('#h1-analysis-results').show();
            displayH1Results(detailedResults);
        } else {
            // Run analysis (legacy behavior)
            console.log('Running fresh analysis');
            h1DetailedResultsCache = null;
            $('#h1-analysis-progress').show();
            $('#h1-analysis-results').hide();
            startH1Analysis();
        }

        console.log('Modal should be visible now');
    };

    function displayH1Results(detailedResults) {
        console.log('displayH1Results called with', detailedResults.length, 'results');

        var $resultsDiv = $('#h1-analysis-results');
        var $tbody = $('#h1-results-tbody');
        var $summary = $('.h1-summary');

        h1DetailedResultsCache = detailedResults;

        // Clear existing results
        $tbody.empty();

        // Process each result and add to table
        detailedResults.forEach(function(result) {
            // Add row to table with debug button for multiple H1s
            var debugButton = '';
            if (result.h1_count !== 1 && !result.is_excluded) {
                debugButton = ' <button class="button button-small h1-debug-btn" data-post-id="' + result.id + '">🐛 Debug</button>';
            }
            var h1CountDisplay = (result.h1_count === null || result.h1_count === undefined) ? '-' : result.h1_count;
            var rowClass = result.is_excluded ? ' class="h1-excluded-row"' : '';
            var excludeCell = '<td><label class="h1-exclude-label"><input type="checkbox" class="h1-exclude-checkbox" data-post-id="' + result.id + '"' + (result.is_excluded ? ' checked' : '') + '> Exclude</label></td>';

            var row = '<tr' + rowClass + ' data-h1-status="' + result.status + '">' +
                '<td>' + result.id + '</td>' +
                '<td><strong>' + result.title + '</strong></td>' +
                '<td>' + h1CountDisplay + '</td>' +
                '<td class="' + result.status_class + '">' + result.status + '</td>' +
                '<td><a href="' + result.edit_url + '" target="_blank" class="button button-small">Edit</a>' + debugButton + '</td>' +
                excludeCell +
            '</tr>';
            $tbody.append(row);
        });

        var results = calculateH1Summary(detailedResults);

        // Show results using the same function as the progressive analysis
        showH1Results(results);
    }

    function startH1Analysis() {
        console.log('startH1Analysis called');

        var $progressBar = $('#h1-progress-bar');
        var $progressText = $('#h1-progress-text');
        var $resultsDiv = $('#h1-analysis-results');
        var $tbody = $('#h1-results-tbody');
        var $summary = $('.h1-summary');

        console.log('Progress elements found:', {
            progressBar: $progressBar.length,
            progressText: $progressText.length,
            resultsDiv: $resultsDiv.length,
            tbody: $tbody.length,
            summary: $summary.length
        });

        // Reset progress
        $progressBar.css('width', '0%');
        $tbody.empty();

        console.log('About to make AJAX call for posts...');

        // Get posts to analyze
        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_get_posts_for_h1_analysis',
                nonce: meta_description_boy_data.nonce
            },
            success: function(response) {
                console.log('Get posts response (raw):', response);
                console.log('Response type:', typeof response);

                // Parse response if it's a string
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                        console.log('Parsed response:', response);
                    } catch (e) {
                        console.error('Failed to parse JSON response:', e);
                        $progressText.text('Invalid response from server');
                        return;
                    }
                }

                if (response.success) {
                    var posts = response.data.posts;
                    var totalPosts = posts.length;

                    if (totalPosts === 0) {
                        $progressText.text('No posts found to analyze');
                        return;
                    }

                    var currentIndex = 0;
                    var startTime = new Date();
                    var results = {
                        correct: 0,
                        no_h1: 0,
                        multiple_h1: 0,
                        total: totalPosts
                    };

                                            function analyzeNextPost() {
                            if (currentIndex >= totalPosts) {
                                // Analysis complete
                                var endTime = new Date();
                                var duration = Math.round((endTime - startTime) / 1000);
                                results.duration = duration;
                                showH1Results(results);
                                return;
                            }

                        var post = posts[currentIndex];
                        var progress = Math.round((currentIndex / totalPosts) * 100);

                        $progressBar.css('width', progress + '%');
                        $progressText.text('Analyzing post ' + (currentIndex + 1) + ' of ' + totalPosts + ': ' + post.title);

                        // Analyze individual post
                        $.ajax({
                            type: 'POST',
                            url: meta_description_boy_data.ajax_url,
                            data: {
                                action: 'meta_description_boy_analyze_single_post_h1',
                                post_id: post.id,
                                nonce: meta_description_boy_data.nonce
                            },
                            success: function(response) {
                                console.log('Single post analysis response (raw):', response);

                                // Parse response if it's a string
                                if (typeof response === 'string') {
                                    try {
                                        response = JSON.parse(response);
                                        console.log('Single post analysis parsed response:', response);
                                    } catch (e) {
                                        console.error('Failed to parse single post JSON response:', e);
                                        // Add error row and continue
                                        var row = '<tr data-h1-status="">' +
                                            '<td>' + post.id + '</td>' +
                                            '<td><strong>' + post.title + '</strong></td>' +
                                            '<td>-</td>' +
                                            '<td class="h1-status-error">Parse Error</td>' +
                                            '<td><a href="' + post.edit_url + '" target="_blank" class="button button-small">Edit</a></td>' +
                                            '<td><span class="h1-exclude-disabled">Unavailable</span></td>' +
                                        '</tr>';
                                        $tbody.append(row);
                                        currentIndex++;
                                        setTimeout(analyzeNextPost, 500);
                                        return;
                                    }
                                }

                                if (response.success) {
                                    var h1Count = response.data.h1_count;
                                    var status = '';
                                    var statusClass = '';

                                    if (h1Count === 0) {
                                        status = 'No H1';
                                        statusClass = 'h1-status-error';
                                        results.no_h1++;
                                    } else if (h1Count === 1) {
                                        status = 'Correct';
                                        statusClass = 'h1-status-success';
                                        results.correct++;
                                    } else {
                                        status = 'Multiple H1';
                                        statusClass = 'h1-status-error';
                                        results.multiple_h1++;
                                    }

                                    // Add row to table
                                    // Add debug button for issues
                                    var debugButton = '';
                                    if (h1Count !== 1) {
                                        debugButton = ' <button class="button button-small h1-debug-btn" data-post-id="' + post.id + '">🐛 Debug</button>';
                                    }
                                    var excludeCell = '<td><label class="h1-exclude-label"><input type="checkbox" class="h1-exclude-checkbox" data-post-id="' + post.id + '"> Exclude</label></td>';
                                    var row = '<tr data-h1-status="' + status + '">' +
                                        '<td>' + post.id + '</td>' +
                                        '<td><strong>' + post.title + '</strong></td>' +
                                        '<td>' + h1Count + '</td>' +
                                        '<td class="' + statusClass + '">' + status + '</td>' +
                                        '<td><a href="' + post.edit_url + '" target="_blank" class="button button-small">Edit</a>' + debugButton + '</td>' +
                                        excludeCell +
                                    '</tr>';
                                    $tbody.append(row);
                                } else {
                                    // Error analyzing post
                                    var row = '<tr data-h1-status="">' +
                                        '<td>' + post.id + '</td>' +
                                        '<td><strong>' + post.title + '</strong></td>' +
                                        '<td>-</td>' +
                                        '<td class="h1-status-error">Error</td>' +
                                        '<td><a href="' + post.edit_url + '" target="_blank" class="button button-small">Edit</a></td>' +
                                        '<td><span class="h1-exclude-disabled">Unavailable</span></td>' +
                                    '</tr>';
                                    $tbody.append(row);
                                }

                                currentIndex++;
                                setTimeout(analyzeNextPost, 500); // Small delay between requests
                            },
                            error: function() {
                                // Network error
                                var row = '<tr data-h1-status="">' +
                                    '<td>' + post.id + '</td>' +
                                    '<td><strong>' + post.title + '</strong></td>' +
                                    '<td>-</td>' +
                                    '<td class="h1-status-error">Network Error</td>' +
                                    '<td><a href="' + post.edit_url + '" target="_blank" class="button button-small">Edit</a></td>' +
                                    '<td><span class="h1-exclude-disabled">Unavailable</span></td>' +
                                '</tr>';
                                $tbody.append(row);

                                currentIndex++;
                                setTimeout(analyzeNextPost, 500);
                            }
                        });
                    }

                    analyzeNextPost();
                } else {
                    var errorMsg = response.message || response.data?.message || 'Unknown error';
                    console.error('Error getting posts:', response);
                    $progressText.text('Error getting posts: ' + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('Network error getting posts:', {xhr: xhr, status: status, error: error});
                console.error('Response text:', xhr.responseText);
                $progressText.text('Network error occurred: ' + error);
            }
        });
    }

    function showH1Results(results) {
        var $progressDiv = $('#h1-analysis-progress');
        var $resultsDiv = $('#h1-analysis-results');
        var $summary = $('.h1-summary');

        // Hide progress, show results
        $progressDiv.hide();
        $resultsDiv.show();

        // Calculate percentage
        var percentage = results.total > 0 ? Math.round((results.correct / results.total) * 100) : 0;

        // Show summary
        var summaryHTML = '<div class="h1-summary-stats">' +
            '<div class="h1-stat-item h1-stat-total">' +
                '<span class="h1-stat-number">' + results.total + '</span>' +
                '<span class="h1-stat-label">Total Posts</span>' +
            '</div>' +
            '<div class="h1-stat-item h1-stat-correct">' +
                '<span class="h1-stat-number">' + results.correct + '</span>' +
                '<span class="h1-stat-label">Correct (1 H1)</span>' +
            '</div>' +
            '<div class="h1-stat-item h1-stat-no-h1">' +
                '<span class="h1-stat-number">' + results.no_h1 + '</span>' +
                '<span class="h1-stat-label">No H1</span>' +
            '</div>' +
            '<div class="h1-stat-item h1-stat-multiple">' +
                '<span class="h1-stat-number">' + results.multiple_h1 + '</span>' +
                '<span class="h1-stat-label">Multiple H1</span>' +
            '</div>' +
            '<div class="h1-stat-item h1-stat-percentage">' +
                '<span class="h1-stat-number">' + percentage + '%</span>' +
                '<span class="h1-stat-label">Correct</span>' +
            '</div>' +
        '</div>';

        $summary.html(summaryHTML);

        // Add completion message and action buttons
        var completionTime = results.duration ? ' (completed in ' + results.duration + ' seconds)' : '';
        $resultsDiv.find('.h1-actions').remove();

        var actionButtons = '<div class="h1-actions">' +
            '<p class="h1-actions-status">✓ Analysis Complete' + completionTime + '</p>' +
            '<p class="h1-exclusion-note">Exclude selected pages from future H1 checks.</p>' +
            '<div class="h1-actions-buttons">' +
                '<button id="save-h1-exclusions" class="button button-secondary" style="margin-right: 10px;">Save Exclusions</button>' +
                '<button id="refresh-dashboard" class="button button-primary" style="margin-right: 10px;">Update Dashboard & Close</button>' +
                '<button id="close-modal-only" class="button button-secondary">Close Modal</button>' +
            '</div>' +
            '<div class="h1-exclusion-message" style="margin-top: 10px;"></div>' +
        '</div>';

        $resultsDiv.append(actionButtons);

        // Handle button clicks
        $('#refresh-dashboard').on('click', function() {
            $('#h1-analysis-modal').hide();
            location.reload();
        });

        $('#close-modal-only').on('click', function() {
            $('#h1-analysis-modal').hide();
        });
    }

    // Save H1 exclusions
    $(document).on('click', '#save-h1-exclusions', function(e) {
        e.preventDefault();

        if (typeof meta_description_boy_data === 'undefined') {
            console.error('meta_description_boy_data is not defined');
            return;
        }

        var excludedIds = [];
        $('.h1-exclude-checkbox:checked').each(function() {
            var postId = parseInt($(this).data('post-id'), 10);
            if (!isNaN(postId)) {
                excludedIds.push(postId);
            }
        });

        var $message = $('.h1-exclusion-message');
        $message.text('Saving exclusions...').css('color', '#555');

        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_update_h1_exclusions',
                post_ids: excludedIds,
                nonce: meta_description_boy_data.nonce
            },
            success: function(response) {
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        $message.text('Could not parse server response.').css('color', '#b32d2e');
                        return;
                    }
                }

                if (!response.success) {
                    var errorMsg = response.message || response.data?.message || 'Failed to save exclusions.';
                    $message.text(errorMsg).css('color', '#b32d2e');
                    return;
                }

                var excludedLookup = {};
                excludedIds.forEach(function(id) {
                    excludedLookup[id] = true;
                });

                $('#h1-results-tbody tr').each(function() {
                    var $row = $(this);
                    var rowId = parseInt($row.find('.h1-exclude-checkbox').data('post-id'), 10);
                    var isExcluded = excludedLookup[rowId] === true;
                    $row.toggleClass('h1-excluded-row', isExcluded);
                });

                if (h1DetailedResultsCache && h1DetailedResultsCache.length) {
                    h1DetailedResultsCache.forEach(function(result) {
                        result.is_excluded = excludedLookup[result.id] === true;
                    });
                }

                var summary = h1DetailedResultsCache && h1DetailedResultsCache.length
                    ? calculateH1Summary(h1DetailedResultsCache)
                    : calculateH1SummaryFromTable();

                showH1Results(summary);
                $('.h1-exclusion-message')
                    .text('Exclusions saved. Refresh to recalculate dashboard stats.')
                    .css('color', '#1d2327');
            },
            error: function() {
                $message.text('Network error while saving exclusions.').css('color', '#b32d2e');
            }
        });
    });

    // Close modal handlers
    $(document).on('click', '.h1-modal-close, .h1-modal-overlay', function(e) {
        if (e.target === this) {
            $('#h1-analysis-modal').hide();
            $('#h1-debug-modal').hide();
        }
    });

    // Prevent modal content clicks from closing modal
    $(document).on('click', '.h1-modal-content', function(e) {
        e.stopPropagation();
    });

    // Handle H1 Debug button click
    $(document).on('click', '.h1-debug-btn', function(e) {
        e.preventDefault();
        var postId = $(this).data('post-id');
        showH1DebugModal(postId);
    });

    // Show H1 Debug Modal
    function showH1DebugModal(postId) {
        console.log('showH1DebugModal called for post ID:', postId);

        // Create modal HTML if it doesn't exist
        if (!$('#h1-debug-modal').length) {
            var debugModalHTML = '<div id="h1-debug-modal" class="h1-modal-overlay" style="display: none;">' +
                '<div class="h1-modal-content" style="max-width: 900px;">' +
                    '<div class="h1-modal-header">' +
                        '<h2>H1 Debug Information</h2>' +
                        '<span class="h1-modal-close">&times;</span>' +
                    '</div>' +
                    '<div class="h1-modal-body" id="h1-debug-body">' +
                        '<div class="h1-debug-loading" style="text-align: center; padding: 40px;">' +
                            '<span class="spinner is-active" style="float: none; margin: 0 auto;"></span>' +
                            '<p>Loading debug information...</p>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
            $('body').append(debugModalHTML);
        }

        // Show modal
        $('#h1-debug-modal').show();

        // Fetch debug data
        $.ajax({
            type: 'POST',
            url: meta_description_boy_data.ajax_url,
            data: {
                action: 'meta_description_boy_debug_post_h1',
                nonce: meta_description_boy_data.nonce,
                post_id: postId
            },
            success: function(response) {
                console.log('Debug response:', response);

                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        console.error('Failed to parse debug response:', e);
                        $('#h1-debug-body').html('<div class="notice notice-error"><p>Error parsing response</p></div>');
                        return;
                    }
                }

                if (response.success && response.data) {
                    displayH1DebugInfo(response.data);
                } else {
                    $('#h1-debug-body').html('<div class="notice notice-error"><p>Error loading debug information</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Debug AJAX error:', error);
                $('#h1-debug-body').html('<div class="notice notice-error"><p>Network error: ' + error + '</p></div>');
            }
        });
    }

    // Display H1 Debug Information
    function displayH1DebugInfo(data) {
        console.log('Displaying debug info:', data);

        var html = '<div class="h1-debug-info">';
        
        // Post information
        html += '<div class="debug-section">';
        html += '<h3>Post Information</h3>';
        html += '<table class="widefat">';
        html += '<tr><th>Post ID:</th><td>' + data.post_id + '</td></tr>';
        html += '<tr><th>Post Title:</th><td>' + data.post_title + '</td></tr>';
        html += '<tr><th>Post URL:</th><td><a href="' + data.post_url + '" target="_blank">' + data.post_url + '</a></td></tr>';
        html += '<tr><th>Edit URL:</th><td><a href="' + data.edit_url + '" target="_blank">Edit Post</a></td></tr>';
        html += '</table>';
        html += '</div>';

        var analysis = data.h1_analysis;

        // Analysis summary with error handling
        html += '<div class="debug-section">';
        html += '<h3>Analysis Summary</h3>';
        
        // Show warning banner if there was an error
        if (analysis.error) {
            html += '<div class="notice notice-warning" style="margin-bottom: 15px; padding: 12px; border-left: 4px solid #ffb900;">';
            html += '<p style="margin: 0 0 8px 0;"><strong>⚠️ ' + analysis.error + '</strong></p>';
            if (analysis.help_text) {
                html += '<p style="margin: 0; font-size: 13px;">' + analysis.help_text + '</p>';
            }
            if (analysis.fallback_used) {
                html += '<p style="margin: 8px 0 0 0; font-size: 13px;"><em>Note: Using content-based analysis (less accurate). H1 count shown is an estimate.</em></p>';
            }
            html += '</div>';
        }
        
        html += '<table class="widefat">';
        html += '<tr><th>Valid H1 Count:</th><td><strong>' + analysis.h1_count + '</strong>' + (analysis.fallback_used ? ' <span style="color: #ffb900;">(estimated)</span>' : '') + '</td></tr>';
        html += '<tr><th>Total H1 Tags Found:</th><td>' + (analysis.total_h1_found !== undefined ? analysis.total_h1_found : 'N/A') + '</td></tr>';
        html += '<tr><th>Response Code:</th><td>' + (analysis.response_code !== undefined ? analysis.response_code : 'N/A') + '</td></tr>';
        if (analysis.error_details) {
            html += '<tr><th>Error Details:</th><td style="color: #dc3232; font-family: monospace; font-size: 12px;">' + analysis.error_details + '</td></tr>';
        }
        html += '</table>';
        html += '</div>';

        // Detailed H1 tags
        if (analysis.h1_tags && analysis.h1_tags.length > 0) {
            html += '<div class="debug-section">';
            html += '<h3>Detected H1 Tags (' + analysis.h1_tags.length + ' total)</h3>';
            html += '<div style="max-height: 400px; overflow-y: auto;">';

            analysis.h1_tags.forEach(function(h1, index) {
                var statusBadge = h1.is_valid ? 
                    '<span style="background: #46b450; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">VALID</span>' :
                    '<span style="background: #dc3232; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">IGNORED</span>';

                html += '<div class="h1-tag-detail" style="background: ' + (h1.is_valid ? '#f0f9ff' : '#fff3cd') + '; border: 1px solid ' + (h1.is_valid ? '#0073aa' : '#ffb900') + '; padding: 15px; margin-bottom: 15px; border-radius: 4px;">';
                html += '<h4 style="margin-top: 0;">H1 Tag #' + (index + 1) + ' ' + statusBadge + '</h4>';
                
                html += '<table class="widefat" style="margin-bottom: 10px;">';
                html += '<tr><th style="width: 150px;">Text Content:</th><td><strong>' + (h1.text_content || '<em>(empty)</em>') + '</strong></td></tr>';
                html += '<tr><th>Is Valid:</th><td>' + (h1.is_valid ? '✅ Yes' : '❌ No') + '</td></tr>';
                html += '<tr><th>Is Empty:</th><td>' + (h1.is_empty ? '⚠️ Yes' : '✅ No') + '</td></tr>';
                html += '<tr><th>Is Editor Element:</th><td>' + (h1.is_editor_element ? '⚠️ Yes' : '✅ No') + '</td></tr>';
                html += '</table>';

                html += '<details style="margin-top: 10px;">';
                html += '<summary style="cursor: pointer; font-weight: bold; margin-bottom: 5px;">View Full HTML Tag</summary>';
                html += '<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto; border: 1px solid #ddd; border-radius: 3px; font-size: 11px;">' + escapeHtml(h1.full_tag) + '</pre>';
                html += '</details>';

                if (h1.inner_html !== h1.text_content) {
                    html += '<details style="margin-top: 10px;">';
                    html += '<summary style="cursor: pointer; font-weight: bold; margin-bottom: 5px;">View Inner HTML</summary>';
                    html += '<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto; border: 1px solid #ddd; border-radius: 3px; font-size: 11px;">' + escapeHtml(h1.inner_html) + '</pre>';
                    html += '</details>';
                }

                html += '</div>';
            });

            html += '</div>';
            html += '</div>';
        } else {
            html += '<div class="debug-section">';
            html += '<h3>No H1 Tags Found</h3>';
            html += '<p>No H1 tags were detected on this page.</p>';
            html += '</div>';
        }

        html += '</div>';

        $('#h1-debug-body').html(html);
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
