<?php
/**
 * XML Sitemap functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check XML sitemap status
 */
function meta_description_boy_check_sitemap() {
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
                return array(
                    'exists' => true,
                    'status' => 'Found',
                    'message' => 'XML sitemap available',
                    'class' => 'status-good',
                    'url' => $sitemap_url
                );
            }
        }
    }

    return array(
        'exists' => false,
        'status' => 'Missing',
        'message' => 'No XML sitemap found',
        'class' => 'status-warning',
        'url' => null
    );
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