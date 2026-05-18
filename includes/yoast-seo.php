<?php
/**
 * Yoast SEO functionality for Website Optimiser.
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Get a Yoast SEO option with a fallback for environments where Yoast helpers are not loaded.
 */
function website_optimiser_get_yoast_option($key, $default = null) {
    if (class_exists('WPSEO_Options')) {
        return WPSEO_Options::get($key, $default);
    }

    $yoast_options = get_option('wpseo', array());
    if (is_array($yoast_options) && array_key_exists($key, $yoast_options)) {
        return $yoast_options[$key];
    }

    return $default;
}

/**
 * Check Yoast SEO status and first-time configuration completion.
 */
function website_optimiser_check_yoast_seo_status() {
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $yoast_plugin = 'wordpress-seo/wp-seo.php';
    $yoast_premium_plugin = 'wordpress-seo-premium/wp-seo-premium.php';
    $is_installed = false;
    $is_active = false;
    $is_premium_active = false;
    $version = '';
    $finished_steps = array();
    $required_steps = 3;
    $configuration_complete = false;

    if (is_plugin_active($yoast_plugin)) {
        $is_installed = true;
        $is_active = true;
    } elseif (file_exists(WP_PLUGIN_DIR . '/' . $yoast_plugin)) {
        $is_installed = true;
    }

    if (is_plugin_active($yoast_premium_plugin)) {
        $is_premium_active = true;
        $is_installed = true;
    }

    if (!$is_active && (class_exists('WPSEO_Options') || defined('WPSEO_VERSION'))) {
        $is_installed = true;
        $is_active = true;
    }

    if ($is_installed && file_exists(WP_PLUGIN_DIR . '/' . $yoast_plugin)) {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $yoast_plugin);
        $version = $plugin_data['Version'] ?? '';
    } elseif (defined('WPSEO_VERSION')) {
        $version = WPSEO_VERSION;
    }

    if ($is_active) {
        $finished_steps = website_optimiser_get_yoast_option('configuration_finished_steps', array());
        if (!is_array($finished_steps)) {
            $finished_steps = array();
        }

        $configuration_complete = count($finished_steps) >= $required_steps;
    }

    if (!$is_installed) {
        return array(
            'installed' => false,
            'active' => false,
            'premium_active' => false,
            'version' => '',
            'finished_steps' => array(),
            'required_steps' => $required_steps,
            'configuration_complete' => false,
            'status' => 'Not Installed',
            'message' => 'Yoast SEO plugin is not installed',
            'class' => 'status-error'
        );
    }

    if (!$is_active) {
        return array(
            'installed' => true,
            'active' => false,
            'premium_active' => $is_premium_active,
            'version' => $version,
            'finished_steps' => array(),
            'required_steps' => $required_steps,
            'configuration_complete' => false,
            'status' => 'Inactive',
            'message' => 'Yoast SEO is installed but not activated',
            'class' => 'status-warning'
        );
    }

    if (!$configuration_complete) {
        return array(
            'installed' => true,
            'active' => true,
            'premium_active' => $is_premium_active,
            'version' => $version,
            'finished_steps' => $finished_steps,
            'required_steps' => $required_steps,
            'configuration_complete' => false,
            'status' => 'Setup Incomplete',
            'message' => 'Yoast SEO is active but first-time configuration has not been completed',
            'class' => 'status-warning'
        );
    }

    return array(
        'installed' => true,
        'active' => true,
        'premium_active' => $is_premium_active,
        'version' => $version,
        'finished_steps' => $finished_steps,
        'required_steps' => $required_steps,
        'configuration_complete' => true,
        'status' => 'Configured',
        'message' => 'Yoast SEO is active and first-time configuration is complete',
        'class' => 'status-good'
    );
}

/**
 * Render Yoast SEO section.
 */
function website_optimiser_render_yoast_seo_section() {
    $yoast_status = website_optimiser_check_yoast_seo_status();
    $configuration_url = admin_url('admin.php?page=wpseo_dashboard#/first-time-configuration');
    ?>
    <div class="seo-stat-item <?php echo esc_attr($yoast_status['class']); ?>">
        <div class="stat-icon">🟢</div>
        <div class="stat-content">
            <h4>Yoast SEO</h4>
            <div class="stat-status <?php echo esc_attr($yoast_status['class']); ?>">
                <?php echo esc_html($yoast_status['status']); ?>
            </div>
            <div class="stat-label">
                <?php echo esc_html($yoast_status['message']); ?>
                <?php if (!empty($yoast_status['version'])): ?>
                    <br><small>Version: <?php echo esc_html($yoast_status['version']); ?></small>
                <?php endif; ?>
                <?php if ($yoast_status['active']): ?>
                    <br><small>Configuration steps: <?php echo esc_html(count($yoast_status['finished_steps'])); ?>/<?php echo esc_html($yoast_status['required_steps']); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if (!$yoast_status['installed']): ?>
                    <a href="<?php echo esc_url(admin_url('plugin-install.php?s=yoast%20seo&tab=search&type=term')); ?>" class="button button-small">
                        Install Yoast SEO
                    </a>
                <?php elseif (!$yoast_status['active']): ?>
                    <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-small">
                        Activate Yoast SEO
                    </a>
                <?php elseif (!$yoast_status['configuration_complete']): ?>
                    <a href="<?php echo esc_url($configuration_url); ?>" class="button button-small">
                        Complete Configuration
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpseo_dashboard')); ?>" class="button button-small">
                        Open Yoast
                    </a>
                    <a href="<?php echo esc_url($configuration_url); ?>" class="button button-small" style="margin-left: 5px;">
                        View Configuration
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
