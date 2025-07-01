<?php
/**
 * HubSpot CRM functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check HubSpot status
 */
function meta_description_boy_check_hubspot_status() {
    // Check if plugin functions are available
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $hubspot_plugin = 'leadin/leadin.php';
    $hubspot_installed = false;
    $hubspot_active = false;
    $hubspot_version = '';
    $hubspot_connected = false;
    $forms_count = 0;
    $contacts_count = 0;

    // Check for manual approval of no CRM needed
    $no_crm_approved = get_option('meta_description_boy_no_crm_approved', false);
    $approved_by = get_option('meta_description_boy_no_crm_approved_by', '');
    $approved_date = get_option('meta_description_boy_no_crm_approved_date', '');

    // Check if HubSpot plugin is installed and active
    if (is_plugin_active($hubspot_plugin)) {
        $hubspot_installed = true;
        $hubspot_active = true;
        if (file_exists(WP_PLUGIN_DIR . '/' . $hubspot_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $hubspot_plugin);
            $hubspot_version = $plugin_data['Version'] ?? '';
        }
    } elseif (file_exists(WP_PLUGIN_DIR . '/' . $hubspot_plugin)) {
        $hubspot_installed = true;
        $hubspot_active = false;
    }

    // Check if HubSpot is connected when plugin is active
    if ($hubspot_active) {
        // Check for HubSpot connection by looking for common HubSpot options
        $hubspot_portal_id = get_option('leadin_portalId');
        $hubspot_api_key = get_option('leadin_apikey');
        $hubspot_connected = !empty($hubspot_portal_id) || !empty($hubspot_api_key);

        // Try to get basic stats if connected
        if ($hubspot_connected) {
            // These are approximations since HubSpot doesn't expose detailed stats easily
            $forms_count = 'Connected'; // We can't easily count forms without API access
            $contacts_count = 'Connected'; // We can't easily count contacts without API access
        }
    }

    // Determine status based on findings
    if ($no_crm_approved) {
        return array(
            'hubspot_installed' => $hubspot_installed,
            'hubspot_active' => $hubspot_active,
            'hubspot_version' => $hubspot_version,
            'hubspot_connected' => $hubspot_connected,
            'forms_count' => $forms_count,
            'contacts_count' => $contacts_count,
            'no_crm_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'No CRM Required',
            'message' => 'Confirmed no CRM/marketing tracking needed for this website',
            'class' => 'status-good'
        );
    } elseif (!$hubspot_installed) {
        return array(
            'hubspot_installed' => false,
            'hubspot_active' => false,
            'hubspot_version' => '',
            'hubspot_connected' => false,
            'forms_count' => 0,
            'contacts_count' => 0,
            'no_crm_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'HubSpot Missing',
            'message' => 'Consider installing HubSpot for CRM and marketing automation',
            'class' => 'status-warning'
        );
    } elseif (!$hubspot_active) {
        return array(
            'hubspot_installed' => true,
            'hubspot_active' => false,
            'hubspot_version' => '',
            'hubspot_connected' => false,
            'forms_count' => 0,
            'contacts_count' => 0,
            'no_crm_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'HubSpot Inactive',
            'message' => 'HubSpot plugin is installed but not activated',
            'class' => 'status-error'
        );
    } elseif (!$hubspot_connected) {
        return array(
            'hubspot_installed' => true,
            'hubspot_active' => true,
            'hubspot_version' => $hubspot_version,
            'hubspot_connected' => false,
            'forms_count' => 0,
            'contacts_count' => 0,
            'no_crm_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'HubSpot Not Connected',
            'message' => 'HubSpot plugin active but not connected to HubSpot account',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'hubspot_installed' => true,
            'hubspot_active' => true,
            'hubspot_version' => $hubspot_version,
            'hubspot_connected' => true,
            'forms_count' => $forms_count,
            'contacts_count' => $contacts_count,
            'no_crm_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'HubSpot Connected',
            'message' => 'HubSpot is connected and tracking visitors',
            'class' => 'status-good'
        );
    }
}

/**
 * Handle AJAX request to approve no CRM needed
 */
