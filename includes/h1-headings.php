<?php
/**
 * H1 Headings functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

function meta_description_boy_get_h1_excluded_ids() {
    $excluded_ids = get_option('meta_description_boy_h1_excluded_ids', array());
    if (!is_array($excluded_ids)) {
        $excluded_ids = array();
    }

    $excluded_ids = array_values(array_unique(array_filter(array_map('intval', $excluded_ids))));

    return $excluded_ids;
}

/**
 * Get saved detailed H1 results if available
 */
function meta_description_boy_get_saved_h1_results() {
    $stored = get_option('meta_description_boy_h1_detailed_results', array());

    if (!is_array($stored) || empty($stored['results']) || !is_array($stored['results'])) {
        return array(
            'generated_at' => null,
            'results' => array()
        );
    }

    return array(
        'generated_at' => isset($stored['generated_at']) ? intval($stored['generated_at']) : null,
        'results' => $stored['results']
    );
}

/**
 * Get H1 heading statistics with caching (dashboard display version)
 */
function meta_description_boy_get_h1_stats() {
    // Check if we have stored data in database
    $stored_stats = get_option('meta_description_boy_h1_stats_data', false);

    // Backward compatibility: migrate from transient to database option
    if ($stored_stats === false) {
        $legacy_stats = get_transient('meta_description_boy_h1_stats');
        if ($legacy_stats !== false && !isset($legacy_stats['needs_refresh'])) {
            // Migrate valid legacy data to database
            update_option('meta_description_boy_h1_stats_data', $legacy_stats);
            delete_transient('meta_description_boy_h1_stats');
            $stored_stats = $legacy_stats;
        }
    }

    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('H1 Stats DB Check - Has Data: ' . ($stored_stats !== false ? 'YES' : 'NO'));
        if ($stored_stats !== false) {
            error_log('H1 Stats Stored Data: ' . print_r($stored_stats, true));
        }
    }

    if ($stored_stats !== false) {
        return $stored_stats;
    }

    // If no cached data, return a "needs refresh" state instead of running expensive analysis
    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));
    $all_posts = get_posts(array(
        'post_type' => $selected_post_types,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids',
    ));

    $excluded_ids = meta_description_boy_get_h1_excluded_ids();
    if (!empty($excluded_ids)) {
        $all_posts = array_diff($all_posts, $excluded_ids);
    }

    $total = count($all_posts);

    // Return a "needs refresh" state when no cached data is available
    $stats = array(
        'total' => $total,
        'correct' => 0,
        'no_h1' => 0,
        'multiple_h1' => 0,
        'issues' => 0,
        'percentage' => 0,
        'needs_refresh' => true
    );

    return $stats;
}

/**
 * Perform actual H1 heading analysis (only called on refresh)
 */
function meta_description_boy_perform_h1_analysis() {
    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));

    // Get all published posts/pages using get_posts for reliable results
    $all_posts = get_posts(array(
        'post_type' => $selected_post_types,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids',
    ));

    $excluded_ids = meta_description_boy_get_h1_excluded_ids();
    if (!empty($excluded_ids)) {
        $all_posts = array_diff($all_posts, $excluded_ids);
    }

    $total = count($all_posts);

    if ($total == 0) {
        $stats = array(
            'total' => 0,
            'correct' => 0,
            'no_h1' => 0,
            'multiple_h1' => 0,
            'issues' => 0,
            'percentage' => 0
        );
        // Store the results in database
        update_option('meta_description_boy_h1_stats_data', $stats);
        return $stats;
    }

    $correct = 0;
    $no_h1 = 0;
    $multiple_h1 = 0;

    // Check each post/page for H1 tags using frontend analysis (more accurate)
    foreach ($all_posts as $post_id) {
        $h1_count = meta_description_boy_analyze_h1_frontend($post_id);

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

    $stats = array(
        'total' => $total,
        'correct' => $correct,
        'no_h1' => $no_h1,
        'multiple_h1' => $multiple_h1,
        'issues' => $issues,
        'percentage' => $percentage
        );

    // Store the results in database
    $stored = update_option('meta_description_boy_h1_stats_data', $stats);

    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('H1 Stats DB Storage - Success: ' . ($stored ? 'YES' : 'NO'));
        error_log('H1 Stats Being Stored: ' . print_r($stats, true));
    }

    return $stats;
}

/**
 * Analyze H1 count from post content (lightweight version)
 */
