<?php
/**
 * WordPress Abilities API integration for Website Optimiser.
 *
 * Requires WordPress 6.9+ (Abilities API in core) or the standalone abilities-api plugin.
 *
 * @package Website_Optimiser
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the Abilities API is available.
 *
 * @return bool
 */
function website_optimiser_abilities_api_available() {
	if ( function_exists( 'wp_register_ability' ) ) {
		return true;
	}

	$autoload = plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';
	if ( file_exists( $autoload ) ) {
		require_once $autoload;
	}

	return function_exists( 'wp_register_ability' );
}

/**
 * Load plugin includes required by ability execute callbacks.
 */
function website_optimiser_abilities_load_dependencies() {
	static $loaded = false;

	if ( $loaded ) {
		return;
	}

	$loaded = true;
	$dir    = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/';

	require_once $dir . 'shared.php';
	require_once $dir . 'meta-descriptions.php';
	require_once $dir . 'alt-text.php';
	require_once $dir . 'h1-headings.php';
	require_once $dir . 'featured-images.php';
}

/**
 * Load all optimisation check modules (for full SEO summary ability).
 */
function website_optimiser_abilities_load_full_dependencies() {
	website_optimiser_abilities_load_dependencies();

	if ( function_exists( 'meta_description_boy_load_section_includes' ) ) {
		meta_description_boy_load_section_includes();
		return;
	}

	$dir = plugin_dir_path( dirname( __FILE__ ) ) . 'includes/';
	$files = array(
		'robots-txt.php',
		'llms-txt.php',
		'xml-sitemap.php',
		'google-search-console-sitemap.php',
		'yoast-seo.php',
		'google-site-kit.php',
		'wp-smush.php',
		'wordfence-security.php',
		'gravity-forms-recaptcha.php',
		'gravity-forms-notifications.php',
		'gravity-forms-confirmations.php',
		'gravity-forms-conversion-events.php',
		'redirects.php',
		'url-search-replace.php',
		'hubspot.php',
		'meta-pixel.php',
		'managewp.php',
		'updraftplus.php',
		'custom-404-page.php',
		'clickable-links.php',
		'navigation-font-size.php',
		'uptime-monitoring.php',
		'favicon.php',
		'wp-debug.php',
		'caching-plugins.php',
		'pagespeed-insights.php',
		'local-schema.php',
		'security-headers.php',
		'dynamic-copyright-year.php',
		'woocommerce.php',
		'woocommerce-google-analytics.php',
		'woocommerce-emails.php',
		'woocommerce-payment-methods.php',
		'woocommerce-shipping-zones.php',
		'woocommerce-tax-settings.php',
		'media-videos.php',
		'hover-states-animations.php',
		'cloudways-cron-optimizer.php',
		'cloudflare-proxy.php',
	);

	foreach ( $files as $file ) {
		$path = $dir . $file;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}

	$dashboard = plugin_dir_path( dirname( __FILE__ ) ) . 'dashboard-widget.php';
	if ( file_exists( $dashboard ) && ! function_exists( 'meta_description_boy_get_seo_summary' ) ) {
		require_once $dashboard;
	}
}

/**
 * Permission check: user can manage site optimisation settings.
 *
 * @return bool|WP_Error
 */
function website_optimiser_abilities_can_manage() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to run this optimisation ability.' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Permission check: user can edit the target post (for generate-meta-description).
 *
 * @param array|null $input Ability input.
 * @return bool|WP_Error
 */
