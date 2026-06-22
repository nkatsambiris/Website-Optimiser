<?php
/**
 * Google PageSpeed Insights checks for Website Optimiser.
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

const WEBSITE_OPTIMISER_PAGESPEED_RESULTS_OPTION = 'website_optimiser_pagespeed_results';
const WEBSITE_OPTIMISER_PAGESPEED_API_KEY_OPTION = 'website_optimiser_pagespeed_api_key';
const WEBSITE_OPTIMISER_PAGESPEED_PASSING_SCORE  = 90;

/**
 * Get the saved PageSpeed Insights result.
 */
function website_optimiser_get_pagespeed_results() {
    $results = get_option( WEBSITE_OPTIMISER_PAGESPEED_RESULTS_OPTION, array() );

    return is_array( $results ) ? $results : array();
}

/**
 * Get the PageSpeed Insights API key, if one is configured.
 *
 * @return string
 */
function website_optimiser_get_pagespeed_api_key() {
    if ( defined( 'WEBSITE_OPTIMISER_PAGESPEED_API_KEY' ) && WEBSITE_OPTIMISER_PAGESPEED_API_KEY ) {
        return (string) WEBSITE_OPTIMISER_PAGESPEED_API_KEY;
    }

    $env_key = getenv( 'PAGESPEED_INSIGHTS_API_KEY' );
    if ( ! empty( $env_key ) ) {
        return (string) $env_key;
    }

    return (string) get_option( WEBSITE_OPTIMISER_PAGESPEED_API_KEY_OPTION, '' );
}

/**
 * Convert a Lighthouse score to a whole-number percentage.
 *
 * @param mixed $score Lighthouse category score.
 * @return int|null
 */
function website_optimiser_format_pagespeed_score( $score ) {
    if ( ! is_numeric( $score ) ) {
        return null;
    }

    return (int) round( (float) $score * 100 );
}

/**
 * Run PageSpeed Insights for a single strategy.
 *
 * @param string $strategy mobile or desktop.
 * @return array|WP_Error
 */
function website_optimiser_run_pagespeed_strategy( $strategy ) {
    $site_url   = home_url( '/' );
    $categories = array( 'performance', 'accessibility', 'best-practices', 'seo' );
    $api_url    = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . http_build_query(
        array(
            'url'      => $site_url,
            'strategy' => $strategy,
        ),
        '',
        '&',
        PHP_QUERY_RFC3986
    );

    foreach ( $categories as $category ) {
        $api_url .= '&category=' . rawurlencode( $category );
    }

    $api_key = website_optimiser_get_pagespeed_api_key();
    if ( '' !== $api_key ) {
        $api_url .= '&key=' . rawurlencode( $api_key );
    }

    $response = wp_remote_get(
        $api_url,
        array(
            'timeout' => 45,
        )
    );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    $body          = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( 200 !== $response_code || ! is_array( $body ) ) {
        $message = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'PageSpeed Insights returned an unexpected response.', 'website-optimiser' );
        $code    = (int) $response_code;

        if ( 429 === $code || false !== stripos( $message, 'quota' ) ) {
            $message = __( 'PageSpeed Insights quota exceeded. Add a PageSpeed API key in Website Optimiser settings, or try again after the quota resets.', 'website-optimiser' );
        }

        return new WP_Error(
            'pagespeed_request_failed',
            $message,
            array( 'status' => $code )
        );
    }

    if ( empty( $body['lighthouseResult']['categories'] ) || ! is_array( $body['lighthouseResult']['categories'] ) ) {
        return new WP_Error(
            'pagespeed_categories_missing',
            __( 'PageSpeed Insights did not return Lighthouse category scores.', 'website-optimiser' )
        );
    }

    $categories = $body['lighthouseResult']['categories'];
    $scores     = array(
        'performance'    => website_optimiser_format_pagespeed_score( $categories['performance']['score'] ?? null ),
        'accessibility'  => website_optimiser_format_pagespeed_score( $categories['accessibility']['score'] ?? null ),
        'best_practices' => website_optimiser_format_pagespeed_score( $categories['best-practices']['score'] ?? null ),
        'seo'            => website_optimiser_format_pagespeed_score( $categories['seo']['score'] ?? null ),
    );

    return array(
        'strategy' => $strategy,
        'scores'   => $scores,
    );
}

/**
 * Run both mobile and desktop PageSpeed Insights checks and save the result.
 *
 * @return array|WP_Error
 */
