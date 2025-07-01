<?php
/**
 * WP Smush Image Optimization functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check WP Smush status
 */
function meta_description_boy_check_wp_smush_status() {
    // Check if WP Smush plugin is installed
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $smush_free = 'wp-smushit/wp-smush.php';
    $smush_pro = 'wp-smush-pro/wp-smush.php';

    $is_installed = false;
    $is_active = false;
    $version = '';
    $is_pro = false;
    $stats = array();

    // Check if WP Smush is installed and active
    if (is_plugin_active($smush_pro)) {
        $is_installed = true;
        $is_active = true;
        $is_pro = true;
        if (file_exists(WP_PLUGIN_DIR . '/' . $smush_pro)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $smush_pro);
            $version = $plugin_data['Version'] ?? '';
        }
    } elseif (is_plugin_active($smush_free)) {
        $is_installed = true;
        $is_active = true;
        if (file_exists(WP_PLUGIN_DIR . '/' . $smush_free)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $smush_free);
            $version = $plugin_data['Version'] ?? '';
        }
    } elseif (file_exists(WP_PLUGIN_DIR . '/' . $smush_free) || file_exists(WP_PLUGIN_DIR . '/' . $smush_pro)) {
        $is_installed = true;
        $is_active = false;
    }

    // Get optimization stats if WP Smush is active
    if ($is_active) {
        $auto_smush_enabled = false;
        $total_images = 0;
        $optimized_images = 0;
        $savings_bytes = 0;
        $savings_percent = 0;

                // Check if WP Smush classes/functions are available
        if (class_exists('WP_Smush')) {
            // Try to get WP Smush settings
            $settings = get_option('wp-smush-settings', array());
            $auto_smush_enabled = isset($settings['auto']) ? $settings['auto'] : false;

            // Try to get stats from WP Smush using various methods
            $stats_data = array();

            // Method 1: Try the global stats option (most reliable)
            $global_stats = get_option('smush_global_stats', array());
            if (!empty($global_stats)) {
                $stats_data = $global_stats;
            }

            // Method 2: Try WP Smush core functions if available
            if (empty($stats_data) && function_exists('wp_smush_get_savings')) {
                $savings = wp_smush_get_savings();
                if (!empty($savings)) {
                    $stats_data = $savings;
                }
            }

            // Method 3: Try accessing WP Smush core class
            if (empty($stats_data) && class_exists('WP_Smush\Core\Core')) {
                try {
                    $core = WP_Smush\Core\Core::get_instance();
                    if (method_exists($core, 'get_stats')) {
                        $stats_data = $core->get_stats();
                    }
                } catch (Exception $e) {
                    // Silently continue if this fails
                }
            }

            // Method 4: Manual calculation as fallback
            if (empty($stats_data)) {
                // Get all attachments
                $attachments = get_posts(array(
                    'post_type' => 'attachment',
                    'post_mime_type' => 'image',
                    'post_status' => 'inherit',
                    'numberposts' => -1,
                    'fields' => 'ids'
                ));

                $total_images = count($attachments);
                $optimized_count = 0;
                $total_savings = 0;

                // Check how many are optimized
                foreach ($attachments as $attachment_id) {
                    $smush_data = get_post_meta($attachment_id, 'wp-smpro-smush-data', true);
                    if (!empty($smush_data) || get_post_meta($attachment_id, 'wp-smush-lossy', true)) {
                        $optimized_count++;
                        if (!empty($smush_data['stats']['bytes'])) {
                            $total_savings += $smush_data['stats']['bytes'];
                        }
                    }
                }

                $stats_data = array(
                    'total_images' => $total_images,
                    'optimized_images' => $optimized_count,
                    'bytes' => $total_savings,
                    'percent' => $total_images > 0 ? ($total_savings / ($total_images * 100000)) * 100 : 0 // Rough estimate
                );
            }

            // Extract stats safely
            if (!empty($stats_data)) {
                $total_images = isset($stats_data['total_images']) ? $stats_data['total_images'] :
                               (isset($stats_data['image_count']) ? $stats_data['image_count'] : 0);
                $optimized_images = isset($stats_data['optimized_images']) ? $stats_data['optimized_images'] :
                                   (isset($stats_data['count_images']) ? $stats_data['count_images'] : 0);
                $savings_bytes = isset($stats_data['bytes']) ? $stats_data['bytes'] :
                                (isset($stats_data['size_before']) && isset($stats_data['size_after']) ?
                                 ($stats_data['size_before'] - $stats_data['size_after']) : 0);
                $savings_percent = isset($stats_data['percent']) ? $stats_data['percent'] : 0;
            }
        }

        // Determine status
        $status_class = 'status-good';
        $status_text = 'Active';
        $message = 'WP Smush is optimizing your images';

        if ($is_pro) {
            $message .= ' (Pro)';
        }

        if ($optimized_images == 0 && $total_images > 0) {
            $status_class = 'status-warning';
            $status_text = 'Needs Optimization';
            $message = 'WP Smush is active but images need optimization';
        } elseif (!$auto_smush_enabled) {
            $status_class = 'status-warning';
            $status_text = 'Manual Mode';
            $message = 'WP Smush is active but auto-optimization is disabled';
        }

        $stats = array(
            'total_images' => $total_images,
            'optimized_images' => $optimized_images,
            'savings_bytes' => $savings_bytes,
            'savings_percent' => round($savings_percent, 1),
            'auto_smush_enabled' => $auto_smush_enabled
        );

        return array(
            'installed' => $is_installed,
            'active' => $is_active,
            'pro' => $is_pro,
            'version' => $version,
            'stats' => $stats,
            'status' => $status_text,
            'message' => $message,
            'class' => $status_class
        );
    } elseif ($is_installed) {
        return array(
            'installed' => $is_installed,
            'active' => $is_active,
            'pro' => false,
            'version' => '',
            'stats' => array(),
            'status' => 'Inactive',
            'message' => 'WP Smush is installed but not activated',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'installed' => $is_installed,
            'active' => $is_active,
            'pro' => false,
            'version' => '',
            'stats' => array(),
            'status' => 'Not Installed',
            'message' => 'WP Smush image optimization plugin is not installed',
            'class' => 'status-error'
        );
    }
}

