<?php
/**
 * Cloudflare Proxy check for Website Optimiser.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Detect whether the current request is being served through Cloudflare proxy.
 *
 * Cloudflare adds specific headers to proxied requests. Checking $_SERVER
 * is reliable when called during a real admin page load.
 *
 * @return array Detection results.
 */
function website_optimiser_detect_cloudflare_proxy() {
	$indicators = array();

	if ( ! empty( $_SERVER['HTTP_CF_RAY'] ) ) {
		$indicators['cf_ray'] = sanitize_text_field( $_SERVER['HTTP_CF_RAY'] );
	}

	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		$indicators['cf_connecting_ip'] = sanitize_text_field( $_SERVER['HTTP_CF_CONNECTING_IP'] );
	}

	if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
		$indicators['cf_ipcountry'] = sanitize_text_field( $_SERVER['HTTP_CF_IPCOUNTRY'] );
	}

	if ( ! empty( $_SERVER['HTTP_CF_VISITOR'] ) ) {
		$indicators['cf_visitor'] = sanitize_text_field( $_SERVER['HTTP_CF_VISITOR'] );
	}

	$server_header = isset( $_SERVER['HTTP_SERVER'] ) ? strtolower( $_SERVER['HTTP_SERVER'] ) : '';
	if ( false !== strpos( $server_header, 'cloudflare' ) ) {
		$indicators['server_header'] = true;
	}

	return array(
		'detected'   => ! empty( $indicators ),
		'indicators' => $indicators,
	);
}

/**
 * Check Cloudflare proxy status for the dashboard.
 *
 * @return array
 */
function website_optimiser_check_cloudflare_proxy_status() {
	$resolved      = get_option( 'website_optimiser_cloudflare_proxy_resolved', false );
	$resolved_by   = get_option( 'website_optimiser_cloudflare_proxy_resolved_by', '' );
	$resolved_date = get_option( 'website_optimiser_cloudflare_proxy_resolved_date', '' );
	$resolved_note = get_option( 'website_optimiser_cloudflare_proxy_resolved_note', '' );
	$detection     = website_optimiser_detect_cloudflare_proxy();

	if ( $detection['detected'] ) {
		return array(
			'class'         => 'status-good',
			'status'        => __( 'Cloudflare Proxy Active', 'website-optimiser' ),
			'message'       => __( 'This site is being served through Cloudflare proxy.', 'website-optimiser' ),
			'detected'      => true,
			'resolved'      => $resolved,
			'resolved_by'   => $resolved_by,
			'resolved_date' => $resolved_date,
			'resolved_note' => $resolved_note,
			'indicators'    => $detection['indicators'],
		);
	}

	if ( $resolved ) {
		return array(
			'class'         => 'status-good',
			'status'        => __( 'Manually Resolved', 'website-optimiser' ),
			'message'       => ! empty( $resolved_note )
				? $resolved_note
				: __( 'Cloudflare proxy status has been manually reviewed.', 'website-optimiser' ),
			'detected'      => false,
			'resolved'      => true,
			'resolved_by'   => $resolved_by,
			'resolved_date' => $resolved_date,
			'resolved_note' => $resolved_note,
			'indicators'    => array(),
		);
	}

	return array(
		'class'         => 'status-warning',
		'status'        => __( 'Cloudflare Not Detected', 'website-optimiser' ),
		'message'       => __( 'No Cloudflare proxy headers found on the current request. If the site should be proxied through Cloudflare, check the DNS settings.', 'website-optimiser' ),
		'detected'      => false,
		'resolved'      => false,
		'resolved_by'   => '',
		'resolved_date' => '',
		'resolved_note' => '',
		'indicators'    => array(),
	);
}

/**
 * Handle AJAX request to manually resolve Cloudflare proxy check.
 */
function website_optimiser_approve_cloudflare_proxy() {
	if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
	}

	$approved_by = sanitize_text_field( $_POST['approved_by'] ?? '' );
	if ( empty( $approved_by ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Name is required' ) ) );
	}

	$note = sanitize_textarea_field( $_POST['note'] ?? '' );

	update_option( 'website_optimiser_cloudflare_proxy_resolved', true );
	update_option( 'website_optimiser_cloudflare_proxy_resolved_by', $approved_by );
	update_option( 'website_optimiser_cloudflare_proxy_resolved_date', current_time( 'mysql' ) );
	update_option( 'website_optimiser_cloudflare_proxy_resolved_note', $note );

	wp_die( wp_json_encode( array( 'success' => true, 'message' => 'Cloudflare proxy check marked as resolved' ) ) );
}
add_action( 'wp_ajax_website_optimiser_approve_cloudflare_proxy', 'website_optimiser_approve_cloudflare_proxy' );

/**
 * Handle AJAX request to reset Cloudflare proxy resolution.
 */
function website_optimiser_reset_cloudflare_proxy_approval() {
	if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
	}

	delete_option( 'website_optimiser_cloudflare_proxy_resolved' );
	delete_option( 'website_optimiser_cloudflare_proxy_resolved_by' );
	delete_option( 'website_optimiser_cloudflare_proxy_resolved_date' );
	delete_option( 'website_optimiser_cloudflare_proxy_resolved_note' );

	wp_die( wp_json_encode( array( 'success' => true, 'message' => 'Cloudflare proxy resolution reset' ) ) );
}
add_action( 'wp_ajax_website_optimiser_reset_cloudflare_proxy_approval', 'website_optimiser_reset_cloudflare_proxy_approval' );

/**
 * Render Cloudflare proxy dashboard section.
 */
