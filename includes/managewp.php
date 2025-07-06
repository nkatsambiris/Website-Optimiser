<?php
/**
 * ManageWP connection functionality for Website Optimiser Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check ManageWP connection status
 */
function meta_description_boy_check_managewp_status() {
    // Check if plugin functions are available
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $managewp_plugin = 'worker/init.php';
    $managewp_mu_plugin = 'managewp/init.php';

    $managewp_plugin_active = false;
    $managewp_plugin_name = '';
    $managewp_plugin_version = '';
    $managewp_configured = false;
    $managewp_connected = false;

    // Check for manual approval of no ManageWP needed
    $no_managewp_approved = get_option('meta_description_boy_no_managewp_approved', false);
    $approved_by = get_option('meta_description_boy_no_managewp_approved_by', '');
    $approved_date = get_option('meta_description_boy_no_managewp_approved_date', '');

    // Check if ManageWP Worker plugin is installed and active
    if (is_plugin_active($managewp_plugin)) {
        $managewp_plugin_active = true;
        $managewp_plugin_name = 'ManageWP Worker';
        if (file_exists(WP_PLUGIN_DIR . '/' . $managewp_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $managewp_plugin);
            $managewp_plugin_version = $plugin_data['Version'] ?? '';
        }
        $managewp_configured = true;

        // Check if there's a connection key (indicates active connection)
        $managewp_options = get_option('wpr_options', array());
        if (!empty($managewp_options) && isset($managewp_options['public_key'])) {
            $managewp_connected = true;
        }
    }

    // Check for ManageWP as MU plugin
    if (!$managewp_plugin_active && file_exists(WPMU_PLUGIN_DIR . '/managewp/init.php')) {
        $managewp_plugin_active = true;
        $managewp_plugin_name = 'ManageWP (MU Plugin)';
        $managewp_configured = true;
        $managewp_connected = true; // MU plugin is typically pre-configured
    }

    // Determine status based on findings
    if ($no_managewp_approved) {
        return array(
            'managewp_plugin_active' => $managewp_plugin_active,
            'managewp_plugin_name' => $managewp_plugin_name,
            'managewp_plugin_version' => $managewp_plugin_version,
            'managewp_configured' => $managewp_configured,
            'managewp_connected' => $managewp_connected,
            'no_managewp_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'No ManageWP Required',
            'message' => 'Confirmed no ManageWP management needed for this website',
            'class' => 'status-good'
        );
    } elseif (!$managewp_plugin_active) {
        return array(
            'managewp_plugin_active' => false,
            'managewp_plugin_name' => '',
            'managewp_plugin_version' => '',
            'managewp_configured' => false,
            'managewp_connected' => false,
            'no_managewp_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'ManageWP Not Installed',
            'message' => 'Consider installing ManageWP for centralized site management',
            'class' => 'status-warning'
        );
    } elseif (!$managewp_connected) {
        return array(
            'managewp_plugin_active' => true,
            'managewp_plugin_name' => $managewp_plugin_name,
            'managewp_plugin_version' => $managewp_plugin_version,
            'managewp_configured' => false,
            'managewp_connected' => false,
            'no_managewp_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'ManageWP Not Connected',
            'message' => $managewp_plugin_name . ' is active but not connected to ManageWP dashboard',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'managewp_plugin_active' => true,
            'managewp_plugin_name' => $managewp_plugin_name,
            'managewp_plugin_version' => $managewp_plugin_version,
            'managewp_configured' => true,
            'managewp_connected' => true,
            'no_managewp_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'ManageWP Connected',
            'message' => 'Site is successfully connected to ManageWP dashboard',
            'class' => 'status-good'
        );
    }
}

/**
 * Handle AJAX request to approve no ManageWP needed
 */
function meta_description_boy_approve_no_managewp() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    // Save approval data
    update_option('meta_description_boy_no_managewp_approved', true);
    update_option('meta_description_boy_no_managewp_approved_by', $approved_by);
    update_option('meta_description_boy_no_managewp_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'No ManageWP requirement confirmed')));
}
add_action('wp_ajax_meta_description_boy_approve_no_managewp', 'meta_description_boy_approve_no_managewp');

/**
 * Handle AJAX request to reset no ManageWP approval
 */
