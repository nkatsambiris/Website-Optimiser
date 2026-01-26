<?php
/**
 * Gravity Forms reCAPTCHA functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check Gravity Forms reCAPTCHA status
 */
function meta_description_boy_check_gravity_forms_recaptcha_status() {
    // Check if plugin functions are available
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $gravity_forms_plugin = 'gravityforms/gravityforms.php';
    $recaptcha_addon = 'gravityformsrecaptcha/recaptcha.php';

    $gf_installed = false;
    $gf_active = false;
    $addon_installed = false;
    $addon_active = false;
    $has_keys = false;
    $gf_version = '';
    $addon_version = '';

    // Check if Gravity Forms is installed and active
    if (is_plugin_active($gravity_forms_plugin)) {
        $gf_installed = true;
        $gf_active = true;
        if (file_exists(WP_PLUGIN_DIR . '/' . $gravity_forms_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $gravity_forms_plugin);
            $gf_version = $plugin_data['Version'] ?? '';
        }
    } elseif (file_exists(WP_PLUGIN_DIR . '/' . $gravity_forms_plugin)) {
        $gf_installed = true;
        $gf_active = false;
    }

    // Only check addon if Gravity Forms is active
    if ($gf_active) {
        // Check if reCAPTCHA addon is installed and active
        if (is_plugin_active($recaptcha_addon)) {
            $addon_installed = true;
            $addon_active = true;
            if (file_exists(WP_PLUGIN_DIR . '/' . $recaptcha_addon)) {
                $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $recaptcha_addon);
                $addon_version = $plugin_data['Version'] ?? '';
            }
        } elseif (file_exists(WP_PLUGIN_DIR . '/' . $recaptcha_addon)) {
            $addon_installed = true;
            $addon_active = false;
        }

                // Check if reCAPTCHA keys are configured (if addon is active)
        if ($addon_active) {
            // Gravity Forms stores reCAPTCHA settings in their options
            $recaptcha_settings = get_option('gravityformsaddon_gravityformsrecaptcha_settings');

            if ($recaptcha_settings) {
                // Check for reCAPTCHA v3 standard keys
                $has_v3_keys = !empty($recaptcha_settings['site_key_v3']) && !empty($recaptcha_settings['secret_key_v3']);

                // Check for reCAPTCHA v2 standard keys
                $has_v2_keys = !empty($recaptcha_settings['site_key_v2']) && !empty($recaptcha_settings['secret_key_v2']);

                // Check for reCAPTCHA v3 Enterprise mode (connected via Google OAuth)
                // Enterprise mode uses project_id and enterprise site key instead of standard keys
                $has_v3_enterprise = !empty($recaptcha_settings['project_id']) && !empty($recaptcha_settings['site_key_v3_enterprise']);

                // Check for reCAPTCHA v2 Enterprise mode
                $has_v2_enterprise = !empty($recaptcha_settings['project_id']) && !empty($recaptcha_settings['site_key_v2_enterprise']);

                // If any configuration method is set up, consider it configured
                if ($has_v3_keys || $has_v2_keys || $has_v3_enterprise || $has_v2_enterprise) {
                    $has_keys = true;
                }
            }

            // Fallback: check if class exists and has method to get settings
            if (!$has_keys && class_exists('GFRecaptcha')) {
                $recaptcha_instance = GFRecaptcha::get_instance();
                if (method_exists($recaptcha_instance, 'get_plugin_settings')) {
                    $settings = $recaptcha_instance->get_plugin_settings();
                    
                    // Check for standard v3 keys
                    $has_v3_keys = !empty($settings['site_key_v3']) && !empty($settings['secret_key_v3']);
                    
                    // Check for standard v2 keys
                    $has_v2_keys = !empty($settings['site_key_v2']) && !empty($settings['secret_key_v2']);
                    
                    // Check for Enterprise v3 (OAuth connected)
                    $has_v3_enterprise = !empty($settings['project_id']) && !empty($settings['site_key_v3_enterprise']);
                    
                    // Check for Enterprise v2 (OAuth connected)
                    $has_v2_enterprise = !empty($settings['project_id']) && !empty($settings['site_key_v2_enterprise']);

                    if ($has_v3_keys || $has_v2_keys || $has_v3_enterprise || $has_v2_enterprise) {
                        $has_keys = true;
                    }
                }
            }
        }
    }

    // Determine status based on findings
    if (!$gf_installed) {
        return array(
            'gf_installed' => false,
            'gf_active' => false,
            'addon_installed' => false,
            'addon_active' => false,
            'has_keys' => false,
            'gf_version' => '',
            'addon_version' => '',
            'status' => 'Gravity Forms Missing',
            'message' => 'Gravity Forms plugin is not installed',
            'class' => 'status-error'
        );
    } elseif (!$gf_active) {
        return array(
            'gf_installed' => true,
            'gf_active' => false,
            'addon_installed' => $addon_installed,
            'addon_active' => false,
            'has_keys' => false,
            'gf_version' => '',
            'addon_version' => '',
            'status' => 'Gravity Forms Inactive',
            'message' => 'Gravity Forms is installed but not activated',
            'class' => 'status-error'
        );
    } elseif (!$addon_installed) {
        return array(
            'gf_installed' => true,
            'gf_active' => true,
            'addon_installed' => false,
            'addon_active' => false,
            'has_keys' => false,
            'gf_version' => $gf_version,
            'addon_version' => '',
            'status' => 'reCAPTCHA Addon Missing',
            'message' => 'reCAPTCHA addon is not installed',
            'class' => 'status-error'
        );
    } elseif (!$addon_active) {
        return array(
            'gf_installed' => true,
            'gf_active' => true,
            'addon_installed' => true,
            'addon_active' => false,
            'has_keys' => false,
            'gf_version' => $gf_version,
            'addon_version' => '',
            'status' => 'reCAPTCHA Addon Inactive',
            'message' => 'reCAPTCHA addon is installed but not activated',
            'class' => 'status-warning'
        );
    } elseif (!$has_keys) {
        return array(
            'gf_installed' => true,
            'gf_active' => true,
            'addon_installed' => true,
            'addon_active' => true,
            'has_keys' => false,
            'gf_version' => $gf_version,
            'addon_version' => $addon_version,
            'status' => 'Keys Not Configured',
            'message' => 'reCAPTCHA addon is active but API keys are not configured',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'gf_installed' => true,
            'gf_active' => true,
            'addon_installed' => true,
            'addon_active' => true,
            'has_keys' => true,
            'gf_version' => $gf_version,
            'addon_version' => $addon_version,
            'status' => 'Fully Configured',
            'message' => 'Gravity Forms reCAPTCHA is properly configured',
            'class' => 'status-good'
        );
    }
}

