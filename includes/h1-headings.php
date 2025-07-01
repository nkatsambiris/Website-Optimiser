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
        $content = get_post_field('post_content', $post_id);

        // Apply content filters (same as frontend display)
        $content = apply_filters('the_content', $content);

        // Count H1 tags using regex, excluding editor elements
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
            <?php if ($h1_stats['issues'] > 0): ?>
            <div class="stat-action">
                <?php if ($h1_stats['no_h1'] > 0): ?>
                <a href="<?php echo admin_url('edit.php?h1_missing=1'); ?>" class="button button-small">
                    No H1 (<?php echo $h1_stats['no_h1']; ?>)
                </a>
                <?php endif; ?>
                <?php if ($h1_stats['multiple_h1'] > 0): ?>
                <a href="<?php echo admin_url('edit.php?h1_multiple=1'); ?>" class="button button-small" style="margin-left: 2px;">
                    Multiple H1 (<?php echo $h1_stats['multiple_h1']; ?>)
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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
        $content = get_post_field('post_content', $post_id);

        // Apply content filters (same as frontend display)
        $content = apply_filters('the_content', $content);

        // Count H1 tags using regex, excluding editor elements
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