function meta_description_boy_analyze_h1_from_content($post_id) {
    // Check if caching is enabled
    $enable_caching = get_option('meta_description_boy_enable_caching', 1);

    if ($enable_caching) {
        // First check if we have a cached result for this specific post
        $post_cache_key = 'meta_description_boy_h1_count_' . $post_id;
        $cached_count = get_transient($post_cache_key);

        if ($cached_count !== false) {
            return $cached_count;
        }
    }

    // Get post content and apply filters (but don't make HTTP requests)
    $content = get_post_field('post_content', $post_id);
    $content = apply_filters('the_content', $content);

    // For themes that add H1 via template, we can make a reasonable assumption
    // Most WordPress themes add exactly one H1 for the post title
    $post_type = get_post_type($post_id);
    $has_title = !empty(get_the_title($post_id));

    // Count H1 tags in content
    preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $content, $h1_matches);

    $content_h1_count = 0;
    // Filter out Gutenberg editor elements and empty H1s
    foreach ($h1_matches[0] as $index => $h1_tag) {
        // Skip if it's a Gutenberg editor element
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

        $content_h1_count++;
    }

    // Assume most themes add one H1 for the title if the post has a title
    // This is a reasonable assumption for 95% of WordPress sites
    $estimated_h1_count = $content_h1_count;
    if ($has_title && in_array($post_type, array('post', 'page'))) {
        $estimated_h1_count += 1; // Most themes add H1 for post title
    }

    // If there are H1s in content AND a title, that's likely multiple H1s
    if ($content_h1_count > 0 && $has_title) {
        $estimated_h1_count = $content_h1_count + 1; // Title H1 + content H1s
    }

    // Cache individual post result if caching is enabled
    if ($enable_caching) {
        set_transient($post_cache_key, $estimated_h1_count, HOUR_IN_SECONDS);
    }

    return $estimated_h1_count;
}

/**
 * Analyze H1 count from frontend (accurate version)
 */