function website_optimiser_run_pagespeed_insights() {
    $strategies = array( 'mobile', 'desktop' );
    $results    = array();
    $all_scores = array();

    foreach ( $strategies as $strategy ) {
        $strategy_result = website_optimiser_run_pagespeed_strategy( $strategy );

        if ( is_wp_error( $strategy_result ) ) {
            return $strategy_result;
        }

        $results[ $strategy ] = $strategy_result['scores'];
        $all_scores           = array_merge( $all_scores, array_values( $strategy_result['scores'] ) );
    }

    $valid_scores = array_filter(
        $all_scores,
        static function ( $score ) {
            return null !== $score;
        }
    );

    if ( count( $valid_scores ) !== count( $all_scores ) ) {
        return new WP_Error(
            'pagespeed_incomplete_scores',
            __( 'PageSpeed Insights returned incomplete category scores.', 'website-optimiser' )
        );
    }

    $lowest_score = min( $valid_scores );
    $passed       = $lowest_score >= WEBSITE_OPTIMISER_PAGESPEED_PASSING_SCORE;

    $saved_results = array(
        'url'          => home_url( '/' ),
        'checked_at'   => time(),
        'passing_score' => WEBSITE_OPTIMISER_PAGESPEED_PASSING_SCORE,
        'lowest_score' => $lowest_score,
        'passed'       => $passed,
        'results'      => $results,
    );

    update_option( WEBSITE_OPTIMISER_PAGESPEED_RESULTS_OPTION, $saved_results, false );

    return $saved_results;
}

/**
 * Check PageSpeed status from the saved result.
 */
function website_optimiser_check_pagespeed_insights_status() {
    $results  = website_optimiser_get_pagespeed_results();
    $resolved = get_option( 'website_optimiser_pagespeed_resolved', false );

    if ( empty( $results ) && ! $resolved ) {
        return array(
            'class'   => 'status-warning',
            'status'  => 'Not Run',
            'message' => 'Run PageSpeed Insights to check mobile and desktop Lighthouse scores.',
            'results' => array(),
        );
    }

    if ( ! empty( $results['passed'] ) || $resolved ) {
        $status_text = ! empty( $results['passed'] ) ? 'Passed' : 'Manually Resolved';
        return array(
            'class'   => 'status-good',
            'status'  => $status_text,
            'message' => ! empty( $results['passed'] ) ? 'All PageSpeed scores are 90% or higher.' : 'PageSpeed Insights has been manually marked as resolved.',
            'results' => $results,
        );
    }

    return array(
        'class'   => 'status-warning',
        'status'  => 'Needs Work',
        'message' => 'One or more PageSpeed scores are below 90%.',
        'results' => $results,
    );
}

/**
 * Render one PageSpeed strategy score row.
 *
 * @param string $label Strategy label.
 * @param array  $scores Category scores.
 */
