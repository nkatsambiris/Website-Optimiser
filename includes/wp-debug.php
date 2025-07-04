<?php
/**
 * WP Debug functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check WP Debug status
 */
function meta_description_boy_check_wp_debug_status() {
    // Check various debug constants
    $wp_debug = defined('WP_DEBUG') ? WP_DEBUG : false;
    $wp_debug_log = defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false;
    $wp_debug_display = defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : null;
    $script_debug = defined('SCRIPT_DEBUG') ? SCRIPT_DEBUG : false;

    // Determine if any debug mode is enabled
    $debug_enabled = $wp_debug || $wp_debug_log || $wp_debug_display || $script_debug;

    // Get environment type if available (WordPress 5.5+)
    $environment_type = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'unknown';

    // Build detailed status information
    $debug_details = array(
        'WP_DEBUG' => $wp_debug ? 'Enabled' : 'Disabled',
        'WP_DEBUG_LOG' => $wp_debug_log ? 'Enabled' : 'Disabled',
        'WP_DEBUG_DISPLAY' => $wp_debug_display === null ? 'Not Set' : ($wp_debug_display ? 'Enabled' : 'Disabled'),
        'SCRIPT_DEBUG' => $script_debug ? 'Enabled' : 'Disabled'
    );

    // Determine status and message
    if (!$debug_enabled) {
        return array(
            'debug_disabled' => true,
            'wp_debug' => $wp_debug,
            'wp_debug_log' => $wp_debug_log,
            'wp_debug_display' => $wp_debug_display,
            'script_debug' => $script_debug,
            'environment_type' => $environment_type,
            'debug_details' => $debug_details,
            'status' => 'Debug Mode Disabled',
            'message' => 'WordPress debug mode is properly disabled for production',
            'class' => 'status-good'
        );
    } elseif ($environment_type === 'development' || $environment_type === 'staging') {
        return array(
            'debug_disabled' => false,
            'wp_debug' => $wp_debug,
            'wp_debug_log' => $wp_debug_log,
            'wp_debug_display' => $wp_debug_display,
            'script_debug' => $script_debug,
            'environment_type' => $environment_type,
            'debug_details' => $debug_details,
            'status' => 'Debug Mode Enabled',
            'message' => 'Debug mode is enabled (acceptable for ' . $environment_type . ' environment)',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'debug_disabled' => false,
            'wp_debug' => $wp_debug,
            'wp_debug_log' => $wp_debug_log,
            'wp_debug_display' => $wp_debug_display,
            'script_debug' => $script_debug,
            'environment_type' => $environment_type,
            'debug_details' => $debug_details,
            'status' => 'Debug Mode Enabled',
            'message' => 'WordPress debug mode should be disabled on production sites',
            'class' => 'status-error'
        );
    }
}

/**
 * Render WP Debug section
 */
function meta_description_boy_render_wp_debug_section() {
    $debug_status = meta_description_boy_check_wp_debug_status();
    ?>
    <div class="seo-stat-item <?php echo $debug_status['class']; ?>">
        <div class="stat-icon">🔧</div>
        <div class="stat-content">
            <h4>WordPress Debug Mode</h4>
            <div class="stat-status <?php echo $debug_status['class']; ?>">
                <?php echo $debug_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $debug_status['message']; ?>

                <?php if (!empty($debug_status['environment_type']) && $debug_status['environment_type'] !== 'unknown'): ?>
                    <br><small><strong>Environment:</strong> <?php echo ucfirst($debug_status['environment_type']); ?></small>
                <?php endif; ?>

                <br><br><small><strong>Debug Settings:</strong></small>
                <?php foreach ($debug_status['debug_details'] as $setting => $value): ?>
                    <br><small><strong><?php echo $setting; ?>:</strong>
                    <span style="color: <?php echo ($value === 'Enabled') ? '#dc3232' : (($value === 'Disabled') ? '#46b450' : '#646970'); ?>;">
                        <?php echo $value; ?>
                    </span></small>
                <?php endforeach; ?>

                <?php if (!$debug_status['debug_disabled']): ?>
                    <br><br><small><em>Debug mode can expose sensitive information and should be disabled on live websites.</em></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if (!$debug_status['debug_disabled']): ?>
                    <a href="https://wordpress.org/support/article/debugging-in-wordpress/" class="button button-small" target="_blank" style="margin-left: 5px;">
                        Debug Documentation
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}