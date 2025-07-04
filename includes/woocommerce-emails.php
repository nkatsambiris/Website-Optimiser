<?php
/**
 * WooCommerce Emails component for Website Optimiser Plugin
 * Checks if admin-facing WooCommerce emails are using the default site admin email.
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check WooCommerce email settings status.
 *
 * @return array
 */
function website_optimiser_check_woocommerce_emails_status() {
    $admin_email = get_option('admin_email');
    $issues = array();
    $problematic_emails = array();

    // Check the global "from address" setting
    $from_address = get_option('woocommerce_email_from_address');
    if (!empty($from_address) && $from_address === $admin_email) {
        $issues[] = 'using site admin email as from address';
    }

    // Check recipients for admin-facing emails
    $email_checks = array(
        'new_order' => 'New Order',
        'cancelled_order' => 'Cancelled Order',
        'failed_order' => 'Failed Order',
    );

    foreach ($email_checks as $id => $name) {
        $settings = get_option('woocommerce_' . $id . '_settings');

        // Get the recipient, defaulting to admin email if not set
        $recipient = '';
        if (isset($settings['recipient']) && !empty($settings['recipient'])) {
            $recipient = $settings['recipient'];
        } else {
            // If no explicit recipient is set, WooCommerce defaults to admin email
            $recipient = $admin_email;
        }

        // Check if any of the recipients match the admin email
        $recipients = array_map('trim', explode(',', $recipient));
        if (in_array($admin_email, $recipients)) {
            $problematic_emails[] = $name;
        }
    }

    if (!empty($problematic_emails)) {
        $issues[] = 'admin notifications sent to site admin email (' . implode(', ', $problematic_emails) . ')';
    }

    if (!empty($issues)) {
        return array(
            'class' => 'status-warning',
            'text' => 'Review Email Configuration',
            'description' => 'Your WooCommerce email settings need attention: <strong>' . implode('; ', $issues) . '</strong>. It is recommended to use dedicated email addresses for store operations.',
        );
    } else {
        return array(
            'class' => 'status-good',
            'text' => 'Email Settings Configured',
            'description' => 'WooCommerce email settings are using custom addresses separate from the site admin email.',
        );
    }
}

/**
 * Render WooCommerce emails section.
 */
function website_optimiser_render_woocommerce_emails_section() {
    $status = website_optimiser_check_woocommerce_emails_status();
    $settings_url = admin_url('admin.php?page=wc-settings&tab=email');
    ?>
    <div class="seo-stat-item <?php echo esc_attr($status['class']); ?>">
        <div class="stat-icon">✉️</div>
        <div class="stat-content">
            <h4>WooCommerce Emails</h4>
            <div class="stat-status <?php echo esc_attr($status['class']); ?>">
                <?php echo esc_html($status['text']); ?>
            </div>
            <div class="stat-label">
                <?php echo $status['description']; // Contains safe HTML ?>
            </div>
            <?php if ($status['class'] !== 'status-good'): ?>
            <div class="stat-action">
                <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary button-small">
                    Review Email Settings
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}