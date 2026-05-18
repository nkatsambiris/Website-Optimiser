<?php
/**
 * URL Search and Replace functionality for Website Optimiser.
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Get the suggested old development URL for this site.
 */
function website_optimiser_get_suggested_development_url() {
    $live_url = home_url();
    $parts = wp_parse_url($live_url);

    if (empty($parts['host'])) {
        return '';
    }

    $scheme = $parts['scheme'] ?? 'https';
    $host = preg_replace('/^www\./', '', $parts['host']);

    return $scheme . '://dev.' . $host;
}

/**
 * Escape a database identifier that came from WordPress database metadata.
 */
function website_optimiser_escape_db_identifier($identifier) {
    return str_replace('`', '``', $identifier);
}

/**
 * Scan WordPress database tables for development URLs.
 */
function website_optimiser_scan_development_urls() {
    $cached = get_transient('website_optimiser_development_url_scan');
    if ($cached !== false && is_array($cached)) {
        return $cached;
    }

    global $wpdb;

    $search_pattern = '%://dev.%';
    $matching_tables = array();
    $matches = 0;
    $tables = (array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix) . '%'));

    foreach ($tables as $table) {
        $table_name = website_optimiser_escape_db_identifier($table);
        $columns = (array) $wpdb->get_results('SHOW COLUMNS FROM `' . $table_name . '`');

        foreach ($columns as $column) {
            if (empty($column->Field) || empty($column->Type)) {
                continue;
            }

            if (!preg_match('/char|text|blob|json/i', $column->Type)) {
                continue;
            }

            $column_name = website_optimiser_escape_db_identifier($column->Field);
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM `' . $table_name . '` WHERE `' . $column_name . '` LIKE %s',
                    $search_pattern
                )
            );

            if ($count > 0) {
                $matches += $count;
                $matching_tables[$table] = true;
            }
        }
    }

    $result = array(
        'matches' => $matches,
        'tables' => array_keys($matching_tables),
    );

    set_transient('website_optimiser_development_url_scan', $result, 10 * MINUTE_IN_SECONDS);

    return $result;
}

/**
 * Check Better Search Replace status and whether development URLs remain.
 */
function website_optimiser_check_url_search_replace_status() {
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    $confirmed = get_option('website_optimiser_url_search_replace_confirmed', false);
    $confirmed_by = get_option('website_optimiser_url_search_replace_confirmed_by', '');
    $confirmed_date = get_option('website_optimiser_url_search_replace_confirmed_date', '');
    $plugin = 'better-search-replace/better-search-replace.php';
    $installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin);
    $active = is_plugin_active($plugin);
    $version = '';
    $live_url = home_url();
    $suggested_old_url = website_optimiser_get_suggested_development_url();
    $scan = website_optimiser_scan_development_urls();
    $current_site_is_dev = (bool) preg_match('/:\/\/dev\./i', $live_url);

    if ($installed) {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
        $version = $plugin_data['Version'] ?? '';
    }

    if (!$installed) {
        return array(
            'installed' => false,
            'active' => false,
            'version' => '',
            'live_url' => $live_url,
            'suggested_old_url' => $suggested_old_url,
            'dev_matches' => $scan['matches'],
            'dev_tables' => $scan['tables'],
            'confirmed' => false,
            'confirmed_by' => '',
            'confirmed_date' => '',
            'status' => 'Plugin Missing',
            'message' => 'Better Search Replace is not installed',
            'class' => 'status-error'
        );
    }

    if (!$active) {
        return array(
            'installed' => true,
            'active' => false,
            'version' => $version,
            'live_url' => $live_url,
            'suggested_old_url' => $suggested_old_url,
            'dev_matches' => $scan['matches'],
            'dev_tables' => $scan['tables'],
            'confirmed' => false,
            'confirmed_by' => '',
            'confirmed_date' => '',
            'status' => 'Plugin Inactive',
            'message' => 'Better Search Replace is installed but not activated',
            'class' => 'status-warning'
        );
    }

    if ($current_site_is_dev) {
        return array(
            'installed' => true,
            'active' => true,
            'version' => $version,
            'live_url' => $live_url,
            'suggested_old_url' => $suggested_old_url,
            'dev_matches' => $scan['matches'],
            'dev_tables' => $scan['tables'],
            'confirmed' => false,
            'confirmed_by' => '',
            'confirmed_date' => '',
            'status' => 'Development URL Active',
            'message' => 'The current site URL still appears to be a development URL',
            'class' => 'status-error'
        );
    }

    if ($scan['matches'] > 0) {
        return array(
            'installed' => true,
            'active' => true,
            'version' => $version,
            'live_url' => $live_url,
            'suggested_old_url' => $suggested_old_url,
            'dev_matches' => $scan['matches'],
            'dev_tables' => $scan['tables'],
            'confirmed' => false,
            'confirmed_by' => '',
            'confirmed_date' => '',
            'status' => 'Dev URLs Found',
            'message' => 'Development URL references were found in the database',
            'class' => 'status-warning'
        );
    }

    if (!$confirmed) {
        return array(
            'installed' => true,
            'active' => true,
            'version' => $version,
            'live_url' => $live_url,
            'suggested_old_url' => $suggested_old_url,
            'dev_matches' => 0,
            'dev_tables' => array(),
            'confirmed' => false,
            'confirmed_by' => '',
            'confirmed_date' => '',
            'status' => 'Confirmation Pending',
            'message' => 'Please confirm that URL search and replace has been completed',
            'class' => 'status-warning'
        );
    }

    return array(
        'installed' => true,
        'active' => true,
        'version' => $version,
        'live_url' => $live_url,
        'suggested_old_url' => $suggested_old_url,
        'dev_matches' => 0,
        'dev_tables' => array(),
        'confirmed' => true,
        'confirmed_by' => $confirmed_by,
        'confirmed_date' => $confirmed_date,
        'status' => 'Search Replace Confirmed',
        'message' => 'URL search and replace has been confirmed and no dev URL references were found',
        'class' => 'status-good'
    );
}