function website_optimiser_render_cloudflare_proxy_section() {
	$status = website_optimiser_check_cloudflare_proxy_status();
	?>
	<div class="seo-stat-item <?php echo esc_attr( $status['class'] ); ?>">
		<div class="stat-icon">CF</div>
		<div class="stat-content">
			<h4><?php esc_html_e( 'Cloudflare Proxy', 'website-optimiser' ); ?></h4>
			<div class="stat-status <?php echo esc_attr( $status['class'] ); ?>">
				<?php echo esc_html( $status['status'] ); ?>
			</div>
			<div class="stat-label">
				<?php echo esc_html( $status['message'] ); ?>

				<?php if ( $status['detected'] && ! empty( $status['indicators'] ) ) : ?>
					<br><br><small>
						<?php if ( ! empty( $status['indicators']['cf_ray'] ) ) : ?>
							<strong>CF-Ray:</strong> <?php echo esc_html( $status['indicators']['cf_ray'] ); ?><br>
						<?php endif; ?>
						<?php if ( ! empty( $status['indicators']['cf_ipcountry'] ) ) : ?>
							<strong>Country:</strong> <?php echo esc_html( $status['indicators']['cf_ipcountry'] ); ?><br>
						<?php endif; ?>
					</small>
				<?php endif; ?>

				<?php if ( $status['resolved'] ) : ?>
					<br><br><small><strong>Resolved by:</strong> <?php echo esc_html( $status['resolved_by'] ); ?></small>
					<br><small><strong>Date:</strong> <?php echo esc_html( date( 'M j, Y g:i A', strtotime( $status['resolved_date'] ) ) ); ?></small>
					<?php if ( ! empty( $status['resolved_note'] ) ) : ?>
						<br><small><strong>Note:</strong> <?php echo esc_html( $status['resolved_note'] ); ?></small>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<div class="stat-action">
				<?php if ( $status['resolved'] ) : ?>
					<button type="button" class="button button-small" onclick="resetCloudflareProxyApproval()">
						Reset Resolution
					</button>
				<?php elseif ( ! $status['detected'] ) : ?>
					<div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
						<label style="display: block; margin-bottom: 8px; font-weight: 600;">
							Manually resolve Cloudflare proxy check?
						</label>
						<div style="margin-bottom: 12px; font-size: 13px; color: #666;">
							Use this if the site intentionally does not use Cloudflare, or if caching and CDN are handled by another provider.
						</div>
						<label style="display: block; margin-bottom: 8px;">
							<input type="checkbox" id="cloudflare-proxy-resolve-checkbox" style="margin-right: 5px;">
							Confirm that Cloudflare proxy status has been reviewed
						</label>
						<textarea id="cloudflare-proxy-resolved-note" placeholder="Optional note (e.g. 'Using Cloudways CDN instead' or 'Cloudflare DNS-only mode')" style="width: 100%; margin-bottom: 8px; min-height: 50px;" disabled></textarea>
						<input type="text" id="cloudflare-proxy-resolved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
						<button type="button" class="button button-small" onclick="approveCloudflareProxy()" disabled id="cloudflare-proxy-resolve-btn">
							Mark as Resolved
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var cfCheckbox = document.getElementById('cloudflare-proxy-resolve-checkbox');
		var cfNote = document.getElementById('cloudflare-proxy-resolved-note');
		var cfName = document.getElementById('cloudflare-proxy-resolved-by-name');
		var cfBtn = document.getElementById('cloudflare-proxy-resolve-btn');
		if (cfCheckbox && cfName && cfBtn) {
			cfCheckbox.addEventListener('change', function() {
				if (this.checked) {
					cfNote.disabled = false;
					cfName.disabled = false;
					cfName.focus();
					cfName.addEventListener('input', function() {
						cfBtn.disabled = this.value.trim() === '';
					});
				} else {
					cfNote.disabled = true;
					cfName.disabled = true;
					cfName.value = '';
					cfBtn.disabled = true;
				}
			});
		}
	});

	function approveCloudflareProxy() {
		var checkbox = document.getElementById('cloudflare-proxy-resolve-checkbox');
		var nameField = document.getElementById('cloudflare-proxy-resolved-by-name');
		var noteField = document.getElementById('cloudflare-proxy-resolved-note');
		if (!checkbox || !checkbox.checked) { alert('Please check the confirmation checkbox first.'); return; }
		var approvedBy = nameField.value.trim();
		if (!approvedBy) { alert('Please enter your name.'); nameField.focus(); return; }
		if (!confirm('Are you sure you want to mark Cloudflare proxy check as resolved? This confirmation will be tracked.')) return;
		jQuery.post(ajaxurl, {
			action: 'website_optimiser_approve_cloudflare_proxy',
			approved_by: approvedBy,
			note: noteField ? noteField.value.trim() : '',
			nonce: '<?php echo wp_create_nonce( 'meta_description_boy_nonce' ); ?>'
		}, function(response) {
			var result = JSON.parse(response);
			if (result.success) { alert('Cloudflare proxy check marked as resolved.'); location.reload(); }
			else { alert('Error: ' + result.message); }
		}).fail(function() { alert('Error processing request. Please try again.'); });
	}

	function resetCloudflareProxyApproval() {
		if (!confirm('Are you sure you want to reset the Cloudflare proxy resolution? This will remove the current confirmation.')) return;
		jQuery.post(ajaxurl, {
			action: 'website_optimiser_reset_cloudflare_proxy_approval',
			nonce: '<?php echo wp_create_nonce( 'meta_description_boy_nonce' ); ?>'
		}, function(response) {
			var result = JSON.parse(response);
			if (result.success) { alert('Cloudflare proxy resolution reset.'); location.reload(); }
			else { alert('Error: ' + result.message); }
		}).fail(function() { alert('Error processing request. Please try again.'); });
	}
	</script>
	<?php
}
