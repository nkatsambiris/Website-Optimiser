<?php
/**
 * SSL certificate check for Website Optimiser.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check SSL status for the dashboard.
 *
 * @return array
 */
function website_optimiser_check_ssl_status() {
	$site_url    = home_url( '/' );
	$is_https    = ( 'https' === wp_parse_url( $site_url, PHP_URL_SCHEME ) );
	$is_ssl_req  = is_ssl();
	$force_ssl   = defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN;

	$resolved      = get_option( 'website_optimiser_ssl_resolved', false );
	$resolved_by   = get_option( 'website_optimiser_ssl_resolved_by', '' );
	$resolved_date = get_option( 'website_optimiser_ssl_resolved_date', '' );
	$resolved_note = get_option( 'website_optimiser_ssl_resolved_note', '' );

	$details = array();

	if ( $is_https ) {
		$details[] = __( 'Site URL uses HTTPS', 'website-optimiser' );
	} else {
		$details[] = __( 'Site URL uses HTTP (not HTTPS)', 'website-optimiser' );
	}

	if ( $is_ssl_req ) {
		$details[] = __( 'Current request served over SSL', 'website-optimiser' );
	}

	if ( $force_ssl ) {
		$details[] = __( 'FORCE_SSL_ADMIN is enabled', 'website-optimiser' );
	}

	$ssl_active = $is_https && $is_ssl_req;

	if ( $ssl_active ) {
		return array(
			'class'         => 'status-good',
			'status'        => __( 'SSL Active', 'website-optimiser' ),
			'message'       => __( 'This site is configured and served over HTTPS.', 'website-optimiser' ),
			'ssl_active'    => true,
			'details'       => $details,
			'resolved'      => $resolved,
			'resolved_by'   => $resolved_by,
			'resolved_date' => $resolved_date,
			'resolved_note' => $resolved_note,
		);
	}

	if ( $resolved ) {
		return array(
			'class'         => 'status-good',
			'status'        => __( 'Manually Resolved', 'website-optimiser' ),
			'message'       => ! empty( $resolved_note )
				? $resolved_note
				: __( 'SSL status has been manually reviewed.', 'website-optimiser' ),
			'ssl_active'    => false,
			'details'       => $details,
			'resolved'      => true,
			'resolved_by'   => $resolved_by,
			'resolved_date' => $resolved_date,
			'resolved_note' => $resolved_note,
		);
	}

	return array(
		'class'         => 'status-error',
		'status'        => __( 'SSL Not Active', 'website-optimiser' ),
		'message'       => __( 'The site does not appear to be fully served over HTTPS. Ensure the SSL certificate is installed and the site URL is set to https://.', 'website-optimiser' ),
		'ssl_active'    => false,
		'details'       => $details,
		'resolved'      => false,
		'resolved_by'   => '',
		'resolved_date' => '',
		'resolved_note' => '',
	);
}

/**
 * Handle AJAX request to manually resolve SSL check.
 */
function website_optimiser_approve_ssl() {
	if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
	}

	$approved_by = sanitize_text_field( $_POST['approved_by'] ?? '' );
	if ( empty( $approved_by ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Name is required' ) ) );
	}

	$note = sanitize_textarea_field( $_POST['note'] ?? '' );

	update_option( 'website_optimiser_ssl_resolved', true );
	update_option( 'website_optimiser_ssl_resolved_by', $approved_by );
	update_option( 'website_optimiser_ssl_resolved_date', current_time( 'mysql' ) );
	update_option( 'website_optimiser_ssl_resolved_note', $note );

	wp_die( wp_json_encode( array( 'success' => true, 'message' => 'SSL check marked as resolved' ) ) );
}
add_action( 'wp_ajax_website_optimiser_approve_ssl', 'website_optimiser_approve_ssl' );

/**
 * Handle AJAX request to reset SSL resolution.
 */
function website_optimiser_reset_ssl_approval() {
	if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
	}

	delete_option( 'website_optimiser_ssl_resolved' );
	delete_option( 'website_optimiser_ssl_resolved_by' );
	delete_option( 'website_optimiser_ssl_resolved_date' );
	delete_option( 'website_optimiser_ssl_resolved_note' );

	wp_die( wp_json_encode( array( 'success' => true, 'message' => 'SSL resolution reset' ) ) );
}
add_action( 'wp_ajax_website_optimiser_reset_ssl_approval', 'website_optimiser_reset_ssl_approval' );

/**
 * Render SSL check dashboard section.
 */
