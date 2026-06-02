<?php
/**
* Plugin Name: Website Optimiser
* Description: A plugin that optimises your website for SEO and performance.
* Version: 3.0.0
* Plugin URI:  https://www.katsambiris.com
* Author: Nicholas Katsambiris
* Update URI: website-optimiser
* License: GPL v3
* Tested up to: 7.0
* Requires at least: 6.2
* Requires PHP: 7.4
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/connectors.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/local-schema.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/security-headers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/abilities.php';

if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'dashboard-widget.php';
}

$plugin = plugin_basename(__FILE__);  // Gets the correct file name for your plugin.
add_filter("plugin_action_links_$plugin", 'meta_description_boy_add_settings_link');

// Code to run during plugin activation
function meta_description_boy_activate() {
    add_option('meta_description_boy_api_key', '');
    add_option('meta_description_boy_post_types', array('post', 'page'));
    add_option('meta_description_boy_auto_alt_text', 1); // Enable by default
    add_option('meta_description_boy_enable_caching', 1); // Enable caching by default
    add_option('meta_description_boy_cache_duration', 6); // Default 6 hours
    add_option('website_optimiser_pagespeed_api_key', '');
    add_option('website_optimiser_local_schema', website_optimiser_get_local_schema_defaults());
    add_option('website_optimiser_security_headers', website_optimiser_get_security_headers_defaults());

    // Clear update caches to ensure updater works properly
    meta_description_boy_clear_update_cache();
}
register_activation_hook(__FILE__, 'meta_description_boy_activate');

// Clear update caches
function meta_description_boy_clear_update_cache() {
    delete_site_transient('update_plugins');
    delete_transient('update_plugins');
    wp_clean_plugins_cache();
}

// Force update check (for testing)
function meta_description_boy_force_update_check() {
    // Clear caches first
    meta_description_boy_clear_update_cache();

    // Force WordPress to check for updates
    wp_update_plugins();

    // Check if update is available
    $updates = get_site_transient('update_plugins');
    $plugin_basename = plugin_basename(__FILE__);

    if (isset($updates->response[$plugin_basename])) {
        return 'Update available: ' . $updates->response[$plugin_basename]->new_version;
    } else {
        return 'No update available';
    }
}

// Add update check to admin if debug is enabled
function meta_description_boy_admin_notices() {
    $debug_enabled = get_option('meta_description_boy_debug_enabled');

    if ($debug_enabled && isset($_GET['force_update_check']) && $_GET['force_update_check'] === '1') {
        $result = meta_description_boy_force_update_check();
        echo '<div class="notice notice-info"><p><strong>Website Optimiser:</strong> ' . esc_html($result) . '</p></div>';
    }

    if ($debug_enabled && current_user_can('manage_options')) {
        $current_screen = get_current_screen();
        if ($current_screen && strpos($current_screen->id, 'meta-description-boy') !== false) {
            echo '<div class="notice notice-info"><p><strong>Debug Mode:</strong> <a href="' . admin_url('admin.php?page=meta-description-boy&force_update_check=1') . '">Force Update Check</a></p></div>';
        }
    }
}
add_action('admin_notices', 'meta_description_boy_admin_notices');

// Remove options when the plugin is uninstalled
function meta_description_boy_uninstall() {
    delete_option('meta_description_boy_api_key');
    delete_option('meta_description_boy_post_types');
    delete_option('meta_description_boy_instruction_text');
    delete_option('meta_description_boy_allowed_roles');
    delete_option('meta_description_boy_debug_enabled');
    delete_option('meta_description_boy_auto_alt_text');
    delete_option('meta_description_boy_auto_generated_alt_text');
    delete_option('meta_description_boy_enable_caching');
    delete_option('meta_description_boy_cache_duration');
    delete_option('website_optimiser_pagespeed_api_key');
    delete_option('website_optimiser_local_schema');
    delete_option('website_optimiser_security_headers');
    delete_option('website_optimiser_pagespeed_results');
    // Clean up ManageWP options
    delete_option('meta_description_boy_no_managewp_approved');
    delete_option('meta_description_boy_no_managewp_approved_by');
    delete_option('meta_description_boy_no_managewp_approved_date');
    // Clean up old OpenAI options if they exist
    delete_option('meta_description_boy_selected_model');
    delete_option('meta_description_boy_prompt_text');
    delete_option('meta_description_boy_access_role');

    // Clear all plugin transients and stored data
    delete_transient('meta_description_boy_h1_stats');
    delete_option('meta_description_boy_h1_stats_data');
    delete_option('meta_description_boy_h1_detailed_results');
    delete_transient('meta_description_boy_sitemap_check');
    delete_transient('meta_description_boy_robots_check');
    delete_transient('meta_description_boy_llms_check');
    delete_transient('meta_description_boy_meta_description_stats');
    delete_transient('meta_description_boy_alt_text_stats');
    delete_transient('meta_description_boy_featured_image_stats');
}
register_uninstall_hook(__FILE__, 'meta_description_boy_uninstall');

// Add admin menu
function meta_description_boy_add_admin_menu() {
    $user = wp_get_current_user();
    $allowed_roles = get_option('meta_description_boy_allowed_roles', array('administrator'));
    if (array_intersect($allowed_roles, $user->roles)) {
        add_submenu_page(
            'website-optimisation',           // Parent slug
            'Settings',                   // Page title
            'Settings',                   // Menu title
            'manage_options',             // Capability
            'meta-description-boy',       // Menu slug
            'meta_description_boy_options_page' // Function
        );
    }
}
add_action('admin_menu', 'meta_description_boy_add_admin_menu');



function meta_description_boy_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=meta-description-boy">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);  // To add the Settings link before other links like Activate/Deactivate.
    return $links;
}


// Display the options page
function meta_description_boy_options_page() {
    ?>
    <div class="wrap">
        <h2>Website Optimisation Settings</h2>
        <form action="options.php" method="post">
            <?php
            settings_fields('meta_description_boy_options');
            do_settings_sections('meta-description-boy');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register custom metabox
function meta_description_boy_add_meta_box() {
    $debug_enabled = get_option('meta_description_boy_debug_enabled');

    if ($debug_enabled) {
        error_log('Meta Description Boy: add_meta_boxes action triggered');
    }

    // Check if the Gemini API key is set
    if ( ! website_optimiser_gemini_api_key_configured() ) {
        if ($debug_enabled) {
            error_log('Meta Description Boy: API key not set, meta box not added');
        }
        // API key not set, so return early and do not add the meta box
        return;
    }

    // Check if the user has access
    $user = wp_get_current_user();
    $allowed_roles = get_option('meta_description_boy_allowed_roles', array('administrator'));
    if ($debug_enabled) {
        error_log('Meta Description Boy: User roles: ' . implode(', ', $user->roles));
        error_log('Meta Description Boy: Allowed roles: ' . implode(', ', $allowed_roles));
    }

    if (!array_intersect($allowed_roles, $user->roles)) {
        if ($debug_enabled) {
            error_log('Meta Description Boy: User does not have required role, meta box not added');
        }
        return;
    }

    $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page')); // Default to post and page if the option is not set.
    if ($debug_enabled) {
        error_log('Meta Description Boy: Selected post types: ' . implode(', ', $selected_post_types));
    }

    foreach ($selected_post_types as $post_type) {
        if ($debug_enabled) {
            error_log('Meta Description Boy: Adding meta box for post type: ' . $post_type);
        }
        add_meta_box(
            'meta_description_boy_meta_box', // Unique ID
            'Meta Description', // Title of the box
            'meta_description_boy_meta_box_callback', // Callback function
            $post_type, // Post type
            'side', // Context
            'default' // Priority
        );
    }

    // Add meta box for attachments (alt text generation)
    add_meta_box(
        'meta_description_boy_alt_text_meta_box', // Unique ID
        'AI Alt Text Generator', // Title of the box
        'meta_description_boy_alt_text_meta_box_callback', // Callback function
        'attachment', // Post type
        'side', // Context
        'default' // Priority
    );
}

add_action('add_meta_boxes', 'meta_description_boy_add_meta_box');

// Temporary diagnostic function - remove this later
function meta_description_boy_diagnostic_admin_notice() {
    $debug_enabled = get_option('meta_description_boy_debug_enabled');
    if (!$debug_enabled) {
        return;
    }

    $screen = get_current_screen();
    if ($screen && ($screen->post_type == 'post' || $screen->post_type == 'page') && $screen->base == 'post') {
        $user = wp_get_current_user();
        $allowed_roles = get_option('meta_description_boy_allowed_roles', array('administrator'));
        $selected_post_types = get_option('meta_description_boy_post_types', array('post', 'page'));

        echo '<div class="notice notice-info">';
        echo '<h3>Website Optimiser Debug Info:</h3>';
        echo '<p><strong>API Key Set:</strong> ' . ( website_optimiser_gemini_api_key_configured() ? 'Yes' : 'No' ) . '</p>';
        echo '<p><strong>API Key Source:</strong> ' . esc_html( website_optimiser_get_gemini_api_key_source_label() ) . '</p>';
        echo '<p><strong>User Roles:</strong> ' . implode(', ', $user->roles) . '</p>';
        echo '<p><strong>Allowed Roles:</strong> ' . implode(', ', $allowed_roles) . '</p>';
        echo '<p><strong>Selected Post Types:</strong> ' . implode(', ', $selected_post_types) . '</p>';
        echo '<p><strong>Current Post Type:</strong> ' . $screen->post_type . '</p>';
        echo '<p><strong>Meta Box Should Show:</strong> ' . (
            website_optimiser_gemini_api_key_configured() &&
            array_intersect($allowed_roles, $user->roles) &&
            in_array($screen->post_type, $selected_post_types)
            ? 'Yes' : 'No'
        ) . '</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'meta_description_boy_diagnostic_admin_notice');

// This function will generate the content displayed inside the meta box:
function meta_description_boy_meta_box_callback($post) {
    $post_id = $post->ID;

    $title = get_the_title($post_id) . ' '; // Equivalent to get_name

    $wc_content = '';
    if ('product' === get_post_type($post_id)) {
        $wc_content = get_post_field('post_excerpt', $post_id);
    }

    $acf_content = '';
    if (function_exists('get_fields')) {
        $acf_data = get_fields($post_id);
        $acf_content_raw = extract_acf_content($acf_data);
        $acf_content = remove_table_content($acf_content_raw);
    }

    $post_content_raw = get_post_field('post_content', $post_id);

    if (!empty(trim($wc_content)) || !empty(trim($acf_content)) || !empty(trim($post_content_raw))) {
        echo '<button id="meta_description_boy_generate_meta_description" class="button button-primary">Generate Meta Description</button>';
        echo '<div id="meta_description_boy_output" style="margin-top: 10px;"></div>'; // Container for output
    } else {
        echo '<p>Add some content to this page first before generating a meta description.</p>';
    }
}

// Meta box callback for attachment alt text generation
function meta_description_boy_alt_text_meta_box_callback($post) {
    if ( ! website_optimiser_gemini_api_key_configured() ) {
        if ( ! website_optimiser_ai_features_enabled() ) {
            echo '<p>' . esc_html__( 'AI features are disabled on this site.' ) . '</p>';
        } elseif ( website_optimiser_connectors_api_available() ) {
            echo '<p>' . sprintf(
                /* translators: %s: Settings → Connectors admin URL */
                wp_kses_post( __( 'Configure your Google API key under <a href="%s">Settings → Connectors</a>, or in Website Optimiser settings below.', 'website-optimiser' ) ),
                esc_url( website_optimiser_get_gemini_credentials_admin_url() )
            ) . '</p>';
        } else {
            echo '<p>' . esc_html__( 'Please configure your Google Gemini API key in the plugin settings first.', 'website-optimiser' ) . '</p>';
        }
        return;
    }

    // Check if this is an image
    if (!wp_attachment_is_image($post->ID)) {
        echo '<p>Alt text generation is only available for images.</p>';
        return;
    }

    $current_alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);

    echo '<div class="alt-text-generator">';
    echo '<p><strong>Current Alt Text:</strong></p>';
    echo '<p><em>' . ($current_alt ? esc_html($current_alt) : 'No alt text set') . '</em></p>';

    echo '<button id="generate_attachment_alt_text" class="button button-primary" data-attachment-id="' . $post->ID . '">Generate Alt Text with AI</button>';
    echo '<div id="alt_text_output" style="margin-top: 10px;"></div>';
    echo '</div>';
}

