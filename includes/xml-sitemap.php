<?php
/**
 * XML Sitemap functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check XML sitemap status with caching
 */
function meta_description_boy_check_sitemap() {
    // Check if caching is enabled
    $enable_caching = get_option('meta_description_boy_enable_caching', 1);

    if ($enable_caching) {
        // Check if we have cached data
        $cache_key = 'meta_description_boy_sitemap_check';
        $cached_result = get_transient($cache_key);

        if ($cached_result !== false) {
            return $cached_result;
        }
    }

    $site_url = get_site_url();
    $possible_sitemaps = array(
        $site_url . '/sitemap_index.xml',
        $site_url . '/sitemap.xml',
        $site_url . '/wp-sitemap.xml'  // WordPress core sitemap
    );

    foreach ($possible_sitemaps as $sitemap_url) {
        $response = wp_remote_get($sitemap_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));

        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($response_code == 200 && !empty(trim($body)) && strpos($body, '<?xml') !== false) {
                $result = array(
                    'exists' => true,
                    'status' => 'Found',
                    'message' => 'XML sitemap available',
                    'class' => 'status-good',
                    'url' => $sitemap_url
                );

                // Cache successful result if caching is enabled
                if ($enable_caching) {
                    $cache_duration = get_option('meta_description_boy_cache_duration', 6);
                    set_transient($cache_key, $result, $cache_duration * HOUR_IN_SECONDS);
                }
                return $result;
            }
        }
    }

    $result = array(
        'exists' => false,
        'status' => 'Missing',
        'message' => 'No XML sitemap found',
        'class' => 'status-warning',
        'url' => null
    );

    // Cache negative result if caching is enabled (shorter cache for missing sitemaps)
    if ($enable_caching) {
        set_transient($cache_key, $result, HOUR_IN_SECONDS);
    }
    return $result;
}

/**
 * Clear sitemap cache
 */
function meta_description_boy_clear_sitemap_cache() {
    delete_transient('meta_description_boy_sitemap_check');
}

/**
 * Render XML sitemap section
 */
function meta_description_boy_render_xml_sitemap_section() {
    $sitemap_status = meta_description_boy_check_sitemap();
    ?>
    <div class="seo-stat-item <?php echo $sitemap_status['class']; ?>">
        <div class="stat-icon">🗺️</div>
        <div class="stat-content">
            <h4>XML Sitemap</h4>
            <div class="stat-status <?php echo $sitemap_status['class']; ?>">
                <?php echo $sitemap_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $sitemap_status['message']; ?>
            </div>
            <?php if ($sitemap_status['url']): ?>
            <div class="stat-action">
                <a href="<?php echo $sitemap_status['url']; ?>" target="_blank" class="button button-small">
                    View Sitemap
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}