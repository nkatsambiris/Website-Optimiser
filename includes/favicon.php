<?php
/**
 * Favicon (Site Icon) functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check favicon (site icon) status
 */
function meta_description_boy_check_favicon_status() {
    // Check if site icon is set using WordPress built-in function
    $has_site_icon = has_site_icon();
    $site_icon_url = get_site_icon_url();
    $site_icon_id = get_option('site_icon');

    // Get additional details if site icon exists
    $icon_details = array();
    if ($has_site_icon && $site_icon_id) {
        $attachment = get_post($site_icon_id);
        if ($attachment) {
            $icon_details = array(
                'filename' => basename(get_attached_file($site_icon_id)),
                'upload_date' => $attachment->post_date,
                'file_size' => size_format(filesize(get_attached_file($site_icon_id))),
                'dimensions' => wp_get_attachment_image_src($site_icon_id, 'full')
            );
        }
    }

    // Determine status
    if ($has_site_icon) {
        return array(
            'has_favicon' => true,
            'site_icon_url' => $site_icon_url,
            'site_icon_id' => $site_icon_id,
            'icon_details' => $icon_details,
            'status' => 'Favicon Configured',
            'message' => 'Site icon (favicon) is properly configured',
            'class' => 'status-good'
        );
    } else {
        return array(
            'has_favicon' => false,
            'site_icon_url' => '',
            'site_icon_id' => 0,
            'icon_details' => array(),
            'status' => 'Favicon Missing',
            'message' => 'No site icon (favicon) has been uploaded',
            'class' => 'status-error'
        );
    }
}

/**
 * Render favicon section
 */
function meta_description_boy_render_favicon_section() {
    $favicon_status = meta_description_boy_check_favicon_status();
    ?>
    <div class="seo-stat-item <?php echo $favicon_status['class']; ?>">
        <div class="stat-icon">🎨</div>
        <div class="stat-content">
            <h4>Favicon (Site Icon)</h4>
            <div class="stat-status <?php echo $favicon_status['class']; ?>">
                <?php echo $favicon_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $favicon_status['message']; ?>

                <?php if ($favicon_status['has_favicon']): ?>
                    <?php if (!empty($favicon_status['icon_details'])): ?>
                        <br><small><strong>Uploaded:</strong> <?php echo date('M j, Y', strtotime($favicon_status['icon_details']['upload_date'])); ?></small>
                    <?php endif; ?>

                    <?php if (!empty($favicon_status['site_icon_url'])): ?>
                        <br><br><div style="text-align: center; padding: 10px; background: #f9f9f9; border-radius: 4px; margin-top: 8px;">
                            <strong>Current Favicon:</strong><br>
                            <img src="<?php echo esc_url($favicon_status['site_icon_url']); ?>" alt="Site Icon" style="width: 32px; height: 32px; margin-top: 5px; border: 1px solid #ddd; border-radius: 2px;">
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <br><br><small><em>A favicon helps users identify your website in browser tabs, bookmarks, and search results.</em></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($favicon_status['has_favicon']): ?>
                    <a href="<?php echo admin_url('options-general.php#site_icon'); ?>" class="button button-small">
                        Change Favicon
                    </a>
                    <?php if (!empty($favicon_status['site_icon_url'])): ?>
                        <a href="<?php echo esc_url($favicon_status['site_icon_url']); ?>" class="button button-small" target="_blank" style="margin-left: 5px;">
                            View Full Size
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?php echo admin_url('options-general.php#site_icon'); ?>" class="button button-primary button-small">
                        Add Favicon
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}