// Register settings
function meta_description_boy_admin_init() {
    register_setting('meta_description_boy_options', 'meta_description_boy_api_key');
    register_setting('meta_description_boy_options', 'meta_description_boy_post_types');
    register_setting('meta_description_boy_options', 'meta_description_boy_instruction_text');
    register_setting('meta_description_boy_options', 'meta_description_boy_allowed_roles');
    register_setting('meta_description_boy_options', 'meta_description_boy_debug_enabled');
    register_setting('meta_description_boy_options', 'meta_description_boy_auto_alt_text');
    register_setting('meta_description_boy_options', 'meta_description_boy_enable_caching');
    register_setting('meta_description_boy_options', 'meta_description_boy_cache_duration');
    register_setting('meta_description_boy_options', 'meta_description_boy_http_auth_user');
    register_setting('meta_description_boy_options', 'meta_description_boy_http_auth_pass');
    register_setting('meta_description_boy_options', 'website_optimiser_pagespeed_api_key', 'sanitize_text_field');

    // Settings sections & fields
    add_settings_section('meta_description_boy_api_settings', 'API Settings', null, 'meta-description-boy');

    add_settings_field('meta_description_boy_api_key_field', 'Google Gemini API Key', 'meta_description_boy_api_key_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('website_optimiser_pagespeed_api_key_field', 'Google PageSpeed API Key', 'website_optimiser_pagespeed_api_key_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('meta_description_boy_post_types_field', 'Post Types', 'meta_description_boy_post_types_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('meta_description_boy_instruction_text_field', 'Instruction Text', 'meta_description_boy_instruction_text_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('meta_description_boy_allowed_roles_field', 'Allowed User Roles', 'meta_description_boy_allowed_roles_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('meta_description_boy_debug_field', 'Enable Debug Output', 'meta_description_boy_debug_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('meta_description_boy_auto_alt_text_field', 'Auto-Generate Alt Text', 'meta_description_boy_auto_alt_text_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('meta_description_boy_caching_field', 'Performance Settings', 'meta_description_boy_caching_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
    add_settings_field('meta_description_boy_http_auth_field', 'HTTP Authentication (Password Protected Sites)', 'meta_description_boy_http_auth_field_cb', 'meta-description-boy', 'meta_description_boy_api_settings');
}
add_action('admin_init', 'meta_description_boy_admin_init');

function meta_description_boy_debug_field_cb() {
    $debug_enabled = get_option('meta_description_boy_debug_enabled');
    echo "<input type='checkbox' name='meta_description_boy_debug_enabled' value='1'" . checked(1, $debug_enabled, false) . " />";
}

function meta_description_boy_auto_alt_text_field_cb() {
    $auto_alt_text = get_option('meta_description_boy_auto_alt_text', 1); // Default to enabled
    echo "<input type='checkbox' name='meta_description_boy_auto_alt_text' value='1'" . checked(1, $auto_alt_text, false) . " />";
    echo "<p class='description'>Automatically generate alt text for images when they are uploaded. Only applies to new uploads.</p>";

    // Add bulk generation button and controls
    echo "<div style='margin-top: 15px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;'>";
    echo "<h4>Bulk Alt Text Generation</h4>";
    echo "<p class='description'>Generate alt text for existing images that don't have alt text or have empty alt text.</p>";
    echo "<button type='button' id='bulk_alt_text_generate' class='button button-secondary'>Start Bulk Generation</button>";
    echo "<button type='button' id='bulk_alt_text_stop' class='button button-secondary' style='display: none; margin-left: 10px;'>Stop</button>";
    echo "<div id='bulk_progress' style='margin-top: 10px; display: none;'>";
    echo "<div style='margin-bottom: 10px;'>";
    echo "<span id='progress_text'>Preparing...</span>";
    echo "<div style='width: 100%; background-color: #f0f0f0; border-radius: 5px; margin-top: 5px;'>";
    echo "<div id='progress_bar' style='width: 0%; height: 20px; background-color: #0073aa; border-radius: 5px; transition: width 0.3s;'></div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "<div id='bulk_status_table_container' style='margin-top: 20px; display: none;'>";
    echo "<h4>Processing Status</h4>";
    echo "<table id='bulk_status_table' class='wp-list-table widefat fixed striped'>";
    echo "<thead><tr>";
    echo "<th style='width: 80px;'>Thumbnail</th>";
    echo "<th>Title</th>";
    echo "<th style='width: 100px;'>Status</th>";
    echo "<th>Alt Text / Error</th>";
    echo "</tr></thead>";
    echo "<tbody id='bulk_status_tbody'></tbody>";
    echo "</table>";
    echo "</div>";
    echo "</div>";
}

function meta_description_boy_caching_field_cb() {
    $enable_caching = get_option('meta_description_boy_enable_caching', 1); // Default to enabled
    $cache_duration = get_option('meta_description_boy_cache_duration', 6); // Default 6 hours

    echo "<div style='margin-bottom: 15px;'>";
    echo "<input type='checkbox' name='meta_description_boy_enable_caching' value='1'" . checked(1, $enable_caching, false) . " id='enable_caching_checkbox' />";
    echo "<label for='enable_caching_checkbox'> Enable performance caching</label>";
    echo "<p class='description'>Caching improves dashboard loading speed by storing analysis results temporarily.</p>";
    echo "</div>";

    echo "<div style='margin-bottom: 15px;'>";
    echo "<label for='cache_duration_select'>Cache Duration: </label>";
    echo "<select name='meta_description_boy_cache_duration' id='cache_duration_select'>";
    echo "<option value='1'" . selected(1, $cache_duration, false) . ">1 hour</option>";
    echo "<option value='3'" . selected(3, $cache_duration, false) . ">3 hours</option>";
    echo "<option value='6'" . selected(6, $cache_duration, false) . ">6 hours</option>";
    echo "<option value='12'" . selected(12, $cache_duration, false) . ">12 hours</option>";
    echo "<option value='24'" . selected(24, $cache_duration, false) . ">24 hours</option>";
    echo "</select>";
    echo "<p class='description'>How long to cache analysis results before checking again.</p>";
    echo "</div>";

    echo "<div style='margin-top: 15px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;'>";
    echo "<h4>Cache Management</h4>";
    echo "<p class='description'>Use these buttons to manually refresh the analysis data.</p>";
    echo "<button type='button' id='clear_all_cache' class='button button-secondary'>Clear All Cache</button>";
    echo "<button type='button' id='refresh_analysis' class='button button-secondary' style='margin-left: 10px;'>Refresh Analysis</button>";
    echo "<div id='cache_status' style='margin-top: 10px;'></div>";
    echo "</div>";

    // Add JavaScript for cache management
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#clear_all_cache').on('click', function() {
            var $button = $(this);
            var $status = $('#cache_status');

            $button.prop('disabled', true).text('Clearing...');
            $status.html('<span style="color: #666;">Clearing cache...</span>');

            $.post(ajaxurl, {
                action: 'meta_description_boy_clear_cache',
                nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    $status.html('<span style="color: #46b450;">✓ Cache cleared successfully</span>');
                } else {
                    $status.html('<span style="color: #dc3232;">✗ Error: ' + response.data.message + '</span>');
                }
                $button.prop('disabled', false).text('Clear All Cache');

                setTimeout(function() {
                    $status.html('');
                }, 3000);
            });
        });

        $('#refresh_analysis').on('click', function() {
            var $button = $(this);
            var $status = $('#cache_status');

            $button.prop('disabled', true).text('Refreshing...');
            $status.html('<span style="color: #666;">Refreshing analysis data...</span>');

            $.post(ajaxurl, {
                action: 'meta_description_boy_refresh_analysis',
                nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    $status.html('<span style="color: #46b450;">✓ Analysis refreshed successfully</span>');
                } else {
                    $status.html('<span style="color: #dc3232;">✗ Error: ' + response.data.message + '</span>');
                }
                $button.prop('disabled', false).text('Refresh Analysis');

                setTimeout(function() {
                    $status.html('');
                }, 3000);
            });
        });
    });
    </script>
    <?php
}