function website_optimiser_render_ssl_check_section() {
	$status = website_optimiser_check_ssl_status();
	?>
	<div class="seo-stat-item <?php echo esc_attr( $status['class'] ); ?>">
		<div class="stat-icon">🔒</div>
		<div class="stat-content">
			<h4><?php esc_html_e( 'SSL Certificate', 'website-optimiser' ); ?></h4>
			<div class="stat-status <?php echo esc_attr( $status['class'] ); ?>">
				<?php echo esc_html( $status['status'] ); ?>
			</div>
			<div class="stat-label">
				<?php echo esc_html( $status['message'] ); ?>

				<?php if ( ! empty( $status['details'] ) ) : ?>
					<br><br><small>
					<?php foreach ( $status['details'] as $detail ) : ?>
						• <?php echo esc_html( $detail ); ?><br>
					<?php endforeach; ?>
					</small>
				<?php endif; ?>

				<?php if ( $status['resolved'] ) : ?>
					<br><small><strong>Resolved by:</strong> <?php echo esc_html( $status['resolved_by'] ); ?></small>
					<br><small><strong>Date:</strong> <?php echo esc_html( date( 'M j, Y g:i A', strtotime( $status['resolved_date'] ) ) ); ?></small>
					<?php if ( ! empty( $status['resolved_note'] ) ) : ?>
						<br><small><strong>Note:</strong> <?php echo esc_html( $status['resolved_note'] ); ?></small>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<div class="stat-action">
				<?php if ( $status['resolved'] ) : ?>
					<button type="button" class="button button-small" onclick="resetSslApproval()">
						Reset Resolution
					</button>
				<?php elseif ( ! $status['ssl_active'] ) : ?>
					<div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
						<label style="display: block; margin-bottom: 8px; font-weight: 600;">
							Manually resolve SSL check?
						</label>
						<div style="margin-bottom: 12px; font-size: 13px; color: #666;">
							Use this if SSL is handled at the hosting or proxy level, or if the site is a local/staging environment where HTTPS is not required.
						</div>
						<label style="display: block; margin-bottom: 8px;">
							<input type="checkbox" id="ssl-resolve-checkbox" style="margin-right: 5px;">
							Confirm that SSL status has been reviewed
						</label>
						<textarea id="ssl-resolved-note" placeholder="Optional note (e.g. 'Local dev environment' or 'SSL terminated at load balancer')" style="width: 100%; margin-bottom: 8px; min-height: 50px;" disabled></textarea>
						<input type="text" id="ssl-resolved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
						<button type="button" class="button button-small" onclick="approveSsl()" disabled id="ssl-resolve-btn">
							Mark as Resolved
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var sslCheckbox = document.getElementById('ssl-resolve-checkbox');
		var sslNote = document.getElementById('ssl-resolved-note');
		var sslName = document.getElementById('ssl-resolved-by-name');
		var sslBtn = document.getElementById('ssl-resolve-btn');
		if (sslCheckbox && sslName && sslBtn) {
			sslCheckbox.addEventListener('change', function() {
				if (this.checked) {
					sslNote.disabled = false;
					sslName.disabled = false;
					sslName.focus();
					sslName.addEventListener('input', function() {
						sslBtn.disabled = this.value.trim() === '';
					});
				} else {
					sslNote.disabled = true;
					sslName.disabled = true;
					sslName.value = '';
					sslBtn.disabled = true;
				}
			});
		}
	});

	function approveSsl() {
		var checkbox = document.getElementById('ssl-resolve-checkbox');
		var nameField = document.getElementById('ssl-resolved-by-name');
		var noteField = document.getElementById('ssl-resolved-note');
		if (!checkbox || !checkbox.checked) { alert('Please check the confirmation checkbox first.'); return; }
		var approvedBy = nameField.value.trim();
		if (!approvedBy) { alert('Please enter your name.'); nameField.focus(); return; }
		if (!confirm('Are you sure you want to mark the SSL check as resolved? This confirmation will be tracked.')) return;
		jQuery.post(ajaxurl, {
			action: 'website_optimiser_approve_ssl',
			approved_by: approvedBy,
			note: noteField ? noteField.value.trim() : '',
			nonce: '<?php echo wp_create_nonce( 'meta_description_boy_nonce' ); ?>'
		}, function(response) {
			var result = JSON.parse(response);
			if (result.success) { alert('SSL check marked as resolved.'); location.reload(); }
			else { alert('Error: ' + result.message); }
		}).fail(function() { alert('Error processing request. Please try again.'); });
	}

	function resetSslApproval() {
		if (!confirm('Are you sure you want to reset the SSL resolution? This will remove the current confirmation.')) return;
		jQuery.post(ajaxurl, {
			action: 'website_optimiser_reset_ssl_approval',
			nonce: '<?php echo wp_create_nonce( 'meta_description_boy_nonce' ); ?>'
		}, function(response) {
			var result = JSON.parse(response);
			if (result.success) { alert('SSL resolution reset.'); location.reload(); }
			else { alert('Error: ' + result.message); }
		}).fail(function() { alert('Error processing request. Please try again.'); });
	}
	</script>
	<?php
}
