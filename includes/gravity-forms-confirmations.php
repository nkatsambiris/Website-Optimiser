<?php
/**
 * Gravity Forms Confirmations functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check Gravity Forms confirmation settings
 */
function meta_description_boy_check_gravity_forms_confirmations_status() {
    // Check if plugin functions are available
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $gravity_forms_plugin = 'gravityforms/gravityforms.php';
    $gf_installed = false;
    $gf_active = false;
    $gf_version = '';
    $total_forms = 0;
    $total_confirmations = 0;
    $text_confirmation_issues = 0;
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

    // Only check confirmations if Gravity Forms is active
    if ($gf_active && class_exists('GFAPI')) {
        $forms = GFAPI::get_forms();
        $total_forms = count($forms);

        foreach ($forms as $form) {
            $form_has_issues = false;
            $form_issues = array();

            // Get confirmations for this form
            $confirmations = isset($form['confirmations']) ? $form['confirmations'] : array();

            foreach ($confirmations as $confirmation_id => $confirmation) {
                $total_confirmations++;

                // Check if confirmation is active
                if (isset($confirmation['isActive']) && !$confirmation['isActive']) {
                    continue; // Skip inactive confirmations
                }

                // Check confirmation type - we want 'page' or 'redirect', not 'message'
                $confirmation_type = isset($confirmation['type']) ? $confirmation['type'] : '';

                if ($confirmation_type === 'message') {
                    $text_confirmation_issues++;
                    $form_has_issues = true;
                    $confirmation_name = isset($confirmation['name']) ? $confirmation['name'] : 'Unnamed Confirmation';
                    $form_issues[] = 'Confirmation "' . $confirmation_name . '" uses text message instead of page redirect';
                } elseif (empty($confirmation_type)) {
                    // If no type is set, it defaults to message
                    $text_confirmation_issues++;
                    $form_has_issues = true;
                    $confirmation_name = isset($confirmation['name']) ? $confirmation['name'] : 'Unnamed Confirmation';
                    $form_issues[] = 'Confirmation "' . $confirmation_name . '" has no type set (defaults to text message)';
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
            'total_confirmations' => 0,
            'text_confirmation_issues' => 0,
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
            'total_confirmations' => 0,
            'text_confirmation_issues' => 0,
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
            'total_confirmations' => 0,
            'text_confirmation_issues' => 0,
            'problematic_forms' => array(),
            'status' => 'No Forms Found',
            'message' => 'No forms have been created yet',
            'class' => 'status-warning'
        );
    } elseif ($text_confirmation_issues > 0) {
        return array(
            'gf_installed' => true,
            'gf_active' => true,
            'gf_version' => $gf_version,
            'total_forms' => $total_forms,
            'total_confirmations' => $total_confirmations,
            'text_confirmation_issues' => $text_confirmation_issues,
            'problematic_forms' => $problematic_forms,
            'status' => 'Text Confirmations Found',
            'message' => $text_confirmation_issues . ' text confirmation(s) found across ' . count($problematic_forms) . ' form(s) - should redirect to pages instead',
            'class' => 'status-error'
        );
    } else {
        return array(
            'gf_installed' => true,
            'gf_active' => true,
            'gf_version' => $gf_version,
            'total_forms' => $total_forms,
            'total_confirmations' => $total_confirmations,
            'text_confirmation_issues' => 0,
            'problematic_forms' => array(),
            'status' => 'All Confirmations Use Redirects',
            'message' => 'All ' . $total_confirmations . ' confirmation(s) across ' . $total_forms . ' form(s) redirect to pages',
            'class' => 'status-good'
        );
    }
}

/**
 * Render Gravity Forms confirmations section
 */
function meta_description_boy_render_gravity_forms_confirmations_section() {
    $confirmations_status = meta_description_boy_check_gravity_forms_confirmations_status();
    ?>
    <div class="seo-stat-item <?php echo $confirmations_status['class']; ?>">
        <div class="stat-icon">✅</div>
        <div class="stat-content">
            <h4>Gravity Forms Confirmations</h4>
            <div class="stat-status <?php echo $confirmations_status['class']; ?>">
                <?php echo $confirmations_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $confirmations_status['message']; ?>
                <?php if (!empty($confirmations_status['gf_version'])): ?>
                    <br><small>Gravity Forms: v<?php echo $confirmations_status['gf_version']; ?></small>
                <?php endif; ?>

                <?php if (!empty($confirmations_status['problematic_forms'])): ?>
                    <br><br><strong>Issues found in:</strong>
                    <?php foreach ($confirmations_status['problematic_forms'] as $form): ?>
                        <br><small>• <strong><?php echo esc_html($form['title']); ?></strong> (ID: <?php echo $form['id']; ?>)</small>
                        <?php foreach ($form['issues'] as $issue): ?>
                            <br><small>&nbsp;&nbsp;- <?php echo esc_html($issue); ?></small>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if (!$confirmations_status['gf_installed']): ?>
                    <!-- Gravity Forms is not installed -->
                <?php elseif (!$confirmations_status['gf_active']): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small">
                        Activate Gravity Forms
                    </a>
                <?php elseif ($confirmations_status['total_forms'] === 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=gf_new_form'); ?>" class="button button-small">
                        Create First Form
                    </a>
                <?php elseif (!empty($confirmations_status['problematic_forms'])): ?>
                    <a href="<?php echo admin_url('admin.php?page=gf_edit_forms'); ?>" class="button button-small">
                        Fix Confirmations
                    </a>
                    <?php if (count($confirmations_status['problematic_forms']) === 1): ?>
                        <a href="<?php echo admin_url('admin.php?page=gf_edit_forms&view=settings&subview=confirmation&id=' . $confirmations_status['problematic_forms'][0]['id']); ?>" class="button button-small" style="margin-left: 5px;">
                            Edit Form Confirmations
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