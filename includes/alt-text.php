<?php
/**
 * Alt Text functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Get alt text statistics
 */
function meta_description_boy_get_alt_text_stats() {
    // Get all image attachments using get_posts for more reliable results
    $all_images = get_posts(array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'post_mime_type' => 'image',
        'numberposts' => -1,
        'fields' => 'ids',
    ));

    // Filter out SVG files as they don't typically need alt text for SEO
    $filtered_images = array();
    foreach ($all_images as $image_id) {
        $mime_type = get_post_mime_type($image_id);
        if ($mime_type !== 'image/svg+xml') {
            $filtered_images[] = $image_id;
        }
    }

    $total = count($filtered_images);

    if ($total == 0) {
        return array(
            'total' => 0,
            'with_alt' => 0,
            'missing' => 0,
            'percentage' => 0
        );
    }

    $with_alt = 0;

    // Check each image for alt text (excluding SVGs)
    foreach ($filtered_images as $image_id) {
        $alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        if (!empty(trim($alt_text))) {
            $with_alt++;
        }
    }

    $missing = $total - $with_alt;
    $percentage = $total > 0 ? ($with_alt / $total) * 100 : 0;

    return array(
        'total' => $total,
        'with_alt' => $with_alt,
        'missing' => $missing,
        'percentage' => $percentage
    );
}

/**
 * Render alt text section
 */
function meta_description_boy_render_alt_text_section() {
    $alt_text_stats = meta_description_boy_get_alt_text_stats();

    // Determine status class and text based on percentage
    $status_class = '';
    $status_text = '';
    if ($alt_text_stats['total'] == 0) {
        $status_class = 'status-good';
        $status_text = 'No Images';
    } elseif ($alt_text_stats['percentage'] >= 100) {
        $status_class = 'status-good';
        $status_text = 'Complete';
    } elseif ($alt_text_stats['percentage'] >= 80) {
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
            <h4>Image Alt Text</h4>
            <div class="stat-status <?php echo $status_class; ?>">
                <?php echo $status_text; ?>
            </div>
            <div class="stat-number">
                <?php echo $alt_text_stats['with_alt']; ?>/<?php echo $alt_text_stats['total']; ?>
            </div>
            <div class="stat-label">
                <?php echo round($alt_text_stats['percentage'], 1); ?>% coverage
            </div>
            <?php if ($alt_text_stats['with_alt'] < $alt_text_stats['total']): ?>
            <div class="stat-action">
                <a href="<?php echo admin_url('upload.php?alt_text_missing=1'); ?>" class="button button-small">
                    View Missing (<?php echo $alt_text_stats['missing']; ?>)
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Filter media library for missing alt text using a safer approach
 */
function meta_description_boy_filter_media_library($query) {
    // Only run on admin media library page
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    // Check if we're on the upload.php page and filtering for missing alt text
    if (isset($_GET['alt_text_missing']) && $_GET['alt_text_missing'] == '1' &&
        isset($_GET['page']) === false && // Not a custom admin page
        strpos($_SERVER['REQUEST_URI'], 'upload.php') !== false) {

        // Get cached missing alt text IDs to avoid database overload
        $missing_alt_ids = get_transient('meta_description_boy_missing_alt_ids');

        if ($missing_alt_ids === false) {
            // Use direct database query to avoid recursion
            global $wpdb;

            // Get all image attachments (excluding SVGs) with a reasonable limit
            $image_ids = $wpdb->get_col($wpdb->prepare("
                SELECT p.ID
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'attachment'
                AND p.post_status = 'inherit'
                AND p.post_mime_type IN ('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff')
                LIMIT %d
            ", 1000)); // Limit to 1000 images for performance

            $missing_alt_ids = array();

            // Check each image for missing alt text
            foreach ($image_ids as $image_id) {
                $alt_text = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                if (empty(trim($alt_text))) {
                    $missing_alt_ids[] = $image_id;
                }
            }

            // If no images are missing alt text, show nothing
            if (empty($missing_alt_ids)) {
                $missing_alt_ids = array(0); // Non-existent ID to show no results
            }

            // Cache for 5 minutes to improve performance
            set_transient('meta_description_boy_missing_alt_ids', $missing_alt_ids, 5 * MINUTE_IN_SECONDS);
        }

        // Filter the query to only show these IDs
        $query->set('post__in', $missing_alt_ids);
        $query->set('post_type', 'attachment');
        $query->set('post_status', 'inherit');
        $query->set('post_mime_type', array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/tiff'));
    }
}

/**
 * Clear the missing alt text cache when attachments are updated
 */
function meta_description_boy_clear_alt_cache($post_id) {
    if (get_post_type($post_id) === 'attachment') {
        delete_transient('meta_description_boy_missing_alt_ids');
    }
}