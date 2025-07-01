<?php
/**
 * Wordfence Security functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check Wordfence security status
 */
function meta_description_boy_check_wordfence_status() {
    // Check if Wordfence plugin is installed
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $wordfence_plugin = 'wordfence/wordfence.php';
    $wordfence_free = 'wordfence/wordfence.php';
    $wordfence_premium = 'wordfence-premium/wordfence.php';

    $is_installed = false;
    $is_active = false;
    $version = '';
    $is_premium = false;

    // Check if Wordfence is installed and active
    if (is_plugin_active($wordfence_free)) {
        $is_installed = true;
        $is_active = true;
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $wordfence_free);
        $version = $plugin_data['Version'] ?? '';
    } elseif (is_plugin_active($wordfence_premium)) {
        $is_installed = true;
        $is_active = true;
        $is_premium = true;
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $wordfence_premium);
        $version = $plugin_data['Version'] ?? '';
    } elseif (file_exists(WP_PLUGIN_DIR . '/' . $wordfence_free)) {
        $is_installed = true;
        $is_active = false;
    }

    // Return status based on findings
    if ($is_active) {
        // Additional checks if Wordfence is active
        $firewall_enabled = false;
        $scan_enabled = false;

        // Check if Wordfence functions are available
        if (class_exists('wfConfig')) {
            $firewall_enabled = wfConfig::get('firewallEnabled', false);
            $scan_enabled = wfConfig::get('scansEnabled', true);
        }

        $status_class = 'status-good';
        $status_text = 'Active';
        $message = 'Wordfence is active and protecting your site';

        if ($is_premium) {
            $message .= ' (Premium)';
        }

        if (!$firewall_enabled) {
            $status_class = 'status-warning';
            $status_text = 'Partially Active';
            $message = 'Wordfence is active but firewall is disabled';
        }

        return array(
            'installed' => $is_installed,
            'active' => $is_active,
            'premium' => $is_premium,
            'version' => $version,
            'firewall_enabled' => $firewall_enabled,
            'scan_enabled' => $scan_enabled,
            'status' => $status_text,
            'message' => $message,
            'class' => $status_class
        );
    } elseif ($is_installed) {
        return array(
            'installed' => $is_installed,
            'active' => $is_active,
            'premium' => false,
            'version' => '',
            'firewall_enabled' => false,
            'scan_enabled' => false,
            'status' => 'Inactive',
            'message' => 'Wordfence is installed but not activated',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'installed' => $is_installed,
            'active' => $is_active,
            'premium' => false,
            'version' => '',
            'firewall_enabled' => false,
            'scan_enabled' => false,
            'status' => 'Not Installed',
            'message' => 'Wordfence security plugin is not installed',
            'class' => 'status-error'
        );
    }
}

/**
 * Render Wordfence security section
 */
function meta_description_boy_render_wordfence_section() {
    $wordfence_status = meta_description_boy_check_wordfence_status();
    ?>
    <div class="seo-stat-item <?php echo $wordfence_status['class']; ?>">
        <div class="stat-icon">🛡️</div>
        <div class="stat-content">
            <h4>Wordfence Security</h4>
            <div class="stat-status <?php echo $wordfence_status['class']; ?>">
                <?php echo $wordfence_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $wordfence_status['message']; ?>
                <?php if (!empty($wordfence_status['version'])): ?>
                    <br><small>Version: <?php echo $wordfence_status['version']; ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($wordfence_status['active']): ?>
                    <a href="<?php echo admin_url('admin.php?page=Wordfence'); ?>" class="button button-small">
                        Open Wordfence
                    </a>
                    <?php if (!$wordfence_status['firewall_enabled']): ?>
                    <a href="<?php echo admin_url('admin.php?page=WordfenceWAF'); ?>" class="button button-small" style="margin-left: 5px;">
                        Enable Firewall
                    </a>
                    <?php endif; ?>
                <?php elseif ($wordfence_status['installed']): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small">
                        Activate Plugin
                    </a>
                <?php else: ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=wordfence&tab=search&type=term'); ?>" class="button button-small">
                        Install Wordfence
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}