function meta_description_boy_http_auth_field_cb() {
    $http_auth_user = get_option('meta_description_boy_http_auth_user', '');
    $http_auth_pass = get_option('meta_description_boy_http_auth_pass', '');
    
    echo "<div style='background: #fff3cd; border-left: 4px solid #ffb900; padding: 15px; margin-bottom: 20px;'>";
    echo "<p style='margin: 0 0 10px 0;'><strong>⚠️ Password Protected Site Detected</strong></p>";
    echo "<p style='margin: 0; font-size: 13px;'>Your site appears to be password protected (HTTP 401 error). ";
    echo "To allow accurate H1 analysis, provide your HTTP authentication credentials below.</p>";
    echo "</div>";
    
    echo "<div style='margin-bottom: 15px;'>";
    echo "<label for='http_auth_user' style='display: block; font-weight: bold; margin-bottom: 5px;'>HTTP Auth Username:</label>";
    echo "<input type='text' name='meta_description_boy_http_auth_user' id='http_auth_user' value='" . esc_attr($http_auth_user) . "' style='width: 300px;' />";
    echo "<p class='description'>Username for HTTP Basic Authentication</p>";
    echo "</div>";
    
    echo "<div style='margin-bottom: 15px;'>";
    echo "<label for='http_auth_pass' style='display: block; font-weight: bold; margin-bottom: 5px;'>HTTP Auth Password:</label>";
    echo "<input type='password' name='meta_description_boy_http_auth_pass' id='http_auth_pass' value='" . esc_attr($http_auth_pass) . "' style='width: 300px;' />";
    echo "<p class='description'>Password for HTTP Basic Authentication</p>";
    echo "</div>";
    
    echo "<div style='background: #e7f4f9; border-left: 4px solid #0073aa; padding: 15px; margin-top: 15px;'>";
    echo "<p style='margin: 0 0 10px 0;'><strong>💡 Alternative Method (wp-config.php)</strong></p>";
    echo "<p style='margin: 0 0 5px 0; font-size: 13px;'>You can also add these constants to your wp-config.php file:</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; overflow-x: auto;'>";
    echo "define('WP_H1_CHECKER_AUTH_USER', 'your_username');\n";
    echo "define('WP_H1_CHECKER_AUTH_PASS', 'your_password');</pre>";
    echo "<p style='margin: 5px 0 0 0; font-size: 13px;'><em>This method is more secure as credentials won't be stored in the database.</em></p>";
    echo "</div>";
}

