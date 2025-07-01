<?php
/**
 * Gravity Forms Notifications functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

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

            // Get notifications for this form
            $notifications = isset($form['notifications']) ? $form['notifications'] : array();

            foreach ($notifications as $notification_id => $notification) {
                $total_notifications++;

                // Check if notification is active
                if (isset($notification['isActive']) && !$notification['isActive']) {
                    continue; // Skip inactive notifications
                }

                // Check 'to' address for {admin_email}
                $to_address = isset($notification['to']) ? $notification['to'] : '';
                if (strpos($to_address, '{admin_email}') !== false) {
                    $admin_email_issues++;
                    $form_has_issues = true;
                    $form_issues[] = 'Uses {admin_email} in TO field';
                }

                // Check 'from' address
                $from_address = isset($notification['from']) ? $notification['from'] : '';
                if ($from_address !== 'noreply@pixeld.com.au') {
                    $from_address_issues++;
                    $form_has_issues = true;
                    $form_issues[] = 'FROM address not set to noreply@pixeld.com.au (currently: ' . $from_address . ')';
                }
            }

            if ($form_has_issues) {
                $problematic_forms[] = array(
                    'id' => $form['id'],
                    'title' => $form['title'],
                    'issues' => $form_issues
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
            'problematic_forms' => array(),
            'status' => 'All Notifications Configured',
            'message' => 'All ' . $total_notifications . ' notifications across ' . $total_forms . ' form(s) are properly configured',
            'class' => 'status-good'
        );
    }
}

/**
 * Render Gravity Forms notifications section
 */
function meta_description_boy_render_gravity_forms_notifications_section() {
    $notifications_status = meta_description_boy_check_gravity_forms_notifications_status();
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
                        <?php foreach ($form['issues'] as $issue): ?>
                            <br><small>&nbsp;&nbsp;- <?php echo esc_html($issue); ?></small>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if (!$notifications_status['gf_installed']): ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=gravity+forms&tab=search&type=term'); ?>" class="button button-small">
                        Install Gravity Forms
                    </a>
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
                <?php else: ?>
                    <a href="<?php echo admin_url('admin.php?page=gf_edit_forms'); ?>" class="button button-small">
                        Manage Forms
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=gf_new_form'); ?>" class="button button-small" style="margin-left: 5px;">
                        Create New Form
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}