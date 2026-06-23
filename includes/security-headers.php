<?php
/**
 * Security headers settings and output for Website Optimiser.
 */

defined( 'ABSPATH' ) || exit;

const WEBSITE_OPTIMISER_SECURITY_HEADERS_OPTION = 'website_optimiser_security_headers';

/**
 * Default security header settings.
 *
 * @return array
 */
function website_optimiser_get_security_headers_defaults() {
	return array(
		'enabled'                    => 0,
		'hsts_enabled'               => 1,
		'hsts_max_age'               => 31536000,
		'hsts_include_subdomains'    => 1,
		'hsts_preload'               => 0,
		'csp_enabled'                => 0,
		'csp_policy'                 => "default-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self';",
		'x_frame_enabled'            => 1,
		'x_frame_option'             => 'SAMEORIGIN',
		'x_content_type_enabled'     => 1,
		'referrer_policy_enabled'    => 1,
		'referrer_policy'            => 'strict-origin-when-cross-origin',
		'permissions_policy_enabled' => 1,
		'permissions_policy'         => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
	);
}

/**
 * Get security header options merged with defaults.
 *
 * @return array
 */
function website_optimiser_get_security_headers_options() {
	$options = get_option( WEBSITE_OPTIMISER_SECURITY_HEADERS_OPTION, array() );

	return wp_parse_args( is_array( $options ) ? $options : array(), website_optimiser_get_security_headers_defaults() );
}

/**
 * Clean a header value so settings cannot inject additional headers.
 *
 * @param string $value Header value.
 * @return string
 */
function website_optimiser_sanitize_header_value( $value ) {
	return trim( str_replace( array( "\r", "\n" ), ' ', (string) $value ) );
}

/**
 * Sanitize security header settings.
 *
 * @param array $input Unsanitized option value.
 * @return array
 */
function website_optimiser_sanitize_security_headers_options( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$defaults = website_optimiser_get_security_headers_defaults();

	$x_frame_options = array( 'DENY', 'SAMEORIGIN' );
	$referrer_options = array(
		'no-referrer',
		'no-referrer-when-downgrade',
		'origin',
		'origin-when-cross-origin',
		'same-origin',
		'strict-origin',
		'strict-origin-when-cross-origin',
		'unsafe-url',
	);

	$hsts_max_age = isset( $input['hsts_max_age'] ) ? absint( $input['hsts_max_age'] ) : $defaults['hsts_max_age'];
	if ( $hsts_max_age < 0 ) {
		$hsts_max_age = $defaults['hsts_max_age'];
	}

	$x_frame_option = strtoupper( sanitize_text_field( $input['x_frame_option'] ?? $defaults['x_frame_option'] ) );
	if ( ! in_array( $x_frame_option, $x_frame_options, true ) ) {
		$x_frame_option = $defaults['x_frame_option'];
	}

	$referrer_policy = sanitize_text_field( $input['referrer_policy'] ?? $defaults['referrer_policy'] );
	if ( ! in_array( $referrer_policy, $referrer_options, true ) ) {
		$referrer_policy = $defaults['referrer_policy'];
	}

	return array(
		'enabled'                    => ! empty( $input['enabled'] ) ? 1 : 0,
		'hsts_enabled'               => ! empty( $input['hsts_enabled'] ) ? 1 : 0,
		'hsts_max_age'               => $hsts_max_age,
		'hsts_include_subdomains'    => ! empty( $input['hsts_include_subdomains'] ) ? 1 : 0,
		'hsts_preload'               => ! empty( $input['hsts_preload'] ) ? 1 : 0,
		'csp_enabled'                => ! empty( $input['csp_enabled'] ) ? 1 : 0,
		'csp_policy'                 => website_optimiser_sanitize_header_value( sanitize_textarea_field( $input['csp_policy'] ?? '' ) ),
		'x_frame_enabled'            => ! empty( $input['x_frame_enabled'] ) ? 1 : 0,
		'x_frame_option'             => $x_frame_option,
		'x_content_type_enabled'     => ! empty( $input['x_content_type_enabled'] ) ? 1 : 0,
		'referrer_policy_enabled'    => ! empty( $input['referrer_policy_enabled'] ) ? 1 : 0,
		'referrer_policy'            => $referrer_policy,
		'permissions_policy_enabled' => ! empty( $input['permissions_policy_enabled'] ) ? 1 : 0,
		'permissions_policy'         => website_optimiser_sanitize_header_value( sanitize_textarea_field( $input['permissions_policy'] ?? '' ) ),
	);
}

