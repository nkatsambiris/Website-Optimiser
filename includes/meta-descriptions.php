<?php
/**
 * Meta Descriptions functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Get meta description statistics
 */
function meta_description_boy_get_meta_description_stats() {
    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));

    // Get total published posts/pages using get_posts instead of WP_Query for more reliable counting
    $all_posts = get_posts(array(
        'post_type' => $selected_post_types,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids',
    ));

    $total = count($all_posts);

    if ($total == 0) {
        return array(
            'total' => 0,
            'with_meta' => 0,
            'missing' => 0,
            'percentage' => 0
        );
    }

    $with_meta = 0;

    // Check multiple SEO plugin meta description fields
    foreach ($all_posts as $post_id) {
        $has_meta = false;

        // Check Yoast SEO
        $yoast_meta = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if (!empty(trim($yoast_meta))) {
            $has_meta = true;
        }

        // Check RankMath
        if (!$has_meta) {
            $rankmath_meta = get_post_meta($post_id, 'rank_math_description', true);
            if (!empty(trim($rankmath_meta))) {
                $has_meta = true;
            }
        }

        // Check All in One SEO
        if (!$has_meta) {
            $aioseo_meta = get_post_meta($post_id, '_aioseo_description', true);
            if (!empty(trim($aioseo_meta))) {
                $has_meta = true;
            }
        }

        // Check SEOPress
        if (!$has_meta) {
            $seopress_meta = get_post_meta($post_id, '_seopress_titles_desc', true);
            if (!empty(trim($seopress_meta))) {
                $has_meta = true;
            }
        }

        // Check custom meta description field (generic)
        if (!$has_meta) {
            $custom_meta = get_post_meta($post_id, 'meta_description', true);
            if (!empty(trim($custom_meta))) {
                $has_meta = true;
            }
        }

        if ($has_meta) {
            $with_meta++;
        }
    }

    $missing = $total - $with_meta;
    $percentage = $total > 0 ? ($with_meta / $total) * 100 : 0;

    return array(
        'total' => $total,
        'with_meta' => $with_meta,
        'missing' => $missing,
        'percentage' => $percentage
    );
}

/**
 * Render meta descriptions section
 */
function meta_description_boy_render_meta_descriptions_section() {
    $meta_desc_stats = meta_description_boy_get_meta_description_stats();

    // Determine status class and text based on percentage
    $status_class = '';
    $status_text = '';
    if ($meta_desc_stats['percentage'] >= 100) {
        $status_class = 'status-good';
        $status_text = 'Complete';
    } elseif ($meta_desc_stats['percentage'] >= 80) {
        $status_class = 'status-warning';
        $status_text = 'Nearly Complete';
    } else {
        $status_class = 'status-error';
        $status_text = 'Needs Attention';
    }
    ?>
    <div class="seo-stat-item <?php echo $status_class; ?>">
        <div class="stat-icon">📝</div>
        <div class="stat-content">
            <h4>Meta Descriptions</h4>
            <div class="stat-status <?php echo $status_class; ?>">
                <?php echo $status_text; ?>
            </div>
            <div class="stat-number">
                <?php echo $meta_desc_stats['with_meta']; ?>/<?php echo $meta_desc_stats['total']; ?>
            </div>
            <div class="stat-label">
                <?php echo round($meta_desc_stats['percentage'], 1); ?>% coverage
            </div>
            <?php if ($meta_desc_stats['with_meta'] < $meta_desc_stats['total']): ?>
            <div class="stat-action">
                <a href="<?php echo admin_url('edit.php?meta_desc_missing=1'); ?>" class="button button-small">
                    View Missing (<?php echo $meta_desc_stats['missing']; ?>)
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}