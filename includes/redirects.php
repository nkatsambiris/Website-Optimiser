<?php
/**
 * Redirects functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check redirects status
 */
function meta_description_boy_check_redirects_status() {
    // Check if plugin functions are available
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $redirection_plugin = 'redirection/redirection.php';
    $redirection_installed = false;
    $redirection_active = false;
    $redirection_version = '';
    $total_redirects = 0;
    $active_redirects = 0;

    // Check for manual approval of no redirects needed
    $no_redirects_approved = get_option('meta_description_boy_no_redirects_approved', false);
    $approved_by = get_option('meta_description_boy_no_redirects_approved_by', '');
    $approved_date = get_option('meta_description_boy_no_redirects_approved_date', '');

    // Check if Redirection plugin is installed and active
    if (is_plugin_active($redirection_plugin)) {
        $redirection_installed = true;
        $redirection_active = true;
        if (file_exists(WP_PLUGIN_DIR . '/' . $redirection_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $redirection_plugin);
            $redirection_version = $plugin_data['Version'] ?? '';
        }
    } elseif (file_exists(WP_PLUGIN_DIR . '/' . $redirection_plugin)) {
        $redirection_installed = true;
        $redirection_active = false;
    }

    // Only check redirects if plugin is active
    if ($redirection_active && class_exists('Red_Item')) {
        global $wpdb;

        // Count total redirects from the redirection_items table
        $table_name = $wpdb->prefix . 'redirection_items';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $total_redirects = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $active_redirects = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'enabled'");
        }
    }

    // Determine status based on findings
    if ($no_redirects_approved) {
        return array(
            'redirection_installed' => $redirection_installed,
            'redirection_active' => $redirection_active,
            'redirection_version' => $redirection_version,
            'total_redirects' => $total_redirects,
            'active_redirects' => $active_redirects,
            'no_redirects_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'No Redirects Required',
            'message' => 'Confirmed no redirects needed for this website',
            'class' => 'status-good'
        );
    } elseif (!$redirection_installed) {
        return array(
            'redirection_installed' => false,
            'redirection_active' => false,
            'redirection_version' => '',
            'total_redirects' => 0,
            'active_redirects' => 0,
            'no_redirects_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Redirection Plugin Missing',
            'message' => 'Consider installing Redirection plugin for redirect management',
            'class' => 'status-warning'
        );
    } elseif (!$redirection_active) {
        return array(
            'redirection_installed' => true,
            'redirection_active' => false,
            'redirection_version' => '',
            'total_redirects' => 0,
            'active_redirects' => 0,
            'no_redirects_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Redirection Plugin Inactive',
            'message' => 'Redirection plugin is installed but not activated',
            'class' => 'status-error'
        );
    } elseif ($total_redirects === 0) {
        return array(
            'redirection_installed' => true,
            'redirection_active' => true,
            'redirection_version' => $redirection_version,
            'total_redirects' => 0,
            'active_redirects' => 0,
            'no_redirects_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'No Redirects Found',
            'message' => 'No redirects configured - confirm if this is intentional',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'redirection_installed' => true,
            'redirection_active' => true,
            'redirection_version' => $redirection_version,
            'total_redirects' => $total_redirects,
            'active_redirects' => $active_redirects,
            'no_redirects_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Redirects Configured',
            'message' => $active_redirects . ' active redirect(s) out of ' . $total_redirects . ' total',
            'class' => 'status-good'
        );
    }
}

/**
 * Handle AJAX request to approve no redirects needed
 */
function meta_description_boy_approve_no_redirects() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    // Save approval data
    update_option('meta_description_boy_no_redirects_approved', true);
    update_option('meta_description_boy_no_redirects_approved_by', $approved_by);
    update_option('meta_description_boy_no_redirects_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'No redirects requirement confirmed')));
}
add_action('wp_ajax_meta_description_boy_approve_no_redirects', 'meta_description_boy_approve_no_redirects');

/**
 * Handle AJAX request to reset no redirects approval
 */
function meta_description_boy_reset_no_redirects_approval() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    // Remove approval data
    delete_option('meta_description_boy_no_redirects_approved');
    delete_option('meta_description_boy_no_redirects_approved_by');
    delete_option('meta_description_boy_no_redirects_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'No redirects approval reset')));
}
add_action('wp_ajax_meta_description_boy_reset_no_redirects_approval', 'meta_description_boy_reset_no_redirects_approval');

/**
 * Render redirects section
 */
function meta_description_boy_render_redirects_section() {
    $redirects_status = meta_description_boy_check_redirects_status();
    $current_user = wp_get_current_user();
    ?>
    <div class="seo-stat-item <?php echo $redirects_status['class']; ?>">
        <div class="stat-icon">🔄</div>
        <div class="stat-content">
            <h4>Redirects</h4>
            <div class="stat-status <?php echo $redirects_status['class']; ?>">
                <?php echo $redirects_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $redirects_status['message']; ?>
                <?php if (!empty($redirects_status['redirection_version'])): ?>
                    <br><small>Redirection plugin: v<?php echo $redirects_status['redirection_version']; ?></small>
                <?php endif; ?>

                <?php if ($redirects_status['no_redirects_approved']): ?>
                    <br><br><small><strong>Approved by:</strong> <?php echo esc_html($redirects_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($redirects_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($redirects_status['no_redirects_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetNoRedirectsApproval()">
                        Reset Approval
                    </button>
                <?php elseif (!$redirects_status['redirection_installed']): ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=redirection&tab=search&type=term'); ?>" class="button button-small">
                        Install Redirection Plugin
                    </a>
                    <br><br>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            New website with no redirects needed?
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="no-redirects-checkbox" style="margin-right: 5px;">
                            Confirm this website doesn't require any redirects
                        </label>
                        <input type="text" id="approved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveNoRedirects()" disabled>
                            Confirm No Redirects Needed
                        </button>
                    </div>
                <?php elseif (!$redirects_status['redirection_active']): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small">
                        Activate Redirection Plugin
                    </a>
                <?php elseif ($redirects_status['total_redirects'] === 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=redirection.php'); ?>" class="button button-small">
                        Add Redirects
                    </a>
                    <br><br>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            New website with no redirects needed?
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="no-redirects-checkbox" style="margin-right: 5px;">
                            Confirm this website doesn't require any redirects
                        </label>
                        <input type="text" id="approved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveNoRedirects()" disabled>
                            Confirm No Redirects Needed
                        </button>
                    </div>
                <?php else: ?>
                    <a href="<?php echo admin_url('admin.php?page=redirection.php'); ?>" class="button button-small">
                        Manage Redirects
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=redirection.php&sub=redirects'); ?>" class="button button-small" style="margin-left: 5px;">
                        View All Redirects
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Enable/disable the name field and button based on checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('no-redirects-checkbox');
        const nameField = document.getElementById('approved-by-name');
        const button = document.querySelector('button[onclick="approveNoRedirects()"]');

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

    function approveNoRedirects() {
        const checkbox = document.getElementById('no-redirects-checkbox');
        const nameField = document.getElementById('approved-by-name');

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

        if (!confirm('Are you sure this website doesn\'t need any redirects? This decision will be tracked.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_approve_no_redirects',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('No redirects requirement confirmed successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetNoRedirectsApproval() {
        if (!confirm('Are you sure you want to reset the no redirects approval? This will remove the current approval record.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_reset_no_redirects_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('No redirects approval reset successfully.');
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