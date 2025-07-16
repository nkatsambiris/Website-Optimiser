<?php
/**
 * Media Videos Check Component
 * Checks if there are any videos stored in the media library (over 5MB)
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Check media library for videos (only count videos over 5MB as problematic)
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
        'numberposts' => -1
    ));

    $large_video_count = 0;
    $total_video_count = count($video_attachments);
    $size_limit = 5 * 1024 * 1024; // 5MB in bytes

    // Count only videos over 5MB
    foreach ($video_attachments as $video) {
        $file_path = get_attached_file($video->ID);

        if ($file_path && file_exists($file_path)) {
            $file_size = filesize($file_path);
            if ($file_size > $size_limit) {
                $large_video_count++;
            }
        }
    }

    if ($large_video_count > 0) {
        return array(
            'class' => 'status-error',
            'status' => 'Large videos found in media library',
            'count' => $large_video_count,
            'total_count' => $total_video_count,
            'message' => $large_video_count . ' video' . ($large_video_count > 1 ? 's' : '') . ' over 5MB found in media library',
            'exists' => false // Using false to indicate this is a "fail" condition
        );
    } else {
        $message = $total_video_count > 0
            ? 'All videos are under 5MB'
            : 'No videos in media library';

        return array(
            'class' => 'status-good',
            'status' => $message,
            'count' => 0,
            'total_count' => $total_video_count,
            'message' => $message,
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
    $large_videos = array();
    $small_videos = array();
    $size_limit = 5 * 1024 * 1024; // 5MB in bytes

    foreach ($video_attachments as $video) {
        $file_path = get_attached_file($video->ID);
        $file_size = 0;

        if ($file_path && file_exists($file_path)) {
            $file_size = filesize($file_path);
            $total_size += $file_size;
        }

        $video_detail = array(
            'id' => $video->ID,
            'title' => $video->post_title,
            'filename' => basename($file_path),
            'mime_type' => $video->post_mime_type,
            'size' => $file_size,
            'date' => $video->post_date
        );

        $video_details[] = $video_detail;

        // Separate large and small videos
        if ($file_size > $size_limit) {
            $large_videos[] = $video_detail;
        } else {
            $small_videos[] = $video_detail;
        }
    }

    return array(
        'total_videos' => $video_count,
        'large_videos_count' => count($large_videos),
        'small_videos_count' => count($small_videos),
        'total_size' => $total_size,
        'total_size_formatted' => size_format($total_size),
        'videos' => $video_details,
        'large_videos' => $large_videos,
        'small_videos' => $small_videos,
        'status' => count($large_videos) > 0 ? 'error' : 'good'
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

                    <?php if ($stats['large_videos_count'] > 0): ?>
                        <br><small><strong>Videos over 5MB:</strong> <?php echo $stats['large_videos_count']; ?></small>
                    <?php endif; ?>

                    <?php if ($stats['small_videos_count'] > 0): ?>
                        <br><small><strong>Videos under 5MB:</strong> <?php echo $stats['small_videos_count']; ?> (acceptable)</small>
                    <?php endif; ?>

                    <?php
                    // Show problematic videos if any
                    if ($stats['large_videos_count'] > 0 && $stats['large_videos_count'] <= 5): ?>
                        <br><br><small><strong>Large videos found (over 5MB):</strong></small>
                        <?php foreach ($stats['large_videos'] as $video): ?>
                            <br><small>• <strong><?php echo esc_html($video['title'] ?: $video['filename']); ?></strong></small>
                            <br><small>&nbsp;&nbsp;Size: <?php echo size_format($video['size']); ?> | Added: <?php echo date('M j, Y', strtotime($video['date'])); ?></small>
                        <?php endforeach; ?>
                    <?php elseif ($stats['large_videos_count'] > 5): ?>
                        <br><br><small><em>Too many large videos to list individually. Use the button below to view all videos.</em></small>
                    <?php endif; ?>

                <?php else: ?>
                    <br><br><small><strong>💡 Best Practice:</strong> Continue using external video platforms for hosting to keep your site fast and reduce hosting costs.</small>
                <?php endif; ?>

                <?php if ($stats['total_videos'] > 0): ?>
                    <br><br><small><strong>💡 Note:</strong> Videos under 5MB are considered acceptable for performance.</small>
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