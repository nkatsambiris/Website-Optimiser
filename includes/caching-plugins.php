<?php
/**
 * Caching Plugins component for Meta Description Boy Plugin
 * Checks if recommended caching plugins are installed and active
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check caching plugin status
 */
function meta_description_boy_check_caching_plugins_status() {
    // Ensure plugin functions are available
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    $installed_plugins = array();
    $active_plugins = array();

    // Check for Autoptimize
    if (is_plugin_active('autoptimize/autoptimize.php')) {
        $active_plugins[] = 'Autoptimize';
        $installed_plugins[] = 'Autoptimize';
    } elseif (file_exists(WP_PLUGIN_DIR . '/autoptimize/autoptimize.php')) {
        $installed_plugins[] = 'Autoptimize (inactive)';
    }

    // Check for Breeze
    if (is_plugin_active('breeze/breeze.php')) {
        $active_plugins[] = 'Breeze';
        $installed_plugins[] = 'Breeze';
    } elseif (file_exists(WP_PLUGIN_DIR . '/breeze/breeze.php')) {
        $installed_plugins[] = 'Breeze (inactive)';
    }

    // Check for Seraphinite Accelerator
    if (is_plugin_active('seraphinite-accelerator/plugin_root.php') ||
        is_plugin_active('seraphinite-accelerator-ext/plugin_root.php')) {
        $active_plugins[] = 'Seraphinite Accelerator';
        $installed_plugins[] = 'Seraphinite Accelerator';
    } elseif (file_exists(WP_PLUGIN_DIR . '/seraphinite-accelerator/plugin_root.php') ||
              file_exists(WP_PLUGIN_DIR . '/seraphinite-accelerator-ext/plugin_root.php')) {
        $installed_plugins[] = 'Seraphinite Accelerator (inactive)';
    }

    $has_active_caching = !empty($active_plugins);
    $has_any_caching = !empty($installed_plugins);

    if ($has_active_caching) {
        return array(
            'class' => 'status-good',
            'text' => 'Active caching plugin found',
            'description' => 'Active: ' . implode(', ', $active_plugins),
            'has_caching' => true,
            'active_plugins' => $active_plugins,
            'installed_plugins' => $installed_plugins
        );
    } elseif ($has_any_caching) {
        return array(
            'class' => 'status-warning',
            'text' => 'Caching plugin installed but not active',
            'description' => 'Installed: ' . implode(', ', $installed_plugins),
            'has_caching' => false,
            'active_plugins' => $active_plugins,
            'installed_plugins' => $installed_plugins
        );
    } else {
        return array(
            'class' => 'status-error',
            'text' => 'No caching plugin found',
            'description' => 'Consider installing Autoptimize, Breeze, or Seraphinite Accelerator',
            'has_caching' => false,
            'active_plugins' => array(),
            'installed_plugins' => array()
        );
    }
}

/**
 * Render caching plugins section
 */
function meta_description_boy_render_caching_plugins_section() {
    $status = meta_description_boy_check_caching_plugins_status();
    ?>
    <div class="seo-stat-item <?php echo $status['class']; ?>">
        <div class="stat-icon">⚡</div>
        <div class="stat-content">
            <h4>Caching Plugin</h4>
            <div class="stat-status <?php echo $status['class']; ?>">
                <?php echo $status['text']; ?>
            </div>
            <div class="stat-label">
                <?php echo $status['description']; ?>

                <?php if ($status['has_caching']): ?>
                    <br><br><div style="background: #d4edda; padding: 10px; border-radius: 4px; border-left: 4px solid #46b450; font-size: 12px;">
                        <strong>✓ Good:</strong> Caching is enabled for better performance.
                    </div>
                <?php elseif (!empty($status['installed_plugins'])): ?>
                    <br><br><small><strong>Installed but inactive:</strong> <?php echo implode(', ', $status['installed_plugins']); ?></small>
                    <br><br><div style="background: #fff3cd; padding: 10px; border-radius: 4px; border-left: 4px solid #ffb900; font-size: 12px;">
                        <strong>⚠ Warning:</strong> You have caching plugins installed but not activated.
                    </div>
                <?php else: ?>
                    <br><br><small><em>Caching plugins improve website performance by storing frequently accessed data.</em></small>
                    <br><br><div style="background: #f8d7da; padding: 10px; border-radius: 4px; border-left: 4px solid #dc3232; font-size: 12px;">
                        <strong>✗ Issue:</strong> No caching plugin detected. This can slow down your website.
                    </div>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($status['has_caching']): ?>
                    <a href="https://developers.google.com/speed/pagespeed/insights/" class="button button-small" target="_blank" style="margin-left: 5px;">
                        Test Speed
                    </a>
                <?php elseif (!empty($status['installed_plugins'])): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-primary button-small">
                        Activate Plugin
                    </a>
                <?php else: ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=autoptimize&tab=search&type=term'); ?>" class="button button-primary button-small">
                        Install Autoptimize
                    </a>
                    <a href="<?php echo admin_url('plugin-install.php?s=breeze&tab=search&type=term'); ?>" class="button button-small" style="margin-left: 5px;">
                        Install Breeze
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}