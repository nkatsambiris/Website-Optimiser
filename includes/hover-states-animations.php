<?php
/**
 * Hover States and Animations functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check hover states and animations status
 */
function meta_description_boy_check_hover_states_animations_status() {
    // Check for manual approval of hover states and animations
    $hover_animations_approved = get_option('meta_description_boy_hover_animations_approved', false);
    $approved_by = get_option('meta_description_boy_hover_animations_approved_by', '');
    $approved_date = get_option('meta_description_boy_hover_animations_approved_date', '');

    // Determine status based on approval
    if ($hover_animations_approved) {
        return array(
            'hover_animations_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'Hover States & Animations Implemented',
            'message' => 'Confirmed hover states and animations have been reviewed and implemented',
            'class' => 'status-good'
        );
    } else {
        return array(
            'hover_animations_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Hover States & Animations Pending',
            'message' => 'Please confirm that hover states and animations have been reviewed and implemented',
            'class' => 'status-warning'
        );
    }
}

/**
 * Handle AJAX request to approve hover states and animations
 */
function meta_description_boy_approve_hover_animations() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    // Save approval data
    update_option('meta_description_boy_hover_animations_approved', true);
    update_option('meta_description_boy_hover_animations_approved_by', $approved_by);
    update_option('meta_description_boy_hover_animations_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'Hover states and animations confirmation saved')));
}
add_action('wp_ajax_meta_description_boy_approve_hover_animations', 'meta_description_boy_approve_hover_animations');

/**
 * Handle AJAX request to reset hover states and animations approval
 */
function meta_description_boy_reset_hover_animations_approval() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    // Remove approval data
    delete_option('meta_description_boy_hover_animations_approved');
    delete_option('meta_description_boy_hover_animations_approved_by');
    delete_option('meta_description_boy_hover_animations_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'Hover states and animations approval reset')));
}
add_action('wp_ajax_meta_description_boy_reset_hover_animations_approval', 'meta_description_boy_reset_hover_animations_approval');

/**
 * Render hover states and animations section
 */
function meta_description_boy_render_hover_animations_section() {
    $hover_animations_status = meta_description_boy_check_hover_states_animations_status();
    $current_user = wp_get_current_user();
    ?>
    <div class="seo-stat-item <?php echo $hover_animations_status['class']; ?>">
        <div class="stat-icon">✨</div>
        <div class="stat-content">
            <h4>Hover States & Animations</h4>
            <div class="stat-status <?php echo $hover_animations_status['class']; ?>">
                <?php echo $hover_animations_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $hover_animations_status['message']; ?>

                <?php if ($hover_animations_status['hover_animations_approved']): ?>
                    <br><br><small><strong>Approved by:</strong> <?php echo esc_html($hover_animations_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($hover_animations_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($hover_animations_status['hover_animations_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetHoverAnimationsApproval()">
                        Reset Approval
                    </button>
                <?php else: ?>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Hover states and animations implemented?
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="hover-animations-checkbox" style="margin-right: 5px;">
                            Confirm that hover states and animations have been reviewed and implemented for buttons, links, cards and interactive elements
                        </label>
                        <input type="text" id="hover-animations-approved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveHoverAnimations()" disabled>
                            Confirm Hover States & Animations
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Enable/disable the name field and button based on checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('hover-animations-checkbox');
        const nameField = document.getElementById('hover-animations-approved-by-name');
        const button = document.querySelector('button[onclick="approveHoverAnimations()"]');

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

    function approveHoverAnimations() {
        const checkbox = document.getElementById('hover-animations-checkbox');
        const nameField = document.getElementById('hover-animations-approved-by-name');

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

        if (!confirm('Are you sure that hover states and animations have been reviewed and implemented for this website? This confirmation will be tracked.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_approve_hover_animations',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Hover states and animations confirmation saved successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetHoverAnimationsApproval() {
        if (!confirm('Are you sure you want to reset the hover states and animations approval? This will remove the current confirmation record.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_reset_hover_animations_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Hover states and animations approval reset successfully.');
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