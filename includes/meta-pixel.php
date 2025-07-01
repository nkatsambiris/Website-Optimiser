<?php
/**
 * Meta Pixel tracking functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check Meta Pixel tracking status
 */
function meta_description_boy_check_meta_pixel_status() {
    // Check if plugin functions are available
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $pixelyoursite_plugin = 'pixelyoursite/pixelyoursite.php';
    $meta_pixel_plugin = 'official-facebook-pixel/facebook-for-wordpress.php';
    $pixel_cat_plugin = 'facebook-conversion-pixel/facebook-conversion-pixel.php';

    $pixel_plugin_active = false;
    $pixel_plugin_name = '';
    $pixel_plugin_version = '';
    $pixel_configured = false;
    $pixel_id = '';
    $active_pixels = 0;

    // Check for manual approval of no pixel tracking needed
    $no_pixel_approved = get_option('meta_description_boy_no_pixel_approved', false);
    $approved_by = get_option('meta_description_boy_no_pixel_approved_by', '');
    $approved_date = get_option('meta_description_boy_no_pixel_approved_date', '');

    // Check which pixel plugin is installed and active
    if (is_plugin_active($pixelyoursite_plugin)) {
        $pixel_plugin_active = true;
        $pixel_plugin_name = 'PixelYourSite';
        if (file_exists(WP_PLUGIN_DIR . '/' . $pixelyoursite_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $pixelyoursite_plugin);
            $pixel_plugin_version = $plugin_data['Version'] ?? '';
        }

        // Check if Facebook pixel is configured in PixelYourSite
        $pys_facebook_id = get_option('pys_facebook_pixel_id');
        if (!empty($pys_facebook_id)) {
            $pixel_configured = true;
            $pixel_id = $pys_facebook_id;
            $active_pixels = 1;
        }

    } elseif (is_plugin_active($meta_pixel_plugin)) {
        $pixel_plugin_active = true;
        $pixel_plugin_name = 'Meta Pixel for WordPress';
        if (file_exists(WP_PLUGIN_DIR . '/' . $meta_pixel_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $meta_pixel_plugin);
            $pixel_plugin_version = $plugin_data['Version'] ?? '';
        }

        // Check if Meta pixel is configured
        $meta_pixel_id = get_option('facebook_pixel_id');
        if (!empty($meta_pixel_id)) {
            $pixel_configured = true;
            $pixel_id = $meta_pixel_id;
            $active_pixels = 1;
        }

    } elseif (is_plugin_active($pixel_cat_plugin)) {
        $pixel_plugin_active = true;
        $pixel_plugin_name = 'Pixel Cat';
        if (file_exists(WP_PLUGIN_DIR . '/' . $pixel_cat_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $pixel_cat_plugin);
            $pixel_plugin_version = $plugin_data['Version'] ?? '';
        }

        // Check if pixel is configured in Pixel Cat
        $pixel_cat_id = get_option('facebook_pixel_id');
        if (!empty($pixel_cat_id)) {
            $pixel_configured = true;
            $pixel_id = $pixel_cat_id;
            $active_pixels = 1;
        }
    }

    // Determine status based on findings
    if ($no_pixel_approved) {
        return array(
            'pixel_plugin_active' => $pixel_plugin_active,
            'pixel_plugin_name' => $pixel_plugin_name,
            'pixel_plugin_version' => $pixel_plugin_version,
            'pixel_configured' => $pixel_configured,
            'pixel_id' => $pixel_id,
            'active_pixels' => $active_pixels,
            'no_pixel_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'No Pixel Tracking Required',
            'message' => 'Confirmed no Meta Pixel tracking needed for this website',
            'class' => 'status-good'
        );
    } elseif (!$pixel_plugin_active) {
        return array(
            'pixel_plugin_active' => false,
            'pixel_plugin_name' => '',
            'pixel_plugin_version' => '',
            'pixel_configured' => false,
            'pixel_id' => '',
            'active_pixels' => 0,
            'no_pixel_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'No Pixel Plugin Found',
            'message' => 'Consider installing a Meta Pixel tracking plugin',
            'class' => 'status-warning'
        );
    } elseif (!$pixel_configured) {
        return array(
            'pixel_plugin_active' => true,
            'pixel_plugin_name' => $pixel_plugin_name,
            'pixel_plugin_version' => $pixel_plugin_version,
            'pixel_configured' => false,
            'pixel_id' => '',
            'active_pixels' => 0,
            'no_pixel_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Pixel Not Configured',
            'message' => $pixel_plugin_name . ' is active but no Meta Pixel ID configured',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'pixel_plugin_active' => true,
            'pixel_plugin_name' => $pixel_plugin_name,
            'pixel_plugin_version' => $pixel_plugin_version,
            'pixel_configured' => true,
            'pixel_id' => $pixel_id,
            'active_pixels' => $active_pixels,
            'no_pixel_approved' => false,
            'approved_by' => '',
            'approved_date' => '',
            'status' => 'Meta Pixel Active',
            'message' => 'Meta Pixel is configured and tracking visitors',
            'class' => 'status-good'
        );
    }
}

/**
 * Handle AJAX request to approve no pixel tracking needed
 */
function meta_description_boy_approve_no_pixel() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    // Save approval data
    update_option('meta_description_boy_no_pixel_approved', true);
    update_option('meta_description_boy_no_pixel_approved_by', $approved_by);
    update_option('meta_description_boy_no_pixel_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'No pixel tracking requirement confirmed')));
}
add_action('wp_ajax_meta_description_boy_approve_no_pixel', 'meta_description_boy_approve_no_pixel');