function meta_description_boy_instruction_text_field_cb() {
    $instruction_text = get_option('meta_description_boy_instruction_text', 'Write a 160 character or less SEO meta description based on the following content.');
    echo "<input type='text' name='meta_description_boy_instruction_text' value='{$instruction_text}' style='width: 100%;'>";
}

function meta_description_boy_allowed_roles_field_cb() {
    $selected_roles = get_option('meta_description_boy_allowed_roles', array('administrator'));  // Default to administrator.
    $all_roles = wp_roles()->roles;
    foreach ($all_roles as $role_slug => $role) {
        $checked = in_array($role_slug, $selected_roles) ? 'checked' : '';
        echo "<input type='checkbox' name='meta_description_boy_allowed_roles[]' value='{$role_slug}' {$checked}> {$role['name']}<br>";
    }
}

function meta_description_boy_api_key_field_cb() {
    $legacy_key  = get_option( 'meta_description_boy_api_key', '' );
    $key_source  = website_optimiser_get_gemini_api_key_source();
    $configured  = website_optimiser_gemini_api_key_configured();

    if ( website_optimiser_connectors_api_available() ) {
        $connectors_url = esc_url( website_optimiser_get_gemini_credentials_admin_url() );

        if ( $configured && 'plugin-setting' !== $key_source ) {
            echo '<p class="description"><strong>' . esc_html__( 'Active key:', 'website-optimiser' ) . '</strong> ';
            echo esc_html( website_optimiser_get_gemini_api_key_source_label() ) . '</p>';
            echo '<p class="description">' . sprintf(
                /* translators: %s: Settings → Connectors URL */
                wp_kses_post( __( 'Manage the Google API key in <a href="%s">Settings → Connectors</a>. Keys can also be set via the <code>GOOGLE_API_KEY</code> environment variable or PHP constant.', 'website-optimiser' ) ),
                $connectors_url
            ) . '</p>';
        } else {
            echo '<p class="description">' . sprintf(
                /* translators: %s: Settings → Connectors URL */
                wp_kses_post( __( 'On WordPress 7.0+, add your Google API key in <a href="%s">Settings → Connectors</a> (recommended), or enter a fallback key below.', 'website-optimiser' ) ),
                $connectors_url
            ) . '</p>';
        }
    } else {
        echo "<p class='description'>" . esc_html__( 'Get your API key from Google AI Studio.', 'website-optimiser' ) . " <a href='https://aistudio.google.com/app/apikey' target='_blank' rel='noopener noreferrer'>Google AI Studio</a></p>";
    }

    $readonly = ( $configured && 'plugin-setting' !== $key_source );
    echo "<input type='password' name='meta_description_boy_api_key' value='" . esc_attr( $legacy_key ) . "' style='width: 400px;'";
    if ( $readonly ) {
        echo " readonly placeholder='" . esc_attr__( 'Using Connectors API key', 'website-optimiser' ) . "'";
    }
    echo '>';

    if ( ! website_optimiser_ai_features_enabled() ) {
        echo '<p class="description" style="color:#b32d2e;">' . esc_html__( 'AI features are disabled on this site (wp_supports_ai).', 'website-optimiser' ) . '</p>';
    }
}

function website_optimiser_pagespeed_api_key_field_cb() {
    $option_key = defined( 'WEBSITE_OPTIMISER_PAGESPEED_API_KEY_OPTION' ) ? WEBSITE_OPTIMISER_PAGESPEED_API_KEY_OPTION : 'website_optimiser_pagespeed_api_key';
    $api_key    = get_option( $option_key, '' );

    if ( defined( 'WEBSITE_OPTIMISER_PAGESPEED_API_KEY' ) && WEBSITE_OPTIMISER_PAGESPEED_API_KEY ) {
        echo '<input type="password" value="" style="width: 400px;" readonly placeholder="' . esc_attr__( 'Using wp-config.php constant', 'website-optimiser' ) . '">';
        echo '<p class="description">' . esc_html__( 'PageSpeed Insights is using WEBSITE_OPTIMISER_PAGESPEED_API_KEY from wp-config.php.', 'website-optimiser' ) . '</p>';
        return;
    }

    $env_key = getenv( 'PAGESPEED_INSIGHTS_API_KEY' );
    if ( ! empty( $env_key ) ) {
        echo '<input type="password" value="" style="width: 400px;" readonly placeholder="' . esc_attr__( 'Using environment variable', 'website-optimiser' ) . '">';
        echo '<p class="description">' . esc_html__( 'PageSpeed Insights is using the PAGESPEED_INSIGHTS_API_KEY environment variable.', 'website-optimiser' ) . '</p>';
        return;
    }

    echo '<input type="password" name="' . esc_attr( $option_key ) . '" value="' . esc_attr( $api_key ) . '" style="width: 400px;">';
    echo '<p class="description">' . wp_kses_post( __( 'Optional, but recommended to avoid shared quota errors. Create a Google Cloud API key with the PageSpeed Insights API enabled.', 'website-optimiser' ) ) . ' <a href="https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com" target="_blank" rel="noopener noreferrer">PageSpeed Insights API</a></p>';
}

function meta_description_boy_post_types_field_cb() {
    $selected_post_types = get_option('meta_description_boy_post_types');
    $post_types = get_post_types();

    // List of post types to exclude
    $excluded_post_types = array(
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
        'acf-taxonomy',
        'acf-post-type',
        'acf-ui-options-page',
        'acf-field-group',
        'acf-field',
        'shop_order',
        'shop_order_refund',
        'shop_coupon',
        'shop_order_placehold',
        'product_variation',
        'scheduled-action',
        'jp_mem_plan',
        'jp_pay_order',
        'jp_pay_product'
    );

    // Filter out the excluded post types
    $post_types = array_diff($post_types, $excluded_post_types);

    foreach ($post_types as $post_type) {
        $checked = in_array($post_type, $selected_post_types) ? 'checked' : '';
        echo "<input type='checkbox' name='meta_description_boy_post_types[]' value='{$post_type}' {$checked}> {$post_type}<br>";
    }
}

