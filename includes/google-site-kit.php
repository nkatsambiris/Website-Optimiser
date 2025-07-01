<?php
/**
 * Google Site Kit functionality for Meta Description Boy Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check Google Site Kit status
 */
function meta_description_boy_check_google_site_kit_status() {
    // Check if Google Site Kit plugin is installed
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $site_kit_plugin = 'google-site-kit/google-site-kit.php';

    $is_installed = false;
    $is_active = false;
    $version = '';
    $is_connected = false;
    $connected_services = array();
    $total_services = 0;

    // Check if Google Site Kit is installed and active
    if (is_plugin_active($site_kit_plugin)) {
        $is_installed = true;
        $is_active = true;

        if (file_exists(WP_PLUGIN_DIR . '/' . $site_kit_plugin)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $site_kit_plugin);
            $version = $plugin_data['Version'] ?? '';
        }
    } elseif (file_exists(WP_PLUGIN_DIR . '/' . $site_kit_plugin)) {
        $is_installed = true;
        $is_active = false;
    }

        // Get connection and service status if Site Kit is active
    if ($is_active) {
        $services_status = array();

        // Check authentication status - multiple methods
        $is_connected = false;

        // Method 1: Check Site Kit authentication
        if (class_exists('Google\Site_Kit\Context') && defined('GOOGLESITEKIT_PLUGIN_MAIN_FILE')) {
            try {
                if (class_exists('Google\Site_Kit\Core\Authentication\Authentication')) {
                    $context = new Google\Site_Kit\Context(GOOGLESITEKIT_PLUGIN_MAIN_FILE);
                    $auth = new Google\Site_Kit\Core\Authentication\Authentication($context);
                    $is_connected = $auth->is_authenticated();
                }
            } catch (Exception $e) {
                // Continue to fallback methods
            }
        }

        // Method 2: Check via options if class method failed
        if (!$is_connected) {
            $auth_options = get_option('googlesitekit_auth_user_options', array());
            $is_connected = !empty($auth_options);
        }

        // Method 3: Check if any module settings exist as final fallback
        if (!$is_connected) {
            $module_options = array(
                'googlesitekit_search-console_settings',
                'googlesitekit_analytics_settings',
                'googlesitekit_analytics-4_settings',
                'googlesitekit_pagespeed-insights_settings'
            );

            foreach ($module_options as $option) {
                $settings = get_option($option, array());
                if (!empty($settings)) {
                    $is_connected = true;
                    break;
                }
            }
        }

        // Check individual services using multiple detection methods
        $service_checks = array(
            'search-console' => array(
                'name' => 'Search Console',
                'options' => array('googlesitekit_search-console_settings', 'googlesitekit_search_console_settings')
            ),
            'analytics' => array(
                'name' => 'Analytics',
                'options' => array('googlesitekit_analytics_settings')
            ),
            'analytics-4' => array(
                'name' => 'Analytics 4',
                'options' => array('googlesitekit_analytics-4_settings', 'googlesitekit_analytics_4_settings')
            ),
            'pagespeed-insights' => array(
                'name' => 'PageSpeed Insights',
                'options' => array('googlesitekit_pagespeed-insights_settings', 'googlesitekit_pagespeed_insights_settings')
            ),
            'adsense' => array(
                'name' => 'AdSense',
                'options' => array('googlesitekit_adsense_settings')
            ),
            'tagmanager' => array(
                'name' => 'Tag Manager',
                'options' => array('googlesitekit_tagmanager_settings')
            )
        );

        foreach ($service_checks as $service_slug => $service_info) {
            $service_connected = false;

            // Method 1: Try Site Kit API
            if (function_exists('googlesitekit_get_module')) {
                try {
                    $module = googlesitekit_get_module($service_slug);
                    if ($module && method_exists($module, 'is_connected')) {
                        $service_connected = $module->is_connected();
                    }
                } catch (Exception $e) {
                    // Continue to option checks
                }
            }

            // Method 2: Check options if API failed
            if (!$service_connected) {
                foreach ($service_info['options'] as $option_name) {
                    $service_settings = get_option($option_name, array());

                    // Check various indicators of connection
                    if (!empty($service_settings)) {
                        // Check for common connection indicators
                        if (isset($service_settings['connected']) && $service_settings['connected']) {
                            $service_connected = true;
                            break;
                        }
                        if (isset($service_settings['accountID']) && !empty($service_settings['accountID'])) {
                            $service_connected = true;
                            break;
                        }
                        if (isset($service_settings['propertyID']) && !empty($service_settings['propertyID'])) {
                            $service_connected = true;
                            break;
                        }
                        if (isset($service_settings['webPropertyID']) && !empty($service_settings['webPropertyID'])) {
                            $service_connected = true;
                            break;
                        }
                        if (isset($service_settings['measurementID']) && !empty($service_settings['measurementID'])) {
                            $service_connected = true;
                            break;
                        }
                        // If settings exist and have substantial data, assume connected
                        if (count($service_settings) > 2) {
                            $service_connected = true;
                            break;
                        }
                    }
                }
            }

            $services_status[$service_slug] = array(
                'name' => $service_info['name'],
                'connected' => $service_connected
            );

            if ($service_connected) {
                $connected_services[] = $service_info['name'];
            }
        }

        $total_services = count($service_checks);

        // Determine status based on connection and services
        if ($is_connected && count($connected_services) > 0) {
            $status_class = 'status-good';
            $status_text = 'Connected';
            $message = 'Site Kit is connected with ' . count($connected_services) . ' service(s)';
        } elseif ($is_connected && count($connected_services) == 0) {
            $status_class = 'status-warning';
            $status_text = 'Connected';
            $message = 'Site Kit is connected but no services are configured';
        } else {
            $status_class = 'status-warning';
            $status_text = 'Not Connected';
            $message = 'Site Kit is active but not connected to Google';
        }

        return array(
            'installed' => $is_installed,
            'active' => $is_active,
            'version' => $version,
            'connected' => $is_connected,
            'connected_services' => $connected_services,
            'total_services' => $total_services,
            'services_count' => count($connected_services),
            'status' => $status_text,
            'message' => $message,
            'class' => $status_class
        );
    } elseif ($is_installed) {
        return array(
            'installed' => $is_installed,
            'active' => $is_active,
            'version' => '',
            'connected' => false,
            'connected_services' => array(),
            'total_services' => 0,
            'services_count' => 0,
            'status' => 'Inactive',
            'message' => 'Google Site Kit is installed but not activated',
            'class' => 'status-warning'
        );
    } else {
        return array(
            'installed' => $is_installed,
            'active' => $is_active,
            'version' => '',
            'connected' => false,
            'connected_services' => array(),
            'total_services' => 0,
            'services_count' => 0,
            'status' => 'Not Installed',
            'message' => 'Google Site Kit plugin is not installed',
            'class' => 'status-error'
        );
    }
}

