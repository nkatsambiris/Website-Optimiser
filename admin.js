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

    // Function to generate alt text
    function generateAltText() {
        var $button = $('#generate-alt-text-btn');
        var $altTextInput = $('.attachment-details .setting[data-setting="alt"] textarea, .attachment-details .setting[data-setting="alt"] input[type="text"]');
        var originalButtonText = $button.text();

        // Get attachment ID from the modal
        var attachmentId = null;

        // Try different methods to get attachment ID
        if ($('.attachment-details').attr('data-id')) {
            attachmentId = $('.attachment-details').attr('data-id');
        } else if ($('.attachment-display-settings').length) {
            var urlInput = $('.attachment-display-settings input[name="url"]');
            if (urlInput.length) {
                var url = urlInput.val();
                var matches = url.match(/wp-content\/uploads\/.*\/(.*?)(\?|$)/);
                if (matches) {
                    // Try to get ID from global wp object if available
                    if (typeof wp !== 'undefined' && wp.media && wp.media.frame && wp.media.frame.state().get('selection')) {
                        var selection = wp.media.frame.state().get('selection').first();
                        if (selection) {
                            attachmentId = selection.get('id');
                        }
                    }
                }
            }
        }

        // Fallback: try to get ID from URL parameters
        if (!attachmentId) {
            var urlParams = new URLSearchParams(window.location.search);
            attachmentId = urlParams.get('item') || urlParams.get('attachment_id');
        }

        // Additional fallback: check for data attributes on various elements
        if (!attachmentId) {
            var dataElements = $('.attachment-details [data-id], .media-modal [data-id], .attachment [data-id]');
            if (dataElements.length) {
                attachmentId = dataElements.first().attr('data-id');
            }
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
                    // Update the alt text field
                    $altTextInput.val(response.data.alt_text).trigger('change');

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

    // Check for media modal periodically and add button if needed
    var checkInterval = setInterval(function() {
        addAltTextButton();
    }, 500);

    // Also bind to media modal events if available
    if (typeof wp !== 'undefined' && wp.media) {
        wp.media.view.Attachment.Details.on('ready', function() {
            setTimeout(addAltTextButton, 100);
        });
    }

    // Handle alt text generation on attachment edit pages
    $('#generate_attachment_alt_text').on('click', function(e) {
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
                    $('#alt_text_output').html('<div class="notice notice-success"><p>Alt text saved successfully!</p></div>');
                    // Refresh the current alt text display
                    location.reload();
                } else {
                    $('#alt_text_output').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $('#alt_text_output').html('<div class="notice notice-error"><p>Error saving alt text.</p></div>');
            }
        });
    }

    // Prepend the "Generate Meta Description" button
    $('#meta_description_boy_generate_meta_description').on('click', function(e) {
        e.preventDefault();

        var $this = $(this); // Reference to the button
        var originalButtonText = $this.text(); // Store the original button text

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
            error: function() {
                // Restore the button to its original state in case of an AJAX error
                $this.text(originalButtonText);
                $this.prop('disabled', false);
            }
        });
    });
});