<?php
/**
 * Media Videos Check Component
 * Checks if there are any videos stored in the media library
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Check media library for videos
 */
function meta_description_boy_check_media_videos_status() {
    // Get video attachments from media library
    $video_attachments = get_posts(array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'post_mime_type' => array(
            'video/mp4',
            'video/quicktime',
            'video/avi',
            'video/wmv',
            'video/mov',
            'video/flv',
            'video/webm',
            'video/mkv',
            'video/3gp',
            'video/ogg'
        ),
        'numberposts' => -1,
        'fields' => 'ids'
    ));

    $video_count = count($video_attachments);

    if ($video_count > 0) {
        return array(
            'class' => 'status-error',
            'status' => 'Videos found in media library',
            'count' => $video_count,
            'message' => $video_count . ' video' . ($video_count > 1 ? 's' : '') . ' found in media library',
            'exists' => false // Using false to indicate this is a "fail" condition
        );
    } else {
        return array(
            'class' => 'status-good',
            'status' => 'No videos in media library',
            'count' => 0,
            'message' => 'No videos found in media library',
            'exists' => true // Using true to indicate this is a "pass" condition
        );
    }
}

/**
 * Get detailed video statistics
 */
function meta_description_boy_get_media_videos_stats() {
    // Get all video attachments with additional details
    $video_attachments = get_posts(array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'post_mime_type' => array(
            'video/mp4',
            'video/quicktime',
            'video/avi',
            'video/wmv',
            'video/mov',
            'video/flv',
            'video/webm',
            'video/mkv',
            'video/3gp',
            'video/ogg'
        ),
        'numberposts' => -1
    ));

    $video_count = count($video_attachments);
    $total_size = 0;
    $video_details = array();

    foreach ($video_attachments as $video) {
        $file_path = get_attached_file($video->ID);
        $file_size = 0;

        if ($file_path && file_exists($file_path)) {
            $file_size = filesize($file_path);
            $total_size += $file_size;
        }

        $video_details[] = array(
            'id' => $video->ID,
            'title' => $video->post_title,
            'filename' => basename($file_path),
            'mime_type' => $video->post_mime_type,
            'size' => $file_size,
            'date' => $video->post_date
        );
    }

    return array(
        'total_videos' => $video_count,
        'total_size' => $total_size,
        'total_size_formatted' => size_format($total_size),
        'videos' => $video_details,
        'status' => $video_count > 0 ? 'error' : 'good'
    );
}

/**
 * Render the media videos section
 */
function meta_description_boy_render_media_videos_section() {
    $status = meta_description_boy_check_media_videos_status();
    $stats = meta_description_boy_get_media_videos_stats();

    ?>
    <div class="seo-stat-item <?php echo $status['class']; ?>">
        <div class="stat-icon">🎬</div>
        <div class="stat-content">
            <h4>Media Library Videos</h4>
            <div class="stat-status <?php echo $status['class']; ?>">
                <?php echo $status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $status['message']; ?>

                <?php if ($stats['total_videos'] > 0): ?>
                    <br><small><strong>Total videos:</strong> <?php echo $stats['total_videos']; ?></small>
                    <br><small><strong>Total size:</strong> <?php echo $stats['total_size_formatted']; ?></small>

                    <?php if (count($stats['videos']) <= 5): // Only show list if not too many ?>
                        <br><br><small><strong>Videos found:</strong></small>
                        <?php foreach ($stats['videos'] as $video): ?>
                            <br><small>• <strong><?php echo esc_html($video['title'] ?: $video['filename']); ?></strong></small>
                            <br><small>&nbsp;&nbsp;Size: <?php echo size_format($video['size']); ?> | Added: <?php echo date('M j, Y', strtotime($video['date'])); ?></small>
                        <?php endforeach; ?>
                    <?php elseif (count($stats['videos']) > 5): ?>
                        <br><br><small><em>Too many videos to list individually. Use the button below to view all videos.</em></small>
                    <?php endif; ?>

                <?php else: ?>
                    <br><br><small><strong>💡 Best Practice:</strong> Continue using external video platforms for hosting to keep your site fast and reduce hosting costs.</small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <a href="<?php echo admin_url('upload.php?post_mime_type=video'); ?>" class="button button-small" target="_blank">
                    View Videos in Media Library
                </a>
            </div>
        </div>
    </div>
    <?php
}