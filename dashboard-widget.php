<?php
/**
 * SEO Optimisation Admin Page for Meta Description Boy Plugin
 * Displays SEO statistics and health metrics in a dedicated admin menu
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

// Include component files
require_once plugin_dir_path(__FILE__) . 'includes/shared.php';
require_once plugin_dir_path(__FILE__) . 'includes/meta-descriptions.php';
require_once plugin_dir_path(__FILE__) . 'includes/alt-text.php';
require_once plugin_dir_path(__FILE__) . 'includes/h1-headings.php';
require_once plugin_dir_path(__FILE__) . 'includes/featured-images.php';
require_once plugin_dir_path(__FILE__) . 'includes/robots-txt.php';
require_once plugin_dir_path(__FILE__) . 'includes/xml-sitemap.php';
require_once plugin_dir_path(__FILE__) . 'includes/google-site-kit.php';
require_once plugin_dir_path(__FILE__) . 'includes/wp-smush.php';
require_once plugin_dir_path(__FILE__) . 'includes/wordfence-security.php';
require_once plugin_dir_path(__FILE__) . 'includes/gravity-forms-recaptcha.php';
require_once plugin_dir_path(__FILE__) . 'includes/gravity-forms-notifications.php';
require_once plugin_dir_path(__FILE__) . 'includes/gravity-forms-confirmations.php';
require_once plugin_dir_path(__FILE__) . 'includes/redirects.php';
require_once plugin_dir_path(__FILE__) . 'includes/hubspot.php';
require_once plugin_dir_path(__FILE__) . 'includes/meta-pixel.php';
require_once plugin_dir_path(__FILE__) . 'includes/updraftplus.php';
require_once plugin_dir_path(__FILE__) . 'includes/custom-404-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/uptime-monitoring.php';
require_once plugin_dir_path(__FILE__) . 'includes/favicon.php';
require_once plugin_dir_path(__FILE__) . 'includes/wp-debug.php';
require_once plugin_dir_path(__FILE__) . 'includes/caching-plugins.php';
require_once plugin_dir_path(__FILE__) . 'includes/dynamic-copyright-year.php';
require_once plugin_dir_path(__FILE__) . 'includes/woocommerce.php';
require_once plugin_dir_path(__FILE__) . 'includes/woocommerce-google-analytics.php';
require_once plugin_dir_path(__FILE__) . 'includes/woocommerce-emails.php';
require_once plugin_dir_path(__FILE__) . 'includes/woocommerce-payment-methods.php';
require_once plugin_dir_path(__FILE__) . 'includes/woocommerce-shipping-zones.php';
require_once plugin_dir_path(__FILE__) . 'includes/woocommerce-tax-settings.php';

/**
 * Add admin menu page for SEO optimization
 */
function meta_description_boy_add_optimisation_menu() {
    // Only show to administrators
    if (current_user_can('administrator')) {
        add_menu_page(
            'Website Optimisation',           // Page title
            'Optimisation',               // Menu title
            'manage_options',             // Capability
            'website-optimisation',           // Menu slug
            'meta_description_boy_optimisation_page', // Function
            'dashicons-search',           // Icon
            100                          // Position
        );
    }
}
add_action('admin_menu', 'meta_description_boy_add_optimisation_menu');

/**
 * SEO Optimisation admin page content
 */
