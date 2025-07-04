<?php
/**
 * WooCommerce Tax Settings component for Website Optimiser Plugin
 * Checks if tax is enabled but no rules are defined
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check WooCommerce tax settings status.
 *
 * @return array
 */
function website_optimiser_check_woocommerce_tax_settings_status() {
    if (!class_exists('WooCommerce')) {
        return array(
            'class' => 'status-info',
            'text' => 'WooCommerce not found',
            'description' => 'This check is only relevant for e-commerce sites using WooCommerce.',
        );
    }

        $tax_enabled = get_option('woocommerce_calc_taxes', 'no');

    if ($tax_enabled === 'no') {
        return array(
            'class' => 'status-warning',
            'text' => 'Tax Calculations Disabled',
            'description' => 'Tax calculations are disabled. Most businesses need to collect and remit taxes. Enable tax calculations and configure appropriate tax rates.',
        );
    }

    // Tax is enabled, now check if there are any tax rates defined
    global $wpdb;
    $tax_rates_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates");

    if ($tax_rates_count == 0) {
        return array(
            'class' => 'status-error',
            'text' => 'Tax Enabled but No Rules Defined',
            'description' => 'Tax calculations are enabled but no tax rates have been configured. Customers will not be charged tax, which may cause legal compliance issues.',
        );
    } else {
        // Get some basic info about configured tax rates
        $tax_classes = $wpdb->get_results("SELECT DISTINCT tax_rate_class FROM {$wpdb->prefix}woocommerce_tax_rates");
        $class_count = count($tax_classes);

        return array(
            'class' => 'status-good',
            'text' => 'Tax Settings Configured',
            'description' => 'Tax calculations are enabled with ' . $tax_rates_count . ' tax rate' . ($tax_rates_count > 1 ? 's' : '') . ' across ' . $class_count . ' tax class' . ($class_count > 1 ? 'es' : '') . '.',
        );
    }
}

/**
 * Render WooCommerce tax settings section.
 */
function website_optimiser_render_woocommerce_tax_settings_section() {
    $status = website_optimiser_check_woocommerce_tax_settings_status();
    $settings_url = admin_url('admin.php?page=wc-settings&tab=tax');
    ?>
    <div class="seo-stat-item <?php echo esc_attr($status['class']); ?>">
        <div class="stat-icon">🧾</div>
        <div class="stat-content">
            <h4>Tax Settings</h4>
            <div class="stat-status <?php echo esc_attr($status['class']); ?>">
                <?php echo esc_html($status['text']); ?>
            </div>
            <div class="stat-label">
                <?php echo esc_html($status['description']); ?>
            </div>
            <?php if ($status['class'] === 'status-warning' || $status['class'] === 'status-error'): ?>
            <div class="stat-action">
                <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary button-small">
                    <?php echo ($status['text'] === 'Tax Calculations Disabled') ? 'Enable Tax Settings' : 'Configure Tax Rates'; ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}