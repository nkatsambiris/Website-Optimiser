<?php
/**
 * Cloudways Cron Optimizer confirmation functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check Cloudways Cron Optimizer status
 */
function meta_description_boy_check_cloudways_cron_optimizer_status() {
    $cron_optimizer_approved = get_option('meta_description_boy_cloudways_cron_optimizer_approved', false);
    $approved_by = get_option('meta_description_boy_cloudways_cron_optimizer_approved_by', '');
    $approved_date = get_option('meta_description_boy_cloudways_cron_optimizer_approved_date', '');

    if ($cron_optimizer_approved) {
        return array(
            'cloudways_cron_optimizer_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'Cloudways Cron Optimizer Enabled',
            'message' => 'Confirmed Cloudways Cron Optimizer has been enabled in server settings',
            'class' => 'status-good'
        );
    }

    return array(
        'cloudways_cron_optimizer_approved' => false,
        'approved_by' => '',
        'approved_date' => '',
        'status' => 'Cloudways Cron Optimizer Pending',
        'message' => 'Please confirm Cloudways Cron Optimizer has been enabled in Cloudways server settings',
        'class' => 'status-warning'
    );
}

/**
 * Handle AJAX request to approve Cloudways Cron Optimizer setup
 */
function meta_description_boy_approve_cloudways_cron_optimizer() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    update_option('meta_description_boy_cloudways_cron_optimizer_approved', true);
    update_option('meta_description_boy_cloudways_cron_optimizer_approved_by', $approved_by);
    update_option('meta_description_boy_cloudways_cron_optimizer_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'Cloudways Cron Optimizer confirmation saved')));
}
add_action('wp_ajax_meta_description_boy_approve_cloudways_cron_optimizer', 'meta_description_boy_approve_cloudways_cron_optimizer');

/**
 * Handle AJAX request to reset Cloudways Cron Optimizer approval
 */
function meta_description_boy_reset_cloudways_cron_optimizer_approval() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    delete_option('meta_description_boy_cloudways_cron_optimizer_approved');
    delete_option('meta_description_boy_cloudways_cron_optimizer_approved_by');
    delete_option('meta_description_boy_cloudways_cron_optimizer_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'Cloudways Cron Optimizer approval reset')));
}
add_action('wp_ajax_meta_description_boy_reset_cloudways_cron_optimizer_approval', 'meta_description_boy_reset_cloudways_cron_optimizer_approval');

/**
 * Render Cloudways Cron Optimizer section
 */
function meta_description_boy_render_cloudways_cron_optimizer_section() {
    $cron_optimizer_status = meta_description_boy_check_cloudways_cron_optimizer_status();
    ?>
    <div class="seo-stat-item <?php echo $cron_optimizer_status['class']; ?>">
        <div class="stat-icon">⏱️</div>
        <div class="stat-content">
            <h4>Cloudways Cron Optimizer</h4>
            <div class="stat-status <?php echo $cron_optimizer_status['class']; ?>">
                <?php echo $cron_optimizer_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $cron_optimizer_status['message']; ?>

                <?php if ($cron_optimizer_status['cloudways_cron_optimizer_approved']): ?>
                    <br><br><small><strong>Approved by:</strong> <?php echo esc_html($cron_optimizer_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($cron_optimizer_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($cron_optimizer_status['cloudways_cron_optimizer_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetCloudwaysCronOptimizerApproval()">
                        Reset Approval
                    </button>
                <?php else: ?>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Cloudways Cron Optimizer enabled?
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="cloudways-cron-optimizer-checkbox" style="margin-right: 5px;">
                            Confirm that Cloudways Cron Optimizer has been enabled in the server settings
                        </label>
                        <input type="text" id="cloudways-cron-optimizer-approved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveCloudwaysCronOptimizer()" disabled>
                            Confirm Cloudways Cron Optimizer
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Enable/disable the name field and button based on checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('cloudways-cron-optimizer-checkbox');
        const nameField = document.getElementById('cloudways-cron-optimizer-approved-by-name');
        const button = document.querySelector('button[onclick="approveCloudwaysCronOptimizer()"]');

        if (checkbox && nameField && button) {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    nameField.disabled = false;
                    nameField.focus();
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

    function approveCloudwaysCronOptimizer() {
        const checkbox = document.getElementById('cloudways-cron-optimizer-checkbox');
        const nameField = document.getElementById('cloudways-cron-optimizer-approved-by-name');

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

        if (!confirm('Are you sure Cloudways Cron Optimizer has been enabled in the server settings? This confirmation will be tracked.')) {
            return;
        }

        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_approve_cloudways_cron_optimizer',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Cloudways Cron Optimizer confirmation saved successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetCloudwaysCronOptimizerApproval() {
        if (!confirm('Are you sure you want to reset the Cloudways Cron Optimizer approval? This will remove the current confirmation record.')) {
            return;
        }

        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_reset_cloudways_cron_optimizer_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Cloudways Cron Optimizer approval reset successfully.');
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
