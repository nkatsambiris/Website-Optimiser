<?php
/**
 * WooCommerce component for Website Optimiser Plugin
 * Checks if WooCommerce is installed and active
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check WooCommerce status.
 *
 * @return array
 */
function website_optimiser_check_woocommerce_status() {
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    if (!class_exists('WooCommerce')) {
        // This part will likely not be shown as we will only render this if WC is active,
        // but it's good for completeness if the check function is ever used elsewhere.
        return array(
            'class' => 'status-info',
            'text' => 'WooCommerce not found',
            'description' => 'This check is only relevant for e-commerce sites using WooCommerce.',
        );
    }

    $coming_soon = get_option('woocommerce_coming_soon', 'no');

    if ($coming_soon === 'yes') {
        return array(
            'class' => 'status-error',
            'text' => 'Store is in "Coming Soon" Mode',
            'description' => 'Your WooCommerce store is currently not visible to the public. Disable "Coming Soon" mode to go live.',
        );
    } else {
        return array(
            'class' => 'status-good',
            'text' => 'WooCommerce is Live',
            'description' => 'Your store is active and visible to the public.',
        );
    }
}

/**
 * Render WooCommerce section.
 */
function website_optimiser_render_woocommerce_section() {
    $status = website_optimiser_check_woocommerce_status();
    $settings_url = admin_url('admin.php?page=wc-settings&tab=site-visibility');
    ?>
    <div class="seo-stat-item <?php echo esc_attr($status['class']); ?>">
        <div class="stat-icon">🛒</div>
        <div class="stat-content">
            <h4>WooCommerce</h4>
            <div class="stat-status <?php echo esc_attr($status['class']); ?>">
                <?php echo esc_html($status['text']); ?>
            </div>
            <div class="stat-label">
                <?php echo esc_html($status['description']); ?>
            </div>
            <?php if ($status['class'] === 'status-error'): ?>
            <div class="stat-action">
                <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary button-small">
                    Disable "Coming Soon"
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}