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
            <span class="seo-last-updated">
                Last updated: <?php echo current_time('M j, g:i A'); ?>
            </span>
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
    }
    </style>
    <?php
}

// Functions moved to separate component files

// Alt text functions moved to includes/alt-text.php

// H1 heading functions moved to includes/h1-headings.php

// Robots.txt and XML sitemap functions moved to includes/robots-txt.php and includes/xml-sitemap.php

// Query filtering functions moved to includes/shared.php

// All functions have been moved to separate component files

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
add_action('admin_notices', 'meta_description_boy_admin_notices');

// Clear H1 cache to ensure updated filtering takes effect
meta_description_boy_force_clear_h1_cache();

// Clear featured image cache to ensure updated filtering takes effect
meta_description_boy_force_clear_featured_image_cache();