function meta_description_boy_optimisation_page() {
    ?>
    <div class="optimisation-page-header">
        <h1><span class="dashicons dashicons-search" style="margin-right: 10px;"></span>Website Optimisation</h1>
    </div>
    <div class="wrap">
        <div id="meta-description-boy-optimisation-page">
    <?php

    // Debug information (kept for troubleshooting)
    $meta_desc_stats = meta_description_boy_get_meta_description_stats();
    $alt_text_stats = meta_description_boy_get_alt_text_stats();
    $h1_stats = meta_description_boy_get_h1_stats();

    // Debug information (remove this after troubleshooting)
    $debug_enabled = get_option('meta_description_boy_debug_enabled');
    if ($debug_enabled) {
        echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin-bottom: 15px; border-radius: 4px;">';
        echo '<strong>Debug Info:</strong><br>';
        echo 'Total posts found: ' . $meta_desc_stats['total'] . '<br>';
        echo 'Total images found: ' . $alt_text_stats['total'] . ' (excluding SVG files)<br>';
        echo 'H1 headings: ' . $h1_stats['correct'] . ' correct, ' . $h1_stats['no_h1'] . ' missing, ' . $h1_stats['multiple_h1'] . ' multiple<br>';
        echo 'Post types checked: ' . implode(', ', get_option('meta_description_boy_post_types', array('post', 'page'))) . '<br>';

        // Sample a few posts to see their meta data
        $sample_posts = get_posts(array(
            'post_type' => get_option('meta_description_boy_post_types', array('post', 'page')),
            'post_status' => 'publish',
            'numberposts' => 3,
            'fields' => 'ids',
        ));

        if (!empty($sample_posts)) {
            echo '<br><strong>Sample post meta data:</strong><br>';
            foreach ($sample_posts as $post_id) {
                echo '<em>Post ID ' . $post_id . ':</em><br>';
                $yoast = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                $rankmath = get_post_meta($post_id, 'rank_math_description', true);
                $aioseo = get_post_meta($post_id, '_aioseo_description', true);
                $seopress = get_post_meta($post_id, '_seopress_titles_desc', true);

                echo '- Yoast: ' . (empty($yoast) ? 'empty' : 'has content') . '<br>';
                echo '- RankMath: ' . (empty($rankmath) ? 'empty' : 'has content') . '<br>';
                echo '- AIOSEO: ' . (empty($aioseo) ? 'empty' : 'has content') . '<br>';
                echo '- SEOPress: ' . (empty($seopress) ? 'empty' : 'has content') . '<br>';

                // Add H1 analysis for this post
                $content = get_post_field('post_content', $post_id);
                $content = apply_filters('the_content', $content);
                preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $content, $h1_matches);

                $h1_count = 0;
                // Filter out Gutenberg editor elements and empty H1s
                foreach ($h1_matches[0] as $index => $h1_tag) {
                    // Skip if it's a Gutenberg editor element
                    if (preg_match('/contenteditable=["\'"]true["\'"]/', $h1_tag) ||
                        preg_match('/class=["\'][^"\']*(?:block-editor|editor-post-title|wp-block-post-title)[^"\']*["\']/', $h1_tag) ||
                        preg_match('/role=["\'"]textbox["\']/', $h1_tag)) {
                        continue;
                    }

                    // Skip if H1 content is empty or just whitespace
                    $h1_content = trim(strip_tags($h1_matches[1][$index]));
                    if (empty($h1_content)) {
                        continue;
                    }

                    $h1_count++;
                }
                echo '- H1 tags found: ' . $h1_count . ' (editor elements excluded)<br>';
            }
        }
        echo '</div>';
    }

    ?>
    <div class="seo-stats-grid">
        <?php
        // Render each SEO component using separate files
        meta_description_boy_render_meta_descriptions_section();
        meta_description_boy_render_alt_text_section();
        meta_description_boy_render_h1_headings_section();
        meta_description_boy_render_featured_images_section();
        meta_description_boy_render_robots_txt_section();
        meta_description_boy_render_xml_sitemap_section();
        meta_description_boy_render_google_site_kit_section();
        meta_description_boy_render_wp_smush_section();
        meta_description_boy_render_wordfence_section();
        meta_description_boy_render_gravity_forms_recaptcha_section();
        meta_description_boy_render_gravity_forms_notifications_section();
        meta_description_boy_render_gravity_forms_confirmations_section();
        meta_description_boy_render_redirects_section();
        meta_description_boy_render_hubspot_section();
        meta_description_boy_render_meta_pixel_section();
        meta_description_boy_render_updraftplus_section();
        meta_description_boy_render_custom_404_section();
        meta_description_boy_render_uptime_monitoring_section();
        meta_description_boy_render_favicon_section();
        meta_description_boy_render_wp_debug_section();
        meta_description_boy_render_caching_plugins_section();
        website_optimiser_render_dynamic_copyright_section();

        // Conditionally render WooCommerce sections if WooCommerce is active
        if (class_exists('WooCommerce')) {
            website_optimiser_render_woocommerce_section();
            website_optimiser_render_woocommerce_ga_section();
            website_optimiser_render_woocommerce_emails_section();
            website_optimiser_render_woocommerce_payment_methods_section();
            website_optimiser_render_woocommerce_shipping_zones_section();
            website_optimiser_render_woocommerce_tax_settings_section();
        }
        ?>
    </div>

        <div class="seo-stats-footer">
        <p><small>Last updated: <?php echo current_time('M j, Y g:i A'); ?> |
        <a href="<?php echo admin_url('admin.php?page=meta-description-boy'); ?>">Plugin Settings</a>
    </div>
        </div>
    </div>
    <?php
}

