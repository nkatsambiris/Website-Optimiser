<?php
/**
 * Gravity Forms Notifications functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Get stored DMARC approvals for Gravity Forms notifications.
 */
function meta_description_boy_get_gravity_forms_notification_dmarc_approvals() {
    $approvals = get_option('meta_description_boy_gf_notification_dmarc_approvals', array());

    return is_array($approvals) ? $approvals : array();
}

/**
 * Build a stable key for a Gravity Forms notification approval.
 */
function meta_description_boy_get_gravity_forms_notification_approval_key($form_id, $notification_id) {
    return absint($form_id) . ':' . sanitize_key((string) $notification_id);
}

/**
 * Check whether a notification has a current DMARC approval for its FROM address.
 */
function meta_description_boy_gravity_forms_notification_has_dmarc_approval($form_id, $notification_id, $from_address) {
    $approvals = meta_description_boy_get_gravity_forms_notification_dmarc_approvals();
    $approval_key = meta_description_boy_get_gravity_forms_notification_approval_key($form_id, $notification_id);

    return !empty($approvals[$approval_key]['approved'])
        && isset($approvals[$approval_key]['from_address'])
        && $approvals[$approval_key]['from_address'] === $from_address;
}

/**
 * Check Gravity Forms notification settings
 */
function meta_description_boy_check_gravity_forms_notifications_status() {
    // Check if plugin functions are available
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $gravity_forms_plugin = 'gravityforms/gravityforms.php';
    $gf_installed = false;
    $gf_active = false;
    $gf_version = '';
    $total_forms = 0;
    $total_notifications = 0;
    $admin_email_issues = 0;
    $from_address_issues = 0;
    $dmarc_approved_notifications = 0;
    $problematic_forms = array();

    // Check if Gravity Forms is installed and active
    if (is_plugin_active($gravity_forms_plugin)) {
        $gf_installed = true;
        $gf_active = true;
        if (file_exists(WP_PLUGIN_DIR . '/' . $gravity_forms_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $gravity_forms_plugin);
            $gf_version = $plugin_data['Version'] ?? '';
        }
    } elseif (file_exists(WP_PLUGIN_DIR . '/' . $gravity_forms_plugin)) {
        $gf_installed = true;
        $gf_active = false;
    }

    // Only check notifications if Gravity Forms is active
    if ($gf_active && class_exists('GFAPI')) {
        $forms = GFAPI::get_forms();
        $total_forms = count($forms);

        foreach ($forms as $form) {
            $form_has_issues = false;
            $form_issues = array();
            $form_notifications = array();

            // Get notifications for this form
            $notifications = isset($form['notifications']) ? $form['notifications'] : array();

            foreach ($notifications as $notification_id => $notification) {
                $total_notifications++;

                // Check if notification is active
                if (isset($notification['isActive']) && !$notification['isActive']) {
                    continue; // Skip inactive notifications
                }

                $notification_has_issues = false;
                $notification_issues = array();
                $notification_name = isset($notification['name']) && $notification['name'] !== '' ? $notification['name'] : 'Unnamed Notification';

                // Check 'to' address for {admin_email}
                $to_address = isset($notification['to']) ? $notification['to'] : '';
                if (strpos($to_address, '{admin_email}') !== false) {
                    $admin_email_issues++;
                    $form_has_issues = true;
                    $notification_has_issues = true;
                    $notification_issues[] = 'Uses {admin_email} in TO field';
                }

                // Check 'from' address
                $from_address = isset($notification['from']) ? $notification['from'] : '';
                $from_address_has_dmarc_approval = false;
                if ($from_address !== 'noreply@pixeld.com.au') {
                    if (meta_description_boy_gravity_forms_notification_has_dmarc_approval($form['id'], $notification_id, $from_address)) {
                        $from_address_has_dmarc_approval = true;
                        $dmarc_approved_notifications++;
                    } else {
                        $from_address_issues++;
                        $form_has_issues = true;
                        $notification_has_issues = true;
                        $notification_issues[] = 'FROM address not set to noreply@pixeld.com.au (currently: ' . $from_address . ')';
                    }
                }

                if ($notification_has_issues) {
                    $form_issues = array_merge($form_issues, $notification_issues);
                    $form_notifications[] = array(
                        'id' => (string) $notification_id,
                        'name' => $notification_name,
                        'from_address' => $from_address,
                        'issues' => $notification_issues,
                        'can_approve_dmarc' => $from_address !== 'noreply@pixeld.com.au' && !$from_address_has_dmarc_approval,
                    );
                }
            }

            if ($form_has_issues) {
                $problematic_forms[] = array(
                    'id' => $form['id'],
                    'title' => $form['title'],
                    'issues' => $form_issues,
                    'notifications' => $form_notifications,
                );
            }
        }
    }

    // Determine status based on findings
    if (!$gf_installed) {
        return array(
            'gf_installed' => false,
            'gf_active' => false,
            'gf_version' => '',
            'total_forms' => 0,
            'total_notifications' => 0,
            'admin_email_issues' => 0,
            'from_address_issues' => 0,
            'dmarc_approved_notifications' => 0,
            'problematic_forms' => array(),
            'status' => 'Gravity Forms Missing',
            'message' => 'Gravity Forms plugin is not installed',
            'class' => 'status-error'
        );
    } elseif (!$gf_active) {
        return array(
            'gf_installed' => true,
            'gf_active' => false,
            'gf_version' => '',
            'total_forms' => 0,
            'total_notifications' => 0,
            'admin_email_issues' => 0,
            'from_address_issues' => 0,
            'dmarc_approved_notifications' => 0,
            'problematic_forms' => array(),
            'status' => 'Gravity Forms Inactive',
            'message' => 'Gravity Forms is installed but not activated',
            'class' => 'status-error'
        );
    } elseif ($total_forms === 0) {
        return array(
            'gf_installed' => true,
            'gf_active' => true,
            'gf_version' => $gf_version,
            'total_forms' => 0,
            'total_notifications' => 0,
            'admin_email_issues' => 0,
            'from_address_issues' => 0,
            'dmarc_approved_notifications' => 0,
            'problematic_forms' => array(),
            'status' => 'No Forms Found',
            'message' => 'No forms have been created yet',
            'class' => 'status-warning'
        );
    } elseif ($admin_email_issues > 0 || $from_address_issues > 0) {
        $issues_count = $admin_email_issues + $from_address_issues;
        return array(
            'gf_installed' => true,
            'gf_active' => true,
            'gf_version' => $gf_version,
            'total_forms' => $total_forms,
            'total_notifications' => $total_notifications,
            'admin_email_issues' => $admin_email_issues,
            'from_address_issues' => $from_address_issues,
            'dmarc_approved_notifications' => $dmarc_approved_notifications,
            'problematic_forms' => $problematic_forms,
            'status' => 'Notification Issues Found',
            'message' => $issues_count . ' notification configuration issue(s) found across ' . count($problematic_forms) . ' form(s)',
            'class' => 'status-error'
        );
    } else {
        return array(
            'gf_installed' => true,
            'gf_active' => true,
            'gf_version' => $gf_version,
            'total_forms' => $total_forms,
            'total_notifications' => $total_notifications,
            'admin_email_issues' => 0,
            'from_address_issues' => 0,
            'dmarc_approved_notifications' => $dmarc_approved_notifications,
            'problematic_forms' => array(),
            'status' => 'All Notifications Configured',
            'message' => 'All ' . $total_notifications . ' notifications across ' . $total_forms . ' form(s) are properly configured',
            'class' => 'status-good'
        );
    }
}