function meta_description_boy_enqueue_admin_scripts($hook) {
    global $post, $pagenow;

    // Debug logging
    $debug_enabled = get_option('meta_description_boy_debug_enabled');
    if ($debug_enabled) {
        error_log('Meta Description Boy: Enqueue script hook: ' . $hook);
        error_log('Meta Description Boy: Current pagenow: ' . $pagenow);
        error_log('Meta Description Boy: Post type: ' . (isset($post) ? $post->post_type : 'no post'));
    }

    // Load on post edit pages, upload page, attachment edit pages, optimization page, and settings page
    $should_load = (
        ('post.php' == $hook || 'post-new.php' == $hook) ||
        ('upload.php' == $hook) ||
        ('post.php' == $hook && isset($post) && $post->post_type == 'attachment') ||
        ('toplevel_page_website-optimisation' == $hook) ||
        (strpos($hook, 'meta-description-boy') !== false)
    );

    if ($debug_enabled) {
        error_log('Meta Description Boy: Should load scripts: ' . ($should_load ? 'YES' : 'NO'));
    }

    if ($should_load) {
        $version = filemtime(plugin_dir_path(__FILE__) . 'admin.js');
        $version_css = filemtime(plugin_dir_path(__FILE__) . 'admin.css');
        wp_enqueue_script('meta_description_boy_admin_js', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), $version, true);
        wp_enqueue_style('meta-description-boy-admin-styles', plugin_dir_url(__FILE__) . 'admin.css', array(), $version_css);

        // Also enqueue media scripts for modal functionality
        wp_enqueue_media();

        // Get post ID more reliably
        $post_id = 0;
        if (isset($post) && $post->ID) {
            $post_id = $post->ID;
        } elseif (isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
        } elseif (isset($_POST['post_ID'])) {
            $post_id = intval($_POST['post_ID']);
        }

        // Localize the script with server-side data
        $meta_description_boy_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id' => $post_id,
            'nonce' => wp_create_nonce('meta_description_boy_nonce'),
            'debug' => get_option('meta_description_boy_debug_enabled', false)
        );
        wp_localize_script('meta_description_boy_admin_js', 'meta_description_boy_data', $meta_description_boy_data);

        if ($debug_enabled) {
            error_log('Meta Description Boy: Scripts enqueued successfully for post ID: ' . $post_id);
        }
    }
}
add_action('admin_enqueue_scripts', 'meta_description_boy_enqueue_admin_scripts');

/**
 * Generate an SEO meta description for a post using Google Gemini.
 *
 * @param int $post_id Post ID.
 * @return string|WP_Error Generated description or error.
 */
function meta_description_boy_generate_meta_description_for_post( $post_id ) {
    $post_id = intval( $post_id );

    if ( ! $post_id ) {
        return new WP_Error( 'invalid_post_id', __( 'No post ID provided.' ) );
    }

    $title = get_the_title( $post_id ) . ' ';

    $wc_content = '';
    if ( 'product' === get_post_type( $post_id ) ) {
        $wc_content = get_post_field( 'post_excerpt', $post_id );
    }

    $acf_content = '';
    if ( function_exists( 'get_fields' ) ) {
        $acf_data          = get_fields( $post_id );
        $acf_content_raw   = extract_acf_content( $acf_data );
        $acf_content       = remove_table_content( $acf_content_raw );
    }

    $post_content_raw = get_post_field( 'post_content', $post_id );
    $post_content     = $title . ' ' . remove_table_content( $post_content_raw ) . ' ' . esc_html( $acf_content ) . ' ' . $wc_content;

    if ( ! website_optimiser_ai_features_enabled() ) {
        return new WP_Error(
            'ai_disabled',
            __( 'AI features are disabled on this site.' )
        );
    }

    $api_key          = website_optimiser_get_gemini_api_key();
    $instruction_text = get_option( 'meta_description_boy_instruction_text', 'Write a 160 character or less SEO meta description based on the following content.' );

    if ( empty( $api_key ) ) {
        $message = website_optimiser_connectors_api_available()
            ? __( 'Google Gemini API key not configured. Add it under Settings → Connectors or in Website Optimiser settings.' )
            : __( 'Google Gemini API key not configured. Please check plugin settings.' );

        return new WP_Error( 'api_key_missing', $message );
    }

    $full_prompt = $instruction_text . "\n\nContent: " . $post_content;

    $response = wp_remote_post(
        website_optimiser_get_gemini_api_url( $api_key ),
        array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode(
                array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array(
                                    'text' => $full_prompt,
                                ),
                            ),
                        ),
                    ),
                )
            ),
            'timeout' => 30,
        )
    );

    if ( is_wp_error( $response ) ) {
        return new WP_Error(
            'gemini_request_error',
            sprintf(
                /* translators: %s: error message from HTTP client */
                __( 'Gemini API Request Error: %s' ),
                $response->get_error_message()
            )
        );
    }

    $response_code = wp_remote_retrieve_response_code( $response );

    if ( 200 !== $response_code ) {
        $body         = wp_remote_retrieve_body( $response );
        $error_data   = json_decode( $body, true );
        $error_message = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'HTTP ' . $response_code;

        return new WP_Error(
            'gemini_response_error',
            sprintf(
                /* translators: %s: error message from API */
                __( 'Gemini API Response Error: %s' ),
                $error_message
            )
        );
    }

    $body        = wp_remote_retrieve_body( $response );
    $data        = json_decode( $body, true );
    $description = isset( $data['candidates'][0]['content']['parts'][0]['text'] )
        ? trim( $data['candidates'][0]['content']['parts'][0]['text'] )
        : '';

    if ( $description ) {
        return $description;
    }

    $error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unexpected error generating description' );

    return new WP_Error( 'gemini_error', sprintf( __( 'Gemini Error: %s' ), $error_message ) );
}

/**
 * Generate alt text for a media attachment using Google Gemini Vision.
 *
 * @param int  $attachment_id Attachment post ID.
 * @param bool $save          Whether to save alt text to attachment meta.
 * @return string|WP_Error Generated alt text or error.
 */
function meta_description_boy_generate_alt_text_for_attachment( $attachment_id, $save = false ) {
    $attachment_id = absint( $attachment_id );

    if ( ! $attachment_id ) {
        return new WP_Error( 'invalid_attachment_id', __( 'Invalid attachment ID.' ) );
    }

    $attachment = get_post( $attachment_id );
    if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
        return new WP_Error( 'attachment_not_found', __( 'Attachment not found.' ) );
    }

    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        return new WP_Error( 'not_an_image', __( 'File is not an image.' ) );
    }

    if ( ! website_optimiser_ai_features_enabled() ) {
        return new WP_Error( 'ai_disabled', __( 'AI features are disabled on this site.' ) );
    }

    $api_key = website_optimiser_get_gemini_api_key();
    if ( empty( $api_key ) ) {
        return new WP_Error( 'api_key_missing', __( 'Google Gemini API key not configured.', 'website-optimiser' ) );
    }

    $image_url = wp_get_attachment_url( $attachment_id );
    if ( ! $image_url ) {
        return new WP_Error( 'image_url_missing', __( 'Could not get image URL.' ) );
    }

    $image_data = wp_remote_get(
        $image_url,
        array(
            'timeout'   => 30,
            'headers'   => meta_description_boy_get_http_auth_headers(),
            'sslverify' => false,
        )
    );

    if ( is_wp_error( $image_data ) ) {
        return new WP_Error(
            'image_fetch_error',
            sprintf(
                /* translators: %s: error message */
                __( 'Could not fetch image data: %s' ),
                $image_data->get_error_message()
            )
        );
    }

    $prompt = 'Generate a concise, descriptive alt text for this image that would be suitable for web accessibility. Focus on what\'s visible in the image and its key elements. Keep it under 125 characters and don\'t start with \'Image of\' or \'Photo of\'.';

    $response = wp_remote_post(
        website_optimiser_get_gemini_api_url( $api_key ),
        array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode(
                array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array(
                                    'text' => $prompt,
                                ),
                                array(
                                    'inline_data' => array(
                                        'mime_type' => get_post_mime_type( $attachment_id ),
                                        'data'      => base64_encode( wp_remote_retrieve_body( $image_data ) ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                )
            ),
            'timeout' => 30,
        )
    );

    if ( is_wp_error( $response ) ) {
        return new WP_Error(
            'gemini_request_error',
            sprintf( __( 'Gemini API Request Error: %s' ), $response->get_error_message() )
        );
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    if ( 200 !== $response_code ) {
        $body          = wp_remote_retrieve_body( $response );
        $error_data    = json_decode( $body, true );
        $error_message = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'HTTP ' . $response_code;

        return new WP_Error(
            'gemini_response_error',
            sprintf( __( 'Gemini API Response Error: %s' ), $error_message )
        );
    }

    $body     = wp_remote_retrieve_body( $response );
    $data     = json_decode( $body, true );
    $alt_text = isset( $data['candidates'][0]['content']['parts'][0]['text'] )
        ? trim( $data['candidates'][0]['content']['parts'][0]['text'] )
        : '';

    $alt_text = trim( $alt_text, "\"'" );

    if ( '' === $alt_text ) {
        $error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unexpected error generating alt text' );

        return new WP_Error( 'gemini_error', sprintf( __( 'Gemini Error: %s' ), $error_message ) );
    }

    if ( $save ) {
        if ( false === update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text ) ) {
            return new WP_Error( 'save_failed', __( 'Failed to save alt text.' ) );
        }
    }

    return $alt_text;
}

