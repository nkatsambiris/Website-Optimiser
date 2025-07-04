<?php
/**
 * WooCommerce Shipping Zones component for Website Optimiser Plugin
 * Checks if at least one shipping zone and method is configured
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check WooCommerce shipping zones status.
 *
 * @return array
 */
function website_optimiser_check_woocommerce_shipping_zones_status() {
    if (!class_exists('WooCommerce')) {
        return array(
            'class' => 'status-info',
            'text' => 'WooCommerce not found',
            'description' => 'This check is only relevant for e-commerce sites using WooCommerce.',
        );
    }

    if (!class_exists('WC_Shipping_Zones')) {
        return array(
            'class' => 'status-info',
            'text' => 'Shipping zones not available',
            'description' => 'This WooCommerce version does not support shipping zones.',
        );
    }

    $shipping_zones = WC_Shipping_Zones::get_zones();
    $zone_count = 0;
    $total_methods = 0;
    $configured_zones = array();

    // Check regular shipping zones
    foreach ($shipping_zones as $zone_id => $zone_data) {
        if (!empty($zone_data['shipping_methods'])) {
            $zone_count++;
            $total_methods += count($zone_data['shipping_methods']);
            $configured_zones[] = $zone_data['zone_name'];
        }
    }

    // Check "Rest of the World" zone (zone 0)
    $rest_of_world = new WC_Shipping_Zone(0);
    $rest_of_world_methods = $rest_of_world->get_shipping_methods();
    if (!empty($rest_of_world_methods)) {
        $zone_count++;
        $total_methods += count($rest_of_world_methods);
        $configured_zones[] = 'Rest of the World';
    }

    if ($zone_count === 0 || $total_methods === 0) {
        return array(
            'class' => 'status-error',
            'text' => 'No Shipping Zones Configured',
            'description' => 'Your store has no shipping zones or methods configured. Customers cannot complete purchases without shipping options.',
        );
    } else {
        $zone_list = implode(', ', array_slice($configured_zones, 0, 3));
        if (count($configured_zones) > 3) {
            $zone_list .= ' and ' . (count($configured_zones) - 3) . ' more';
        }

        return array(
            'class' => 'status-good',
            'text' => $zone_count . ' Shipping Zone' . ($zone_count > 1 ? 's' : '') . ' Configured',
            'description' => 'Active shipping zones: ' . $zone_list . ' (' . $total_methods . ' method' . ($total_methods > 1 ? 's' : '') . ' total).',
        );
    }
}

/**
 * Render WooCommerce shipping zones section.
 */
function website_optimiser_render_woocommerce_shipping_zones_section() {
    $status = website_optimiser_check_woocommerce_shipping_zones_status();
    $settings_url = admin_url('admin.php?page=wc-settings&tab=shipping');
    ?>
    <div class="seo-stat-item <?php echo esc_attr($status['class']); ?>">
        <div class="stat-icon">🚚</div>
        <div class="stat-content">
            <h4>Shipping Zones</h4>
            <div class="stat-status <?php echo esc_attr($status['class']); ?>">
                <?php echo esc_html($status['text']); ?>
            </div>
            <div class="stat-label">
                <?php echo esc_html($status['description']); ?>
            </div>
            <?php if ($status['class'] === 'status-error'): ?>
            <div class="stat-action">
                <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary button-small">
                    Configure Shipping
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}