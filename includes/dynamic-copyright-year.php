<?php
/**
 * Dynamic Copyright Year functionality for Website Optimiser Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check Dynamic Copyright Year status
 */
function website_optimiser_check_dynamic_copyright_status() {
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $dynamic_year_plugin = 'dynamic-year-block/dynamic-year-block.php';

    $plugin_active = is_plugin_active($dynamic_year_plugin);
    $plugin_name = 'Dynamic Year Block';
    $plugin_version = '';

    if ($plugin_active) {
        if (file_exists(WP_PLUGIN_DIR . '/' . $dynamic_year_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $dynamic_year_plugin);
            $plugin_version = $plugin_data['Version'] ?? '';
        }
    }

    $copyright_approved = get_option('website_optimiser_dynamic_copyright_approved', false);
    $approved_by = get_option('website_optimiser_dynamic_copyright_approved_by', '');
    $approved_date = get_option('website_optimiser_dynamic_copyright_approved_date', '');

    if ($copyright_approved) {
        return array(
            'plugin_active' => $plugin_active,
            'plugin_name' => $plugin_name,
            'plugin_version' => $plugin_version,
            'copyright_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'Dynamic Copyright Enabled',
            'message' => 'Confirmed dynamic copyright year is handled for this website.',
            'class' => 'status-good'
        );
    } elseif ($plugin_active) {
        return array(
            'plugin_active' => true,
            'plugin_name' => $plugin_name,
            'plugin_version' => $plugin_version,
            'copyright_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Plugin Active, Not Confirmed',
            'message' => 'Dynamic Year Block plugin is active. Please confirm it is in use.',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'plugin_active' => false,
            'plugin_name' => '',
            'plugin_version' => '',
            'copyright_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Not Enabled',
            'message' => 'Consider installing Dynamic Year Block for automatic copyright year updates.',
            'class' => 'status-warning'
        );
    }
}

/**
 * Handle AJAX request to approve dynamic copyright
 */
function website_optimiser_approve_dynamic_copyright() {
    if (!check_ajax_referer('website_optimiser_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    update_option('website_optimiser_dynamic_copyright_approved', true);
    update_option('website_optimiser_dynamic_copyright_approved_by', $approved_by);
    update_option('website_optimiser_dynamic_copyright_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'Dynamic copyright requirement confirmed')));
}
add_action('wp_ajax_website_optimiser_approve_dynamic_copyright', 'website_optimiser_approve_dynamic_copyright');

/**
 * Handle AJAX request to reset dynamic copyright approval
 */
function website_optimiser_reset_dynamic_copyright_approval() {
    if (!check_ajax_referer('website_optimiser_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    delete_option('website_optimiser_dynamic_copyright_approved');
    delete_option('website_optimiser_dynamic_copyright_approved_by');
    delete_option('website_optimiser_dynamic_copyright_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'Dynamic copyright approval reset')));
}
add_action('wp_ajax_website_optimiser_reset_dynamic_copyright_approval', 'website_optimiser_reset_dynamic_copyright_approval');


/**
 * Render Dynamic Copyright Year section
 */
function website_optimiser_render_dynamic_copyright_section() {
    $copyright_status = website_optimiser_check_dynamic_copyright_status();
    ?>
    <div class="seo-stat-item <?php echo $copyright_status['class']; ?>">
        <div class="stat-icon">©</div>
        <div class="stat-content">
            <h4>Dynamic Copyright Year</h4>
            <div class="stat-status <?php echo $copyright_status['class']; ?>">
                <?php echo $copyright_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $copyright_status['message']; ?>
                <?php if (!empty($copyright_status['plugin_name']) && $copyright_status['plugin_active']): ?>
                    <br><small><?php echo $copyright_status['plugin_name']; ?>: v<?php echo $copyright_status['plugin_version']; ?></small>
                <?php endif; ?>

                <?php if ($copyright_status['copyright_approved']): ?>
                    <br><br><small><strong>Approved by:</strong> <?php echo esc_html($copyright_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($copyright_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($copyright_status['copyright_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetDynamicCopyrightApproval()">
                        Reset Approval
                    </button>
                <?php else: ?>
                    <?php if (!$copyright_status['plugin_active']): ?>
                        <a href="<?php echo admin_url('plugin-install.php?s=dynamic-year-block&tab=search&type=term'); ?>" class="button button-small">
                            Install Dynamic Year Block
                        </a>
                        <br><br>
                    <?php endif; ?>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Is dynamic copyright handled correctly?
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="dynamic-copyright-checkbox" style="margin-right: 5px;">
                            Confirm this website handles dynamic copyright year
                        </label>
                        <input type="text" id="approved-by-name-copyright" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveDynamicCopyright()" disabled>
                            Confirm
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('dynamic-copyright-checkbox');
        const nameField = document.getElementById('approved-by-name-copyright');
        const button = document.querySelector('button[onclick="approveDynamicCopyright()"]');

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

    function approveDynamicCopyright() {
        const checkbox = document.getElementById('dynamic-copyright-checkbox');
        const nameField = document.getElementById('approved-by-name-copyright');

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

        if (!confirm('Are you sure this website handles the dynamic copyright year correctly? This decision will be tracked.')) {
            return;
        }

        jQuery.post(ajaxurl, {
            action: 'website_optimiser_approve_dynamic_copyright',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('website_optimiser_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Dynamic copyright confirmation saved.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetDynamicCopyrightApproval() {
        if (!confirm('Are you sure you want to reset the dynamic copyright approval?')) {
            return;
        }

        jQuery.post(ajaxurl, {
            action: 'website_optimiser_reset_dynamic_copyright_approval',
            nonce: '<?php echo wp_create_nonce('website_optimiser_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Dynamic copyright approval reset.');
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