function meta_description_boy_reset_no_managewp_approval() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    // Remove approval data
    delete_option('meta_description_boy_no_managewp_approved');
    delete_option('meta_description_boy_no_managewp_approved_by');
    delete_option('meta_description_boy_no_managewp_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'No ManageWP approval reset')));
}
add_action('wp_ajax_meta_description_boy_reset_no_managewp_approval', 'meta_description_boy_reset_no_managewp_approval');

/**
 * Render ManageWP section
 */
function meta_description_boy_render_managewp_section() {
    $managewp_status = meta_description_boy_check_managewp_status();
    ?>
    <div class="seo-stat-item <?php echo $managewp_status['class']; ?>">
        <div class="stat-icon">🔧</div>
        <div class="stat-content">
            <h4>ManageWP Connection</h4>
            <div class="stat-status <?php echo $managewp_status['class']; ?>">
                <?php echo $managewp_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $managewp_status['message']; ?>
                <?php if (!empty($managewp_status['managewp_plugin_name']) && !empty($managewp_status['managewp_plugin_version'])): ?>
                    <br><small><?php echo $managewp_status['managewp_plugin_name']; ?>: v<?php echo $managewp_status['managewp_plugin_version']; ?></small>
                <?php endif; ?>

                <?php if ($managewp_status['no_managewp_approved']): ?>
                    <br><br><small><strong>Approved by:</strong> <?php echo esc_html($managewp_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($managewp_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($managewp_status['no_managewp_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetNoManageWPApproval()">
                        Reset Approval
                    </button>
                <?php elseif (!$managewp_status['managewp_plugin_active']): ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=managewp&tab=search&type=term'); ?>" class="button button-small">
                        Install ManageWP
                    </a>
                    <a href="https://managewp.com/" class="button button-small" target="_blank" style="margin-left: 5px;">
                        Learn More
                    </a>
                    <br><br>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Single site with no centralized management needed?
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="no-managewp-checkbox" style="margin-right: 5px;">
                            Confirm this website doesn't require ManageWP management
                        </label>
                        <input type="text" id="approved-by-name-managewp" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveNoManageWP()" disabled>
                            Confirm No ManageWP Needed
                        </button>
                    </div>
                <?php elseif (!$managewp_status['managewp_connected']): ?>
                    <a href="https://orion.managewp.com/login/" class="button button-small" target="_blank">
                        Connect to ManageWP
                    </a>
                    <p style="margin-top: 10px; color: #666; font-size: 13px;">
                        <em>After connecting, refresh this page to see the updated status.</em>
                    </p>
                <?php else: ?>
                    <a href="https://managewp.com/dashboard" class="button button-small" target="_blank">
                        Open ManageWP Dashboard
                    </a>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small" style="margin-left: 5px;">
                        Manage Plugins
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Enable/disable the name field and button based on checkbox for ManageWP
    document.addEventListener('DOMContentLoaded', function() {
        const noManageWPCheckbox = document.getElementById('no-managewp-checkbox');
        const nameField = document.getElementById('approved-by-name-managewp');

        // Handle "No ManageWP needed" checkbox
        if (noManageWPCheckbox && nameField) {
            const noManageWPButton = document.querySelector('button[onclick="approveNoManageWP()"]');

            noManageWPCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    nameField.disabled = false;
                    nameField.focus();
                    // Enable button when name is entered
                    nameField.addEventListener('input', function() {
                        if (noManageWPButton) {
                            noManageWPButton.disabled = this.value.trim() === '';
                        }
                    });
                } else {
                    nameField.disabled = true;
                    nameField.value = '';
                    if (noManageWPButton) {
                        noManageWPButton.disabled = true;
                    }
                }
            });
        }
    });

    function approveNoManageWP() {
        const checkbox = document.getElementById('no-managewp-checkbox');
        const nameField = document.getElementById('approved-by-name-managewp');

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

        if (!confirm('Are you sure this website doesn\'t need ManageWP management? This decision will be tracked.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_approve_no_managewp',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('No ManageWP requirement confirmed successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }



    function resetNoManageWPApproval() {
        if (!confirm('Are you sure you want to reset the no ManageWP approval? This will remove the current approval record.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_reset_no_managewp_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('No ManageWP approval reset successfully.');
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