/**
 * Build enabled security headers from settings.
 *
 * @return array
 */
function website_optimiser_get_enabled_security_headers() {
	$options = website_optimiser_get_security_headers_options();

	if ( empty( $options['enabled'] ) ) {
		return array();
	}

	$headers = array();

	if ( ! empty( $options['hsts_enabled'] ) && is_ssl() ) {
		$hsts = 'max-age=' . absint( $options['hsts_max_age'] );
		if ( ! empty( $options['hsts_include_subdomains'] ) ) {
			$hsts .= '; includeSubDomains';
		}
		if ( ! empty( $options['hsts_preload'] ) ) {
			$hsts .= '; preload';
		}
		$headers['Strict-Transport-Security'] = $hsts;
	}

	if ( ! empty( $options['csp_enabled'] ) && '' !== $options['csp_policy'] ) {
		$headers['Content-Security-Policy'] = $options['csp_policy'];
	}

	if ( ! empty( $options['x_frame_enabled'] ) ) {
		$headers['X-Frame-Options'] = $options['x_frame_option'];
	}

	if ( ! empty( $options['x_content_type_enabled'] ) ) {
		$headers['X-Content-Type-Options'] = 'nosniff';
	}

	if ( ! empty( $options['referrer_policy_enabled'] ) ) {
		$headers['Referrer-Policy'] = $options['referrer_policy'];
	}

	if ( ! empty( $options['permissions_policy_enabled'] ) && '' !== $options['permissions_policy'] ) {
		$headers['Permissions-Policy'] = $options['permissions_policy'];
	}

	return $headers;
}

/**
 * Send configured security headers on front-end responses.
 */
function website_optimiser_send_security_headers() {
	if ( is_admin() || wp_doing_ajax() || headers_sent() ) {
		return;
	}

	foreach ( website_optimiser_get_enabled_security_headers() as $name => $value ) {
		header( $name . ': ' . $value, true );
	}
}
add_action( 'send_headers', 'website_optimiser_send_security_headers' );

/**
 * Check security headers status for the optimisation dashboard.
 *
 * @return array
 */
