<?php
/**
 * llms.txt functionality for Website Optimiser.
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Check llms.txt status with caching.
 *
 * @return array
 */
function meta_description_boy_check_llms_txt() {
    $enable_caching = get_option('meta_description_boy_enable_caching', 1);
    $cache_key = 'meta_description_boy_llms_check';

    if ($enable_caching) {
        $cached_result = get_transient($cache_key);

        if (is_array($cached_result) && !empty($cached_result['exists'])) {
            return $cached_result;
        }
    }

    $site_url = home_url();
    $llms_urls = array(
        $site_url . '/llms.txt',
        $site_url . '/llm.txt',
    );
    $had_error = false;

    foreach ($llms_urls as $llms_url) {
        $response = wp_remote_get($llms_url, array(
            'timeout' => 10,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            $had_error = true;
            continue;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code == 200 && !empty(trim($body))) {
            $path = wp_parse_url($llms_url, PHP_URL_PATH);
            $path = $path ? $path : '/llms.txt';
            $result = array(
                'exists' => true,
                'status' => 'Found',
                'message' => 'LLM text file found at ' . $path,
                'class' => 'status-good',
                'url' => $llms_url,
                'path' => $path,
            );

            if ($enable_caching) {
                $cache_duration = get_option('meta_description_boy_cache_duration', 6);
                set_transient($cache_key, $result, $cache_duration * HOUR_IN_SECONDS);
            }

            return $result;
        }
    }

    if ($had_error) {
        $result = array(
            'exists' => false,
            'status' => 'Error',
            'message' => 'Could not check llms.txt',
            'class' => 'status-error',
            'url' => null,
            'path' => null,
        );

        return $result;
    }

    $result = array(
        'exists' => false,
        'status' => 'Missing',
        'message' => 'No llms.txt file found',
        'class' => 'status-warning',
        'url' => null,
        'path' => null,
    );

    return $result;
}

/**
 * Render llms.txt section.
 */
function meta_description_boy_render_llms_txt_section() {
    $llms_txt_status = meta_description_boy_check_llms_txt();
    ?>
    <div class="seo-stat-item <?php echo esc_attr($llms_txt_status['class']); ?>">
        <div class="stat-icon">AI</div>
        <div class="stat-content">
            <h4>LLMs.txt</h4>
            <div class="stat-status <?php echo esc_attr($llms_txt_status['class']); ?>">
                <?php echo esc_html($llms_txt_status['status']); ?>
            </div>
            <div class="stat-label">
                <?php echo esc_html($llms_txt_status['message']); ?>
            </div>
            <div class="stat-action">
                <?php if ($llms_txt_status['url']): ?>
                    <a href="<?php echo esc_url($llms_txt_status['url']); ?>" target="_blank" class="button button-small">
                        View LLMs.txt
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank" class="button button-small">
                        Check LLMs.txt
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Clear llms.txt cache.
 */
function meta_description_boy_clear_llms_cache() {
    delete_transient('meta_description_boy_llms_check');
}
