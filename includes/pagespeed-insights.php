<?php
/**
 * Google PageSpeed Insights checks for Website Optimiser.
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

const WEBSITE_OPTIMISER_PAGESPEED_RESULTS_OPTION = 'website_optimiser_pagespeed_results';
const WEBSITE_OPTIMISER_PAGESPEED_PASSING_SCORE  = 90;

/**
 * Get the saved PageSpeed Insights result.
 */
function website_optimiser_get_pagespeed_results() {
    $results = get_option( WEBSITE_OPTIMISER_PAGESPEED_RESULTS_OPTION, array() );

    return is_array( $results ) ? $results : array();
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

        return new WP_Error(
            'pagespeed_request_failed',
            $message,
            array( 'status' => $response_code )
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
    $results = website_optimiser_get_pagespeed_results();

    if ( empty( $results ) ) {
        return array(
            'class'   => 'status-warning',
            'status'  => 'Not Run',
            'message' => 'Run PageSpeed Insights to check mobile and desktop Lighthouse scores.',
            'results' => array(),
        );
    }

    if ( ! empty( $results['passed'] ) ) {
        return array(
            'class'   => 'status-good',
            'status'  => 'Passed',
            'message' => 'All PageSpeed scores are 90% or higher.',
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
    });
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
 * Clear saved PageSpeed results.
 */
function website_optimiser_clear_pagespeed_results() {
    delete_option( WEBSITE_OPTIMISER_PAGESPEED_RESULTS_OPTION );
}