function website_optimiser_check_security_headers_status() {
	$options  = website_optimiser_get_security_headers_options();
	$resolved = get_option( 'website_optimiser_security_headers_resolved', false );

	if ( empty( $options['enabled'] ) && ! $resolved ) {
		return array(
			'class'       => 'status-warning',
			'text'        => __( 'Security headers disabled', 'website-optimiser' ),
			'description' => __( 'Enable the module to send recommended browser security headers.', 'website-optimiser' ),
			'enabled'     => false,
			'active'      => array(),
		);
	}

	if ( $resolved ) {
		return array(
			'class'       => 'status-good',
			'text'        => __( 'Manually Resolved', 'website-optimiser' ),
			'description' => __( 'Security Headers have been manually marked as resolved.', 'website-optimiser' ),
			'enabled'     => ! empty( $options['enabled'] ),
			'active'      => array(),
		);
	}

	$active = array();

	if ( ! empty( $options['hsts_enabled'] ) ) {
		$active[] = is_ssl() ? 'Strict-Transport-Security' : 'Strict-Transport-Security (requires HTTPS)';
	}
	if ( ! empty( $options['csp_enabled'] ) && '' !== $options['csp_policy'] ) {
		$active[] = 'Content-Security-Policy';
	}
	if ( ! empty( $options['x_frame_enabled'] ) ) {
		$active[] = 'X-Frame-Options';
	}
	if ( ! empty( $options['x_content_type_enabled'] ) ) {
		$active[] = 'X-Content-Type-Options';
	}
	if ( ! empty( $options['referrer_policy_enabled'] ) ) {
		$active[] = 'Referrer-Policy';
	}
	if ( ! empty( $options['permissions_policy_enabled'] ) && '' !== $options['permissions_policy'] ) {
		$active[] = 'Permissions-Policy';
	}

	$count = count( $active );
	$class = ( $count >= 6 && is_ssl() ) ? 'status-good' : 'status-warning';

	if ( 0 === $count ) {
		$class = 'status-error';
	}

	return array(
		'class'       => $class,
		'text'        => sprintf(
			/* translators: %d: number of configured security headers */
			_n( '%d security header configured', '%d security headers configured', $count, 'website-optimiser' ),
			$count
		),
		'description' => empty( $active ) ? __( 'No security headers are currently active.', 'website-optimiser' ) : implode( ', ', $active ),
		'enabled'     => true,
		'active'      => $active,
	);
}

/**
 * Register security headers settings.
 */
function website_optimiser_register_security_headers_settings() {
	register_setting(
		'website_optimiser_security_headers_options',
		WEBSITE_OPTIMISER_SECURITY_HEADERS_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'website_optimiser_sanitize_security_headers_options',
			'default'           => website_optimiser_get_security_headers_defaults(),
		)
	);

	add_settings_section(
		'website_optimiser_security_headers_section',
		__( 'Security Headers', 'website-optimiser' ),
		'website_optimiser_security_headers_section_cb',
		'website-optimiser-security-headers'
	);

	add_settings_field(
		'website_optimiser_security_headers_fields',
		__( 'Header Settings', 'website-optimiser' ),
		'website_optimiser_security_headers_fields_cb',
		'website-optimiser-security-headers',
		'website_optimiser_security_headers_section'
	);
}
add_action( 'admin_init', 'website_optimiser_register_security_headers_settings' );

/**
 * Add Security Headers settings page.
 */
function website_optimiser_add_security_headers_menu() {
	add_submenu_page(
		'website-optimisation',
		__( 'Security Headers', 'website-optimiser' ),
		__( 'Security Headers', 'website-optimiser' ),
		'manage_options',
		'website-optimiser-security-headers',
		'website_optimiser_render_security_headers_page'
	);
}
add_action( 'admin_menu', 'website_optimiser_add_security_headers_menu', 25 );

/**
 * Settings section intro.
 */
function website_optimiser_security_headers_section_cb() {
	echo '<p>' . esc_html__( 'Configure browser security headers sent by Website Optimiser on front-end responses. Test Content Security Policy carefully before enabling it on a live site.', 'website-optimiser' ) . '</p>';
}

/**
 * Render security headers settings page.
 */
