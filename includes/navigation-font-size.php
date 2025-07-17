<?php
/**
 * Navigation Font Size Testing functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check navigation font size testing status
 */
function meta_description_boy_check_navigation_font_size_status() {
    // Check for manual approval of navigation font size testing
    $font_size_approved = get_option('meta_description_boy_navigation_font_size_approved', false);
    $approved_by = get_option('meta_description_boy_navigation_font_size_approved_by', '');
    $approved_date = get_option('meta_description_boy_navigation_font_size_approved_date', '');

    // Determine status based on approval
    if ($font_size_approved) {
        return array(
            'font_size_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'Navigation Font Size Verified',
            'message' => 'Navigation font sizes meet accessibility standards (16px+ desktop, 20px+ mobile)',
            'class' => 'status-good'
        );
    } else {
        return array(
            'font_size_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Navigation Font Size Check Pending',
            'message' => 'Please verify navigation font sizes meet accessibility standards',
            'class' => 'status-warning'
        );
    }
}

/**
 * Handle AJAX request to approve navigation font size testing
 */
function meta_description_boy_approve_navigation_font_size() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    // Save approval data
    update_option('meta_description_boy_navigation_font_size_approved', true);
    update_option('meta_description_boy_navigation_font_size_approved_by', $approved_by);
    update_option('meta_description_boy_navigation_font_size_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'Navigation font size verification saved')));
}
add_action('wp_ajax_meta_description_boy_approve_navigation_font_size', 'meta_description_boy_approve_navigation_font_size');

/**
 * Handle AJAX request to reset navigation font size approval
 */
function meta_description_boy_reset_navigation_font_size_approval() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    // Remove approval data
    delete_option('meta_description_boy_navigation_font_size_approved');
    delete_option('meta_description_boy_navigation_font_size_approved_by');
    delete_option('meta_description_boy_navigation_font_size_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'Navigation font size verification reset')));
}
add_action('wp_ajax_meta_description_boy_reset_navigation_font_size_approval', 'meta_description_boy_reset_navigation_font_size_approval');

/**
 * Render navigation font size testing section
 */
function meta_description_boy_render_navigation_font_size_section() {
    $font_size_status = meta_description_boy_check_navigation_font_size_status();
    $current_user = wp_get_current_user();
    ?>
    <div class="seo-stat-item <?php echo $font_size_status['class']; ?>">
        <div class="stat-icon">📱</div>
        <div class="stat-content">
            <h4>Navigation Font Size</h4>
            <div class="stat-status <?php echo $font_size_status['class']; ?>">
                <?php echo $font_size_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $font_size_status['message']; ?>

                <?php if ($font_size_status['font_size_approved']): ?>
                    <br><br><small><strong>Verified by:</strong> <?php echo esc_html($font_size_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($font_size_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($font_size_status['font_size_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetNavigationFontSizeApproval()">
                        Reset Verification
                    </button>
                <?php else: ?>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Have you verified navigation font sizes?
                        </label>
                        <div style="margin-bottom: 12px; font-size: 13px; color: #666;">
                            <strong>Accessibility Requirements:</strong><br>
                            • Desktop navigation: Minimum 16px font size<br>
                            • Mobile navigation: Minimum 20px font size<br>
                        </div>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="navigation-font-size-checkbox" style="margin-right: 5px;">
                            Confirm that navigation font sizes meet accessibility standards (16px+ desktop, 20px+ mobile)
                        </label>
                        <input type="text" id="navigation-font-size-approved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveNavigationFontSize()" disabled>
                            Confirm Font Sizes
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Enable/disable the name field and button based on checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('navigation-font-size-checkbox');
        const nameField = document.getElementById('navigation-font-size-approved-by-name');
        const button = document.querySelector('button[onclick="approveNavigationFontSize()"]');

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

    function approveNavigationFontSize() {
        const checkbox = document.getElementById('navigation-font-size-checkbox');
        const nameField = document.getElementById('navigation-font-size-approved-by-name');

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

        if (!confirm('Are you sure that navigation font sizes meet accessibility standards (16px+ desktop, 20px+ mobile)? This verification will be tracked.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_approve_navigation_font_size',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Navigation font size verification saved successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetNavigationFontSizeApproval() {
        if (!confirm('Are you sure you want to reset the navigation font size verification? This will remove the current confirmation.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_reset_navigation_font_size_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Navigation font size verification reset successfully.');
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