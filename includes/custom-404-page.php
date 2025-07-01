<?php
/**
 * Custom 404 Page functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check custom 404 page status
 */
function meta_description_boy_check_custom_404_status() {
    // Check for manual approval of custom 404 page
    $custom_404_approved = get_option('meta_description_boy_custom_404_approved', false);
    $approved_by = get_option('meta_description_boy_custom_404_approved_by', '');
    $approved_date = get_option('meta_description_boy_custom_404_approved_date', '');

    // Determine status based on approval
    if ($custom_404_approved) {
        return array(
            'custom_404_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'Custom 404 Page Configured',
            'message' => 'Confirmed custom 404 page has been added and customized',
            'class' => 'status-good'
        );
    } else {
        return array(
            'custom_404_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Custom 404 Page Pending',
            'message' => 'Please confirm that a custom 404 page has been added and customized',
            'class' => 'status-warning'
        );
    }
}

/**
 * Handle AJAX request to approve custom 404 page
 */
function meta_description_boy_approve_custom_404() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    // Save approval data
    update_option('meta_description_boy_custom_404_approved', true);
    update_option('meta_description_boy_custom_404_approved_by', $approved_by);
    update_option('meta_description_boy_custom_404_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'Custom 404 page confirmation saved')));
}
add_action('wp_ajax_meta_description_boy_approve_custom_404', 'meta_description_boy_approve_custom_404');

/**
 * Handle AJAX request to reset custom 404 page approval
 */
function meta_description_boy_reset_custom_404_approval() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    // Remove approval data
    delete_option('meta_description_boy_custom_404_approved');
    delete_option('meta_description_boy_custom_404_approved_by');
    delete_option('meta_description_boy_custom_404_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'Custom 404 page approval reset')));
}
add_action('wp_ajax_meta_description_boy_reset_custom_404_approval', 'meta_description_boy_reset_custom_404_approval');

/**
 * Render custom 404 page section
 */
function meta_description_boy_render_custom_404_section() {
    $custom_404_status = meta_description_boy_check_custom_404_status();
    $current_user = wp_get_current_user();
    ?>
    <div class="seo-stat-item <?php echo $custom_404_status['class']; ?>">
        <div class="stat-icon">📄</div>
        <div class="stat-content">
            <h4>Custom 404 Page</h4>
            <div class="stat-status <?php echo $custom_404_status['class']; ?>">
                <?php echo $custom_404_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $custom_404_status['message']; ?>

                <?php if ($custom_404_status['custom_404_approved']): ?>
                    <br><br><small><strong>Approved by:</strong> <?php echo esc_html($custom_404_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($custom_404_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($custom_404_status['custom_404_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetCustom404Approval()">
                        Reset Approval
                    </button>
                <?php else: ?>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Custom 404 page added and customized?
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="custom-404-checkbox" style="margin-right: 5px;">
                            Confirm that a custom 404 page has been added and customized for this website
                        </label>
                        <input type="text" id="custom-404-approved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveCustom404()" disabled>
                            Confirm Custom 404 Page
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Enable/disable the name field and button based on checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('custom-404-checkbox');
        const nameField = document.getElementById('custom-404-approved-by-name');
        const button = document.querySelector('button[onclick="approveCustom404()"]');

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

    function approveCustom404() {
        const checkbox = document.getElementById('custom-404-checkbox');
        const nameField = document.getElementById('custom-404-approved-by-name');

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

        if (!confirm('Are you sure that a custom 404 page has been added and customized for this website? This confirmation will be tracked.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_approve_custom_404',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Custom 404 page confirmation saved successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetCustom404Approval() {
        if (!confirm('Are you sure you want to reset the custom 404 page approval? This will remove the current confirmation record.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_reset_custom_404_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Custom 404 page approval reset successfully.');
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