/**
 * Get overall SEO optimization status summary
 */
function meta_description_boy_get_seo_summary() {
    $total_checks = 0;
    $optimized_checks = 0;
    $warnings = 0;
    $errors = 0;

    // Get status from each component (only if functions exist)
    $statuses = array();

    // Core stats functions
    if (function_exists('meta_description_boy_get_meta_description_stats')) {
        $statuses[] = meta_description_boy_get_meta_description_stats();
    }
    if (function_exists('meta_description_boy_get_alt_text_stats')) {
        $statuses[] = meta_description_boy_get_alt_text_stats();
    }
    if (function_exists('meta_description_boy_get_h1_stats')) {
        $statuses[] = meta_description_boy_get_h1_stats();
    }
    if (function_exists('meta_description_boy_get_featured_image_stats')) {
        $statuses[] = meta_description_boy_get_featured_image_stats();
    }

    // Component status functions
    if (function_exists('meta_description_boy_check_robots_txt')) {
        $statuses[] = meta_description_boy_check_robots_txt();
    }
    if (function_exists('meta_description_boy_check_sitemap')) {
        $statuses[] = meta_description_boy_check_sitemap();
    }
    if (function_exists('meta_description_boy_check_google_site_kit_status')) {
        $statuses[] = meta_description_boy_check_google_site_kit_status();
    }
    if (function_exists('meta_description_boy_check_wp_smush_status')) {
        $statuses[] = meta_description_boy_check_wp_smush_status();
    }
    if (function_exists('meta_description_boy_check_wordfence_status')) {
        $statuses[] = meta_description_boy_check_wordfence_status();
    }
    if (function_exists('meta_description_boy_check_gravity_forms_recaptcha_status')) {
        $statuses[] = meta_description_boy_check_gravity_forms_recaptcha_status();
    }
    if (function_exists('meta_description_boy_check_gravity_forms_notifications_status')) {
        $statuses[] = meta_description_boy_check_gravity_forms_notifications_status();
    }
    if (function_exists('meta_description_boy_check_gravity_forms_confirmations_status')) {
        $statuses[] = meta_description_boy_check_gravity_forms_confirmations_status();
    }
    if (function_exists('meta_description_boy_check_redirects_status')) {
        $statuses[] = meta_description_boy_check_redirects_status();
    }
    if (function_exists('meta_description_boy_check_hubspot_status')) {
        $statuses[] = meta_description_boy_check_hubspot_status();
    }
    if (function_exists('meta_description_boy_check_meta_pixel_status')) {
        $statuses[] = meta_description_boy_check_meta_pixel_status();
    }
    if (function_exists('meta_description_boy_check_updraftplus_status')) {
        $statuses[] = meta_description_boy_check_updraftplus_status();
    }
    if (function_exists('meta_description_boy_check_custom_404_status')) {
        $statuses[] = meta_description_boy_check_custom_404_status();
    }
    if (function_exists('meta_description_boy_check_uptime_monitoring_status')) {
        $statuses[] = meta_description_boy_check_uptime_monitoring_status();
    }
    if (function_exists('meta_description_boy_check_favicon_status')) {
        $statuses[] = meta_description_boy_check_favicon_status();
    }
    if (function_exists('meta_description_boy_check_wp_debug_status')) {
        $statuses[] = meta_description_boy_check_wp_debug_status();
    }
    if (function_exists('meta_description_boy_check_caching_plugins_status')) {
        $statuses[] = meta_description_boy_check_caching_plugins_status();
    }
    if (function_exists('website_optimiser_check_dynamic_copyright_status')) {
        $statuses[] = website_optimiser_check_dynamic_copyright_status();
    }

    // Conditionally check WooCommerce statuses if WooCommerce is active
    if (class_exists('WooCommerce')) {
        if (function_exists('website_optimiser_check_woocommerce_status')) {
            $statuses[] = website_optimiser_check_woocommerce_status();
        }
        if (function_exists('website_optimiser_check_woocommerce_ga_status')) {
            $statuses[] = website_optimiser_check_woocommerce_ga_status();
        }
        if (function_exists('website_optimiser_check_woocommerce_emails_status')) {
            $statuses[] = website_optimiser_check_woocommerce_emails_status();
        }
        if (function_exists('website_optimiser_check_woocommerce_payment_methods_status')) {
            $statuses[] = website_optimiser_check_woocommerce_payment_methods_status();
        }
        if (function_exists('website_optimiser_check_woocommerce_shipping_zones_status')) {
            $statuses[] = website_optimiser_check_woocommerce_shipping_zones_status();
        }
        if (function_exists('website_optimiser_check_woocommerce_tax_settings_status')) {
            $statuses[] = website_optimiser_check_woocommerce_tax_settings_status();
        }
    }

            foreach ($statuses as $status) {
        // Skip if status is not an array or function doesn't exist
        if (!is_array($status)) {
            continue;
        }

        $total_checks++;

        // Handle different status formats
        if (isset($status['class'])) {
            switch ($status['class']) {
                case 'status-good':
                    $optimized_checks++;
                    break;
                case 'status-warning':
                    $warnings++;
                    break;
                case 'status-error':
                    $errors++;
                    break;
            }
        } elseif (isset($status['total']) && (isset($status['with_meta']) || isset($status['with_alt']) || isset($status['with_featured']))) {
            // Handle meta description, alt text, and featured image stats format (total, with_[field], missing, percentage)
            if (isset($status['percentage'])) {
                if ($status['percentage'] >= 100) {
                    $optimized_checks++;
                } elseif ($status['percentage'] >= 80) {
                    $warnings++;
                } else {
                    $errors++;
                }
            } else {
                // Fallback calculation if percentage not provided
                $with_count = 0;
                if (isset($status['with_meta'])) {
                    $with_count = $status['with_meta'];
                } elseif (isset($status['with_alt'])) {
                    $with_count = $status['with_alt'];
                } elseif (isset($status['with_featured'])) {
                    $with_count = $status['with_featured'];
                }

                if ($status['total'] > 0 && $with_count == $status['total']) {
                    $optimized_checks++;
                } elseif ($status['total'] > 0 && ($with_count / $status['total']) >= 0.8) {
                    $warnings++;
                } else {
                    $errors++;
                }
            }
        } elseif (isset($status['total']) && isset($status['correct'])) {
            // Handle H1/alt-text stats format (total, correct, missing)
            if ($status['total'] > 0 && $status['correct'] == $status['total']) {
                $optimized_checks++;
            } elseif ($status['total'] > 0 && ($status['correct'] / $status['total']) >= 0.8) {
                $warnings++;
            } else {
                $errors++;
            }
        } elseif (isset($status['exists'])) {
            // Handle robots.txt and sitemap status format
            if ($status['exists'] === true) {
                $optimized_checks++;
            } else {
                $warnings++;
            }
        } else {
            // Default to optimized if we can't determine status
            $optimized_checks++;
        }
    }

    return array(
        'total' => $total_checks,
        'optimized' => $optimized_checks,
        'warnings' => $warnings,
        'errors' => $errors,
        'percentage' => $total_checks > 0 ? round(($optimized_checks / $total_checks) * 100) : 0
    );
}

