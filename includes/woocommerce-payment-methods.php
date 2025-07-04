<?php
/**
 * WooCommerce Payment Methods component for Website Optimiser Plugin
 * Checks if at least one payment method is enabled
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check WooCommerce payment methods status.
 *
 * @return array
 */
function website_optimiser_check_woocommerce_payment_methods_status() {
    if (!class_exists('WooCommerce')) {
        return array(
            'class' => 'status-info',
            'text' => 'WooCommerce not found',
            'description' => 'This check is only relevant for e-commerce sites using WooCommerce.',
        );
    }

    $enabled_gateways = array();
    $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

    if (!empty($available_gateways)) {
        foreach ($available_gateways as $gateway) {
            if ($gateway->enabled === 'yes') {
                $enabled_gateways[] = $gateway->get_title();
            }
        }
    }

    if (empty($enabled_gateways)) {
        return array(
            'class' => 'status-error',
            'text' => 'No Payment Methods Enabled',
            'description' => 'Your store has no active payment methods. Customers cannot complete purchases without at least one payment method enabled.',
        );
    } else {
        $count = count($enabled_gateways);
        $gateway_list = implode(', ', array_slice($enabled_gateways, 0, 3));
        if ($count > 3) {
            $gateway_list .= ' and ' . ($count - 3) . ' more';
        }

        return array(
            'class' => 'status-good',
            'text' => $count . ' Payment Method' . ($count > 1 ? 's' : '') . ' Enabled',
            'description' => 'Active payment methods: ' . $gateway_list . '.',
        );
    }
}

/**
 * Render WooCommerce payment methods section.
 */
function website_optimiser_render_woocommerce_payment_methods_section() {
    $status = website_optimiser_check_woocommerce_payment_methods_status();
    $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout');
    ?>
    <div class="seo-stat-item <?php echo esc_attr($status['class']); ?>">
        <div class="stat-icon">💳</div>
        <div class="stat-content">
            <h4>Payment Methods</h4>
            <div class="stat-status <?php echo esc_attr($status['class']); ?>">
                <?php echo esc_html($status['text']); ?>
            </div>
            <div class="stat-label">
                <?php echo esc_html($status['description']); ?>
            </div>
            <?php if ($status['class'] === 'status-error'): ?>
            <div class="stat-action">
                <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary button-small">
                    Configure Payment Methods
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}