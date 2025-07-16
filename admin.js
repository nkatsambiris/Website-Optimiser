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

        console.log('Alt text input field found:', $altTextInput.length > 0);
        if ($altTextInput.length) {
            console.log('Alt text input selector:', $altTextInput[0]);
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
                    console.log('Generated alt text:', altText);

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
                                    console.log('Alt text set in media model');
                                }
                            }
                        }

                        console.log('Alt text field updated with:', altText);
                    } else {
                        console.log('Alt text field not found, attempting to save directly via AJAX');
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

        console.log('Meta description button clicked'); // Debug log

        var $this = $(this); // Reference to the button
        var originalButtonText = $this.text(); // Store the original button text

        // Check if meta_description_boy_data is available
        if (typeof meta_description_boy_data === 'undefined') {
            console.error('meta_description_boy_data is not defined');
            alert('Script configuration error. Please refresh the page and try again.');
            return;
        }

        console.log('Using post ID:', meta_description_boy_data.post_id); // Debug log

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
                console.log('AJAX response:', response); // Debug log

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

        // Debug: Log when the script loads and check for required elements
    console.log('Website Optimiser admin.js loaded');
    console.log('meta_description_boy_data available:', typeof meta_description_boy_data !== 'undefined');

    if (typeof meta_description_boy_data !== 'undefined') {
        console.log('meta_description_boy_data:', meta_description_boy_data);
    }

    // Check if the meta description button exists on page load
    if ($('#meta_description_boy_generate_meta_description').length) {
        console.log('Meta description button found on page load');
    } else {
        console.log('Meta description button not found on page load - will use event delegation');
    }

    // Check if meta box exists
    if ($('#meta_description_boy_meta_box').length) {
        console.log('Meta description meta box found');
    } else {
        console.log('Meta description meta box not found');
    }

        // Test event delegation by adding a manual test (for debugging only)
    $(document).on('click', '.test-event-delegation', function() {
        console.log('Event delegation test successful');
    });
});