/**
 * Add SEO optimization dashboard widget
 */
function meta_description_boy_add_dashboard_widget() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }

    // Double-check user role for extra security
    $current_user = wp_get_current_user();
    if (!in_array('administrator', $current_user->roles)) {
        return;
    }

    wp_add_dashboard_widget(
        'meta_description_boy_seo_overview',
        '🔍 SEO Optimisation Overview',
        'meta_description_boy_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'meta_description_boy_add_dashboard_widget');

/**
 * Dashboard widget content
 */
function meta_description_boy_dashboard_widget_content() {
    // Security check - only administrators can view this widget
    if (!current_user_can('manage_options')) {
        echo '<p>Access denied. Only administrators can view this widget.</p>';
        return;
    }

    $summary = meta_description_boy_get_seo_summary();
    $optimisation_url = admin_url('admin.php?page=website-optimisation');

    // Determine overall status based on percentage and issues
    if ($summary['percentage'] >= 100) {
        $status_class = 'good';
        $status_text = 'All Good';
        $status_color = '#46b450';
    } elseif ($summary['percentage'] >= 85) {
        $status_class = 'warning';
        $status_text = 'Improvements Needed';
        $status_color = '#ffb900';
    } elseif ($summary['errors'] > 0) {
        $status_class = 'error';
        $status_text = 'Issues Found';
        $status_color = '#dc3232';
    } elseif ($summary['warnings'] > 0) {
        $status_class = 'warning';
        $status_text = 'Needs Attention';
        $status_color = '#ffb900';
    } else {
        $status_class = 'good';
        $status_text = 'All Good';
        $status_color = '#46b450';
    }
    ?>
    <div class="seo-dashboard-widget">
        <div class="seo-summary-stats">
            <div class="seo-summary-main">
                <div class="seo-score">
                    <span class="seo-score-number" style="color: <?php echo $status_color; ?>;">
                        <?php echo $summary['optimized']; ?>/<?php echo $summary['total']; ?>
                    </span>
                    <span class="seo-score-label">Optimised</span>
                </div>
                <div class="seo-status">
                    <span class="seo-status-badge seo-status-<?php echo $status_class; ?>" style="background-color: <?php echo $status_color; ?>;">
                        <?php echo $status_text; ?>
                    </span>
                    <div class="seo-percentage"><?php echo $summary['percentage']; ?>% Complete</div>
                </div>
            </div>

            <?php if ($summary['warnings'] > 0 || $summary['errors'] > 0): ?>
            <div class="seo-summary-issues">
                <?php if ($summary['errors'] > 0): ?>
                    <span class="seo-issue-count seo-errors">
                        <span class="dashicons dashicons-warning"></span>
                        <?php echo $summary['errors']; ?> Error<?php echo $summary['errors'] > 1 ? 's' : ''; ?>
                    </span>
                <?php endif; ?>
                <?php if ($summary['warnings'] > 0): ?>
                    <span class="seo-issue-count seo-warnings">
                        <span class="dashicons dashicons-info"></span>
                        <?php echo $summary['warnings']; ?> Warning<?php echo $summary['warnings'] > 1 ? 's' : ''; ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="seo-summary-actions">
            <a href="<?php echo $optimisation_url; ?>" class="button button-primary">
                <span class="dashicons dashicons-search"></span>
                View Full Report
            </a>
            <button type="button" class="button button-secondary" id="seo-email-report-btn">
                <span class="dashicons dashicons-email"></span>
                Email Report
            </button>
            <span class="seo-last-updated">
                Last updated: <?php echo current_time('M j, g:i A'); ?>
            </span>
        </div>
    </div>

    <!-- Email Report Modal -->
    <div id="seo-email-modal" class="seo-modal" style="display: none;">
        <div class="seo-modal-content">
            <div class="seo-modal-header">
                <h3>Email SEO Report</h3>
                <span class="seo-modal-close">&times;</span>
            </div>
            <div class="seo-modal-body">
                <form id="seo-email-form">
                    <div class="form-field">
                        <label for="seo-email-address">Email Address:</label>
                        <input type="email" id="seo-email-address" name="email_address" required
                               placeholder="Enter email address to send report">
                    </div>
                    <div class="form-field">
                        <label for="seo-email-subject">Subject (optional):</label>
                        <input type="text" id="seo-email-subject" name="email_subject"
                               placeholder="SEO Report for <?php echo get_bloginfo('name'); ?>">
                    </div>
                    <div class="form-field">
                        <label for="seo-email-message">Additional Message (optional):</label>
                        <textarea id="seo-email-message" name="email_message" rows="3"
                                  placeholder="Add any additional notes or context..."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-email"></span>
                            Send Report
                        </button>
                        <button type="button" class="button button-secondary seo-modal-cancel">
                            Cancel
                        </button>
                    </div>
                </form>
                <div id="seo-email-status" class="seo-email-status" style="display: none;"></div>
            </div>
        </div>
    </div>

    <style>
    .seo-dashboard-widget {
        margin: -6px -12px -12px;
        padding: 16px;
    }

    .seo-summary-main {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 15px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f0f0f1;
    }

    .seo-score {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .seo-score-number {
        font-size: 32px;
        font-weight: bold;
        line-height: 1;
        margin-bottom: 4px;
    }

    .seo-score-label {
        font-size: 13px;
        color: #646970;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .seo-status {
        text-align: right;
    }

    .seo-status-badge {
        display: inline-block;
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .seo-percentage {
        font-size: 14px;
        color: #646970;
    }

    .seo-summary-issues {
        display: flex;
        gap: 12px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }

    .seo-issue-count {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 13px;
        color: #646970;
    }

    .seo-issue-count.seo-errors .dashicons {
        color: #dc3232;
    }

    .seo-issue-count.seo-warnings .dashicons {
        color: #ffb900;
    }

    .seo-summary-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .seo-summary-actions .button {
        display: flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
    }

    .seo-last-updated {
        font-size: 12px;
        color: #8c8f94;
    }

    /* Modal Styles */
    .seo-modal {
        display: none;
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .seo-modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 0;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
    }

    .seo-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #f0f0f1;
        background-color: #f9f9f9;
    }

    .seo-modal-header h3 {
        margin: 0;
        font-size: 18px;
        color: #23282d;
    }

    .seo-modal-close {
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #666;
        line-height: 1;
    }

    .seo-modal-close:hover {
        color: #dc3232;
    }

    .seo-modal-body {
        padding: 20px;
    }

    .form-field {
        margin-bottom: 15px;
    }

    .form-field label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #23282d;
    }

    .form-field input,
    .form-field textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        box-sizing: border-box;
    }

    .form-field input:focus,
    .form-field textarea:focus {
        border-color: #005cee;
        outline: none;
        box-shadow: 0 0 0 1px #005cee;
    }

    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #f0f0f1;
    }

    .seo-email-status {
        margin-top: 15px;
        padding: 10px;
        border-radius: 4px;
        font-size: 14px;
    }

    .seo-email-status.success {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }

    .seo-email-status.error {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }

    .seo-email-status.loading {
        background-color: #d1ecf1;
        border: 1px solid #bee5eb;
        color: #0c5460;
    }

    @media (max-width: 782px) {
        .seo-summary-main {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }

        .seo-status {
            text-align: center;
        }

        .seo-summary-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .seo-summary-actions .button {
            justify-content: center;
        }

        .seo-last-updated {
            text-align: center;
        }

        .seo-modal-content {
            width: 95%;
            margin: 2% auto;
        }

        .form-actions {
            flex-direction: column;
        }
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Email Report Modal functionality
        $('#seo-email-report-btn').on('click', function() {
            $('#seo-email-modal').fadeIn();
        });

        $('.seo-modal-close, .seo-modal-cancel').on('click', function() {
            $('#seo-email-modal').fadeOut();
        });

        // Close modal when clicking outside
        $('#seo-email-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).fadeOut();
            }
        });

        // Handle form submission
        $('#seo-email-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $status = $('#seo-email-status');
            var $submitBtn = $form.find('button[type="submit"]');

            var formData = {
                action: 'seo_email_report',
                email_address: $('#seo-email-address').val(),
                email_subject: $('#seo-email-subject').val(),
                email_message: $('#seo-email-message').val(),
                nonce: '<?php echo wp_create_nonce('seo_email_report_nonce'); ?>'
            };

            // Show loading state
            $status.removeClass('success error').addClass('loading').html('Sending report...').show();
            $submitBtn.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('loading error').addClass('success').html(response.data.message);
                        $form[0].reset();
                        setTimeout(function() {
                            $('#seo-email-modal').fadeOut();
                            $status.hide();
                        }, 2000);
                    } else {
                        $status.removeClass('loading success').addClass('error').html(response.data.message || 'Failed to send report.');
                    }
                },
                error: function() {
                    $status.removeClass('loading success').addClass('error').html('An error occurred while sending the report.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Handle AJAX request to email SEO report
 */
function meta_description_boy_handle_email_report() {
    // Security checks
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Access denied.'));
    }

    if (!wp_verify_nonce($_POST['nonce'], 'seo_email_report_nonce')) {
        wp_send_json_error(array('message' => 'Invalid security token.'));
    }

    // Get and validate email address
    $email_address = sanitize_email($_POST['email_address']);
    if (!is_email($email_address)) {
        wp_send_json_error(array('message' => 'Please enter a valid email address.'));
    }

    // Get optional fields
    $email_subject = sanitize_text_field($_POST['email_subject']);
    $email_message = sanitize_textarea_field($_POST['email_message']);

    // Set default subject if empty
    if (empty($email_subject)) {
        $email_subject = 'SEO Report for ' . get_bloginfo('name');
    }

    // Generate report content
    $report_content = meta_description_boy_generate_email_report($email_message);

    // Send email
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $sent = wp_mail($email_address, $email_subject, $report_content, $headers);

    if ($sent) {
        wp_send_json_success(array('message' => 'Report sent successfully to ' . $email_address));
    } else {
        wp_send_json_error(array('message' => 'Failed to send email. Please check your email configuration.'));
    }
}
add_action('wp_ajax_seo_email_report', 'meta_description_boy_handle_email_report');

/**
 * Generate HTML email report content
 */
function meta_description_boy_generate_email_report($additional_message = '') {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $summary = meta_description_boy_get_seo_summary();
    $current_time = current_time('F j, Y g:i A');

    // Get detailed stats
    $meta_desc_stats = meta_description_boy_get_meta_description_stats();
    $alt_text_stats = meta_description_boy_get_alt_text_stats();
    $h1_stats = meta_description_boy_get_h1_stats();
    $featured_stats = meta_description_boy_get_featured_image_stats();

    // Determine overall status
    if ($summary['percentage'] >= 100) {
        $status_text = 'All Good';
        $status_color = '#46b450';
    } elseif ($summary['percentage'] >= 85) {
        $status_text = 'Improvements Needed';
        $status_color = '#ffb900';
    } elseif ($summary['errors'] > 0) {
        $status_text = 'Issues Found';
        $status_color = '#dc3232';
    } else {
        $status_text = 'Needs Attention';
        $status_color = '#ffb900';
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>SEO Report - ' . esc_html($site_name) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f9f9f9; }
            .container { max-width: 600px; margin: 0 auto; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background-color: #0073aa; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 20px; }
            .summary { background-color: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
            .score { font-size: 48px; font-weight: bold; color: ' . $status_color . '; margin-bottom: 10px; }
            .status { display: inline-block; padding: 8px 16px; border-radius: 20px; color: white; font-weight: bold; background-color: ' . $status_color . '; }
            .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
            .stat-item { background-color: #f1f1f1; padding: 15px; border-radius: 6px; text-align: center; }
            .stat-number { font-size: 24px; font-weight: bold; color: #0073aa; }
            .stat-label { font-size: 14px; color: #666; margin-top: 5px; }
            .additional-message { background-color: #e8f4f8; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #0073aa; }
            .footer { background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 12px; color: #666; }
            .footer a { color: #0073aa; text-decoration: none; }
            @media (max-width: 600px) {
                .stats-grid { grid-template-columns: 1fr; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔍 SEO Report</h1>
                <p>Website: ' . esc_html($site_name) . '</p>
            </div>

            <div class="content">
                <div class="summary">
                    <div class="score">' . $summary['optimized'] . '/' . $summary['total'] . '</div>
                    <p><strong>Components Optimized</strong></p>
                    <div class="status">' . $status_text . '</div>
                    <p style="margin-top: 10px; font-size: 18px;">' . $summary['percentage'] . '% Complete</p>
                </div>';

    if (!empty($additional_message)) {
        $html .= '
                <div class="additional-message">
                    <h3>Additional Notes:</h3>
                    <p>' . nl2br(esc_html($additional_message)) . '</p>
                </div>';
    }

    $html .= '
                <h3>Detailed Statistics</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number">' . $meta_desc_stats['percentage'] . '%</div>
                        <div class="stat-label">Meta Descriptions<br>(' . $meta_desc_stats['with_meta'] . '/' . $meta_desc_stats['total'] . ')</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">' . $alt_text_stats['percentage'] . '%</div>
                        <div class="stat-label">Alt Text<br>(' . $alt_text_stats['with_alt'] . '/' . $alt_text_stats['total'] . ')</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">' . $h1_stats['correct'] . '</div>
                        <div class="stat-label">Proper H1 Tags<br>(' . $h1_stats['no_h1'] . ' missing, ' . $h1_stats['multiple_h1'] . ' multiple)</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">' . $featured_stats['percentage'] . '%</div>
                        <div class="stat-label">Featured Images<br>(' . $featured_stats['with_featured'] . '/' . $featured_stats['total'] . ')</div>
                    </div>
                </div>';

    if ($summary['warnings'] > 0 || $summary['errors'] > 0) {
        $html .= '
                <h3>Issues Summary</h3>
                <ul>';
        if ($summary['errors'] > 0) {
            $html .= '<li style="color: #dc3232;"><strong>' . $summary['errors'] . ' Error' . ($summary['errors'] > 1 ? 's' : '') . '</strong> - Critical issues that need immediate attention</li>';
        }
        if ($summary['warnings'] > 0) {
            $html .= '<li style="color: #ffb900;"><strong>' . $summary['warnings'] . ' Warning' . ($summary['warnings'] > 1 ? 's' : '') . '</strong> - Areas that could be improved</li>';
        }
        $html .= '</ul>';
    }

    $html .= '
                <p>For detailed information and to make improvements, please visit your <a href="' . admin_url('admin.php?page=website-optimisation') . '">Website Optimisation Dashboard</a>.</p>
            </div>

            <div class="footer">
                <p>Report generated on ' . $current_time . '</p>
                <p><a href="' . esc_url($site_url) . '">' . esc_html($site_name) . '</a></p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}


// Register action hooks from component files
add_filter('query_vars', 'meta_description_boy_add_query_vars');
add_action('pre_get_posts', 'meta_description_boy_admin_query_filter');
add_action('pre_get_posts', 'meta_description_boy_filter_media_library');
add_action('updated_post_meta', 'meta_description_boy_clear_alt_cache');
add_action('added_post_meta', 'meta_description_boy_clear_alt_cache');
add_action('save_post', 'meta_description_boy_clear_h1_cache');
add_action('wp_trash_post', 'meta_description_boy_clear_h1_cache');
add_action('untrash_post', 'meta_description_boy_clear_h1_cache');
add_action('save_post', 'meta_description_boy_clear_featured_image_cache');
add_action('wp_trash_post', 'meta_description_boy_clear_featured_image_cache');
add_action('untrash_post', 'meta_description_boy_clear_featured_image_cache');
add_action('updated_post_meta', 'meta_description_boy_clear_featured_image_cache_on_thumbnail_update', 10, 4);
add_action('added_post_meta', 'meta_description_boy_clear_featured_image_cache_on_thumbnail_update', 10, 4);
add_action('admin_notices', 'meta_description_boy_filtered_view_notices');

// Clear H1 cache to ensure updated filtering takes effect
meta_description_boy_force_clear_h1_cache();

// Clear featured image cache to ensure updated filtering takes effect
meta_description_boy_force_clear_featured_image_cache();