function meta_description_boy_analyze_h1_frontend($post_id, $return_details = false) {
    // Check if caching is enabled (but skip cache if we need details)
    $enable_caching = get_option('meta_description_boy_enable_caching', 1);

    if ($enable_caching && !$return_details) {
        // First check if we have a cached result for this specific post
        $post_cache_key = 'meta_description_boy_h1_frontend_' . $post_id;
        $cached_count = get_transient($post_cache_key);

        if ($cached_count !== false) {
            return $cached_count;
        }
    }

    // Get the actual rendered content by doing a frontend request
    $post_url = get_permalink($post_id);
    
    // Prepare request arguments with authentication bypass for password-protected sites
    $request_args = array(
        'timeout' => 30,
        'user-agent' => 'Mozilla/5.0 (compatible; WordPress H1 Checker)',
        'headers' => array(
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ),
        'sslverify' => false, // For local dev environments
        'cookies' => array() // Will be populated below
    );
    
    // Try to pass authentication cookies for password-protected sites
    foreach ($_COOKIE as $name => $value) {
        // Pass WordPress auth cookies and other relevant cookies
        if (strpos($name, 'wordpress') === 0 || strpos($name, 'wp-') === 0) {
            $request_args['cookies'][] = new WP_Http_Cookie(array(
                'name' => $name,
                'value' => $value
            ));
        }
    }
    
    // Add HTTP Basic Auth credentials if available in wp-config
    if (defined('WP_HTTP_BLOCK_EXTERNAL') && !WP_HTTP_BLOCK_EXTERNAL) {
        // Site allows external requests, good to go
    }
    
    // Check if HTTP authentication is needed (common on staging sites)
    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
        $request_args['headers']['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']);
    }
    
    // Check for stored HTTP authentication credentials (for password-protected sites)
    $http_auth_user = get_option('meta_description_boy_http_auth_user', '');
    $http_auth_pass = get_option('meta_description_boy_http_auth_pass', '');
    if (!empty($http_auth_user) && !empty($http_auth_pass)) {
        $request_args['headers']['Authorization'] = 'Basic ' . base64_encode($http_auth_user . ':' . $http_auth_pass);
    }
    
    // Check for wp-config.php defined credentials
    if (defined('WP_H1_CHECKER_AUTH_USER') && defined('WP_H1_CHECKER_AUTH_PASS')) {
        $request_args['headers']['Authorization'] = 'Basic ' . base64_encode(WP_H1_CHECKER_AUTH_USER . ':' . WP_H1_CHECKER_AUTH_PASS);
    }
    
    $response = wp_remote_get($post_url, $request_args);

    $response_code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
    
    if (is_wp_error($response) || $response_code !== 200) {
        // Log the fallback for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . $response_code;
            error_log('H1 Frontend Analysis Fallback for Post ID ' . $post_id . ': ' . $error_msg . ' - URL: ' . $post_url);
        }
        
        // If returning details, try content-based analysis and note the issue
        if ($return_details) {
            $content_based_count = meta_description_boy_analyze_h1_from_content($post_id);
            $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . $response_code;
            
            // Provide helpful error message for common issues
            $help_text = '';
            if ($response_code == 401) {
                $help_text = 'Site is password protected. Add HTTP authentication credentials to wp-config.php or disable password protection temporarily for accurate H1 analysis.';
            } elseif ($response_code == 403) {
                $help_text = 'Access forbidden. Check firewall or security plugin settings.';
            } elseif ($response_code == 404) {
                $help_text = 'Page not found. The post may be unpublished or URL structure changed.';
            }
            
            return array(
                'h1_count' => $content_based_count,
                'h1_tags' => array(),
                'error' => 'Frontend request failed - using fallback analysis',
                'error_details' => $error_msg,
                'help_text' => $help_text,
                'fallback_used' => true,
                'post_url' => $post_url,
                'response_code' => $response_code
            );
        }
        
        // Fallback to content field method if frontend request fails
        return meta_description_boy_analyze_h1_from_content($post_id);
    }

    // Use the actual rendered HTML
    $content = wp_remote_retrieve_body($response);

    // Count H1 tags using regex, excluding editor elements
    preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $content, $h1_matches);

    $h1_count = 0;
    $h1_details = array();
    
    // Filter out Gutenberg editor elements and empty H1s
    foreach ($h1_matches[0] as $index => $h1_tag) {
        $h1_inner_html = $h1_matches[1][$index];
        $h1_text_content = trim(strip_tags($h1_inner_html));
        
        // Check if it's a Gutenberg editor element
        $is_editor = (
            preg_match('/contenteditable=["\'"]true["\'"]/', $h1_tag) ||
            preg_match('/class=["\'][^"\']*(?:block-editor|editor-post-title)[^"\']*["\']/', $h1_tag) ||
            preg_match('/role=["\'"]textbox["\']/', $h1_tag)
        );
        
        $is_empty = empty($h1_text_content);
        $is_valid = !$is_editor && !$is_empty;
        
        // Store details if requested
        if ($return_details) {
            $h1_details[] = array(
                'full_tag' => $h1_tag,
                'inner_html' => $h1_inner_html,
                'text_content' => $h1_text_content,
                'is_editor_element' => $is_editor,
                'is_empty' => $is_empty,
                'is_valid' => $is_valid
            );
        }

        // Skip if it's a Gutenberg editor element
        if ($is_editor) {
            continue;
        }

        // Skip if H1 content is empty or just whitespace
        if ($is_empty) {
            continue;
        }

        $h1_count++;
    }

    // Cache individual post result if caching is enabled (only cache the count)
    if ($enable_caching && !$return_details) {
        set_transient($post_cache_key, $h1_count, HOUR_IN_SECONDS);
    }

    // Return details if requested
    if ($return_details) {
        return array(
            'h1_count' => $h1_count,
            'h1_tags' => $h1_details,
            'total_h1_found' => count($h1_matches[0]),
            'post_url' => $post_url,
            'response_code' => wp_remote_retrieve_response_code($response)
        );
    }

    return $h1_count;
}

/**
 * Clear H1 stats cache
 */
function meta_description_boy_clear_h1_cache($post_id = null) {
    // Clear the main stats data
    delete_option('meta_description_boy_h1_stats_data');
    delete_option('meta_description_boy_h1_detailed_results');

    // If specific post ID provided, clear its individual cache (these can stay as transients for performance)
    if ($post_id) {
        delete_transient('meta_description_boy_h1_count_' . $post_id);
        delete_transient('meta_description_boy_h1_frontend_' . $post_id);
    }
}

/**
 * Force clear all H1 cache (for when settings change)
 */
function meta_description_boy_force_clear_h1_cache() {
    delete_option('meta_description_boy_h1_stats_data');
    delete_option('meta_description_boy_h1_detailed_results');

    // Clear individual post caches for selected post types
    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));
    $all_posts = get_posts(array(
        'post_type' => $selected_post_types,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids',
    ));

    foreach ($all_posts as $post_id) {
        delete_transient('meta_description_boy_h1_count_' . $post_id);
        delete_transient('meta_description_boy_h1_frontend_' . $post_id);
    }
}

/**
 * Render H1 headings section
 */
