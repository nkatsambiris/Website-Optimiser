<?php
/**
 * WooCommerce Google Analytics Integration component for Website Optimiser Plugin
 * Checks if the Google Analytics integration is active.
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check WooCommerce Google Analytics Integration status.
 *
 * @return array
 */
function website_optimiser_check_woocommerce_ga_status() {
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $plugin_slug = 'woocommerce-google-analytics-integration/woocommerce-google-analytics-integration.php';

    if (!is_plugin_active($plugin_slug)) {
        return array(
            'class' => 'status-warning',
            'text' => 'Not Active',
            'description' => 'The official WooCommerce Google Analytics plugin is not active. Tracking data might be missing.',
        );
    }

    $ga_settings = get_option('woocommerce_google_analytics_settings');

    if (!empty($ga_settings) && !empty($ga_settings['ga_id'])) {
        return array(
            'class' => 'status-good',
            'text' => 'Active and Tracking',
            'description' => 'GA ID (<strong>' . esc_html($ga_settings['ga_id']) . '</strong>) is configured. E-commerce data is being sent to Google Analytics.',
        );
    } else {
        return array(
            'class' => 'status-error',
            'text' => 'Configuration Needed',
            'description' => 'The WooCommerce Google Analytics plugin is active, but the GA ID has not been configured.',
        );
    }
}

/**
 * Render WooCommerce Google Analytics Integration section.
 */
function website_optimiser_render_woocommerce_ga_section() {
    $status = website_optimiser_check_woocommerce_ga_status();
    $plugin_slug = 'woocommerce-google-analytics-integration/woocommerce-google-analytics-integration.php';
    $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug;
    $settings_url = admin_url('admin.php?page=wc-settings&tab=integration&section=google_analytics');
    ?>
    <div class="seo-stat-item <?php echo esc_attr($status['class']); ?>">
        <div class="stat-icon">📈</div>
        <div class="stat-content">
            <h4>WooCommerce Google Analytics</h4>
            <div class="stat-status <?php echo esc_attr($status['class']); ?>">
                <?php echo esc_html($status['text']); ?>
            </div>
            <div class="stat-label">
                <?php echo $status['description']; // Contains safe HTML ?>
            </div>
            <?php if ($status['class'] !== 'status-good'): ?>
            <div class="stat-action">
                <?php if ($status['class'] === 'status-error'): ?>
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary button-small">
                        Configure GA ID
                    </a>
                <?php elseif (file_exists($plugin_file)): ?>
                    <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-primary button-small">
                        Activate Plugin
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(admin_url('plugin-install.php?s=woocommerce+google+analytics+integration&tab=search&type=term')); ?>" class="button button-primary button-small">
                        Install for Free
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}