function website_optimiser_render_security_headers_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Security Headers', 'website-optimiser' ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'website_optimiser_security_headers_options' );
			do_settings_sections( 'website-optimiser-security-headers' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Render all security header inputs.
 */
function website_optimiser_security_headers_fields_cb() {
	$options = website_optimiser_get_security_headers_options();
	$name    = WEBSITE_OPTIMISER_SECURITY_HEADERS_OPTION;
	?>
	<style>
		.website-optimiser-security-fields {
			max-width: 920px;
		}
		.website-optimiser-security-card {
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			margin-bottom: 16px;
			padding: 16px;
		}
		.website-optimiser-security-card h3 {
			margin-top: 0;
		}
		.website-optimiser-security-card textarea {
			width: 100%;
			min-height: 90px;
			font-family: monospace;
		}
		.website-optimiser-security-inline {
			display: flex;
			flex-wrap: wrap;
			gap: 12px 20px;
			align-items: center;
		}
		.website-optimiser-security-fields code {
			white-space: normal;
		}
	</style>
	<div class="website-optimiser-security-fields">
		<div class="website-optimiser-security-card">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( 1, $options['enabled'] ); ?>>
				<strong><?php esc_html_e( 'Enable Website Optimiser security headers', 'website-optimiser' ); ?></strong>
			</label>
			<p class="description"><?php esc_html_e( 'When enabled, the selected headers are sent on front-end responses. Admin screens are excluded.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-security-card">
			<h3><?php esc_html_e( 'Strict-Transport-Security', 'website-optimiser' ); ?></h3>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hsts_enabled]" value="1" <?php checked( 1, $options['hsts_enabled'] ); ?>>
				<?php esc_html_e( 'Send HSTS header on HTTPS requests', 'website-optimiser' ); ?>
			</label>
			<div class="website-optimiser-security-inline" style="margin-top: 12px;">
				<label>
					<?php esc_html_e( 'Max age', 'website-optimiser' ); ?>
					<input type="number" min="0" name="<?php echo esc_attr( $name ); ?>[hsts_max_age]" value="<?php echo esc_attr( $options['hsts_max_age'] ); ?>" class="small-text">
				</label>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hsts_include_subdomains]" value="1" <?php checked( 1, $options['hsts_include_subdomains'] ); ?>>
					<?php esc_html_e( 'Include subdomains', 'website-optimiser' ); ?>
				</label>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hsts_preload]" value="1" <?php checked( 1, $options['hsts_preload'] ); ?>>
					<?php esc_html_e( 'Add preload directive', 'website-optimiser' ); ?>
				</label>
			</div>
			<p class="description"><?php esc_html_e( 'Recommended baseline: max-age=31536000; includeSubDomains. Only enable preload if the domain is ready for browser preload lists.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-security-card">
			<h3><?php esc_html_e( 'Content-Security-Policy', 'website-optimiser' ); ?></h3>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[csp_enabled]" value="1" <?php checked( 1, $options['csp_enabled'] ); ?>>
				<?php esc_html_e( 'Send Content-Security-Policy header', 'website-optimiser' ); ?>
			</label>
			<p>
				<textarea name="<?php echo esc_attr( $name ); ?>[csp_policy]"><?php echo esc_textarea( $options['csp_policy'] ); ?></textarea>
			</p>
			<p class="description"><?php esc_html_e( 'CSP can block scripts, styles, fonts, embeds, and analytics that are not explicitly allowed. Start with report-only testing outside this module if the site has complex third-party assets.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-security-card">
			<h3><?php esc_html_e( 'Frame and MIME Protection', 'website-optimiser' ); ?></h3>
			<div class="website-optimiser-security-inline">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[x_frame_enabled]" value="1" <?php checked( 1, $options['x_frame_enabled'] ); ?>>
					<?php esc_html_e( 'Send X-Frame-Options', 'website-optimiser' ); ?>
				</label>
				<select name="<?php echo esc_attr( $name ); ?>[x_frame_option]">
					<option value="SAMEORIGIN" <?php selected( 'SAMEORIGIN', $options['x_frame_option'] ); ?>>SAMEORIGIN</option>
					<option value="DENY" <?php selected( 'DENY', $options['x_frame_option'] ); ?>>DENY</option>
				</select>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[x_content_type_enabled]" value="1" <?php checked( 1, $options['x_content_type_enabled'] ); ?>>
					<?php esc_html_e( 'Send X-Content-Type-Options: nosniff', 'website-optimiser' ); ?>
				</label>
			</div>
		</div>

		<div class="website-optimiser-security-card">
			<h3><?php esc_html_e( 'Referrer-Policy', 'website-optimiser' ); ?></h3>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[referrer_policy_enabled]" value="1" <?php checked( 1, $options['referrer_policy_enabled'] ); ?>>
				<?php esc_html_e( 'Send Referrer-Policy header', 'website-optimiser' ); ?>
			</label>
			<select name="<?php echo esc_attr( $name ); ?>[referrer_policy]" style="display: block; margin-top: 12px;">
				<?php foreach ( array( 'no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin', 'same-origin', 'strict-origin', 'strict-origin-when-cross-origin', 'unsafe-url' ) as $policy ) : ?>
					<option value="<?php echo esc_attr( $policy ); ?>" <?php selected( $policy, $options['referrer_policy'] ); ?>><?php echo esc_html( $policy ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="website-optimiser-security-card">
			<h3><?php esc_html_e( 'Permissions-Policy', 'website-optimiser' ); ?></h3>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[permissions_policy_enabled]" value="1" <?php checked( 1, $options['permissions_policy_enabled'] ); ?>>
				<?php esc_html_e( 'Send Permissions-Policy header', 'website-optimiser' ); ?>
			</label>
			<p>
				<textarea name="<?php echo esc_attr( $name ); ?>[permissions_policy]"><?php echo esc_textarea( $options['permissions_policy'] ); ?></textarea>
			</p>
			<p class="description"><?php esc_html_e( 'Default disables camera, microphone, geolocation, payment, and USB APIs for all origins.', 'website-optimiser' ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * Handle AJAX request to manually resolve Security Headers.
 */
function website_optimiser_approve_security_headers() {
	if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
	}

	$approved_by = sanitize_text_field( $_POST['approved_by'] ?? '' );
	if ( empty( $approved_by ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Name is required' ) ) );
	}

	update_option( 'website_optimiser_security_headers_resolved', true );
	update_option( 'website_optimiser_security_headers_resolved_by', $approved_by );
	update_option( 'website_optimiser_security_headers_resolved_date', current_time( 'mysql' ) );

	wp_die( wp_json_encode( array( 'success' => true, 'message' => 'Security Headers marked as resolved' ) ) );
}
add_action( 'wp_ajax_website_optimiser_approve_security_headers', 'website_optimiser_approve_security_headers' );

/**
 * Handle AJAX request to reset Security Headers manual resolution.
 */
function website_optimiser_reset_security_headers_approval() {
	if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
	}

	delete_option( 'website_optimiser_security_headers_resolved' );
	delete_option( 'website_optimiser_security_headers_resolved_by' );
	delete_option( 'website_optimiser_security_headers_resolved_date' );

	wp_die( wp_json_encode( array( 'success' => true, 'message' => 'Security Headers resolution reset' ) ) );
}
add_action( 'wp_ajax_website_optimiser_reset_security_headers_approval', 'website_optimiser_reset_security_headers_approval' );

/**
 * Render Security Headers dashboard section.
 */
function website_optimiser_render_security_headers_section() {
	$status = website_optimiser_check_security_headers_status();
	$sh_resolved      = get_option( 'website_optimiser_security_headers_resolved', false );
	$sh_resolved_by   = get_option( 'website_optimiser_security_headers_resolved_by', '' );
	$sh_resolved_date = get_option( 'website_optimiser_security_headers_resolved_date', '' );
	?>
	<div class="seo-stat-item <?php echo esc_attr( $sh_resolved ? 'status-good' : $status['class'] ); ?>">
		<div class="stat-icon">SH</div>
		<div class="stat-content">
			<h4><?php esc_html_e( 'Security Headers', 'website-optimiser' ); ?></h4>
			<div class="stat-status <?php echo esc_attr( $sh_resolved ? 'status-good' : $status['class'] ); ?>">
				<?php echo esc_html( $sh_resolved ? 'Manually Resolved' : $status['text'] ); ?>
			</div>
			<div class="stat-label">
				<?php echo esc_html( $sh_resolved ? 'Security Headers have been manually marked as resolved.' : $status['description'] ); ?>
				<?php if ( ! is_ssl() && ! empty( $status['enabled'] ) ) : ?>
					<br><br><small><em><?php esc_html_e( 'HSTS is only sent over HTTPS.', 'website-optimiser' ); ?></em></small>
				<?php endif; ?>
				<?php if ( $sh_resolved ) : ?>
					<br><br><small><strong>Resolved by:</strong> <?php echo esc_html( $sh_resolved_by ); ?></small>
					<br><small><strong>Date:</strong> <?php echo esc_html( date( 'M j, Y g:i A', strtotime( $sh_resolved_date ) ) ); ?></small>
				<?php endif; ?>
			</div>
			<div class="stat-action">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=website-optimiser-security-headers' ) ); ?>" class="button button-small">
					<?php esc_html_e( 'Configure Headers', 'website-optimiser' ); ?>
				</a>
				<?php if ( $sh_resolved ) : ?>
					<button type="button" class="button button-small" onclick="resetSecurityHeadersApproval()">
						Reset Resolution
					</button>
				<?php else : ?>
					<div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
						<label style="display: block; margin-bottom: 8px; font-weight: 600;">
							Manually mark Security Headers as resolved?
						</label>
						<div style="margin-bottom: 12px; font-size: 13px; color: #666;">
							Use this if you have reviewed the security headers configuration and are satisfied with the current setup, or headers are managed at the server/hosting level.
						</div>
						<label style="display: block; margin-bottom: 8px;">
							<input type="checkbox" id="security-headers-resolve-checkbox" style="margin-right: 5px;">
							Confirm that Security Headers have been reviewed and any issues addressed
						</label>
						<input type="text" id="security-headers-resolved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
						<button type="button" class="button button-small" onclick="approveSecurityHeaders()" disabled id="security-headers-resolve-btn">
							Mark as Resolved
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var shCheckbox = document.getElementById('security-headers-resolve-checkbox');
		var shName = document.getElementById('security-headers-resolved-by-name');
		var shBtn = document.getElementById('security-headers-resolve-btn');
		if (shCheckbox && shName && shBtn) {
			shCheckbox.addEventListener('change', function() {
				if (this.checked) {
					shName.disabled = false;
					shName.focus();
					shName.addEventListener('input', function() {
						shBtn.disabled = this.value.trim() === '';
					});
				} else {
					shName.disabled = true;
					shName.value = '';
					shBtn.disabled = true;
				}
			});
		}
	});

	function approveSecurityHeaders() {
		var checkbox = document.getElementById('security-headers-resolve-checkbox');
		var nameField = document.getElementById('security-headers-resolved-by-name');
		if (!checkbox || !checkbox.checked) { alert('Please check the confirmation checkbox first.'); return; }
		var approvedBy = nameField.value.trim();
		if (!approvedBy) { alert('Please enter your name.'); nameField.focus(); return; }
		if (!confirm('Are you sure you want to mark Security Headers as resolved? This confirmation will be tracked.')) return;
		jQuery.post(ajaxurl, {
			action: 'website_optimiser_approve_security_headers',
			approved_by: approvedBy,
			nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
		}, function(response) {
			var result = JSON.parse(response);
			if (result.success) { alert('Security Headers marked as resolved.'); location.reload(); }
			else { alert('Error: ' + result.message); }
		}).fail(function() { alert('Error processing request. Please try again.'); });
	}

	function resetSecurityHeadersApproval() {
		if (!confirm('Are you sure you want to reset the Security Headers resolution? This will remove the current confirmation.')) return;
		jQuery.post(ajaxurl, {
			action: 'website_optimiser_reset_security_headers_approval',
			nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
		}, function(response) {
			var result = JSON.parse(response);
			if (result.success) { alert('Security Headers resolution reset.'); location.reload(); }
			else { alert('Error: ' + result.message); }
		}).fail(function() { alert('Error processing request. Please try again.'); });
	}
	</script>
	<?php
}
