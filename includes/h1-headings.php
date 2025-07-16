<?php
/**
 * H1 Headings functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Get H1 heading statistics
 */
function meta_description_boy_get_h1_stats() {
    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));

    // Get all published posts/pages using get_posts for reliable results
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
            'correct' => 0,
            'no_h1' => 0,
            'multiple_h1' => 0,
            'issues' => 0,
            'percentage' => 0
        );
    }

    $correct = 0;
    $no_h1 = 0;
    $multiple_h1 = 0;

    // Check each post/page for H1 tags
    foreach ($all_posts as $post_id) {
        // Get the actual rendered content by doing a simulated frontend request
        $post_url = get_permalink($post_id);
        $response = wp_remote_get($post_url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress H1 Checker)'
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Fallback to content field method if remote request fails
            $content = get_post_field('post_content', $post_id);
            $content = apply_filters('the_content', $content);
        } else {
            // Use the actual rendered HTML
            $content = wp_remote_retrieve_body($response);
        }

        // Count H1 tags using regex, excluding editor elements
        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $content, $h1_matches);

        $h1_count = 0;
        // Filter out Gutenberg editor elements and empty H1s
        foreach ($h1_matches[0] as $index => $h1_tag) {
            // Skip if it's a Gutenberg editor element (but allow rendered post titles)
            if (preg_match('/contenteditable=["\'"]true["\'"]/', $h1_tag) ||
                preg_match('/class=["\'][^"\']*(?:block-editor|editor-post-title)[^"\']*["\']/', $h1_tag) ||
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

        if ($h1_count == 0) {
            $no_h1++;
        } elseif ($h1_count == 1) {
            $correct++;
        } else {
            $multiple_h1++;
        }
    }

    $issues = $no_h1 + $multiple_h1;
    $percentage = $total > 0 ? ($correct / $total) * 100 : 0;

    return array(
        'total' => $total,
        'correct' => $correct,
        'no_h1' => $no_h1,
        'multiple_h1' => $multiple_h1,
        'issues' => $issues,
        'percentage' => $percentage
    );
}

/**
 * Render H1 headings section
 */
function meta_description_boy_render_h1_headings_section() {
    $h1_stats = meta_description_boy_get_h1_stats();

    // Determine status class and text based on percentage
    $status_class = '';
    $status_text = '';
    if ($h1_stats['percentage'] >= 100) {
        $status_class = 'status-good';
        $status_text = 'All Correct';
    } elseif ($h1_stats['percentage'] >= 80) {
        $status_class = 'status-warning';
        $status_text = 'Nearly Complete';
    } else {
        $status_class = 'status-error';
        $status_text = 'Issues Found';
    }
    ?>
    <div class="seo-stat-item <?php echo $status_class; ?>">
        <div class="stat-icon">📰</div>
        <div class="stat-content">
            <h4>H1 Headings</h4>
            <div class="stat-status <?php echo $status_class; ?>">
                <?php echo $status_text; ?>
            </div>
            <div class="stat-number">
                <?php echo $h1_stats['correct']; ?>/<?php echo $h1_stats['total']; ?>
            </div>
            <div class="stat-label">
                <?php echo round($h1_stats['percentage'], 1); ?>% correct
            </div>
            <div class="stat-action">
                <button id="refresh-h1-analysis" class="button button-small" style="margin-bottom: 5px;">
                    🔄 Refresh
                </button>
                <?php if ($h1_stats['issues'] > 0): ?>
                    <?php if ($h1_stats['no_h1'] > 0): ?>
                    <a href="<?php echo admin_url('edit.php?h1_missing=1'); ?>" class="button button-small" style="margin-left: 2px;">
                        No H1 (<?php echo $h1_stats['no_h1']; ?>)
                    </a>
                    <?php endif; ?>
                    <?php if ($h1_stats['multiple_h1'] > 0): ?>
                    <a href="<?php echo admin_url('edit.php?h1_multiple=1'); ?>" class="button button-small" style="margin-left: 2px;">
                        Multiple H1 (<?php echo $h1_stats['multiple_h1']; ?>)
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Analyze H1 tags and return post IDs with issues
 */
function meta_description_boy_analyze_h1_tags() {
    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));

    // Get all published posts/pages
    $all_posts = get_posts(array(
        'post_type' => $selected_post_types,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids',
    ));

    $no_h1_ids = array();
    $multiple_h1_ids = array();

    // Check each post/page for H1 tags
    foreach ($all_posts as $post_id) {
        // Get the actual rendered content by doing a simulated frontend request
        $post_url = get_permalink($post_id);
        $response = wp_remote_get($post_url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress H1 Checker)'
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Fallback to content field method if remote request fails
            $content = get_post_field('post_content', $post_id);
            $content = apply_filters('the_content', $content);
        } else {
            // Use the actual rendered HTML
            $content = wp_remote_retrieve_body($response);
        }

        // Count H1 tags using regex, excluding editor elements
        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $content, $h1_matches);

        $h1_count = 0;
        // Filter out Gutenberg editor elements and empty H1s
        foreach ($h1_matches[0] as $index => $h1_tag) {
            // Skip if it's a Gutenberg editor element (but allow rendered post titles)
            if (preg_match('/contenteditable=["\'"]true["\'"]/', $h1_tag) ||
                preg_match('/class=["\'][^"\']*(?:block-editor|editor-post-title)[^"\']*["\']/', $h1_tag) ||
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

        if ($h1_count == 0) {
            $no_h1_ids[] = $post_id;
        } elseif ($h1_count > 1) {
            $multiple_h1_ids[] = $post_id;
        }
    }

    return array(
        'no_h1_ids' => $no_h1_ids,
        'multiple_h1_ids' => $multiple_h1_ids
    );
}