function meta_description_boy_render_h1_headings_section() {
    $h1_stats = meta_description_boy_get_h1_stats();
    $saved_h1_results = meta_description_boy_get_saved_h1_results();
    $has_saved_results = !empty($saved_h1_results['results']);

    // Check if analysis needs to be run
    if (isset($h1_stats['needs_refresh']) && $h1_stats['needs_refresh']) {
        $status_class = 'status-warning';
        $status_text = 'Click Refresh to Analyze';
    } else {
        // Determine status class and text based on percentage
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
    }
    ?>
    <div class="seo-stat-item <?php echo $status_class; ?>">
        <div class="stat-icon">📰</div>
        <div class="stat-content">
            <h4>H1 Headings</h4>
            <div class="stat-status <?php echo $status_class; ?>">
                <?php echo $status_text; ?>
            </div>
            <?php if (isset($h1_stats['needs_refresh']) && $h1_stats['needs_refresh']): ?>
            <div class="stat-number">
                <?php echo $h1_stats['total']; ?> pages found
            </div>
            <div class="stat-label">
                Analysis needed
            </div>
            <div class="stat-action">
                <button id="refresh-h1-analysis" class="button button-small" style="margin-bottom: 5px;">
                    🔄 Run Analysis
                </button>
            </div>
            <?php else: ?>
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
                <?php if ($has_saved_results): ?>
                    <button id="view-h1-analysis" class="button button-small" style="margin-left: 2px;">
                        📄 View Last Analysis
                    </button>
                <?php endif; ?>
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

    $excluded_ids = meta_description_boy_get_h1_excluded_ids();
    if (!empty($excluded_ids)) {
        $all_posts = array_diff($all_posts, $excluded_ids);
    }

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
 * Clear the H1 analysis cache when posts are updated (compatibility function)
 */
function meta_description_boy_clear_h1_cache_legacy($post_id) {
    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));
    if (in_array(get_post_type($post_id), $selected_post_types)) {
        // Clear both old transient and new database option for compatibility
        delete_transient('meta_description_boy_h1_analysis');
        delete_option('meta_description_boy_h1_stats_data');
        meta_description_boy_clear_h1_cache($post_id);
    }
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

    // Perform the actual analysis (this will cache the results)
    $h1_stats = meta_description_boy_perform_h1_analysis();

    // Also get detailed post-by-post results for the modal
    $detailed_results = meta_description_boy_get_detailed_h1_results();
    update_option('meta_description_boy_h1_detailed_results', array(
        'generated_at' => time(),
        'results' => $detailed_results
    ));

    wp_die(json_encode(array(
        'success' => true,
        'message' => 'H1 analysis refreshed successfully',
        'stats' => $h1_stats,
        'detailed_results' => $detailed_results
    )));
}
add_action('wp_ajax_meta_description_boy_refresh_h1_analysis', 'meta_description_boy_refresh_h1_analysis');

/**
 * Get saved H1 analysis results
 */
function meta_description_boy_get_saved_h1_analysis() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $saved = meta_description_boy_get_saved_h1_results();

    wp_die(json_encode(array(
        'success' => true,
        'data' => $saved
    )));
}
add_action('wp_ajax_meta_description_boy_get_saved_h1_analysis', 'meta_description_boy_get_saved_h1_analysis');

/**
 * Get detailed H1 results for modal display
 */
function meta_description_boy_get_detailed_h1_results() {
    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));

    // Get all published posts/pages
    $all_posts = get_posts(array(
        'post_type' => $selected_post_types,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids',
    ));

    $excluded_ids = meta_description_boy_get_h1_excluded_ids();
    $excluded_lookup = array_flip($excluded_ids);

    $detailed_results = array();

    // Check each post/page for H1 tags using the same method as the bulk analysis
    foreach ($all_posts as $post_id) {
        $is_excluded = isset($excluded_lookup[$post_id]);
        if ($is_excluded) {
            $h1_count = null;
            $status = 'Excluded';
            $status_class = 'h1-status-excluded';
        } else {
            $h1_count = meta_description_boy_analyze_h1_frontend($post_id);

            // Determine status based on count
            if ($h1_count === 0) {
                $status = 'No H1';
                $status_class = 'h1-status-error';
            } elseif ($h1_count === 1) {
                $status = 'Correct';
                $status_class = 'h1-status-success';
            } else {
                $status = 'Multiple H1';
                $status_class = 'h1-status-error';
            }
        }

        $detailed_results[] = array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'edit_url' => get_edit_post_link($post_id),
            'h1_count' => $h1_count,
            'status' => $status,
            'status_class' => $status_class,
            'is_excluded' => $is_excluded
        );
    }

    return $detailed_results;
}