function website_optimiser_render_pagespeed_score_row( $label, $scores ) {
    $category_labels = array(
        'performance'    => 'Performance',
        'accessibility'  => 'Accessibility',
        'best_practices' => 'Best Practices',
        'seo'            => 'SEO',
    );
    ?>
    <div style="margin-top: 8px;">
        <strong><?php echo esc_html( $label ); ?>:</strong>
        <?php foreach ( $category_labels as $key => $category_label ) : ?>
            <?php
            $score       = isset( $scores[ $key ] ) ? $scores[ $key ] : null;
            $score_class = ( null !== $score && $score >= WEBSITE_OPTIMISER_PAGESPEED_PASSING_SCORE ) ? 'status-good' : 'status-warning';
            ?>
            <br><small class="<?php echo esc_attr( $score_class ); ?>">
                <?php echo esc_html( $category_label ); ?>:
                <?php echo null === $score ? 'N/A' : esc_html( $score . '%' ); ?>
            </small>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Render PageSpeed Insights section.
 */
function website_optimiser_render_pagespeed_insights_section() {
    $status  = website_optimiser_check_pagespeed_insights_status();
    $results = $status['results'];
    $api_key = website_optimiser_get_pagespeed_api_key();
    ?>
    <div class="seo-stat-item <?php echo esc_attr( $status['class'] ); ?>">
        <div class="stat-icon">PSI</div>
        <div class="stat-content">
            <h4>PageSpeed Insights</h4>
            <div class="stat-status <?php echo esc_attr( $status['class'] ); ?>">
                <?php echo esc_html( $status['status'] ); ?>
            </div>
            <div class="stat-label">
                <?php echo esc_html( $status['message'] ); ?>

                <?php if ( ! empty( $results['checked_at'] ) ) : ?>
                    <br><small>Last checked: <?php echo esc_html( date_i18n( 'M j, Y g:i A', (int) $results['checked_at'] ) ); ?></small>
                <?php endif; ?>

                <?php if ( '' === $api_key ) : ?>
                    <br><small>Add a PageSpeed API key in <a href="<?php echo esc_url( admin_url( 'admin.php?page=meta-description-boy' ) ); ?>">plugin settings</a> to avoid shared quota limits.</small>
                <?php endif; ?>

                <?php if ( ! empty( $results['results'] ) && is_array( $results['results'] ) ) : ?>
                    <?php
                    if ( ! empty( $results['results']['mobile'] ) ) {
                        website_optimiser_render_pagespeed_score_row( 'Mobile', $results['results']['mobile'] );
                    }
                    if ( ! empty( $results['results']['desktop'] ) ) {
                        website_optimiser_render_pagespeed_score_row( 'Desktop', $results['results']['desktop'] );
                    }
                    ?>
                <?php endif; ?>
            </div>
            <div class="stat-action">
                <button type="button" class="button button-primary button-small" id="website-optimiser-run-pagespeed">
                    Run PageSpeed Test
                </button>
                <a href="<?php echo esc_url( 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( home_url( '/' ) ) ); ?>" class="button button-small" target="_blank" rel="noopener noreferrer" style="margin-left: 5px;">
                    Open PageSpeed
                </a>
                <div id="website-optimiser-pagespeed-status" style="display: none; margin-top: 8px;"></div>
            </div>
            <?php
            $psi_resolved    = get_option( 'website_optimiser_pagespeed_resolved', false );
            $psi_resolved_by = get_option( 'website_optimiser_pagespeed_resolved_by', '' );
            $psi_resolved_date = get_option( 'website_optimiser_pagespeed_resolved_date', '' );
            ?>
            <div style="margin-top: 12px; border-top: 1px solid #eee; padding-top: 12px;">
                <?php if ( $psi_resolved ) : ?>
                    <div style="background: #edfaef; padding: 10px; border-radius: 4px; border-left: 4px solid #46b450;">
                        <strong>✓ Manually Marked as Resolved</strong><br>
                        <small><strong>Resolved by:</strong> <?php echo esc_html( $psi_resolved_by ); ?></small><br>
                        <small><strong>Date:</strong> <?php echo esc_html( date( 'M j, Y g:i A', strtotime( $psi_resolved_date ) ) ); ?></small>
                    </div>
                    <button type="button" class="button button-small" style="margin-top: 8px;" onclick="resetPagespeedApproval()">
                        Reset Resolution
                    </button>
                <?php else : ?>
                    <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">
                            Manually mark PageSpeed Insights as resolved?
                        </label>
                        <div style="margin-bottom: 12px; font-size: 13px; color: #666;">
                            Use this if you have reviewed the PageSpeed scores and are satisfied with the current results, or have addressed the issues externally.
                        </div>
                        <label style="display: block; margin-bottom: 8px;">
                            <input type="checkbox" id="pagespeed-resolve-checkbox" style="margin-right: 5px;">
                            Confirm that PageSpeed Insights has been reviewed and any issues addressed
                        </label>
                        <input type="text" id="pagespeed-resolved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
                        <button type="button" class="button button-small" onclick="approvePagespeed()" disabled id="pagespeed-resolve-btn">
                            Mark as Resolved
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    jQuery(function($) {
        $('#website-optimiser-run-pagespeed').on('click', function() {
            const $button = $(this);
            const $status = $('#website-optimiser-pagespeed-status');

            $button.prop('disabled', true).text('Running...');
            $status.show().text('Running mobile and desktop PageSpeed checks. This can take up to a minute.');

            $.post(ajaxurl, {
                action: 'website_optimiser_run_pagespeed',
                nonce: '<?php echo esc_js( wp_create_nonce( 'website_optimiser_pagespeed_nonce' ) ); ?>'
            }).done(function(response) {
                if (response && response.success) {
                    $status.text('PageSpeed test complete. Refreshing results...');
                    window.location.reload();
                    return;
                }

                const message = response && response.data && response.data.message ? response.data.message : 'PageSpeed test failed.';
                $status.text(message);
            }).fail(function() {
                $status.text('PageSpeed test failed. Please try again.');
            }).always(function() {
                $button.prop('disabled', false).text('Run PageSpeed Test');
            });
        });

        var psiCheckbox = document.getElementById('pagespeed-resolve-checkbox');
        var psiName = document.getElementById('pagespeed-resolved-by-name');
        var psiBtn = document.getElementById('pagespeed-resolve-btn');
        if (psiCheckbox && psiName && psiBtn) {
            psiCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    psiName.disabled = false;
                    psiName.focus();
                    psiName.addEventListener('input', function() {
                        psiBtn.disabled = this.value.trim() === '';
                    });
                } else {
                    psiName.disabled = true;
                    psiName.value = '';
                    psiBtn.disabled = true;
                }
            });
        }
    });

    function approvePagespeed() {
        var checkbox = document.getElementById('pagespeed-resolve-checkbox');
        var nameField = document.getElementById('pagespeed-resolved-by-name');
        if (!checkbox || !checkbox.checked) { alert('Please check the confirmation checkbox first.'); return; }
        var approvedBy = nameField.value.trim();
        if (!approvedBy) { alert('Please enter your name.'); nameField.focus(); return; }
        if (!confirm('Are you sure you want to mark PageSpeed Insights as resolved? This confirmation will be tracked.')) return;
        jQuery.post(ajaxurl, {
            action: 'website_optimiser_approve_pagespeed',
            approved_by: approvedBy,
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            var result = JSON.parse(response);
            if (result.success) { alert('PageSpeed Insights marked as resolved.'); location.reload(); }
            else { alert('Error: ' + result.message); }
        }).fail(function() { alert('Error processing request. Please try again.'); });
    }

    function resetPagespeedApproval() {
        if (!confirm('Are you sure you want to reset the PageSpeed Insights resolution? This will remove the current confirmation.')) return;
        jQuery.post(ajaxurl, {
            action: 'website_optimiser_reset_pagespeed_approval',
            nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
        }, function(response) {
            var result = JSON.parse(response);
            if (result.success) { alert('PageSpeed Insights resolution reset.'); location.reload(); }
            else { alert('Error: ' + result.message); }
        }).fail(function() { alert('Error processing request. Please try again.'); });
    }
    </script>
    <?php
}

