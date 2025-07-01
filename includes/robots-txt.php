<?php
/**
 * Robots.txt functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check robots.txt status
 */
function meta_description_boy_check_robots_txt() {
    $site_url = get_site_url();
    $robots_url = $site_url . '/robots.txt';

    // Check if robots.txt exists and is accessible
    $response = wp_remote_get($robots_url, array(
        'timeout' => 10,
        'sslverify' => false
    ));

    if (is_wp_error($response)) {
        return array(
            'exists' => false,
            'status' => 'Error',
            'message' => 'Could not check robots.txt',
            'class' => 'status-error'
        );
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($response_code == 200 && !empty(trim($body))) {
        // Check if it's not blocking everything
        $is_blocking_all = preg_match('/User-agent:\s*\*\s*Disallow:\s*\/\s*$/mi', $body);

        if ($is_blocking_all) {
            return array(
                'exists' => true,
                'status' => 'Blocking',
                'message' => 'Robots.txt blocks all crawlers',
                'class' => 'status-warning'
            );
        } else {
            return array(
                'exists' => true,
                'status' => 'Active',
                'message' => 'Robots.txt found and accessible',
                'class' => 'status-good'
            );
        }
    } else {
        return array(
            'exists' => false,
            'status' => 'Missing',
            'message' => 'No robots.txt file found',
            'class' => 'status-warning'
        );
    }
}

/**
 * Render robots.txt section
 */
function meta_description_boy_render_robots_txt_section() {
    $robots_txt_status = meta_description_boy_check_robots_txt();
    ?>
    <div class="seo-stat-item <?php echo $robots_txt_status['class']; ?>">
        <div class="stat-icon">🤖</div>
        <div class="stat-content">
            <h4>Robots.txt</h4>
            <div class="stat-status <?php echo $robots_txt_status['class']; ?>">
                <?php echo $robots_txt_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $robots_txt_status['message']; ?>
            </div>
            <div class="stat-action">
                <a href="<?php echo get_site_url(); ?>/robots.txt" target="_blank" class="button button-small">
                    View Robots.txt
                </a>
                <?php if (!$robots_txt_status['exists']): ?>
                                    <a href="<?php echo admin_url('admin.php?page=meta-description-boy'); ?>" class="button button-small" style="margin-left: 5px;">
                    Learn More
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}