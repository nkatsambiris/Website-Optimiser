<?php
/**
 * UpdraftPlus functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check UpdraftPlus status and Google Drive configuration
 */
function meta_description_boy_check_updraftplus_status() {
    // Check if plugin functions are available
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $updraftplus_plugin = 'updraftplus/updraftplus.php';
    $udp_installed = false;
    $udp_active = false;
    $udp_version = '';
    $gdrive_configured = false;
    $gdrive_authenticated = false;
    $last_backup = '';
    $backup_schedule = '';

    // Check if UpdraftPlus is installed and active
    if (is_plugin_active($updraftplus_plugin)) {
        $udp_installed = true;
        $udp_active = true;
        if (file_exists(WP_PLUGIN_DIR . '/' . $updraftplus_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $updraftplus_plugin);
            $udp_version = $plugin_data['Version'] ?? '';
        }
    } elseif (file_exists(WP_PLUGIN_DIR . '/' . $updraftplus_plugin)) {
        $udp_installed = true;
        $udp_active = false;
    }

    // Only check configuration if UpdraftPlus is active
    if ($udp_active) {
        // Check Google Drive configuration
        $updraft_googledrive = get_option('updraft_googledrive');
        if (!empty($updraft_googledrive) && isset($updraft_googledrive['settings']) && is_array($updraft_googledrive['settings'])) {
            // UpdraftPlus stores settings in nested structure with random keys
            foreach ($updraft_googledrive['settings'] as $instance_key => $instance_settings) {
                if (is_array($instance_settings) && isset($instance_settings['instance_enabled']) && $instance_settings['instance_enabled'] == '1') {
                    // Check if Google Drive is configured (has folder set and user authenticated)
                    if (!empty($instance_settings['folder']) || !empty($instance_settings['user_id'])) {
                        $gdrive_configured = true;

                        // Check authentication - newer UpdraftPlus uses tmp_access_token or user_id for authentication
                        if ((!empty($instance_settings['tmp_access_token']) && isset($instance_settings['tmp_access_token']['access_token']) && !empty($instance_settings['tmp_access_token']['access_token'])) ||
                            (!empty($instance_settings['user_id'])) ||
                            (!empty($instance_settings['token']))) {
                            $gdrive_authenticated = true;
                        }
                        break; // Found an enabled instance, stop checking
                    }
                }
            }
        }

        // Get backup schedule
        $backup_schedule = get_option('updraft_interval');
        if (empty($backup_schedule) || $backup_schedule === 'manual') {
            $backup_schedule = 'Manual only';
        } else {
            $backup_schedule = ucfirst(str_replace('_', ' ', $backup_schedule));
        }

        // Get last backup time
        $last_backup_time = get_option('updraft_last_backup');
        if (!empty($last_backup_time)) {
            // Handle case where UpdraftPlus stores backup time as array
            if (is_array($last_backup_time)) {
                // UpdraftPlus stores backup data with timestamp values in specific keys
                $backup_timestamp = null;

                // Try to get the most recent backup timestamp
                if (isset($last_backup_time['backup_time']) && is_numeric($last_backup_time['backup_time'])) {
                    $backup_timestamp = (int) $last_backup_time['backup_time'];
                } elseif (isset($last_backup_time['nonincremental_backup_time']) && is_numeric($last_backup_time['nonincremental_backup_time'])) {
                    $backup_timestamp = (int) $last_backup_time['nonincremental_backup_time'];
                }

                if ($backup_timestamp && $backup_timestamp > 0) {
                    $last_backup = human_time_diff($backup_timestamp, current_time('timestamp')) . ' ago';
                } else {
                    $last_backup = 'Never';
                }
            } elseif (is_numeric($last_backup_time)) {
                // Handle normal timestamp format
                $last_backup = human_time_diff((int) $last_backup_time, current_time('timestamp')) . ' ago';
            } else {
                $last_backup = 'Unknown format';
            }
        } else {
            $last_backup = 'Never';
        }
    }

    // Determine status based on findings
    if (!$udp_installed) {
        return array(
            'udp_installed' => false,
            'udp_active' => false,
            'udp_version' => '',
            'gdrive_configured' => false,
            'gdrive_authenticated' => false,
            'backup_schedule' => '',
            'last_backup' => '',
            'status' => 'UpdraftPlus Missing',
            'message' => 'UpdraftPlus plugin is not installed',
            'class' => 'status-error'
        );
    } elseif (!$udp_active) {
        return array(
            'udp_installed' => true,
            'udp_active' => false,
            'udp_version' => '',
            'gdrive_configured' => false,
            'gdrive_authenticated' => false,
            'backup_schedule' => '',
            'last_backup' => '',
            'status' => 'UpdraftPlus Inactive',
            'message' => 'UpdraftPlus is installed but not activated',
            'class' => 'status-error'
        );
    } elseif (!$gdrive_configured) {
        return array(
            'udp_installed' => true,
            'udp_active' => true,
            'udp_version' => $udp_version,
            'gdrive_configured' => false,
            'gdrive_authenticated' => false,
            'backup_schedule' => $backup_schedule,
            'last_backup' => $last_backup,
            'status' => 'Google Drive Not Configured',
            'message' => 'UpdraftPlus is active but Google Drive is not configured as backup destination',
            'class' => 'status-error'
        );
    } elseif (!$gdrive_authenticated) {
        return array(
            'udp_installed' => true,
            'udp_active' => true,
            'udp_version' => $udp_version,
            'gdrive_configured' => true,
            'gdrive_authenticated' => false,
            'backup_schedule' => $backup_schedule,
            'last_backup' => $last_backup,
            'status' => 'Google Drive Not Authenticated',
            'message' => 'Google Drive is configured but not authenticated',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'udp_installed' => true,
            'udp_active' => true,
            'udp_version' => $udp_version,
            'gdrive_configured' => true,
            'gdrive_authenticated' => true,
            'backup_schedule' => $backup_schedule,
            'last_backup' => $last_backup,
            'status' => 'Configured & Authenticated',
            'message' => 'UpdraftPlus is properly configured with Google Drive',
            'class' => 'status-good'
        );
    }
}

