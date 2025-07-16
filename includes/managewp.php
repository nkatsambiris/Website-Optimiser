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
    $managewp_connected = false;

    // Check if ManageWP Worker plugin is installed and active
    if (is_plugin_active($managewp_plugin)) {
        $managewp_plugin_active = true;
        $managewp_plugin_name = 'ManageWP Worker';
        if (file_exists(WP_PLUGIN_DIR . '/' . $managewp_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $managewp_plugin);
            $managewp_plugin_version = $plugin_data['Version'] ?? '';
        }

        // Check for connection using mwp_communication_keys option
        $communication_keys = get_option('mwp_communication_keys', '');
        if (!empty($communication_keys)) {
            $managewp_connected = true;
        }
    }

    // Check for ManageWP as MU plugin
    if (!$managewp_plugin_active && file_exists(WPMU_PLUGIN_DIR . '/managewp/init.php')) {
        $managewp_plugin_active = true;
        $managewp_plugin_name = 'ManageWP (MU Plugin)';

        // Check for connection using mwp_communication_keys option for MU plugin too
        $communication_keys = get_option('mwp_communication_keys', '');
        if (!empty($communication_keys)) {
            $managewp_connected = true;
        }
    }

    // Determine status based on findings
    if (!$managewp_plugin_active) {
        return array(
            'managewp_plugin_active' => false,
            'managewp_plugin_name' => '',
            'managewp_plugin_version' => '',
            'managewp_connected' => false,
            'status' => 'ManageWP Not Installed',
            'message' => 'Consider installing ManageWP for centralized site management',
            'class' => 'status-warning'
        );
    } elseif (!$managewp_connected) {
        return array(
            'managewp_plugin_active' => true,
            'managewp_plugin_name' => $managewp_plugin_name,
            'managewp_plugin_version' => $managewp_plugin_version,
            'managewp_connected' => false,
            'status' => 'ManageWP Not Connected',
            'message' => $managewp_plugin_name . ' is active but not connected to ManageWP dashboard',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'managewp_plugin_active' => true,
            'managewp_plugin_name' => $managewp_plugin_name,
            'managewp_plugin_version' => $managewp_plugin_version,
            'managewp_connected' => true,
            'status' => 'ManageWP Connected',
            'message' => 'Site is successfully connected to ManageWP dashboard',
            'class' => 'status-good'
        );
    }
}

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
            </div>
            <div class="stat-action">
                <?php if (!$managewp_status['managewp_plugin_active']): ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=managewp&tab=search&type=term'); ?>" class="button button-small">
                        Install ManageWP
                    </a>
                    <a href="https://managewp.com/" class="button button-small" target="_blank" style="margin-left: 5px;">
                        Learn More
                    </a>
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
    <?php
}