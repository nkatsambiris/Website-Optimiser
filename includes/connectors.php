<?php
/**
 * WordPress Connectors API integration (WordPress 7.0+) for centralised API keys.
 *
 * @see https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/
 * @package Website_Optimiser
 */

defined( 'ABSPATH' ) || exit;

/**
 * Connector ID for Google / Gemini in WordPress core.
 */
const WEBSITE_OPTIMISER_GOOGLE_CONNECTOR_ID = 'google';

/**
 * Legacy option name when Connectors API is unavailable.
 */
const WEBSITE_OPTIMISER_LEGACY_GEMINI_OPTION = 'meta_description_boy_api_key';

/**
 * Whether the Connectors API is available (WordPress 7.0+).
 *
 * @return bool
 */
function website_optimiser_connectors_api_available() {
	return function_exists( 'wp_get_connector' ) && function_exists( 'wp_is_connector_registered' );
}

/**
 * Whether site-level AI features are enabled (WordPress 7.0+).
 *
 * @return bool
 */
function website_optimiser_ai_features_enabled() {
	if ( function_exists( 'wp_supports_ai' ) ) {
		return (bool) wp_supports_ai();
	}

	return true;
}

/**
 * Whether the Google connector is registered.
 *
 * @return bool
 */
function website_optimiser_google_connector_available() {
	return website_optimiser_connectors_api_available()
		&& wp_is_connector_registered( WEBSITE_OPTIMISER_GOOGLE_CONNECTOR_ID );
}

/**
 * Resolve API key from connector authentication metadata (env → constant → database).
 *
 * Mirrors core Connectors API priority. Returns empty string when unavailable.
 *
 * @param string $connector_id Connector slug (e.g. google).
 * @return string
 */
function website_optimiser_get_connector_api_key( $connector_id ) {
	if ( ! website_optimiser_connectors_api_available() || ! wp_is_connector_registered( $connector_id ) ) {
		return '';
	}

	$connector = wp_get_connector( $connector_id );
	if ( ! is_array( $connector ) || empty( $connector['authentication'] ) ) {
		return '';
	}

	$auth = $connector['authentication'];
	if ( 'api_key' !== ( $auth['method'] ?? '' ) ) {
		return '';
	}

	$env_var_name  = $auth['env_var_name'] ?? '';
	$constant_name = $auth['constant_name'] ?? '';
	$setting_name  = $auth['setting_name'] ?? '';

	if ( '' !== $env_var_name ) {
		$env_value = getenv( $env_var_name );
		if ( false !== $env_value && '' !== $env_value ) {
			return (string) $env_value;
		}
	}

	if ( '' !== $constant_name && defined( $constant_name ) ) {
		$const_value = constant( $constant_name );
		if ( is_string( $const_value ) && '' !== $const_value ) {
			return $const_value;
		}
	}

	if ( '' !== $setting_name ) {
		return (string) get_option( $setting_name, '' );
	}

	return '';
}

/**
 * Where the active Gemini API key comes from.
 *
 * @return string connector-env|connector-constant|connector-database|plugin-setting|empty
 */
function website_optimiser_get_gemini_api_key_source() {
	if ( ! website_optimiser_ai_features_enabled() ) {
		return 'empty';
	}

	if ( website_optimiser_google_connector_available() ) {
		$connector = wp_get_connector( WEBSITE_OPTIMISER_GOOGLE_CONNECTOR_ID );
		$auth      = $connector['authentication'] ?? array();

		$env_var_name  = $auth['env_var_name'] ?? '';
		$constant_name = $auth['constant_name'] ?? '';
		$setting_name  = $auth['setting_name'] ?? '';

		if ( '' !== $env_var_name ) {
			$env_value = getenv( $env_var_name );
			if ( false !== $env_value && '' !== $env_value ) {
				return 'connector-env';
			}
		}

		if ( '' !== $constant_name && defined( $constant_name ) ) {
			$const_value = constant( $constant_name );
			if ( is_string( $const_value ) && '' !== $const_value ) {
				return 'connector-constant';
			}
		}

		if ( '' !== $setting_name && '' !== get_option( $setting_name, '' ) ) {
			return 'connector-database';
		}
	}

	$legacy = get_option( WEBSITE_OPTIMISER_LEGACY_GEMINI_OPTION, '' );
	if ( '' !== $legacy ) {
		return 'plugin-setting';
	}

	return 'empty';
}

/**
 * Get the Google Gemini API key (Connectors API on WP 7.0+, legacy option otherwise).
 *
 * @return string API key or empty string.
 */
function website_optimiser_get_gemini_api_key() {
	if ( ! website_optimiser_ai_features_enabled() ) {
		return '';
	}

	if ( website_optimiser_google_connector_available() ) {
		$connector_key = website_optimiser_get_connector_api_key( WEBSITE_OPTIMISER_GOOGLE_CONNECTOR_ID );
		if ( '' !== $connector_key ) {
			return $connector_key;
		}
	}

	return (string) get_option( WEBSITE_OPTIMISER_LEGACY_GEMINI_OPTION, '' );
}

/**
 * Whether a Gemini API key is configured.
 *
 * @return bool
 */
function website_optimiser_gemini_api_key_configured() {
	return '' !== website_optimiser_get_gemini_api_key();
}

/**
 * Human-readable label for the active API key source (settings UI).
 *
 * @return string
 */
function website_optimiser_get_gemini_api_key_source_label() {
	switch ( website_optimiser_get_gemini_api_key_source() ) {
		case 'connector-env':
			return __( 'WordPress Connectors (environment variable)' );
		case 'connector-constant':
			return __( 'WordPress Connectors (PHP constant)' );
		case 'connector-database':
			return __( 'Settings → Connectors (Google)' );
		case 'plugin-setting':
			return __( 'Website Optimiser settings (legacy)' );
		case 'empty':
		default:
			if ( ! website_optimiser_ai_features_enabled() ) {
				return __( 'AI features are disabled on this site' );
			}
			return __( 'Not configured' );
	}
}

/**
 * URL to manage Google API credentials.
 *
 * @return string
 */
function website_optimiser_get_gemini_credentials_admin_url() {
	if ( website_optimiser_connectors_api_available() ) {
		return admin_url( 'options-connectors.php' );
	}

	return admin_url( 'admin.php?page=meta-description-boy' );
}

/**
 * Gemini model ID for generateContent requests.
 *
 * gemini-2.0-flash was retired 2026-06-01; default is gemini-2.5-flash.
 *
 * @return string
 */
function website_optimiser_get_gemini_model_id() {
	/**
	 * Filter the Gemini model used for meta descriptions and alt text.
	 *
	 * @param string $model_id Model ID (e.g. gemini-2.5-flash).
	 */
	return apply_filters( 'website_optimiser_gemini_model', 'gemini-2.5-flash' );
}

/**
 * Full generateContent endpoint URL for the configured Gemini model.
 *
 * @param string $api_key Google API key.
 * @return string
 */
function website_optimiser_get_gemini_api_url( $api_key ) {
	$model = website_optimiser_get_gemini_model_id();

	return add_query_arg(
		'key',
		$api_key,
		sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
			$model
		)
	);
}