/**
 * Handle AJAX request to approve a non-standard notification FROM address.
 */
function meta_description_boy_approve_gravity_forms_notification_dmarc() {
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $form_id = absint($_POST['form_id'] ?? 0);
    $notification_id = sanitize_text_field($_POST['notification_id'] ?? '');
    $from_address = sanitize_text_field($_POST['from_address'] ?? '');
    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (!$form_id || $notification_id === '' || $from_address === '') {
        wp_die(json_encode(array('success' => false, 'message' => 'Missing notification details')));
    }

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    if (!class_exists('GFAPI')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Gravity Forms is not available')));
    }

    $form = GFAPI::get_form($form_id);
    if (empty($form) || empty($form['notifications'][$notification_id])) {
        wp_die(json_encode(array('success' => false, 'message' => 'Notification could not be found')));
    }

    $current_from_address = isset($form['notifications'][$notification_id]['from']) ? $form['notifications'][$notification_id]['from'] : '';
    if ($current_from_address !== $from_address) {
        wp_die(json_encode(array('success' => false, 'message' => 'Notification FROM address has changed. Please refresh and review it again.')));
    }

    if ($current_from_address === 'noreply@pixeld.com.au') {
        wp_die(json_encode(array('success' => false, 'message' => 'This notification already uses the required FROM address')));
    }

    $approvals = meta_description_boy_get_gravity_forms_notification_dmarc_approvals();
    $approval_key = meta_description_boy_get_gravity_forms_notification_approval_key($form_id, $notification_id);

    $approvals[$approval_key] = array(
        'approved' => true,
        'form_id' => $form_id,
        'notification_id' => $notification_id,
        'notification_name' => isset($form['notifications'][$notification_id]['name']) ? sanitize_text_field($form['notifications'][$notification_id]['name']) : '',
        'from_address' => $current_from_address,
        'approved_by' => $approved_by,
        'approved_date' => current_time('mysql'),
    );

    update_option('meta_description_boy_gf_notification_dmarc_approvals', $approvals);

    wp_die(json_encode(array('success' => true, 'message' => 'Notification DMARC confirmation saved')));
}
add_action('wp_ajax_meta_description_boy_approve_gravity_forms_notification_dmarc', 'meta_description_boy_approve_gravity_forms_notification_dmarc');