/**
 * Get posts for H1 analysis
 */
function meta_description_boy_get_posts_for_h1_analysis() {

    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));
    // Get all published posts/pages
    $all_posts = get_posts(array(
        'post_type' => $selected_post_types,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids',
    ));

    $excluded_ids = meta_description_boy_get_h1_excluded_ids();
    if (!empty($excluded_ids)) {
        $all_posts = array_diff($all_posts, $excluded_ids);
    }

    $posts_data = array();

    foreach ($all_posts as $post_id) {
        $posts_data[] = array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'edit_url' => get_edit_post_link($post_id)
        );
    }

    wp_die(json_encode(array(
        'success' => true,
        'data' => array(
            'posts' => $posts_data
        )
    )));
}
add_action('wp_ajax_meta_description_boy_get_posts_for_h1_analysis', 'meta_description_boy_get_posts_for_h1_analysis');

/**
 * Update excluded posts for H1 analysis
 */
function meta_description_boy_update_h1_exclusions() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $post_ids = isset($_POST['post_ids']) ? (array) $_POST['post_ids'] : array();
    $post_ids = array_values(array_unique(array_filter(array_map('intval', $post_ids))));

    update_option('meta_description_boy_h1_excluded_ids', $post_ids);

    delete_option('meta_description_boy_h1_stats_data');
    delete_transient('meta_description_boy_h1_analysis');

    wp_die(json_encode(array(
        'success' => true,
        'excluded_ids' => $post_ids
    )));
}
add_action('wp_ajax_meta_description_boy_update_h1_exclusions', 'meta_description_boy_update_h1_exclusions');

/**
 * Analyze H1 for a single post
 */
function meta_description_boy_analyze_single_post_h1() {

    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $post_id = intval($_POST['post_id']);

    if (!$post_id) {
        wp_die(json_encode(array('success' => false, 'message' => 'Invalid post ID')));
    }

    // Use the frontend function to analyze H1 count
    $h1_count = meta_description_boy_analyze_h1_frontend($post_id);

    wp_die(json_encode(array(
        'success' => true,
        'data' => array(
            'h1_count' => $h1_count,
            'post_id' => $post_id
        )
    )));
}
add_action('wp_ajax_meta_description_boy_analyze_single_post_h1', 'meta_description_boy_analyze_single_post_h1');

/**
 * Get detailed H1 debug information for a single post
 */
function meta_description_boy_debug_post_h1() {
    // Check nonce and permissions
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $post_id = intval($_POST['post_id']);

    if (!$post_id) {
        wp_die(json_encode(array('success' => false, 'message' => 'Invalid post ID')));
    }

    // Get detailed H1 analysis
    $h1_details = meta_description_boy_analyze_h1_frontend($post_id, true);

    // Add post information
    $debug_data = array(
        'post_id' => $post_id,
        'post_title' => get_the_title($post_id),
        'post_url' => get_permalink($post_id),
        'edit_url' => get_edit_post_link($post_id),
        'h1_analysis' => $h1_details
    );

    wp_die(json_encode(array(
        'success' => true,
        'data' => $debug_data
    )));
}
add_action('wp_ajax_meta_description_boy_debug_post_h1', 'meta_description_boy_debug_post_h1');

// Note: Cache is now only cleared when user clicks refresh button
// This prevents slow dashboard loads

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

    // Find all H1 tags in content
    preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $filtered_content, $h1_matches);

    $debug_info = array(
        'post_id' => $post_id,
        'post_title' => get_the_title($post_id),
        'post_url' => get_permalink($post_id),
        'raw_content_length' => strlen($content),
        'filtered_content_length' => strlen($filtered_content),
        'content_h1_matches_count' => count($h1_matches[0]),
        'content_h1_tags_found' => array(),
        'content_valid_h1_count' => 0,
        'frontend_h1_count' => meta_description_boy_analyze_h1_frontend($post_id),
        'old_method_h1_count' => meta_description_boy_analyze_h1_from_content($post_id)
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
            $debug_info['content_valid_h1_count']++;
        }

        $debug_info['content_h1_tags_found'][] = array(
            'tag' => $h1_tag,
            'content' => $h1_content,
            'is_editor_element' => $is_editor_element,
            'is_empty' => $is_empty,
            'is_valid' => $is_valid
        );
    }

    return $debug_info;
}