/**
 * Render Gravity Forms reCAPTCHA section
 */
function meta_description_boy_render_gravity_forms_recaptcha_section() {
    $recaptcha_status = meta_description_boy_check_gravity_forms_recaptcha_status();
    ?>
    <div class="seo-stat-item <?php echo $recaptcha_status['class']; ?>">
        <div class="stat-icon">🔒</div>
        <div class="stat-content">
            <h4>Gravity Forms reCAPTCHA</h4>
            <div class="stat-status <?php echo $recaptcha_status['class']; ?>">
                <?php echo $recaptcha_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $recaptcha_status['message']; ?>
                <?php if (!empty($recaptcha_status['gf_version'])): ?>
                    <br><small>Gravity Forms: v<?php echo $recaptcha_status['gf_version']; ?></small>
                <?php endif; ?>
                <?php if (!empty($recaptcha_status['addon_version'])): ?>
                    <br><small>reCAPTCHA Addon: v<?php echo $recaptcha_status['addon_version']; ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if (!$recaptcha_status['gf_installed']): ?>
                    <!-- Gravity Forms is not installed -->
                <?php elseif (!$recaptcha_status['gf_active']): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small">
                        Activate Gravity Forms
                    </a>
                <?php elseif (!$recaptcha_status['addon_installed']): ?>
                    <a href="<?php echo admin_url('admin.php?page=gf_addons'); ?>" class="button button-small">
                        Install reCAPTCHA Addon
                    </a>
                <?php elseif (!$recaptcha_status['addon_active']): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small">
                        Activate reCAPTCHA Addon
                    </a>
                <?php elseif (!$recaptcha_status['has_keys']): ?>
                    <a href="<?php echo admin_url('admin.php?page=gf_settings&subview=gravityformsrecaptcha'); ?>" class="button button-small">
                        Configure API Keys
                    </a>
                <?php else: ?>
                    <a href="<?php echo admin_url('admin.php?page=gf_settings&subview=gravityformsrecaptcha'); ?>" class="button button-small">
                        View Settings
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=gf_forms'); ?>" class="button button-small" style="margin-left: 5px;">
                        Manage Forms
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}