function meta_description_boy_handle_ajax_request() {
    $debug_enabled = get_option( 'meta_description_boy_debug_enabled' );
    if ( $debug_enabled ) {
        error_log( 'Meta Description Boy: AJAX request received. POST data: ' . print_r( $_POST, true ) );
    }

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'meta_description_boy_nonce' ) ) {
        if ( $debug_enabled ) {
            error_log( 'Meta Description Boy: Nonce verification failed' );
        }
        wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

    if ( $debug_enabled ) {
        error_log( 'Meta Description Boy: Processing request for post ID: ' . $post_id );
    }

    $result = meta_description_boy_generate_meta_description_for_post( $post_id );

    if ( is_wp_error( $result ) ) {
        if ( $debug_enabled ) {
            error_log( 'Meta Description Boy: ' . $result->get_error_message() );
        }
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    wp_send_json_success( array( 'description' => $result ) );
}
add_action('wp_ajax_meta_description_boy_generate_description', 'meta_description_boy_handle_ajax_request');

// Handle AJAX request for generating alt text
function meta_description_boy_handle_alt_text_ajax_request() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'meta_description_boy_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Check if user has permission
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

    if (!$attachment_id) {
        wp_send_json_error(array('message' => 'Invalid attachment ID'));
    }

    // Get attachment data
    $attachment = get_post($attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
        wp_send_json_error(array('message' => 'Attachment not found'));
    }

    // Check if it's an image
    if (!wp_attachment_is_image($attachment_id)) {
        wp_send_json_error(array('message' => 'File is not an image'));
    }

    $result = meta_description_boy_generate_alt_text_for_attachment( $attachment_id, false );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    wp_send_json_success( array( 'alt_text' => $result ) );
}
add_action('wp_ajax_meta_description_boy_generate_alt_text', 'meta_description_boy_handle_alt_text_ajax_request');

// Handle AJAX request for saving alt text
function meta_description_boy_save_alt_text_ajax_request() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'meta_description_boy_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Check if user has permission
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
    $alt_text = isset($_POST['alt_text']) ? sanitize_text_field($_POST['alt_text']) : '';

    if (!$attachment_id) {
        wp_send_json_error(array('message' => 'Invalid attachment ID'));
    }

    // Get attachment data
    $attachment = get_post($attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
        wp_send_json_error(array('message' => 'Attachment not found'));
    }

    // Save the alt text
    $result = update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);

    if ($result !== false) {
        wp_send_json_success(array('message' => 'Alt text saved successfully'));
    } else {
        wp_send_json_error(array('message' => 'Failed to save alt text'));
    }
}
add_action('wp_ajax_meta_description_boy_save_alt_text', 'meta_description_boy_save_alt_text_ajax_request');

// Handle AJAX request for bulk alt text generation - get images without alt text
function meta_description_boy_get_images_without_alt_text() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'meta_description_boy_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Check if user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    // Query for images without alt text or with empty alt text
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'post_status' => 'inherit',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => '_wp_attachment_image_alt',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_wp_attachment_image_alt',
                'value' => '',
                'compare' => '='
            )
        )
    );

    $images = get_posts($args);
    $image_data = array();

    foreach ($images as $image) {
        $thumbnail = wp_get_attachment_image_src($image->ID, 'thumbnail');
        $image_data[] = array(
            'id' => $image->ID,
            'title' => $image->post_title,
            'thumbnail' => $thumbnail ? $thumbnail[0] : '',
            'filename' => basename(get_attached_file($image->ID))
        );
    }

    wp_send_json_success(array(
        'images' => $image_data,
        'total' => count($image_data)
    ));
}
add_action('wp_ajax_meta_description_boy_get_images_without_alt_text', 'meta_description_boy_get_images_without_alt_text');

// Handle AJAX request for bulk alt text generation - process single image
function meta_description_boy_bulk_process_single_image() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'meta_description_boy_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Check if user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

    if (!$attachment_id) {
        wp_send_json_error(array('message' => 'Invalid attachment ID'));
    }

    // Get attachment data
    $attachment = get_post($attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
        wp_send_json_error(array('message' => 'Attachment not found'));
    }

    // Check if it's an image
    if (!wp_attachment_is_image($attachment_id)) {
        wp_send_json_error(array('message' => 'File is not an image'));
    }

    $result = meta_description_boy_generate_alt_text_for_attachment( $attachment_id, true );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    wp_send_json_success( array( 'alt_text' => $result ) );
}
add_action('wp_ajax_meta_description_boy_bulk_process_single_image', 'meta_description_boy_bulk_process_single_image');

// Handle AJAX request for clearing cache
function meta_description_boy_handle_clear_cache() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'meta_description_boy_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Check if user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    // Clear all plugin caches
    meta_description_boy_force_clear_h1_cache();
    meta_description_boy_clear_sitemap_cache();
    meta_description_boy_clear_robots_cache();
    if (function_exists('meta_description_boy_clear_llms_cache')) {
        meta_description_boy_clear_llms_cache();
    }

    // Clear meta description cache if it exists
    delete_transient('meta_description_boy_meta_description_stats');

    // Clear alt text cache if it exists
    delete_transient('meta_description_boy_alt_text_stats');

    // Clear featured image cache if it exists
    delete_transient('meta_description_boy_featured_image_stats');

    wp_send_json_success(array('message' => 'All caches cleared successfully'));
}
add_action('wp_ajax_meta_description_boy_clear_cache', 'meta_description_boy_handle_clear_cache');