function website_optimiser_abilities_can_edit_post( $input = null ) {
	$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

	if ( ! $post_id ) {
		return new WP_Error(
			'invalid_post_id',
			__( 'A valid post_id is required.' ),
			array( 'status' => 400 )
		);
	}

	$allowed_roles = get_option( 'meta_description_boy_allowed_roles', array( 'administrator' ) );
	$user          = wp_get_current_user();

	if ( ! array_intersect( (array) $allowed_roles, (array) $user->roles ) ) {
		return new WP_Error(
			'rest_forbidden',
			__( 'Your role is not allowed to generate meta descriptions.' ),
			array( 'status' => 403 )
		);
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to edit this post.' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Shared meta annotations for read-only abilities exposed via REST.
 *
 * @return array
 */
function website_optimiser_abilities_readonly_meta() {
	return array(
		'annotations'  => array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		),
		'show_in_rest' => true,
	);
}

/**
 * Register Website Optimiser ability categories.
 */
function website_optimiser_register_ability_categories() {
	wp_register_ability_category(
		'website-optimiser',
		array(
			'label'       => __( 'Website Optimiser', 'website-optimiser' ),
			'description' => __( 'SEO and performance optimisation checks and AI-assisted content tools.', 'website-optimiser' ),
		)
	);
}

/**
 * Execute: aggregate SEO optimisation summary.
 *
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_get_seo_summary() {
	website_optimiser_abilities_load_full_dependencies();

	if ( ! function_exists( 'meta_description_boy_get_seo_summary' ) ) {
		return new WP_Error(
			'dependency_missing',
			__( 'SEO summary is unavailable.' ),
			array( 'status' => 500 )
		);
	}

	return meta_description_boy_get_seo_summary();
}

/**
 * Execute: meta description coverage stats.
 *
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_get_meta_description_stats() {
	website_optimiser_abilities_load_dependencies();

	return meta_description_boy_get_meta_description_stats();
}

/**
 * Execute: H1 heading analysis stats.
 *
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_get_h1_stats() {
	website_optimiser_abilities_load_dependencies();

	return meta_description_boy_get_h1_stats();
}

/**
 * Execute: image alt text coverage stats.
 *
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_get_alt_text_stats() {
	website_optimiser_abilities_load_dependencies();

	return meta_description_boy_get_alt_text_stats();
}

/**
 * Execute: featured image coverage stats.
 *
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_get_featured_image_stats() {
	website_optimiser_abilities_load_dependencies();

	return meta_description_boy_get_featured_image_stats();
}

/**
 * Execute: generate SEO meta description for a post via Gemini.
 *
 * @param array $input Must include post_id.
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_generate_meta_description( $input ) {
	$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
	$result  = meta_description_boy_generate_meta_description_for_post( $post_id );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'post_id'     => $post_id,
		'description' => $result,
	);
}

/**
 * Permission check: user can edit a media attachment.
 *
 * @param array|null $input Ability input.
 * @return bool|WP_Error
 */
function website_optimiser_abilities_can_edit_attachment( $input = null ) {
	$attachment_id = isset( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : 0;

	if ( ! $attachment_id ) {
		return new WP_Error(
			'invalid_attachment_id',
			__( 'A valid attachment_id is required.' ),
			array( 'status' => 400 )
		);
	}

	if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to edit this attachment.' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Meta for mutating (non-destructive) abilities exposed via REST.
 *
 * @return array
 */
function website_optimiser_abilities_action_meta() {
	return array(
		'annotations'  => array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		),
		'show_in_rest' => true,
	);
}

/**
 * JSON schema for flexible status/check objects.
 *
 * @return array
 */
function website_optimiser_abilities_generic_object_schema() {
	return array(
		'type'                 => 'object',
		'additionalProperties' => true,
	);
}

/**
 * Registered optimisation check callbacks keyed by slug.
 *
 * @return array<string, string> Slug => callable name.
 */
function website_optimiser_abilities_get_check_callbacks() {
	website_optimiser_abilities_load_full_dependencies();

	$checks = array(
		'robots_txt'                    => 'meta_description_boy_check_robots_txt',
		'llms_txt'                      => 'meta_description_boy_check_llms_txt',
		'xml_sitemap'                   => 'meta_description_boy_check_sitemap',
		'google_search_console_sitemap' => 'meta_description_boy_check_google_search_console_sitemap_status',
		'yoast_seo'                     => 'website_optimiser_check_yoast_seo_status',
		'google_site_kit'               => 'meta_description_boy_check_google_site_kit_status',
		'wp_smush'                      => 'meta_description_boy_check_wp_smush_status',
		'wordfence'                     => 'meta_description_boy_check_wordfence_status',
		'gravity_forms_recaptcha'       => 'meta_description_boy_check_gravity_forms_recaptcha_status',
		'gravity_forms_notifications'   => 'meta_description_boy_check_gravity_forms_notifications_status',
		'gravity_forms_confirmations'   => 'meta_description_boy_check_gravity_forms_confirmations_status',
		'gravity_forms_conversion_events' => 'meta_description_boy_check_gravity_forms_conversion_events_status',
		'redirects'                     => 'meta_description_boy_check_redirects_status',
		'url_search_replace'            => 'website_optimiser_check_url_search_replace_status',
		'hubspot'                       => 'meta_description_boy_check_hubspot_status',
		'meta_pixel'                    => 'meta_description_boy_check_meta_pixel_status',
		'managewp'                      => 'meta_description_boy_check_managewp_status',
		'updraftplus'                   => 'meta_description_boy_check_updraftplus_status',
		'custom_404'                    => 'meta_description_boy_check_custom_404_status',
		'clickable_links'               => 'meta_description_boy_check_clickable_links_status',
		'navigation_font_size'          => 'meta_description_boy_check_navigation_font_size_status',
		'uptime_monitoring'             => 'meta_description_boy_check_uptime_monitoring_status',
		'favicon'                       => 'meta_description_boy_check_favicon_status',
		'wp_debug'                      => 'meta_description_boy_check_wp_debug_status',
		'caching_plugins'               => 'meta_description_boy_check_caching_plugins_status',
		'local_schema'                  => 'website_optimiser_check_local_schema_status',
		'dynamic_copyright_year'        => 'website_optimiser_check_dynamic_copyright_status',
		'media_videos'                  => 'meta_description_boy_check_media_videos_status',
		'hover_states_animations'       => 'meta_description_boy_check_hover_states_animations_status',
		'cloudways_cron_optimizer'      => 'meta_description_boy_check_cloudways_cron_optimizer_status',
		'cloudflare_proxy'              => 'website_optimiser_check_cloudflare_proxy_status',
	);

	if ( class_exists( 'WooCommerce' ) ) {
		$checks['woocommerce']                  = 'website_optimiser_check_woocommerce_status';
		$checks['woocommerce_ga']               = 'website_optimiser_check_woocommerce_ga_status';
		$checks['woocommerce_emails']           = 'website_optimiser_check_woocommerce_emails_status';
		$checks['woocommerce_payment_methods']  = 'website_optimiser_check_woocommerce_payment_methods_status';
		$checks['woocommerce_shipping_zones']   = 'website_optimiser_check_woocommerce_shipping_zones_status';
		$checks['woocommerce_tax_settings']     = 'website_optimiser_check_woocommerce_tax_settings_status';
	}

	return $checks;
}

/**
 * Execute: generate alt text for an attachment.
 *
 * @param array $input attachment_id, optional save (default false).
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_generate_alt_text( $input ) {
	$attachment_id = isset( $input['attachment_id'] ) ? absint( $input['attachment_id'] ) : 0;
	$save            = ! empty( $input['save'] );
	$result          = meta_description_boy_generate_alt_text_for_attachment( $attachment_id, $save );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'attachment_id' => $attachment_id,
		'alt_text'      => $result,
		'saved'         => $save,
	);
}

/**
 * Execute: list images missing alt text.
 *
 * @param array $input Optional limit (default 100, max 500).
 * @return array
 */
function website_optimiser_ability_execute_get_images_without_alt_text( $input = array() ) {
	$limit = isset( $input['limit'] ) ? (int) $input['limit'] : 100;
	if ( $limit <= 0 ) {
		$limit = 100;
	}
	if ( $limit > 500 ) {
		$limit = 500;
	}

	$args = array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'posts_per_page' => $limit,
		'meta_query'     => array(
			'relation' => 'OR',
			array(
				'key'     => '_wp_attachment_image_alt',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_wp_attachment_image_alt',
				'value'   => '',
				'compare' => '=',
			),
		),
	);

	$images     = get_posts( $args );
	$image_data = array();

	foreach ( $images as $image ) {
		if ( 'image/svg+xml' === get_post_mime_type( $image->ID ) ) {
			continue;
		}

		$thumbnail    = wp_get_attachment_image_src( $image->ID, 'thumbnail' );
		$image_data[] = array(
			'id'        => $image->ID,
			'title'     => $image->post_title,
			'thumbnail' => $thumbnail ? $thumbnail[0] : '',
			'filename'  => basename( (string) get_attached_file( $image->ID ) ),
			'edit_url'  => get_edit_post_link( $image->ID, 'raw' ),
		);
	}

	return array(
		'images' => $image_data,
		'total'  => count( $image_data ),
	);
}

/**
 * Execute: run full H1 analysis and refresh stored results.
 *
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_refresh_h1_analysis() {
	website_optimiser_abilities_load_dependencies();

	if ( ! function_exists( 'meta_description_boy_perform_h1_analysis' ) ) {
		return new WP_Error( 'dependency_missing', __( 'H1 analysis is unavailable.' ), array( 'status' => 500 ) );
	}

	meta_description_boy_force_clear_h1_cache();
	$stats = meta_description_boy_perform_h1_analysis();

	if ( function_exists( 'meta_description_boy_get_detailed_h1_results' ) ) {
		$detailed = meta_description_boy_get_detailed_h1_results();
		update_option(
			'meta_description_boy_h1_detailed_results',
			array(
				'generated_at' => time(),
				'results'      => $detailed,
			)
		);
	}

	return array(
		'stats'  => $stats,
		'message' => __( 'H1 analysis refreshed successfully.', 'website-optimiser' ),
	);
}

/**
 * Execute: per-post H1 analysis details.
 *
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_get_h1_detailed_results() {
	website_optimiser_abilities_load_dependencies();

	if ( ! function_exists( 'meta_description_boy_get_detailed_h1_results' ) ) {
		return new WP_Error( 'dependency_missing', __( 'H1 detailed results are unavailable.' ), array( 'status' => 500 ) );
	}

	return array(
		'results' => meta_description_boy_get_detailed_h1_results(),
	);
}

/**
 * Execute: all registered optimisation module checks.
 *
 * @return array
 */
function website_optimiser_ability_execute_get_optimisation_checks() {
	$results   = array();
	$callbacks = website_optimiser_abilities_get_check_callbacks();

	foreach ( $callbacks as $slug => $callback ) {
		if ( is_string( $callback ) && function_exists( $callback ) ) {
			$results[ $slug ] = call_user_func( $callback );
		}
	}

	$results['meta_descriptions'] = meta_description_boy_get_meta_description_stats();
	$results['alt_text']          = meta_description_boy_get_alt_text_stats();
	$results['h1_headings']         = meta_description_boy_get_h1_stats();
	$results['featured_images']     = meta_description_boy_get_featured_image_stats();

	return $results;
}

/**
 * Execute: detect active SEO plugins.
 *
 * @return array
 */
function website_optimiser_ability_execute_detect_seo_plugins() {
	website_optimiser_abilities_load_dependencies();

	return array(
		'plugins' => meta_description_boy_detect_seo_plugins(),
	);
}

/**
 * Execute: clear Website Optimiser transients and analysis caches.
 *
 * @return array
 */
function website_optimiser_ability_execute_clear_optimisation_cache() {
	website_optimiser_abilities_load_full_dependencies();

	if ( function_exists( 'meta_description_boy_force_clear_h1_cache' ) ) {
		meta_description_boy_force_clear_h1_cache();
	}
	if ( function_exists( 'meta_description_boy_clear_sitemap_cache' ) ) {
		meta_description_boy_clear_sitemap_cache();
	}
	if ( function_exists( 'meta_description_boy_clear_robots_cache' ) ) {
		meta_description_boy_clear_robots_cache();
	}
	if ( function_exists( 'meta_description_boy_clear_llms_cache' ) ) {
		meta_description_boy_clear_llms_cache();
	}
	if ( function_exists( 'website_optimiser_clear_pagespeed_results' ) ) {
		website_optimiser_clear_pagespeed_results();
	}

	delete_transient( 'meta_description_boy_meta_description_stats' );
	delete_transient( 'meta_description_boy_alt_text_stats' );
	delete_transient( 'meta_description_boy_featured_image_stats' );

	return array(
		'success' => true,
		'message' => __( 'All optimisation caches cleared successfully.', 'website-optimiser' ),
	);
}

/**
 * Execute: robots.txt check.
 *
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_check_robots_txt() {
	website_optimiser_abilities_load_full_dependencies();

	if ( ! function_exists( 'meta_description_boy_check_robots_txt' ) ) {
		return new WP_Error( 'dependency_missing', __( 'Robots.txt check is unavailable.' ), array( 'status' => 500 ) );
	}

	return meta_description_boy_check_robots_txt();
}

/**
 * Execute: llms.txt check.
 *
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_check_llms_txt() {
	website_optimiser_abilities_load_full_dependencies();

	if ( ! function_exists( 'meta_description_boy_check_llms_txt' ) ) {
		return new WP_Error( 'dependency_missing', __( 'LLMs.txt check is unavailable.' ), array( 'status' => 500 ) );
	}

	return meta_description_boy_check_llms_txt();
}

/**
 * Execute: XML sitemap check.
 *
 * @return array|WP_Error
 */
function website_optimiser_ability_execute_check_xml_sitemap() {
	website_optimiser_abilities_load_full_dependencies();

	if ( ! function_exists( 'meta_description_boy_check_sitemap' ) ) {
		return new WP_Error( 'dependency_missing', __( 'XML sitemap check is unavailable.' ), array( 'status' => 500 ) );
	}

	return meta_description_boy_check_sitemap();
}

/**
 * Execute: AI / Gemini configuration status (no secrets returned).
 *
 * @return array
 */
function website_optimiser_ability_execute_get_ai_config_status() {
	return array(
		'ai_enabled'        => website_optimiser_ai_features_enabled(),
		'api_key_configured' => website_optimiser_gemini_api_key_configured(),
		'api_key_source'    => website_optimiser_get_gemini_api_key_source(),
		'api_key_source_label' => website_optimiser_get_gemini_api_key_source_label(),
		'gemini_model'      => website_optimiser_get_gemini_model_id(),
		'connectors_available' => website_optimiser_connectors_api_available(),
	);
}

/**
 * JSON schema fragment for percentage-based stat objects.
 *
 * @return array
 */
function website_optimiser_abilities_percentage_stats_schema() {
	return array(
		'type'       => 'object',
		'properties' => array(
			'total'      => array( 'type' => 'integer' ),
			'missing'    => array( 'type' => 'integer' ),
			'percentage' => array( 'type' => 'number' ),
		),
	);
}

/**
 * Register Website Optimiser abilities.
 */
function website_optimiser_register_abilities() {
	$readonly_meta = website_optimiser_abilities_readonly_meta();

	wp_register_ability(
		'website-optimiser/get-seo-summary',
		array(
			'label'               => __( 'Get SEO Optimisation Summary', 'website-optimiser' ),
			'description'         => __( 'Returns an aggregate summary of all Website Optimiser checks (totals, warnings, errors, and overall percentage).', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'total'      => array( 'type' => 'integer' ),
					'optimized'  => array( 'type' => 'integer' ),
					'warnings'   => array( 'type' => 'integer' ),
					'errors'     => array( 'type' => 'integer' ),
					'percentage' => array( 'type' => 'integer' ),
				),
			),
			'execute_callback'    => 'website_optimiser_ability_execute_get_seo_summary',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/get-meta-description-stats',
		array(
			'label'               => __( 'Get Meta Description Stats', 'website-optimiser' ),
			'description'         => __( 'Returns counts and percentage of published posts/pages that have an SEO meta description set.', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => array_merge(
				website_optimiser_abilities_percentage_stats_schema(),
				array(
					'properties' => array_merge(
						website_optimiser_abilities_percentage_stats_schema()['properties'],
						array( 'with_meta' => array( 'type' => 'integer' ) )
					),
				)
			),
			'execute_callback'    => 'website_optimiser_ability_execute_get_meta_description_stats',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/get-h1-stats',
		array(
			'label'               => __( 'Get H1 Heading Stats', 'website-optimiser' ),
			'description'         => __( 'Returns H1 heading analysis statistics for configured post types (correct, missing, multiple H1, and issues).', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'total'          => array( 'type' => 'integer' ),
					'correct'        => array( 'type' => 'integer' ),
					'no_h1'          => array( 'type' => 'integer' ),
					'multiple_h1'    => array( 'type' => 'integer' ),
					'issues'         => array( 'type' => 'integer' ),
					'percentage'     => array( 'type' => 'number' ),
					'needs_refresh'  => array( 'type' => 'boolean' ),
				),
			),
			'execute_callback'    => 'website_optimiser_ability_execute_get_h1_stats',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/get-alt-text-stats',
		array(
			'label'               => __( 'Get Alt Text Stats', 'website-optimiser' ),
			'description'         => __( 'Returns counts and percentage of media library images that have alt text (excluding SVG).', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => array_merge(
				website_optimiser_abilities_percentage_stats_schema(),
				array(
					'properties' => array_merge(
						website_optimiser_abilities_percentage_stats_schema()['properties'],
						array( 'with_alt' => array( 'type' => 'integer' ) )
					),
				)
			),
			'execute_callback'    => 'website_optimiser_ability_execute_get_alt_text_stats',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/get-featured-image-stats',
		array(
			'label'               => __( 'Get Featured Image Stats', 'website-optimiser' ),
			'description'         => __( 'Returns counts and percentage of published posts/pages that have a featured image.', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => array_merge(
				website_optimiser_abilities_percentage_stats_schema(),
				array(
					'properties' => array_merge(
						website_optimiser_abilities_percentage_stats_schema()['properties'],
						array( 'with_featured' => array( 'type' => 'integer' ) )
					),
				)
			),
			'execute_callback'    => 'website_optimiser_ability_execute_get_featured_image_stats',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/generate-meta-description',
		array(
			'label'               => __( 'Generate Meta Description', 'website-optimiser' ),
			'description'         => __( 'Uses Google Gemini to generate an SEO meta description for a post based on its title and content.', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'post_id' ),
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'The post ID to generate a meta description for.',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'     => array( 'type' => 'integer' ),
					'description' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => 'website_optimiser_ability_execute_generate_meta_description',
			'permission_callback' => 'website_optimiser_abilities_can_edit_post',
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
			),
		)
	);

	$action_meta = website_optimiser_abilities_action_meta();

	wp_register_ability(
		'website-optimiser/generate-alt-text',
		array(
			'label'               => __( 'Generate Alt Text', 'website-optimiser' ),
			'description'         => __( 'Uses Google Gemini Vision to generate accessible alt text for a media attachment. Optionally saves it to the attachment.', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'input_schema'        => array(
				'type'       => 'object',
				'required'   => array( 'attachment_id' ),
				'properties' => array(
					'attachment_id' => array(
						'type'        => 'integer',
						'description' => 'Media attachment ID.',
					),
					'save'          => array(
						'type'        => 'boolean',
						'description' => 'Whether to save alt text to the attachment (default false).',
						'default'     => false,
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id' => array( 'type' => 'integer' ),
					'alt_text'      => array( 'type' => 'string' ),
					'saved'         => array( 'type' => 'boolean' ),
				),
			),
			'execute_callback'    => 'website_optimiser_ability_execute_generate_alt_text',
			'permission_callback' => 'website_optimiser_abilities_can_edit_attachment',
			'meta'                => $action_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/get-images-without-alt-text',
		array(
			'label'               => __( 'Get Images Without Alt Text', 'website-optimiser' ),
			'description'         => __( 'Returns a list of image attachments that are missing alt text (excluding SVG).', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'limit' => array(
						'type'        => 'integer',
						'description' => 'Maximum images to return (default 100, max 500).',
						'default'     => 100,
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'images' => array( 'type' => 'array' ),
					'total'  => array( 'type' => 'integer' ),
				),
			),
			'execute_callback'    => 'website_optimiser_ability_execute_get_images_without_alt_text',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/refresh-h1-analysis',
		array(
			'label'               => __( 'Refresh H1 Analysis', 'website-optimiser' ),
			'description'         => __( 'Clears cached H1 data, re-runs the full H1 heading analysis, and stores updated results.', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => website_optimiser_abilities_generic_object_schema(),
			'execute_callback'    => 'website_optimiser_ability_execute_refresh_h1_analysis',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $action_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/get-h1-detailed-results',
		array(
			'label'               => __( 'Get H1 Detailed Results', 'website-optimiser' ),
			'description'         => __( 'Returns per-post H1 analysis (correct, missing, multiple, or excluded) for configured post types.', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'results' => array( 'type' => 'array' ),
				),
			),
			'execute_callback'    => 'website_optimiser_ability_execute_get_h1_detailed_results',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/get-optimisation-checks',
		array(
			'label'               => __( 'Get All Optimisation Checks', 'website-optimiser' ),
			'description'         => __( 'Runs every Website Optimiser module check and returns keyed status objects (plugins, security, SEO, WooCommerce, etc.).', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => website_optimiser_abilities_generic_object_schema(),
			'execute_callback'    => 'website_optimiser_ability_execute_get_optimisation_checks',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/detect-seo-plugins',
		array(
			'label'               => __( 'Detect SEO Plugins', 'website-optimiser' ),
			'description'         => __( 'Returns names of active SEO plugins detected on the site (Yoast, Rank Math, etc.).', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'plugins' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
			),
			'execute_callback'    => 'website_optimiser_ability_execute_detect_seo_plugins',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/clear-optimisation-cache',
		array(
			'label'               => __( 'Clear Optimisation Cache', 'website-optimiser' ),
			'description'         => __( 'Clears Website Optimiser transients and cached analysis data (H1, robots.txt, llms.txt, sitemap, stats).', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'message' => array( 'type' => 'string' ),
				),
			),
			'execute_callback'    => 'website_optimiser_ability_execute_clear_optimisation_cache',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $action_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/check-robots-txt',
		array(
			'label'               => __( 'Check robots.txt', 'website-optimiser' ),
			'description'         => __( 'Checks whether robots.txt exists and is accessible.', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => website_optimiser_abilities_generic_object_schema(),
			'execute_callback'    => 'website_optimiser_ability_execute_check_robots_txt',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/check-llms-txt',
		array(
			'label'               => __( 'Check llms.txt', 'website-optimiser' ),
			'description'         => __( 'Checks whether llms.txt exists and is accessible.', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => website_optimiser_abilities_generic_object_schema(),
			'execute_callback'    => 'website_optimiser_ability_execute_check_llms_txt',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/check-xml-sitemap',
		array(
			'label'               => __( 'Check XML Sitemap', 'website-optimiser' ),
			'description'         => __( 'Checks whether an XML sitemap is available on the site.', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => website_optimiser_abilities_generic_object_schema(),
			'execute_callback'    => 'website_optimiser_ability_execute_check_xml_sitemap',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);

	wp_register_ability(
		'website-optimiser/get-ai-config-status',
		array(
			'label'               => __( 'Get AI Config Status', 'website-optimiser' ),
			'description'         => __( 'Returns whether AI is enabled, Gemini is configured, key source, and model ID (no API secrets).', 'website-optimiser' ),
			'category'            => 'website-optimiser',
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'ai_enabled'           => array( 'type' => 'boolean' ),
					'api_key_configured'   => array( 'type' => 'boolean' ),
					'api_key_source'       => array( 'type' => 'string' ),
					'api_key_source_label' => array( 'type' => 'string' ),
					'gemini_model'         => array( 'type' => 'string' ),
					'connectors_available' => array( 'type' => 'boolean' ),
				),
			),
			'execute_callback'    => 'website_optimiser_ability_execute_get_ai_config_status',
			'permission_callback' => 'website_optimiser_abilities_can_manage',
			'meta'                => $readonly_meta,
		)
	);
}

/**
 * Bootstrap Abilities API hooks when the API is available.
 */
function website_optimiser_bootstrap_abilities_api() {
	if ( ! website_optimiser_abilities_api_available() ) {
		return;
	}

	add_action( 'wp_abilities_api_categories_init', 'website_optimiser_register_ability_categories' );
	add_action( 'wp_abilities_api_init', 'website_optimiser_register_abilities' );
}

website_optimiser_bootstrap_abilities_api();