/**
 * Handle AJAX request to reset a notification DMARC approval.
 */
function meta_description_boy_reset_gravity_forms_notification_dmarc_approval() {
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $form_id = absint($_POST['form_id'] ?? 0);
    $notification_id = sanitize_text_field($_POST['notification_id'] ?? '');

    if (!$form_id || $notification_id === '') {
        wp_die(json_encode(array('success' => false, 'message' => 'Missing notification details')));
    }

    $approvals = meta_description_boy_get_gravity_forms_notification_dmarc_approvals();
    $approval_key = meta_description_boy_get_gravity_forms_notification_approval_key($form_id, $notification_id);

    unset($approvals[$approval_key]);
    update_option('meta_description_boy_gf_notification_dmarc_approvals', $approvals);

    wp_die(json_encode(array('success' => true, 'message' => 'Notification DMARC confirmation reset')));
}
add_action('wp_ajax_meta_description_boy_reset_gravity_forms_notification_dmarc_approval', 'meta_description_boy_reset_gravity_forms_notification_dmarc_approval');

/**
 * Render Gravity Forms notifications section
 */
function meta_description_boy_render_gravity_forms_notifications_section() {
    $notifications_status = meta_description_boy_check_gravity_forms_notifications_status();
    $dmarc_approvals = meta_description_boy_get_gravity_forms_notification_dmarc_approvals();
    ?>
    <div class="seo-stat-item <?php echo $notifications_status['class']; ?>">
        <div class="stat-icon">📧</div>
        <div class="stat-content">
            <h4>Gravity Forms Notifications</h4>
            <div class="stat-status <?php echo $notifications_status['class']; ?>">
                <?php echo $notifications_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $notifications_status['message']; ?>
                <?php if (!empty($notifications_status['gf_version'])): ?>
                    <br><small>Gravity Forms: v<?php echo $notifications_status['gf_version']; ?></small>
                <?php endif; ?>

                <?php if (!empty($notifications_status['problematic_forms'])): ?>
                    <br><br><strong>Issues found in:</strong>
                    <?php foreach ($notifications_status['problematic_forms'] as $form): ?>
                        <br><small>• <strong><?php echo esc_html($form['title']); ?></strong> (ID: <?php echo $form['id']; ?>)</small>
                        <?php if (!empty($form['notifications'])): ?>
                            <?php foreach ($form['notifications'] as $notification): ?>
                                <br><small>&nbsp;&nbsp;<strong><?php echo esc_html($notification['name']); ?></strong> (Notification ID: <?php echo esc_html($notification['id']); ?>)</small>
                                <?php foreach ($notification['issues'] as $issue): ?>
                                    <br><small>&nbsp;&nbsp;&nbsp;&nbsp;- <?php echo esc_html($issue); ?></small>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($form['issues'] as $issue): ?>
                                <br><small>&nbsp;&nbsp;- <?php echo esc_html($issue); ?></small>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($notifications_status['dmarc_approved_notifications'])): ?>
                    <br><br><small><strong><?php echo absint($notifications_status['dmarc_approved_notifications']); ?></strong> notification FROM address override(s) approved with DMARC confirmation.</small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if (!$notifications_status['gf_installed']): ?>
                    <!-- Gravity Forms is not installed -->
                <?php elseif (!$notifications_status['gf_active']): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small">
                        Activate Gravity Forms
                    </a>
                <?php elseif ($notifications_status['total_forms'] === 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=gf_new_form'); ?>" class="button button-small">
                        Create First Form
                    </a>
                <?php elseif (!empty($notifications_status['problematic_forms'])): ?>
                    <a href="<?php echo admin_url('admin.php?page=gf_edit_forms'); ?>" class="button button-small">
                        Fix Notifications
                    </a>
                    <?php if (count($notifications_status['problematic_forms']) === 1): ?>
                        <a href="<?php echo admin_url('admin.php?page=gf_edit_forms&view=settings&subview=notification&id=' . $notifications_status['problematic_forms'][0]['id']); ?>" class="button button-small" style="margin-left: 5px;">
                            Edit Form Notifications
                        </a>
                    <?php endif; ?>

                    <?php
                    $notifications_requiring_dmarc_approval = array();
                    foreach ($notifications_status['problematic_forms'] as $form) {
                        foreach ($form['notifications'] as $notification) {
                            if (!empty($notification['can_approve_dmarc'])) {
                                $notifications_requiring_dmarc_approval[] = array(
                                    'form_id' => $form['id'],
                                    'form_title' => $form['title'],
                                    'notification_id' => $notification['id'],
                                    'notification_name' => $notification['name'],
                                    'from_address' => $notification['from_address'],
                                );
                            }
                        }
                    }
                    ?>

                    <?php if (!empty($notifications_requiring_dmarc_approval)): ?>
                        <div class="gf-notification-dmarc-approvals" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd;">
                            <strong style="display: block; margin-bottom: 8px;">DMARC override approvals</strong>
                            <p style="margin: 0 0 10px; color: #666; font-size: 13px;">
                                Confirm only when DMARC records have been added for the notification's FROM domain.
                            </p>
                            <?php foreach ($notifications_requiring_dmarc_approval as $approval_notification): ?>
                                <div class="gf-notification-dmarc-approval" style="margin-bottom: 10px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                                    <small style="display: block; margin-bottom: 6px;">
                                        <strong><?php echo esc_html($approval_notification['form_title']); ?></strong>:
                                        <?php echo esc_html($approval_notification['notification_name']); ?>
                                        <br>FROM: <code><?php echo esc_html($approval_notification['from_address']); ?></code>
                                    </small>
                                    <label style="display: block; margin-bottom: 6px;">
                                        <input type="checkbox" class="gf-notification-dmarc-confirmation-checkbox">
                                        I confirm DMARC records have been added and this notification can use this FROM address.
                                    </label>
                                    <input type="text" class="gf-notification-dmarc-approved-by" placeholder="Your name" style="width: 100%; max-width: 260px; margin-bottom: 6px;" disabled>
                                    <button
                                        type="button"
                                        class="button button-small gf-notification-dmarc-approve-button"
                                        data-form-id="<?php echo esc_attr($approval_notification['form_id']); ?>"
                                        data-notification-id="<?php echo esc_attr($approval_notification['notification_id']); ?>"
                                        data-from-address="<?php echo esc_attr($approval_notification['from_address']); ?>"
                                        disabled>
                                        Confirm DMARC Override
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?php echo admin_url('admin.php?page=gf_edit_forms'); ?>" class="button button-small">
                        Manage Forms
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=gf_new_form'); ?>" class="button button-small" style="margin-left: 5px;">
                        Create New Form
                    </a>
                <?php endif; ?>

                <?php if (!empty($dmarc_approvals)): ?>
                    <div class="gf-notification-dmarc-existing-approvals" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd;">
                        <strong style="display: block; margin-bottom: 8px;">Approved DMARC overrides</strong>
                        <?php foreach ($dmarc_approvals as $approval): ?>
                            <?php if (empty($approval['approved'])) continue; ?>
                            <div style="margin-bottom: 8px;">
                                <small>
                                    <strong><?php echo esc_html($approval['notification_name'] ?: 'Notification'); ?></strong>
                                    FROM <code><?php echo esc_html($approval['from_address']); ?></code>
                                    <?php if (!empty($approval['approved_by'])): ?>
                                        approved by <strong><?php echo esc_html($approval['approved_by']); ?></strong>
                                    <?php endif; ?>
                                    <?php if (!empty($approval['approved_date'])): ?>
                                        on <?php echo esc_html(date('M j, Y', strtotime($approval['approved_date']))); ?>
                                    <?php endif; ?>
                                </small>
                                <button
                                    type="button"
                                    class="button button-small gf-notification-dmarc-reset-button"
                                    data-form-id="<?php echo esc_attr($approval['form_id']); ?>"
                                    data-notification-id="<?php echo esc_attr($approval['notification_id']); ?>"
                                    style="margin-left: 5px;">
                                    Reset
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.gf-notification-dmarc-approval').forEach(function(container) {
            const checkbox = container.querySelector('.gf-notification-dmarc-confirmation-checkbox');
            const nameField = container.querySelector('.gf-notification-dmarc-approved-by');
            const button = container.querySelector('.gf-notification-dmarc-approve-button');

            if (!checkbox || !nameField || !button) {
                return;
            }

            function updateButtonState() {
                const isConfirmed = checkbox.checked;
                nameField.disabled = !isConfirmed;
                button.disabled = !isConfirmed || !nameField.value.trim();
            }

            checkbox.addEventListener('change', updateButtonState);
            nameField.addEventListener('input', updateButtonState);

            button.addEventListener('click', function() {
                const approvedBy = nameField.value.trim();

                if (!checkbox.checked) {
                    alert('Please check the DMARC confirmation checkbox first.');
                    return;
                }

                if (!approvedBy) {
                    alert('Please enter your name.');
                    return;
                }

                if (!confirm('Confirm that DMARC records have been added for this notification FROM address? This decision will be tracked.')) {
                    return;
                }

                jQuery.post(ajaxurl, {
                    action: 'meta_description_boy_approve_gravity_forms_notification_dmarc',
                    form_id: button.dataset.formId,
                    notification_id: button.dataset.notificationId,
                    from_address: button.dataset.fromAddress,
                    approved_by: approvedBy,
                    nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
                }, function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                }).fail(function() {
                    alert('Error processing request. Please try again.');
                });
            });
        });

        document.querySelectorAll('.gf-notification-dmarc-reset-button').forEach(function(button) {
            button.addEventListener('click', function() {
                if (!confirm('Are you sure you want to reset this notification DMARC override?')) {
                    return;
                }

                jQuery.post(ajaxurl, {
                    action: 'meta_description_boy_reset_gravity_forms_notification_dmarc_approval',
                    form_id: button.dataset.formId,
                    notification_id: button.dataset.notificationId,
                    nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
                }, function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                }).fail(function() {
                    alert('Error processing request. Please try again.');
                });
            });
        });
    });
    </script>
    <?php
}