// Handle AJAX request for refreshing analysis
function meta_description_boy_handle_refresh_analysis() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'meta_description_boy_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    // Check if user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    // Clear caches first
    meta_description_boy_clear_sitemap_cache();
    meta_description_boy_clear_robots_cache();
    if (function_exists('meta_description_boy_clear_llms_cache')) {
        meta_description_boy_clear_llms_cache();
    }
    meta_description_boy_force_clear_h1_cache();

    // Re-run the H1 analysis so results are stored in the database
    if (function_exists('meta_description_boy_perform_h1_analysis')) {
        meta_description_boy_perform_h1_analysis();
    }
    if (function_exists('meta_description_boy_check_sitemap')) {
        meta_description_boy_check_sitemap();
    }
    if (function_exists('meta_description_boy_check_llms_txt')) {
        meta_description_boy_check_llms_txt();
    }

    wp_send_json_success(array('message' => 'Analysis refreshed successfully'));
}
add_action('wp_ajax_meta_description_boy_refresh_analysis', 'meta_description_boy_handle_refresh_analysis');

// Automatically generate alt text when an image is uploaded
function meta_description_boy_auto_generate_alt_text($attachment_id) {
    $debug_enabled = get_option('meta_description_boy_debug_enabled');

    if ($debug_enabled) {
        error_log('Meta Description Boy: Starting auto alt text generation for attachment ' . $attachment_id);
    }

    // Check if auto alt text generation is enabled
    $auto_alt_text_enabled = get_option('meta_description_boy_auto_alt_text', 1);
    if (!$auto_alt_text_enabled) {
        if ($debug_enabled) {
            error_log('Meta Description Boy: Auto alt text generation is disabled');
        }
        return;
    }

    // Check if API key is configured
    if ( ! website_optimiser_ai_features_enabled() || ! website_optimiser_gemini_api_key_configured() ) {
        if ($debug_enabled) {
            error_log('Meta Description Boy: API key not configured or AI disabled');
        }
        return;
    }

    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        if ( $debug_enabled ) {
            error_log( 'Meta Description Boy: Attachment ' . $attachment_id . ' is not an image' );
        }
        return;
    }

    $existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
    if ( ! empty( $existing_alt ) ) {
        if ( $debug_enabled ) {
            error_log( 'Meta Description Boy: Alt text already exists for attachment ' . $attachment_id . ': ' . $existing_alt );
        }
        return;
    }

    $alt_text = meta_description_boy_generate_alt_text_for_attachment( $attachment_id, true );

    if ( is_wp_error( $alt_text ) ) {
        if ( $debug_enabled ) {
            error_log( 'Meta Description Boy: Auto alt text failed for attachment ' . $attachment_id . ': ' . $alt_text->get_error_message() );
        }
        return;
    }

    $auto_generated                      = get_option( 'meta_description_boy_auto_generated_alt_text', array() );
    $auto_generated[ $attachment_id ]    = array(
        'alt_text'     => $alt_text,
        'generated_at' => current_time( 'mysql' ),
    );
    update_option( 'meta_description_boy_auto_generated_alt_text', $auto_generated );

    if ( $debug_enabled ) {
        error_log( 'Meta Description Boy: Auto-generated alt text for attachment ' . $attachment_id . ': ' . $alt_text );
    }
}

// Hook into attachment upload
function meta_description_boy_auto_generate_alt_text_on_upload($metadata, $attachment_id, $context) {
    // Debug logging if enabled
    $debug_enabled = get_option('meta_description_boy_debug_enabled');
    if ($debug_enabled) {
        error_log('Meta Description Boy: wp_generate_attachment_metadata hook triggered for attachment ' . $attachment_id . ' with context: ' . $context);
    }

    // Generate alt text immediately after metadata generation
    meta_description_boy_auto_generate_alt_text($attachment_id);

    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'meta_description_boy_auto_generate_alt_text_on_upload', 20, 3);

// Handle flexible content
function extract_acf_content($data) {
    $content = '';

    if (is_array($data)) {
        foreach ($data as $key => $value) {
            // If it's a "content" key and the value is a string
            if ($key === "content" && is_string($value)) {
                $content .= ' ' . $value;
            }
            // If it's an array, delve deeper (recursive)
            elseif (is_array($value)) {
                $content .= ' ' . extract_acf_content($value);
            }
            // If it's a standard field, simply add it to the content
            elseif (is_string($value)) {
                $content .= ' ' . $value;
            }
        }
    } elseif (is_string($data)) {
        $content = $data;
    }

    return $content;
}

// Debug output
function debug_content_on_admin() {
    $debug_enabled = get_option('meta_description_boy_debug_enabled');
    if ($debug_enabled) {
        global $pagenow;

        // Check if we're editing a post or a page
        if ($pagenow == 'post.php' && isset($_GET['action']) && $_GET['action'] == 'edit') {

            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

            $title = get_the_title($post_id) . ' '; // Equivalent to get_name

            $wc_content = '';
            if ('product' === get_post_type($post_id)) {
                $wc_content = get_post_field('post_excerpt', $post_id);
            }

            $acf_content = '';
            if (function_exists('get_fields')) {
                $acf_data = get_fields($post_id);
                $acf_content_raw = extract_acf_content($acf_data);
                $acf_content = remove_table_content($acf_content_raw);
            }

            $post_content_raw = get_post_field('post_content', $post_id);
            $post_content = $title . ' ' . remove_table_content($post_content_raw) . ' ' . esc_html($acf_content) . ' ' . $wc_content;

            // Echo the content
            echo '<div class="notice notice-info"><p>' . esc_html($post_content) . '</p></div>';
        }
    }
}
add_action('admin_notices', 'debug_content_on_admin');

// Show admin notice for auto-generated alt text
function meta_description_boy_show_auto_alt_text_notices() {
    $auto_generated = get_option('meta_description_boy_auto_generated_alt_text', array());

    if (!empty($auto_generated)) {
        $screen = get_current_screen();

        // Show on upload/media pages and dashboard
        if ($screen && (
            strpos($screen->id, 'upload') !== false ||
            strpos($screen->id, 'media') !== false ||
            strpos($screen->id, 'dashboard') !== false ||
            strpos($screen->id, 'edit') !== false
        )) {
            $count = count($auto_generated);
            $message = sprintf(
                _n(
                    'Alt text was automatically generated for %d image using AI.',
                    'Alt text was automatically generated for %d images using AI.',
                    $count
                ),
                $count
            );

            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Meta Description Boy:</strong> ' . esc_html($message) . '</p>';
            echo '</div>';

            // Clear the notifications after showing them only after some time
            $oldest_generated = reset($auto_generated);
            $time_diff = time() - strtotime($oldest_generated['generated_at']);

            // Clear notifications after 5 minutes or when viewing media library
            if ($time_diff > 300 || strpos($screen->id, 'upload') !== false) {
                delete_option('meta_description_boy_auto_generated_alt_text');
            }
        }
    }
}
add_action('admin_notices', 'meta_description_boy_show_auto_alt_text_notices');

// Remove table content from being sent to Gemini
function remove_table_content($content) {
    return preg_replace('/<table.*?>.*?<\/table>/si', '', $content);
}

// Add custom field and button in Quick Edit
function meta_description_boy_quick_edit($column_name, $post_type) {
    if ('meta_description_boy_yst_meta_description' === $column_name && in_array($post_type, ['post', 'page'])) {
        wp_nonce_field('meta_description_boy_nonce', 'meta_description_boy_nonce');
        echo '<fieldset class="inline-edit-col-right">
                <div class="inline-edit-col">
                    <div class="mdb-error-notice"></div>
                    <label>
                        <span class="title">Meta Description</span>
                        <span class="input-text-wrap">
                            <textarea name="mdb-yoast-meta-description" class="ptitle"></textarea>
                        </span>
                    </label>
                    <button type="button" class="button generate-meta-description" data-post-id="">
                        Generate Meta Description
                    </button>
                </div>
            </fieldset>';
    }
}
add_action('quick_edit_custom_box', 'meta_description_boy_quick_edit', 10, 2);