/**
 * Handle AJAX request to reset no pixel approval
 */
function meta_description_boy_reset_no_pixel_approval() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    // Remove approval data
    delete_option('meta_description_boy_no_pixel_approved');
    delete_option('meta_description_boy_no_pixel_approved_by');
    delete_option('meta_description_boy_no_pixel_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'No pixel approval reset')));
}
add_action('wp_ajax_meta_description_boy_reset_no_pixel_approval', 'meta_description_boy_reset_no_pixel_approval');

/**
 * Render Meta Pixel section
 */
function meta_description_boy_render_meta_pixel_section() {
    $pixel_status = meta_description_boy_check_meta_pixel_status();
    ?>
    <div class="seo-stat-item <?php echo $pixel_status['class']; ?>">
        <div class="stat-icon">📈</div>
        <div class="stat-content">
            <h4>Meta Pixel Tracking</h4>
            <div class="stat-status <?php echo $pixel_status['class']; ?>">
                <?php echo $pixel_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $pixel_status['message']; ?>
                <?php if (!empty($pixel_status['pixel_plugin_name']) && !empty($pixel_status['pixel_plugin_version'])): ?>
                    <br><small><?php echo $pixel_status['pixel_plugin_name']; ?>: v<?php echo $pixel_status['pixel_plugin_version']; ?></small>
                <?php endif; ?>

                <?php if (!empty($pixel_status['pixel_id'])): ?>
                    <br><small><strong>Pixel ID:</strong> <?php echo esc_html($pixel_status['pixel_id']); ?></small>
                <?php endif; ?>

                <?php if ($pixel_status['no_pixel_approved']): ?>
                    <br><br><small><strong>Approved by:</strong> <?php echo esc_html($pixel_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($pixel_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($pixel_status['no_pixel_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetNoPixelApproval()">
                        Reset Approval
                    </button>
                <?php elseif (!$pixel_status['pixel_plugin_active']): ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=pixelyoursite&tab=search&type=term'); ?>" class="button button-small">
                        Install PixelYourSite
                    </a>
                    <a href="<?php echo admin_url('plugin-install.php?s=meta+pixel&tab=search&type=term'); ?>" class="button button-small" style="margin-left: 5px;">
                        Install Meta Pixel
                    </a>
                    <br><br>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            New website with no pixel tracking needed?
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="no-pixel-checkbox" style="margin-right: 5px;">
                            Confirm this website doesn't require Meta Pixel tracking
                        </label>
                        <input type="text" id="approved-by-name-pixel" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveNoPixel()" disabled>
                            Confirm No Pixel Needed
                        </button>
                    </div>
                <?php elseif (!$pixel_status['pixel_configured']): ?>
                    <?php if ($pixel_status['pixel_plugin_name'] === 'PixelYourSite'): ?>
                        <a href="<?php echo admin_url('admin.php?page=pixelyoursite'); ?>" class="button button-small">
                            Configure PixelYourSite
                        </a>
                    <?php elseif ($pixel_status['pixel_plugin_name'] === 'Meta Pixel for WordPress'): ?>
                        <a href="<?php echo admin_url('admin.php?page=facebook_for_wordpress'); ?>" class="button button-small">
                            Configure Meta Pixel
                        </a>
                    <?php elseif ($pixel_status['pixel_plugin_name'] === 'Pixel Cat'): ?>
                        <a href="<?php echo admin_url('admin.php?page=pixel-cat'); ?>" class="button button-small">
                            Configure Pixel Cat
                        </a>
                    <?php endif; ?>
                    <br><br>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            New website with no pixel tracking needed?
                        </label>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="no-pixel-checkbox" style="margin-right: 5px;">
                            Confirm this website doesn't require Meta Pixel tracking
                        </label>
                        <input type="text" id="approved-by-name-pixel" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveNoPixel()" disabled>
                            Confirm No Pixel Needed
                        </button>
                    </div>
                <?php else: ?>
                    <?php if ($pixel_status['pixel_plugin_name'] === 'PixelYourSite'): ?>
                        <a href="<?php echo admin_url('admin.php?page=pixelyoursite'); ?>" class="button button-small">
                            Manage PixelYourSite
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=pixelyoursite_events'); ?>" class="button button-small" style="margin-left: 5px;">
                            View Events
                        </a>
                    <?php elseif ($pixel_status['pixel_plugin_name'] === 'Meta Pixel for WordPress'): ?>
                        <a href="<?php echo admin_url('admin.php?page=facebook_for_wordpress'); ?>" class="button button-small">
                            Manage Meta Pixel
                        </a>
                    <?php elseif ($pixel_status['pixel_plugin_name'] === 'Pixel Cat'): ?>
                        <a href="<?php echo admin_url('admin.php?page=pixel-cat'); ?>" class="button button-small">
                            Manage Pixel Cat
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Enable/disable the name field and button based on checkbox for pixel
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('no-pixel-checkbox');
        const nameField = document.getElementById('approved-by-name-pixel');
        const button = document.querySelector('button[onclick="approveNoPixel()"]');

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

    function approveNoPixel() {
        const checkbox = document.getElementById('no-pixel-checkbox');
        const nameField = document.getElementById('approved-by-name-pixel');

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

        if (!confirm('Are you sure this website doesn\'t need Meta Pixel tracking? This decision will be tracked.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_approve_no_pixel',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('No pixel tracking requirement confirmed successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetNoPixelApproval() {
        if (!confirm('Are you sure you want to reset the no pixel approval? This will remove the current approval record.')) {
            return;
        }

        // Send AJAX request
        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_reset_no_pixel_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('No pixel approval reset successfully.');
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