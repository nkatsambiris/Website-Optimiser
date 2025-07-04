<?php
/**
 * Uptime Monitoring functionality for Website Optimiser Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check uptime monitoring status
 */
function meta_description_boy_check_uptime_monitoring_status() {
    // Check for manual approval of uptime monitoring
    $uptime_approved = get_option('meta_description_boy_uptime_monitoring_approved', false);
    $approved_by = get_option('meta_description_boy_uptime_monitoring_approved_by', '');
    $approved_date = get_option('meta_description_boy_uptime_monitoring_approved_date', '');

    // Determine status based on approval
    if ($uptime_approved) {
        return array(
            'uptime_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'Uptime Monitoring Configured',
            'message' => 'Confirmed website has been added to uptime monitoring',
            'class' => 'status-good'
        );
    } else {
        return array(
            'uptime_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Uptime Monitoring Pending',
            'message' => 'Please confirm that the website has been added to uptime monitoring',
            'class' => 'status-warning'
        );
    }
}

/**
 * Handle AJAX request to approve uptime monitoring
 */
function meta_description_boy_approve_uptime_monitoring() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    // Save approval data
    update_option('meta_description_boy_uptime_monitoring_approved', true);
    update_option('meta_description_boy_uptime_monitoring_approved_by', $approved_by);
    update_option('meta_description_boy_uptime_monitoring_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'Uptime monitoring confirmation saved')));
}
add_action('wp_ajax_meta_description_boy_approve_uptime_monitoring', 'meta_description_boy_approve_uptime_monitoring');

/**
 * Handle AJAX request to reset uptime monitoring approval
 */
function meta_description_boy_reset_uptime_monitoring_approval() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    // Remove approval data
    delete_option('meta_description_boy_uptime_monitoring_approved');
    delete_option('meta_description_boy_uptime_monitoring_approved_by');
    delete_option('meta_description_boy_uptime_monitoring_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'Uptime monitoring approval reset')));
}
add_action('wp_ajax_meta_description_boy_reset_uptime_monitoring_approval', 'meta_description_boy_reset_uptime_monitoring_approval');

/**
 * Render uptime monitoring section
 */
function meta_description_boy_render_uptime_monitoring_section() {
    $uptime_status = meta_description_boy_check_uptime_monitoring_status();
    $current_user = wp_get_current_user();
    ?>
    <div class="seo-stat-item <?php echo $uptime_status['class']; ?>">
        <div class="stat-icon">⏱️</div>
        <div class="stat-content">
            <h4>Uptime Monitoring</h4>
            <div class="stat-status <?php echo $uptime_status['class']; ?>">
                <?php echo $uptime_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $uptime_status['message']; ?>

                <?php if ($uptime_status['uptime_approved']): ?>
                    <br><br><small><strong>Approved by:</strong> <?php echo esc_html($uptime_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($uptime_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($uptime_status['uptime_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetUptimeMonitoringApproval()">
                        Reset Approval
                    </button>
                <?php else: ?>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="uptime-monitoring-checkbox" style="margin-right: 5px;">
                            Confirm that this website has been added to uptime monitoring
                        </label>
                        <input type="text" id="uptime-monitoring-approved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveUptimeMonitoring()" disabled>
                            Confirm Uptime Monitoring
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Enable/disable the name field and button based on checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('uptime-monitoring-checkbox');
        const nameField = document.getElementById('uptime-monitoring-approved-by-name');
        const button = document.querySelector('button[onclick="approveUptimeMonitoring()"]');

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

    function approveUptimeMonitoring() {
        const checkbox = document.getElementById('uptime-monitoring-checkbox');
        const nameField = document.getElementById('uptime-monitoring-approved-by-name');

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

        if (!confirm('Are you sure that this website has been added to uptime monitoring? This confirmation will be tracked.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_approve_uptime_monitoring',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Uptime monitoring confirmation saved successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetUptimeMonitoringApproval() {
        if (!confirm('Are you sure you want to reset the uptime monitoring approval? This will remove the current confirmation record.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_reset_uptime_monitoring_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Uptime monitoring approval reset successfully.');
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