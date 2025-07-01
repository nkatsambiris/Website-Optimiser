<?php
/**
 * Shared functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Detect active SEO plugins
 */
function meta_description_boy_detect_seo_plugins() {
    $seo_plugins = array();

    // Check for Yoast SEO
    if (is_plugin_active('wordpress-seo/wp-seo.php') || class_exists('WPSEO_Options')) {
        $seo_plugins[] = 'Yoast SEO';
    }

    // Check for RankMath
    if (is_plugin_active('seo-by-rank-math/rank-math.php') || class_exists('RankMath')) {
        $seo_plugins[] = 'RankMath';
    }

    // Check for All in One SEO
    if (is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php') || class_exists('AIOSEOPro')) {
        $seo_plugins[] = 'All in One SEO';
    }

    // Check for SEOPress
    if (is_plugin_active('wp-seopress/seopress.php') || class_exists('SEOPress')) {
        $seo_plugins[] = 'SEOPress';
    }

    return $seo_plugins;
}

/**
 * Add custom query vars for filtering
 */
function meta_description_boy_add_query_vars($vars) {
    $vars[] = 'meta_desc_missing';
    $vars[] = 'h1_missing';
    $vars[] = 'h1_multiple';
    $vars[] = 'featured_image_missing';
    return $vars;
}

/**
 * Modify admin queries to show missing meta descriptions
 */
function meta_description_boy_admin_query_filter($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    // Filter for missing meta descriptions
    if (get_query_var('meta_desc_missing')) {
        $meta_query = $query->get('meta_query');
        if (!$meta_query) {
            $meta_query = array();
        }

        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key' => '_yoast_wpseo_metadesc',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_yoast_wpseo_metadesc',
                'value' => '',
                'compare' => '='
            )
        );

        $query->set('meta_query', $meta_query);
    }

    // Filter for H1 heading issues
    if (get_query_var('h1_missing') || get_query_var('h1_multiple')) {
        $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));

        // Get cached H1 analysis to avoid recalculating on every request
        $h1_analysis = get_transient('meta_description_boy_h1_analysis');

        if ($h1_analysis === false) {
            $h1_analysis = meta_description_boy_analyze_h1_tags();
            set_transient('meta_description_boy_h1_analysis', $h1_analysis, 10 * MINUTE_IN_SECONDS);
        }

        $filter_ids = array();

        if (get_query_var('h1_missing')) {
            $filter_ids = $h1_analysis['no_h1_ids'];
        } elseif (get_query_var('h1_multiple')) {
            $filter_ids = $h1_analysis['multiple_h1_ids'];
        }

        if (empty($filter_ids)) {
            $filter_ids = array(0); // Non-existent ID to show no results
        }

        $query->set('post__in', $filter_ids);
        $query->set('post_type', $selected_post_types);
    }

    // Filter for missing featured images
    if (get_query_var('featured_image_missing')) {
        $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));

        // Get cached featured image analysis to avoid recalculating on every request
        $featured_analysis = get_transient('meta_description_boy_featured_image_analysis');

        if ($featured_analysis === false) {
            $featured_analysis = meta_description_boy_analyze_featured_images();
            set_transient('meta_description_boy_featured_image_analysis', $featured_analysis, 10 * MINUTE_IN_SECONDS);
        }

        $filter_ids = $featured_analysis['missing_featured_ids'];

        if (empty($filter_ids)) {
            $filter_ids = array(0); // Non-existent ID to show no results
        }

        $query->set('post__in', $filter_ids);
        $query->set('post_type', $selected_post_types);
    }
}

/**
 * Add admin notices for filtered views
 */
function meta_description_boy_admin_notices() {
    if (get_query_var('meta_desc_missing')) {
        echo '<div class="notice notice-info"><p><strong>Showing posts/pages missing meta descriptions.</strong> <a href="' . admin_url('edit.php') . '">View all posts</a></p></div>';
    }

    if (get_query_var('h1_missing')) {
        echo '<div class="notice notice-warning"><p><strong>Showing posts/pages with no H1 headings.</strong> <a href="' . admin_url('edit.php') . '">View all posts</a></p></div>';
    }

    if (get_query_var('h1_multiple')) {
        echo '<div class="notice notice-warning"><p><strong>Showing posts/pages with multiple H1 headings.</strong> <a href="' . admin_url('edit.php') . '">View all posts</a></p></div>';
    }

    if (get_query_var('featured_image_missing')) {
        $current_post_type = get_current_screen()->post_type ?? 'post';
        $post_type_obj = get_post_type_object($current_post_type);
        $post_type_label = $post_type_obj ? strtolower($post_type_obj->labels->name) : 'posts';

        $view_all_url = ($current_post_type === 'post') ? admin_url('edit.php') : admin_url('edit.php?post_type=' . $current_post_type);

        echo '<div class="notice notice-warning"><p><strong>Showing ' . $post_type_label . ' missing featured images.</strong> <a href="' . $view_all_url . '">View all ' . $post_type_label . '</a></p></div>';
    }

    if (isset($_GET['alt_text_missing']) && $_GET['alt_text_missing'] == '1') {
        echo '<div class="notice notice-info"><p><strong>Showing images missing alt text (excluding SVG files).</strong> <a href="' . admin_url('upload.php') . '">View all media</a></p></div>';
    }
}