/**
 * Handle AJAX request to confirm URL search and replace completion.
 */
function website_optimiser_confirm_url_search_replace() {
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(wp_json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $confirmed_by = sanitize_text_field($_POST['confirmed_by'] ?? '');

    if (empty($confirmed_by)) {
        wp_die(wp_json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    update_option('website_optimiser_url_search_replace_confirmed', true);
    update_option('website_optimiser_url_search_replace_confirmed_by', $confirmed_by);
    update_option('website_optimiser_url_search_replace_confirmed_date', current_time('mysql'));
    delete_transient('website_optimiser_development_url_scan');

    wp_die(wp_json_encode(array('success' => true, 'message' => 'URL search and replace confirmation saved')));
}
add_action('wp_ajax_website_optimiser_confirm_url_search_replace', 'website_optimiser_confirm_url_search_replace');

/**
 * Handle AJAX request to reset URL search and replace confirmation.
 */
function website_optimiser_reset_url_search_replace_confirmation() {
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(wp_json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    delete_option('website_optimiser_url_search_replace_confirmed');
    delete_option('website_optimiser_url_search_replace_confirmed_by');
    delete_option('website_optimiser_url_search_replace_confirmed_date');
    delete_transient('website_optimiser_development_url_scan');

    wp_die(wp_json_encode(array('success' => true, 'message' => 'URL search and replace confirmation reset')));
}
add_action('wp_ajax_website_optimiser_reset_url_search_replace_confirmation', 'website_optimiser_reset_url_search_replace_confirmation');

/**
 * Render URL Search and Replace section.
 */
function website_optimiser_render_url_search_replace_section() {
    $status = website_optimiser_check_url_search_replace_status();
    $search_replace_url = admin_url('tools.php?page=better-search-replace&tab=bsr_search_replace');
    ?>
    <div class="seo-stat-item <?php echo esc_attr($status['class']); ?>">
        <div class="stat-icon">🔁</div>
        <div class="stat-content">
            <h4>URL Search and Replace</h4>
            <div class="stat-status <?php echo esc_attr($status['class']); ?>">
                <?php echo esc_html($status['status']); ?>
            </div>
            <div class="stat-label">
                <?php echo esc_html($status['message']); ?>
                <?php if (!empty($status['version'])): ?>
                    <br><small>Better Search Replace: v<?php echo esc_html($status['version']); ?></small>
                <?php endif; ?>
                <br><small>Search for: <code><?php echo esc_html($status['suggested_old_url']); ?></code></small>
                <br><small>Replace with: <code><?php echo esc_html($status['live_url']); ?></code></small>
                <?php if ($status['dev_matches'] > 0): ?>
                    <br><small>Detected <?php echo esc_html($status['dev_matches']); ?> dev URL reference(s) across <?php echo esc_html(count($status['dev_tables'])); ?> table(s).</small>
                <?php endif; ?>
                <?php if ($status['confirmed']): ?>
                    <br><br><small><strong>Confirmed by:</strong> <?php echo esc_html($status['confirmed_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo esc_html(date('M j, Y g:i A', strtotime($status['confirmed_date']))); ?></small>
                <?php endif; ?>
                <br><br><small><em>Run a dry run first, then run live once the results look right.</em></small>
            </div>
            <div class="stat-action">
                <?php if (!$status['installed']): ?>
                    <a href="<?php echo esc_url(admin_url('plugin-install.php?s=better+search+replace&tab=search&type=term')); ?>" class="button button-small">
                        Install Better Search Replace
                    </a>
                <?php elseif (!$status['active']): ?>
                    <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-small">
                        Activate Plugin
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url($search_replace_url); ?>" class="button button-small">
                        Open Search Replace
                    </a>
                    <a href="<?php echo esc_url(admin_url('tools.php?page=better-search-replace&tab=bsr_help')); ?>" class="button button-small" style="margin-left: 5px;">
                        Help
                    </a>
                    <?php if ($status['confirmed']): ?>
                        <button type="button" class="button button-small" onclick="resetUrlSearchReplaceConfirmation()" style="margin-left: 5px;">
                            Reset Confirmation
                        </button>
                    <?php else: ?>
                        <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa; margin-top: 10px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                                Have you completed the URL search and replace?
                            </label>
                            <div style="margin-bottom: 12px; font-size: 13px; color: #666;">
                                <strong>Confirm after completing:</strong><br>
                                &bull; Run a dry run in Better Search Replace<br>
                                &bull; Replace <code><?php echo esc_html($status['suggested_old_url']); ?></code> with <code><?php echo esc_html($status['live_url']); ?></code><br>
                                &bull; Run the live search and replace once the dry run looks correct
                            </div>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" id="url-search-replace-confirmation-checkbox" style="margin-right: 5px;">
                                Confirm that the URL search and replace has been completed
                            </label>
                            <input type="text" id="url-search-replace-confirmed-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                            <button type="button" class="button button-small" onclick="confirmUrlSearchReplace()" disabled>
                                Confirm Search Replace
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('url-search-replace-confirmation-checkbox');
        const nameField = document.getElementById('url-search-replace-confirmed-by-name');
        const button = document.querySelector('button[onclick="confirmUrlSearchReplace()"]');

        if (checkbox && nameField && button) {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    nameField.disabled = false;
                    nameField.focus();
                } else {
                    nameField.disabled = true;
                    nameField.value = '';
                    button.disabled = true;
                }
            });

            nameField.addEventListener('input', function() {
                button.disabled = this.value.trim() === '';
            });
        }
    });

    function confirmUrlSearchReplace() {
        const checkbox = document.getElementById('url-search-replace-confirmation-checkbox');
        const nameField = document.getElementById('url-search-replace-confirmed-by-name');

        if (!checkbox || !checkbox.checked) {
            alert('Please check the confirmation checkbox first.');
            return;
        }

        const confirmedBy = nameField.value.trim();
        if (!confirmedBy) {
            alert('Please enter your name.');
            nameField.focus();
            return;
        }

        if (!confirm('Are you sure the URL search and replace has been completed? This confirmation will be tracked.')) {
            return;
        }

        jQuery.post(ajaxurl, {
            action: 'website_optimiser_confirm_url_search_replace',
            confirmed_by: confirmedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('URL search and replace confirmation saved successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetUrlSearchReplaceConfirmation() {
        if (!confirm('Are you sure you want to reset the URL search and replace confirmation? This will remove the current confirmation record.')) {
            return;
        }

        jQuery.post(ajaxurl, {
            action: 'website_optimiser_reset_url_search_replace_confirmation',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('URL search and replace confirmation reset successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }
    </script>
    <?php
}