/**
 * Render UpdraftPlus section
 */
function meta_description_boy_render_updraftplus_section() {
    $updraft_status = meta_description_boy_check_updraftplus_status();
    ?>
    <div class="seo-stat-item <?php echo $updraft_status['class']; ?>">
        <div class="stat-icon">☁️</div>
        <div class="stat-content">
            <h4>UpdraftPlus Backup</h4>
            <div class="stat-status <?php echo $updraft_status['class']; ?>">
                <?php echo $updraft_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $updraft_status['message']; ?>
                <?php if (!empty($updraft_status['udp_version'])): ?>
                    <br><small>UpdraftPlus: v<?php echo $updraft_status['udp_version']; ?></small>
                <?php endif; ?>

                <?php if ($updraft_status['udp_active']): ?>
                    <br><small>Schedule: <?php echo $updraft_status['backup_schedule']; ?></small>
                    <br><small>Last backup: <?php echo $updraft_status['last_backup']; ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if (!$updraft_status['udp_installed']): ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=updraftplus&tab=search&type=term'); ?>" class="button button-small">
                        Install UpdraftPlus
                    </a>
                <?php elseif (!$updraft_status['udp_active']): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small">
                        Activate UpdraftPlus
                    </a>
                <?php elseif (!$updraft_status['gdrive_configured'] || !$updraft_status['gdrive_authenticated']): ?>
                    <a href="<?php echo admin_url('options-general.php?page=updraftplus'); ?>" class="button button-small">
                        Configure Google Drive
                    </a>
                <?php else: ?>
                    <a href="<?php echo admin_url('options-general.php?page=updraftplus'); ?>" class="button button-small">
                        Manage Backups
                    </a>
                    <a href="<?php echo admin_url('options-general.php?page=updraftplus&tab=backups'); ?>" class="button button-small" style="margin-left: 5px;">
                        View Backups
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}