<?php
/**
 * Google Search Console Sitemap functionality for Website Optimiser Plugin
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

/**
 * Check Google Search Console sitemap submission status
 */
function meta_description_boy_check_google_search_console_sitemap_status() {
    $sitemap_approved = get_option('meta_description_boy_google_search_console_sitemap_approved', false);
    $approved_by = get_option('meta_description_boy_google_search_console_sitemap_approved_by', '');
    $approved_date = get_option('meta_description_boy_google_search_console_sitemap_approved_date', '');

    if ($sitemap_approved) {
        return array(
            'sitemap_approved' => true,
            'approved_by' => $approved_by,
            'approved_date' => $approved_date,
            'status' => 'Google Search Console Sitemap Submitted',
            'message' => 'Confirmed sitemap has been added to Google Search Console',
            'class' => 'status-good'
        );
    }

    return array(
        'sitemap_approved' => false,
        'approved_by' => '',
        'approved_date' => '',
        'status' => 'Google Search Console Sitemap Pending',
        'message' => 'Please confirm that the sitemap has been added to Google Search Console',
        'class' => 'status-warning'
    );
}

/**
 * Handle AJAX request to approve Google Search Console sitemap submission
 */
function meta_description_boy_approve_google_search_console_sitemap() {
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');

    if (empty($approved_by)) {
        wp_die(json_encode(array('success' => false, 'message' => 'Name is required')));
    }

    update_option('meta_description_boy_google_search_console_sitemap_approved', true);
    update_option('meta_description_boy_google_search_console_sitemap_approved_by', $approved_by);
    update_option('meta_description_boy_google_search_console_sitemap_approved_date', current_time('mysql'));

    wp_die(json_encode(array('success' => true, 'message' => 'Google Search Console sitemap confirmation saved')));
}
add_action('wp_ajax_meta_description_boy_approve_google_search_console_sitemap', 'meta_description_boy_approve_google_search_console_sitemap');

/**
 * Handle AJAX request to reset Google Search Console sitemap approval
 */
function meta_description_boy_reset_google_search_console_sitemap_approval() {
    if (!check_ajax_referer('meta_description_boy_nonce', 'nonce', false) || !current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'Unauthorized')));
    }

    delete_option('meta_description_boy_google_search_console_sitemap_approved');
    delete_option('meta_description_boy_google_search_console_sitemap_approved_by');
    delete_option('meta_description_boy_google_search_console_sitemap_approved_date');

    wp_die(json_encode(array('success' => true, 'message' => 'Google Search Console sitemap approval reset')));
}
add_action('wp_ajax_meta_description_boy_reset_google_search_console_sitemap_approval', 'meta_description_boy_reset_google_search_console_sitemap_approval');

/**
 * Render Google Search Console sitemap section
 */
function meta_description_boy_render_google_search_console_sitemap_section() {
    $sitemap_status = meta_description_boy_check_google_search_console_sitemap_status();
    ?>
    <div class="seo-stat-item <?php echo $sitemap_status['class']; ?>">
        <div class="stat-icon">🔎</div>
        <div class="stat-content">
            <h4>Google Search Console Sitemap</h4>
            <div class="stat-status <?php echo $sitemap_status['class']; ?>">
                <?php echo $sitemap_status['status']; ?>
            </div>
            <div class="stat-label">
                <?php echo $sitemap_status['message']; ?>

                <?php if ($sitemap_status['sitemap_approved']): ?>
                    <br><br><small><strong>Approved by:</strong> <?php echo esc_html($sitemap_status['approved_by']); ?></small>
                    <br><small><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($sitemap_status['approved_date'])); ?></small>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <?php if ($sitemap_status['sitemap_approved']): ?>
                    <button type="button" class="button button-small" onclick="resetGoogleSearchConsoleSitemapApproval()">
                        Reset Approval
                    </button>
                <?php else: ?>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="google-search-console-sitemap-checkbox" style="margin-right: 5px;">
                            Confirm that this website's sitemap has been added to Google Search Console
                        </label>
                        <input type="text" id="google-search-console-sitemap-approved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approveGoogleSearchConsoleSitemap()" disabled>
                            Confirm Search Console Sitemap
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkbox = document.getElementById('google-search-console-sitemap-checkbox');
        const nameField = document.getElementById('google-search-console-sitemap-approved-by-name');
        const button = document.querySelector('button[onclick="approveGoogleSearchConsoleSitemap()"]');

        if (checkbox && nameField && button) {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    nameField.disabled = false;
                    nameField.focus();
                    nameField.addEventListener('input', function() {
                        button.disabled = this.value.trim() === '';
                    });
                } else {
                    nameField.disabled = true;
                    nameField.value = '';
                    button.disabled = true;
                }
            });
        }
    });

    function approveGoogleSearchConsoleSitemap() {
        const checkbox = document.getElementById('google-search-console-sitemap-checkbox');
        const nameField = document.getElementById('google-search-console-sitemap-approved-by-name');

        if (!checkbox || !checkbox.checked) {
            alert('Please check the confirmation checkbox first.');
            return;
        }

        const approvedBy = nameField.value.trim();
        if (!approvedBy) {
            alert('Please enter your name.');
            nameField.focus();
            return;
        }

        if (!confirm('Are you sure that this sitemap has been added to Google Search Console? This confirmation will be tracked.')) {
            return;
        }

        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_approve_google_search_console_sitemap',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Google Search Console sitemap confirmation saved successfully.');
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }).fail(function() {
            alert('Error processing request. Please try again.');
        });
    }

    function resetGoogleSearchConsoleSitemapApproval() {
        if (!confirm('Are you sure you want to reset the Google Search Console sitemap approval? This will remove the current confirmation record.')) {
            return;
        }

        jQuery.post(ajaxurl, {
            action: 'meta_description_boy_reset_google_search_console_sitemap_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            const result = JSON.parse(response);
            if (result.success) {
                alert('Google Search Console sitemap approval reset successfully.');
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