/**
 * Clear the H1 analysis cache when posts are updated
 */
function meta_description_boy_clear_h1_cache($post_id) {
    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));
    if (in_array(get_post_type($post_id), $selected_post_types)) {
        delete_transient('meta_description_boy_h1_analysis');
    }
}

/**
 * Clear H1 analysis cache to ensure updated filtering takes effect
 */
function meta_description_boy_force_clear_h1_cache() {
    delete_transient('meta_description_boy_h1_analysis');
}

/**
 * Handle AJAX request to refresh H1 analysis
 */
function meta_description_boy_refresh_h1_analysis() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    // Clear the cache to force fresh analysis
    meta_description_boy_force_clear_h1_cache();

    // Get fresh stats
    $h1_stats = meta_description_boy_get_h1_stats();

    wp_die(json_encode(array(
        'success' => true,
        'message' => 'H1 analysis refreshed successfully',
        'stats' => $h1_stats
    )));
}
add_action('wp_ajax_meta_description_boy_refresh_h1_analysis', 'meta_description_boy_refresh_h1_analysis');

// Clear cache immediately after updating the filtering logic
meta_description_boy_force_clear_h1_cache();

/**
 * Debug function to test H1 detection on a specific post
 */
function meta_description_boy_debug_h1_detection($post_id = null) {
    if (!$post_id) {
        // Get the first post to test with
        $posts = get_posts(array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields' => 'ids',
        ));
        if (empty($posts)) {
            return 'No posts found';
        }
        $post_id = $posts[0];
    }

    $content = get_post_field('post_content', $post_id);
    $filtered_content = apply_filters('the_content', $content);

    // Find all H1 tags
    preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $filtered_content, $h1_matches);

    $debug_info = array(
        'post_id' => $post_id,
        'post_title' => get_the_title($post_id),
        'raw_content_length' => strlen($content),
        'filtered_content_length' => strlen($filtered_content),
        'h1_matches_count' => count($h1_matches[0]),
        'h1_tags_found' => array(),
        'valid_h1_count' => 0
    );

    foreach ($h1_matches[0] as $index => $h1_tag) {
        $h1_content = trim(strip_tags($h1_matches[1][$index]));

        $is_editor_element = (
            preg_match('/contenteditable=["\'"]true["\'"]/', $h1_tag) ||
            preg_match('/class=["\'][^"\']*(?:block-editor|editor-post-title)[^"\']*["\']/', $h1_tag) ||
            preg_match('/role=["\'"]textbox["\']/', $h1_tag)
        );

        $is_empty = empty($h1_content);
        $is_valid = !$is_editor_element && !$is_empty;

        if ($is_valid) {
            $debug_info['valid_h1_count']++;
        }

        $debug_info['h1_tags_found'][] = array(
            'tag' => $h1_tag,
            'content' => $h1_content,
            'is_editor_element' => $is_editor_element,
            'is_empty' => $is_empty,
            'is_valid' => $is_valid
        );
    }

    return $debug_info;
}