function add_yoast_meta_desc_column($columns) {
    $columns['meta_description_boy_yst_meta_description'] = 'Meta Description';
    return $columns;
}
add_filter('manage_posts_columns', 'add_yoast_meta_desc_column');
add_filter('manage_pages_columns', 'add_yoast_meta_desc_column');

function display_yoast_meta_desc_column($column, $post_id) {
    if ($column == 'meta_description_boy_yst_meta_description') {
        // Fetch and display the Yoast meta description for the post
        echo get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
    }
}
add_action('manage_posts_custom_column', 'display_yoast_meta_desc_column', 10, 2);
add_action('manage_pages_custom_column', 'display_yoast_meta_desc_column', 10, 2);

function save_yoast_meta_description() {
    if (!isset($_POST['meta_description_boy_nonce']) || !wp_verify_nonce($_POST['meta_description_boy_nonce'], 'meta_description_boy_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $meta_desc = isset($_POST['meta_desc']) ? sanitize_text_field($_POST['meta_desc']) : '';

    if (!empty($post_id)) {
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_desc);
        echo 'Meta description updated successfully';
    } else {
        echo 'Error: Post ID or meta description is missing';
    }
    wp_die();
}
add_action('wp_ajax_save_yoast_meta_description', 'save_yoast_meta_description');

function enqueue_meta_description_boy_quick_edit_script($hook) {
    if ('edit.php' !== $hook) {
        return;
    }
    wp_enqueue_script('meta-description-boy-quick-edit', plugin_dir_url(__FILE__) . 'quick-edit.js', array('jquery'), '1.0', true);
    wp_localize_script('meta-description-boy-quick-edit', 'ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));
}
add_action('admin_enqueue_scripts', 'enqueue_meta_description_boy_quick_edit_script');

// Updater
class My_Plugin_Updater {

    private $current_version;
    private $api_url;
    private $plugin_basename;

    public function __construct($current_version, $api_url, $plugin_basename) {
        $this->current_version = $current_version;
        $this->api_url = $api_url;
        $this->plugin_basename = $plugin_basename;
    }

    public function check_for_update() {
        $debug_enabled = get_option('meta_description_boy_debug_enabled');

        if ($debug_enabled) {
            error_log('Website Optimiser Updater: Making API call to: ' . $this->api_url);
        }

        $response = wp_remote_get($this->api_url);
        if (is_wp_error($response)) {
            if ($debug_enabled) {
                error_log('Website Optimiser Updater: API call failed: ' . $response->get_error_message());
            }
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($debug_enabled) {
            error_log('Website Optimiser Updater: API response: ' . $body);
        }

        if ($data && isset($data['version']) && version_compare($data['version'], $this->current_version, '>')) {
            if ($debug_enabled) {
                error_log('Website Optimiser Updater: Version comparison - Remote: ' . $data['version'] . ' vs Local: ' . $this->current_version);
            }
            return $data;
        }

        if ($debug_enabled) {
            error_log('Website Optimiser Updater: No update needed or invalid response data');
        }

        return false;
    }
}

function meta_description_boy_check_for_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    // Get the plugin basename correctly
    $plugin_basename = plugin_basename(__FILE__);

    // Check if this plugin is in the checked list
    if (!isset($transient->checked[$plugin_basename])) {
        return $transient;
    }

    // Get current version from plugin header
    $plugin_data = get_plugin_data(__FILE__);
    $current_version = $plugin_data['Version'];

    // Debug logging if enabled
    $debug_enabled = get_option('meta_description_boy_debug_enabled');
    if ($debug_enabled) {
        error_log('Website Optimiser Updater: Checking for updates. Current version: ' . $current_version);
        error_log('Website Optimiser Updater: Plugin basename: ' . $plugin_basename);
    }

    $updater = new My_Plugin_Updater($current_version, 'https://raw.githubusercontent.com/nkatsambiris/website-optimiser/main/updates.json', $plugin_basename);
    $update_data = $updater->check_for_update();

    if ($update_data) {
        if ($debug_enabled) {
            error_log('Website Optimiser Updater: Update available. New version: ' . $update_data['version']);
        }

        $transient->response[$plugin_basename] = (object) array(
            'slug' => dirname($plugin_basename),
            'plugin' => $plugin_basename,
            'new_version' => $update_data['version'],
            'url' => isset($update_data['details_url']) ? $update_data['details_url'] : '',
            'package' => $update_data['download_url'],
            'icons' => array(),
            'banners' => array(),
            'tested' => isset($update_data['tested']) ? $update_data['tested'] : '',
            'requires_php' => isset($update_data['requires_php']) ? $update_data['requires_php'] : '',
            'compatibility' => new stdClass(),
        );
    } else {
        if ($debug_enabled) {
            error_log('Website Optimiser Updater: No update available or error checking for updates');
        }
    }

    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'meta_description_boy_check_for_update');

// Displayed in the plugin info window
function meta_description_boy_plugin_info($false, $action, $args) {
    $plugin_basename = plugin_basename(__FILE__);
    $plugin_slug = dirname($plugin_basename);

    if (isset($args->slug) && $args->slug === $plugin_slug) {
        $response = wp_remote_get('https://raw.githubusercontent.com/nkatsambiris/website-optimiser/main/plugin-info.json');
        if (!is_wp_error($response)) {
            $plugin_info = json_decode(wp_remote_retrieve_body($response));
            if ($plugin_info) {
                return (object) array(
                    'slug' => $plugin_slug,
                    'name' => $plugin_info->name,
                    'version' => $plugin_info->version,
                    'author' => $plugin_info->author,
                    'homepage' => isset($plugin_info->homepage) ? $plugin_info->homepage : '',
                    'requires' => $plugin_info->requires,
                    'tested' => $plugin_info->tested,
                    'requires_php' => isset($plugin_info->requires_php) ? $plugin_info->requires_php : '',
                    'last_updated' => $plugin_info->last_updated,
                    'sections' => array(
                        'description' => $plugin_info->sections->description,
                        'changelog' => $plugin_info->sections->changelog
                    ),
                    'download_link' => $plugin_info->download_link,
                    'banners' => array(
                        'low' => 'https://raw.githubusercontent.com/nkatsambiris/website-optimiser/main/banner-772x250.jpg',
                        'high' => 'https://raw.githubusercontent.com/nkatsambiris/website-optimiser/main/banner-1544x500.jpg'
                    ),
                    'icons' => array(),
                );
            }
        }
    }
    return $false;
}
add_filter('plugins_api', 'meta_description_boy_plugin_info', 10, 3);

// Used to handle the plugin folder name during updates
function meta_description_boy_upgrader_package_options($options) {
    $plugin_basename = plugin_basename(__FILE__);

    if (isset($options['hook_extra']['plugin']) && $options['hook_extra']['plugin'] === $plugin_basename) {
        $plugin_slug = dirname($plugin_basename);
        $options['destination'] = WP_PLUGIN_DIR . '/' . $plugin_slug;
        $options['clear_destination'] = true; // Overwrite the files
    }
    return $options;
}
add_filter('upgrader_package_options', 'meta_description_boy_upgrader_package_options');