/**
 * Render Google Site Kit section
 */
function meta_description_boy_render_google_site_kit_section() {
    $site_kit_status = meta_description_boy_check_google_site_kit_status();
    ?>
    <div class="seo-stat-item <?php echo $site_kit_status['class']; ?>">
        <div class="stat-icon">📊</div>
        <div class="stat-content">
            <h4>Google Site Kit</h4>
            <div class="stat-status <?php echo $site_kit_status['class']; ?>">
                <?php echo $site_kit_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $site_kit_status['message']; ?>
                <?php if (!empty($site_kit_status['version'])): ?>
                    <br><small>Version: <?php echo $site_kit_status['version']; ?></small>
                <?php endif; ?>
                <?php if ($site_kit_status['active'] && !empty($site_kit_status['connected_services'])): ?>
                    <br><small>
                        Connected services: <?php echo implode(', ', $site_kit_status['connected_services']); ?>
                    </small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($site_kit_status['active']): ?>
                    <a href="<?php echo admin_url('admin.php?page=googlesitekit-dashboard'); ?>" class="button button-small">
                        Open Site Kit
                    </a>
                    <?php if (!$site_kit_status['connected']): ?>
                    <a href="<?php echo admin_url('admin.php?page=googlesitekit-splash'); ?>" class="button button-small" style="margin-left: 5px;">
                        Connect Google
                    </a>
                    <?php elseif ($site_kit_status['services_count'] < 3): ?>
                    <a href="<?php echo admin_url('admin.php?page=googlesitekit-settings'); ?>" class="button button-small" style="margin-left: 5px;">
                        Add Services
                    </a>
                    <?php endif; ?>
                <?php elseif ($site_kit_status['installed']): ?>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-small">
                        Activate Plugin
                    </a>
                <?php else: ?>
                    <a href="<?php echo admin_url('plugin-install.php?s=google+site+kit&tab=search&type=term'); ?>" class="button button-small">
                        Install Site Kit
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}