function meta_description_boy_approve_no_crm() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    // Save approval data
    update_option('meta_description_boy_no_crm_approved', true);
    update_option('meta_description_boy_no_crm_approved_by', $approved_by);
    update_option('meta_description_boy_no_crm_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'No CRM requirement confirmed')));
}
add_action('wp_ajax_meta_description_boy_approve_no_crm', 'meta_description_boy_approve_no_crm');

/**
 * Handle AJAX request to reset no CRM approval
 */
function meta_description_boy_reset_no_crm_approval() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    // Remove approval data
    delete_option('meta_description_boy_no_crm_approved');
    delete_option('meta_description_boy_no_crm_approved_by');
    delete_option('meta_description_boy_no_crm_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'No CRM approval reset')));
}
add_action('wp_ajax_meta_description_boy_reset_no_crm_approval', 'meta_description_boy_reset_no_crm_approval');

/**
 * Render HubSpot section
 */
function meta_description_boy_render_hubspot_section() {
    $hubspot_status = meta_description_boy_check_hubspot_status();
    ?>
    <div class="seo-stat-item <?php echo $hubspot_status['class']; ?>">
        <div class="stat-icon">📊</div>
        <div class="stat-content">
            <h4>HubSpot CRM</h4>
            <div class="stat-status <?php echo $hubspot_status['class']; ?>">
                <?php echo $hubspot_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $hubspot_status['message']; ?>
                <?php if (!empty($hubspot_status['hubspot_version'])): ?>
                    <br><small>HubSpot plugin: v<?php echo $hubspot_status['hubspot_version']; ?></small>
                <?php endif; ?>

                <?php if ($hubspot_status['no_crm_approved']): ?>
                    <br><br><small><strong>Approved by:</strong> <?php echo esc_html($hubspot_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($hubspot_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($hubspot_status['no_crm_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetNoCrmApproval()">
                        Reset Approval
                    </button>
                <?php elseif (!$hubspot_status['hubspot_installed']): ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=hubspot&tab=search&type=term'); ?>" class="button button-small">
                        Install HubSpot
                    </a>
                    <br><br>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            New website with no CRM/marketing tracking needed?
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="no-crm-checkbox" style="margin-right: 5px;">
                            Confirm this website doesn't require CRM or marketing tracking
                        </label>
                        <input type="text" id="approved-by-name-crm" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveNoCrm()" disabled>
                            Confirm No CRM Needed
                        </button>
                    </div>
                <?php elseif (!$hubspot_status['hubspot_active']): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small">
                        Activate HubSpot
                    </a>
                <?php elseif (!$hubspot_status['hubspot_connected']): ?>
                    <a href="<?php echo admin_url('admin.php?page=leadin'); ?>" class="button button-small">
                        Connect HubSpot
                    </a>
                    <br><br>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            New website with no CRM/marketing tracking needed?
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="no-crm-checkbox" style="margin-right: 5px;">
                            Confirm this website doesn't require CRM or marketing tracking
                        </label>
                        <input type="text" id="approved-by-name-crm" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveNoCrm()" disabled>
                            Confirm No CRM Needed
                        </button>
                    </div>
                <?php else: ?>
                    <a href="<?php echo admin_url('admin.php?page=leadin'); ?>" class="button button-small">
                        Manage HubSpot
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=leadin_settings'); ?>" class="button button-small" style="margin-left: 5px;">
                        HubSpot Settings
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Enable/disable the name field and button based on checkbox for CRM
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('no-crm-checkbox');
        const nameField = document.getElementById('approved-by-name-crm');
        const button = document.querySelector('button[onclick="approveNoCrm()"]');

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

    function approveNoCrm() {
        const checkbox = document.getElementById('no-crm-checkbox');
        const nameField = document.getElementById('approved-by-name-crm');

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

        if (!confirm('Are you sure this website doesn\'t need CRM or marketing tracking? This decision will be tracked.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_approve_no_crm',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('No CRM requirement confirmed successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetNoCrmApproval() {
        if (!confirm('Are you sure you want to reset the no CRM approval? This will remove the current approval record.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_reset_no_crm_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('No CRM approval reset successfully.');
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