/**
 * Handle AJAX PageSpeed run requests.
 */
function website_optimiser_handle_run_pagespeed() {
    if ( ! check_ajax_referer( 'website_optimiser_pagespeed_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
    }

    $results = website_optimiser_run_pagespeed_insights();

    if ( is_wp_error( $results ) ) {
        wp_send_json_error( array( 'message' => $results->get_error_message() ) );
    }

    wp_send_json_success(
        array(
            'message' => 'PageSpeed Insights completed.',
            'results' => $results,
        )
    );
}
add_action( 'wp_ajax_website_optimiser_run_pagespeed', 'website_optimiser_handle_run_pagespeed' );

/**
 * Handle AJAX request to manually resolve PageSpeed Insights.
 */
function website_optimiser_approve_pagespeed() {
    if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
        wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
    }

    $approved_by = sanitize_text_field( $_POST['approved_by'] ?? '' );
    if ( empty( $approved_by ) ) {
        wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Name is required' ) ) );
    }

    update_option( 'website_optimiser_pagespeed_resolved', true );
    update_option( 'website_optimiser_pagespeed_resolved_by', $approved_by );
    update_option( 'website_optimiser_pagespeed_resolved_date', current_time( 'mysql' ) );

    wp_die( wp_json_encode( array( 'success' => true, 'message' => 'PageSpeed Insights marked as resolved' ) ) );
}
add_action( 'wp_ajax_website_optimiser_approve_pagespeed', 'website_optimiser_approve_pagespeed' );

/**
 * Handle AJAX request to reset PageSpeed Insights manual resolution.
 */
function website_optimiser_reset_pagespeed_approval() {
    if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
        wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
    }

    delete_option( 'website_optimiser_pagespeed_resolved' );
    delete_option( 'website_optimiser_pagespeed_resolved_by' );
    delete_option( 'website_optimiser_pagespeed_resolved_date' );

    wp_die( wp_json_encode( array( 'success' => true, 'message' => 'PageSpeed Insights resolution reset' ) ) );
}
add_action( 'wp_ajax_website_optimiser_reset_pagespeed_approval', 'website_optimiser_reset_pagespeed_approval' );

/**
 * Clear saved PageSpeed results.
 */
function website_optimiser_clear_pagespeed_results() {
    delete_option( WEBSITE_OPTIMISER_PAGESPEED_RESULTS_OPTION );
}
