<?php
/**
 * Featured Images functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Get featured image statistics
 */
function meta_description_boy_get_featured_image_stats() {
    // Check for cached results first
    $cache_key = 'meta_description_boy_featured_image_stats';
    $cached_stats = get_transient($cache_key);

    if ($cached_stats !== false) {
        return $cached_stats;
    }

    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));

    $total = 0;
    $with_featured = 0;
    $without_featured = 0;
    $post_type_stats = array();

    foreach ($selected_post_types as $post_type) {
        // Get all published posts for this post type
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
        ));

        $type_total = count($posts);
        $type_with_featured = 0;
        $type_without_featured = 0;
        $missing_ids = array();

        // Check each post for featured image
        foreach ($posts as $post_id) {
            if (has_post_thumbnail($post_id)) {
                // Verify the featured image actually exists and is accessible
                $thumbnail_id = get_post_thumbnail_id($post_id);
                if ($thumbnail_id && wp_attachment_is_image($thumbnail_id)) {
                    $type_with_featured++;
                } else {
                    $type_without_featured++;
                    $missing_ids[] = $post_id;
                }
            } else {
                $type_without_featured++;
                $missing_ids[] = $post_id;
            }
        }

        // Store stats for each post type
        if ($type_total > 0) {
            $post_type_stats[$post_type] = array(
                'total' => $type_total,
                'with_featured' => $type_with_featured,
                'without_featured' => $type_without_featured,
                'missing_ids' => $missing_ids,
                'percentage' => ($type_with_featured / $type_total) * 100
            );
        }

        // Add to overall totals
        $total += $type_total;
        $with_featured += $type_with_featured;
        $without_featured += $type_without_featured;
    }

    $percentage = $total > 0 ? ($with_featured / $total) * 100 : 0;

    $stats = array(
        'total' => $total,
        'with_featured' => $with_featured,
        'without_featured' => $without_featured,
        'percentage' => $percentage,
        'post_type_stats' => $post_type_stats
    );

    // Cache the results for 1 hour (3600 seconds)
    set_transient($cache_key, $stats, 3600);

    return $stats;
}

/**
 * Render featured images section
 */
function meta_description_boy_render_featured_images_section() {
    $featured_stats = meta_description_boy_get_featured_image_stats();

    // Determine status class and text based on percentage
    $status_class = '';
    $status_text = '';
    if ($featured_stats['percentage'] >= 100) {
        $status_class = 'status-good';
        $status_text = 'Complete';
    } elseif ($featured_stats['percentage'] >= 80) {
        $status_class = 'status-warning';
        $status_text = 'Nearly Complete';
    } else {
        $status_class = 'status-error';
        $status_text = 'Needs Attention';
    }
    ?>
    <div class="seo-stat-item <?php echo $status_class; ?>">
        <div class="stat-icon">🖼️</div>
        <div class="stat-content">
            <h4>Featured Images</h4>
            <div class="stat-status <?php echo $status_class; ?>">
                <?php echo $status_text; ?>
            </div>
            <div class="stat-number">
                <?php echo $featured_stats['with_featured']; ?>/<?php echo $featured_stats['total']; ?>
            </div>
            <div class="stat-label">
                <?php echo round($featured_stats['percentage'], 1); ?>% have featured images
            </div>
            <?php if ($featured_stats['without_featured'] > 0 && !empty($featured_stats['post_type_stats'])): ?>
            <div class="stat-action">
                <?php foreach ($featured_stats['post_type_stats'] as $post_type => $stats): ?>
                    <?php if ($stats['without_featured'] > 0): ?>
                        <?php
                        // Get post type object for proper labels
                        $post_type_obj = get_post_type_object($post_type);
                        $post_type_label = $post_type_obj ? $post_type_obj->labels->name : ucfirst($post_type);

                        // Build the correct admin URL for each post type
                        if ($post_type === 'post') {
                            $edit_url = admin_url('edit.php?featured_image_missing=1');
                        } else {
                            $edit_url = admin_url('edit.php?post_type=' . $post_type . '&featured_image_missing=1');
                        }
                        ?>
                        <a href="<?php echo $edit_url; ?>" class="button button-small" style="margin: 2px;">
                            <?php echo $post_type_label; ?> (<?php echo $stats['without_featured']; ?>)
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <a href="<?php echo admin_url('upload.php'); ?>" class="button button-small" style="margin: 2px;">
                    Media Library
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Analyze featured images and return post IDs with issues
 */
function meta_description_boy_analyze_featured_images() {
    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));

    // Get all published posts/pages
    $all_posts = get_posts(array(
        'post_type' => $selected_post_types,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids',
    ));

    $missing_featured_ids = array();

    // Check each post/page for featured image
    foreach ($all_posts as $post_id) {
        if (!has_post_thumbnail($post_id)) {
            $missing_featured_ids[] = $post_id;
        } else {
            // Double-check that the featured image actually exists
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if (!$thumbnail_id || !wp_attachment_is_image($thumbnail_id)) {
                $missing_featured_ids[] = $post_id;
            }
        }
    }

    return array(
        'missing_featured_ids' => $missing_featured_ids
    );
}

/**
 * Clear the featured image analysis cache when posts are updated
 */
function meta_description_boy_clear_featured_image_cache($post_id) {
    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));
    if (in_array(get_post_type($post_id), $selected_post_types)) {
        delete_transient('meta_description_boy_featured_image_stats');
        delete_transient('meta_description_boy_featured_image_analysis');
    }
}

/**
 * Clear featured image analysis cache when thumbnails are updated
 */
function meta_description_boy_clear_featured_image_cache_on_thumbnail_update($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key === '_thumbnail_id') {
        meta_description_boy_clear_featured_image_cache($post_id);
    }
}

/**
 * Clear featured image analysis cache to ensure updated filtering takes effect
 */
function meta_description_boy_force_clear_featured_image_cache() {
    delete_transient('meta_description_boy_featured_image_stats');
    delete_transient('meta_description_boy_featured_image_analysis');
}