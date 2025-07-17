<?php
/**
 * Clickable Links Testing functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check clickable links testing status
 */
function meta_description_boy_check_clickable_links_status() {
    // Check for manual approval of clickable links testing
    $links_tested_approved = get_option('meta_description_boy_clickable_links_approved', false);
    $approved_by = get_option('meta_description_boy_clickable_links_approved_by', '');
    $approved_date = get_option('meta_description_boy_clickable_links_approved_date', '');

    // Determine status based on approval
    if ($links_tested_approved) {
        return array(
            'links_tested_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'Clickable Links Tested',
            'message' => 'All clickable links (phone, email, social media) have been tested and verified',
            'class' => 'status-good'
        );
    } else {
        return array(
            'links_tested_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Clickable Links Testing Pending',
            'message' => 'Please test all clickable links (phone, email, social media) to ensure they work correctly',
            'class' => 'status-warning'
        );
    }
}

/**
 * Handle AJAX request to approve clickable links testing
 */
function meta_description_boy_approve_clickable_links() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    // Save approval data
    update_option('meta_description_boy_clickable_links_approved', true);
    update_option('meta_description_boy_clickable_links_approved_by', $approved_by);
    update_option('meta_description_boy_clickable_links_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'Clickable links testing confirmation saved')));
}
add_action('wp_ajax_meta_description_boy_approve_clickable_links', 'meta_description_boy_approve_clickable_links');

/**
 * Handle AJAX request to reset clickable links testing approval
 */
function meta_description_boy_reset_clickable_links_approval() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    // Remove approval data
    delete_option('meta_description_boy_clickable_links_approved');
    delete_option('meta_description_boy_clickable_links_approved_by');
    delete_option('meta_description_boy_clickable_links_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'Clickable links testing approval reset')));
}
add_action('wp_ajax_meta_description_boy_reset_clickable_links_approval', 'meta_description_boy_reset_clickable_links_approval');

/**
 * Render clickable links testing section
 */
function meta_description_boy_render_clickable_links_section() {
    $links_status = meta_description_boy_check_clickable_links_status();
    $current_user = wp_get_current_user();
    ?>
    <div class="seo-stat-item <?php echo $links_status['class']; ?>">
        <div class="stat-icon">🔗</div>
        <div class="stat-content">
            <h4>Clickable Links Testing</h4>
            <div class="stat-status <?php echo $links_status['class']; ?>">
                <?php echo $links_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $links_status['message']; ?>

                <?php if ($links_status['links_tested_approved']): ?>
                    <br><br><small><strong>Tested by:</strong> <?php echo esc_html($links_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($links_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($links_status['links_tested_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetClickableLinksApproval()">
                        Reset Testing Record
                    </button>
                <?php else: ?>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Have you tested all clickable links?
                        </label>
                        <div style="margin-bottom: 12px; font-size: 13px; color: #666;">
                            <strong>Test the following:</strong><br>
                            • Phone links (tel:) - should open phone dialer<br>
                            • Email links (mailto:) - should open email client<br>
                            • Social media links - should open in new window/tab<br>
                            • External links - should open in new window/tab if desired<br>
                            • Internal navigation links - should work correctly
                        </div>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="clickable-links-checkbox" style="margin-right: 5px;">
                            Confirm that all clickable links have been tested and work correctly
                        </label>
                        <input type="text" id="clickable-links-approved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveClickableLinks()" disabled>
                            Confirm Links Tested
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Enable/disable the name field and button based on checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('clickable-links-checkbox');
        const nameField = document.getElementById('clickable-links-approved-by-name');
        const button = document.querySelector('button[onclick="approveClickableLinks()"]');

        if (checkbox && nameField && button) {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    nameField.disabled = false;
                    nameField.focus();
                    // Enable button when name is entered
                    nameField.addEventListener('input', function() {
                        button.disabled = this.value.trim() === '';
                    });
                } else {
                    nameField.disabled = true;
                    nameField.value = '';
                    button.disabled = true;
                }
            });
        }
    });

    function approveClickableLinks() {
        const checkbox = document.getElementById('clickable-links-checkbox');
        const nameField = document.getElementById('clickable-links-approved-by-name');

        if (!checkbox || !checkbox.checked) {
            alert('Please check the confirmation checkbox first.');
            return;
        }

        const approvedBy = nameField.value.trim();
        if (!approvedBy) {
            alert('Please enter your name.');
            nameField.focus();
            return;
        }

        if (!confirm('Are you sure that all clickable links (phone, email, social media) have been tested and work correctly? This confirmation will be tracked.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_approve_clickable_links',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Clickable links testing confirmation saved successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetClickableLinksApproval() {
        if (!confirm('Are you sure you want to reset the clickable links testing record? This will remove the current confirmation.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_reset_clickable_links_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Clickable links testing approval reset successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }
    </script>
    <?php
}