/**
 * Format bytes into human readable format
 */
function meta_description_boy_format_bytes($size, $precision = 2) {
    if ($size == 0) return '0 B';

    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');

    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

/**
 * Render WP Smush section
 */
function meta_description_boy_render_wp_smush_section() {
    $smush_status = meta_description_boy_check_wp_smush_status();
    ?>
    <div class="seo-stat-item <?php echo $smush_status['class']; ?>">
        <div class="stat-icon">🖼️</div>
        <div class="stat-content">
            <h4>Image Optimization</h4>
            <div class="stat-status <?php echo $smush_status['class']; ?>">
                <?php echo $smush_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $smush_status['message']; ?>
                <?php if (!empty($smush_status['version'])): ?>
                    <br><small>Version: <?php echo $smush_status['version']; ?></small>
                <?php endif; ?>
                <?php if ($smush_status['active'] && !empty($smush_status['stats'])): ?>
                    <?php $stats = $smush_status['stats']; ?>
                    <br><small>
                        <?php if ($stats['optimized_images'] > 0): ?>
                            <?php echo $stats['optimized_images']; ?>/<?php echo $stats['total_images']; ?> images optimized
                            <?php if ($stats['savings_bytes'] > 0): ?>
                                <br>Saved: <?php echo meta_description_boy_format_bytes($stats['savings_bytes']); ?>
                                <?php if ($stats['savings_percent'] > 0): ?>
                                    (<?php echo $stats['savings_percent']; ?>%)
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo $stats['total_images']; ?> images found, none optimized yet
                        <?php endif; ?>
                    </small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($smush_status['active']): ?>
                    <a href="<?php echo admin_url('admin.php?page=smush'); ?>" class="button button-small">
                        Open WP Smush
                    </a>
                    <?php if (!empty($smush_status['stats']) && $smush_status['stats']['total_images'] > $smush_status['stats']['optimized_images']): ?>
                    <a href="<?php echo admin_url('admin.php?page=smush&view=bulk'); ?>" class="button button-small" style="margin-left: 5px;">
                        Bulk Optimize
                    </a>
                    <?php endif; ?>
                <?php elseif ($smush_status['installed']): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small">
                        Activate Plugin
                    </a>
                <?php else: ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=smush&tab=search&type=term'); ?>" class="button button-small">
                        Install WP Smush
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}