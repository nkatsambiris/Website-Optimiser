<?php
/**
 * Mail delivery checks for Website Optimiser.
 *
 * Focuses on whether WordPress can send email (test + optional server-level confirmation).
 * Per-notification FROM/TO rules are handled in Gravity Forms Notifications and WooCommerce Emails.
 */

defined( 'ABSPATH' ) || exit;

const WEBSITE_OPTIMISER_SMTP_TEST_RESULT_OPTION = 'website_optimiser_smtp_test_result';

/**
 * Known SMTP plugin slugs mapped to display names.
 *
 * @return array<string, string>
 */
function website_optimiser_get_smtp_plugin_map() {
	return array(
		'wp-mail-smtp/wp_mail_smtp.php'                     => 'WP Mail SMTP',
		'post-smtp/postman-smtp.php'                        => 'Post SMTP',
		'fluent-smtp/fluent-smtp.php'                       => 'FluentSMTP',
		'easy-wp-smtp/easy-wp-smtp.php'                     => 'Easy WP SMTP',
		'wp-smtp/wp-smtp.php'                               => 'WP SMTP',
		'smtp-mailer/main.php'                              => 'SMTP Mailer',
		'gosmtp/gosmtp.php'                                 => 'GoSMTP',
		'mail-smtp/mail-smtp.php'                           => 'Mail SMTP',
		'sendgrid-email-delivery-simplified/wpsendgrid.php' => 'SendGrid (WordPress plugin)',
	);
}

/**
 * Get stored server-level mail confirmation details.
 *
 * @return array
 */
function website_optimiser_get_server_mail_approval() {
	return array(
		'approved'      => (bool) get_option( 'website_optimiser_server_mail_approved', false ),
		'approved_by'   => (string) get_option( 'website_optimiser_server_mail_approved_by', '' ),
		'approved_date' => (string) get_option( 'website_optimiser_server_mail_approved_date', '' ),
		'approved_note' => (string) get_option( 'website_optimiser_server_mail_approved_note', '' ),
	);
}

/**
 * Detect how mail transport appears to be configured.
 *
 * @return array
 */
function website_optimiser_detect_mail_transport() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_map     = website_optimiser_get_smtp_plugin_map();
	$active_plugins = array();
	$installed      = array();

	foreach ( $plugin_map as $plugin_file => $plugin_name ) {
		$path = WP_PLUGIN_DIR . '/' . $plugin_file;

		if ( ! file_exists( $path ) ) {
			continue;
		}

		$installed[] = $plugin_name;

		if ( is_plugin_active( $plugin_file ) ) {
			$active_plugins[] = $plugin_name;
		}
	}

	$wp_config_smtp = defined( 'SMTP_HOST' ) || defined( 'SMTP_USER' );
	$server_mail    = website_optimiser_get_server_mail_approval();

	if ( $server_mail['approved'] ) {
		$transport = 'server';
		$label     = __( 'Server-level (confirmed)', 'website-optimiser' );
	} elseif ( ! empty( $active_plugins ) ) {
		$transport = 'plugin';
		$label     = __( 'WordPress SMTP plugin', 'website-optimiser' );
	} elseif ( $wp_config_smtp ) {
		$transport = 'wp_config';
		$label     = __( 'wp-config.php SMTP constants', 'website-optimiser' );
	} else {
		$transport = 'unknown';
		$label     = __( 'Not confirmed in WordPress', 'website-optimiser' );
	}

	return array(
		'transport'       => $transport,
		'transport_label' => $label,
		'active_plugins'  => $active_plugins,
		'installed_plugins' => $installed,
		'wp_config_smtp'  => $wp_config_smtp,
		'mail_function'   => function_exists( 'mail' ),
		'server_mail'     => $server_mail,
	);
}

/**
 * Get the last saved mail test result.
 *
 * @return array
 */
function website_optimiser_get_smtp_test_result() {
	$result = get_option( WEBSITE_OPTIMISER_SMTP_TEST_RESULT_OPTION, array() );

	return is_array( $result ) ? $result : array();
}

/**
 * Send a test email and store the result.
 *
 * @param string $recipient Optional recipient address.
 * @return array|WP_Error
 */
function website_optimiser_run_smtp_test_email( $recipient = '' ) {
	if ( empty( $recipient ) ) {
		$recipient = get_option( 'admin_email' );
	}

	$recipient = sanitize_email( $recipient );

	if ( ! is_email( $recipient ) ) {
		return new WP_Error(
			'invalid_email',
			__( 'Please provide a valid email address for the test.', 'website-optimiser' )
		);
	}

	if ( ! function_exists( 'wp_mail' ) ) {
		return new WP_Error(
			'wp_mail_unavailable',
			__( 'The wp_mail() function is not available on this server.', 'website-optimiser' )
		);
	}

	$site_name = get_bloginfo( 'name' );
	$subject   = sprintf(
		/* translators: %s: site name */
		__( '[%s] Website Optimiser Email Test', 'website-optimiser' ),
		$site_name
	);
	$message = sprintf(
		/* translators: 1: site name, 2: site URL, 3: date/time */
		__( "This is a test email from Website Optimiser.\n\nSite: %1\$s\nURL: %2\$s\nSent: %3\$s\n\nIf you received this message, WordPress mail delivery is working.", 'website-optimiser' ),
		$site_name,
		home_url(),
		current_time( 'mysql' )
	);

	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	$sent    = wp_mail( $recipient, $subject, $message, $headers );

	$result = array(
		'success'   => (bool) $sent,
		'recipient' => $recipient,
		'tested_at' => time(),
		'tested_by' => get_current_user_id(),
	);

	if ( ! $sent ) {
		global $phpmailer;

		if ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
			$result['error'] = sanitize_text_field( $phpmailer->ErrorInfo );
		} else {
			$result['error'] = __( 'wp_mail() returned false. Check server mail or SendGrid settings.', 'website-optimiser' );
		}
	}

	update_option( WEBSITE_OPTIMISER_SMTP_TEST_RESULT_OPTION, $result, false );

	return $result;
}

/**
 * Check mail delivery status for the dashboard.
 *
 * @return array
 */
function website_optimiser_check_smtp_email_status() {
	$transport   = website_optimiser_detect_mail_transport();
	$last_test   = website_optimiser_get_smtp_test_result();
	$admin_mail  = get_option( 'admin_email' );
	$test_passed = ! empty( $last_test['success'] );
	$test_failed = array_key_exists( 'success', $last_test ) && empty( $last_test['success'] );
	$test_run    = ! empty( $last_test );
	$server_ok   = ! empty( $transport['server_mail']['approved'] );

	$base = array(
		'transport'          => $transport['transport'],
		'transport_label'    => $transport['transport_label'],
		'active_plugins'     => $transport['active_plugins'],
		'installed_plugins'  => $transport['installed_plugins'],
		'wp_config_smtp'     => $transport['wp_config_smtp'],
		'mail_function'      => $transport['mail_function'],
		'server_mail'        => $transport['server_mail'],
		'admin_email'        => $admin_mail,
		'admin_email_valid'  => is_email( $admin_mail ),
		'last_test'          => $last_test,
		'test_passed'        => $test_passed,
		'test_failed'        => $test_failed,
		'test_run'           => $test_run,
	);

	if ( ! $base['admin_email_valid'] ) {
		return array_merge(
			$base,
			array(
				'status'  => __( 'Invalid Admin Email', 'website-optimiser' ),
				'message' => __( 'The site admin email address is missing or invalid. Update it under Settings → General before testing mail delivery.', 'website-optimiser' ),
				'class'   => 'status-error',
			)
		);
	}

	if ( $test_failed ) {
		$error_message = ! empty( $last_test['error'] )
			? $last_test['error']
			: __( 'The last test email failed to send.', 'website-optimiser' );

		return array_merge(
			$base,
			array(
				'status'  => __( 'Test Failed', 'website-optimiser' ),
				'message' => $error_message,
				'class'   => 'status-error',
			)
		);
	}

	if ( $test_passed ) {
		return array_merge(
			$base,
			array(
				'status'  => __( 'Mail Delivery Verified', 'website-optimiser' ),
				'message' => __( 'A test email was sent successfully from this site. Notification FROM/TO rules are checked separately in Gravity Forms Notifications and WooCommerce Emails.', 'website-optimiser' ),
				'class'   => 'status-good',
			)
		);
	}

	if ( $server_ok ) {
		$message = ! empty( $transport['server_mail']['approved_note'] )
			? $transport['server_mail']['approved_note']
			: __( 'Server-level mail delivery (e.g. SendGrid at hosting) has been confirmed. Send a test email when possible to verify end-to-end delivery.', 'website-optimiser' );

		return array_merge(
			$base,
			array(
				'status'  => __( 'Server Mail Confirmed', 'website-optimiser' ),
				'message' => $message,
				'class'   => 'status-good',
			)
		);
	}

	if ( ! $test_run ) {
		return array_merge(
			$base,
			array(
				'status'  => __( 'Mail Not Verified', 'website-optimiser' ),
				'message' => __( 'Send a test email, or confirm that mail is handled at the server level (e.g. SendGrid on hosting). Form notification addresses are checked separately in Gravity Forms Notifications.', 'website-optimiser' ),
				'class'   => 'status-warning',
			)
		);
	}

	return array_merge(
		$base,
		array(
			'status'  => __( 'Mail Not Verified', 'website-optimiser' ),
			'message' => __( 'Mail delivery has not been verified yet. Send a test email or confirm server-level mail configuration.', 'website-optimiser' ),
			'class'   => 'status-warning',
		)
	);
}

/**
 * Handle AJAX mail test requests.
 */
function website_optimiser_handle_run_smtp_test() {
	if ( ! check_ajax_referer( 'website_optimiser_smtp_test_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'website-optimiser' ) ) );
	}

	$recipient = isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '';
	$result    = website_optimiser_run_smtp_test_email( $recipient );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	if ( empty( $result['success'] ) ) {
		wp_send_json_error(
			array(
				'message' => $result['error'] ?? __( 'Test email failed to send.', 'website-optimiser' ),
				'result'  => $result,
			)
		);
	}

	wp_send_json_success(
		array(
			'message' => sprintf(
				/* translators: %s: email address */
				__( 'Test email sent to %s.', 'website-optimiser' ),
				$result['recipient']
			),
			'result'  => $result,
		)
	);
}
add_action( 'wp_ajax_website_optimiser_run_smtp_test', 'website_optimiser_handle_run_smtp_test' );

/**
 * Handle AJAX request to confirm server-level mail delivery.
 */
function website_optimiser_approve_server_mail() {
	if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
	}

	$approved_by = sanitize_text_field( $_POST['approved_by'] ?? '' );
	if ( empty( $approved_by ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Name is required' ) ) );
	}

	$note = sanitize_textarea_field( $_POST['note'] ?? '' );

	update_option( 'website_optimiser_server_mail_approved', true );
	update_option( 'website_optimiser_server_mail_approved_by', $approved_by );
	update_option( 'website_optimiser_server_mail_approved_date', current_time( 'mysql' ) );
	update_option( 'website_optimiser_server_mail_approved_note', $note );

	wp_die( wp_json_encode( array( 'success' => true, 'message' => 'Server mail configuration confirmed' ) ) );
}
add_action( 'wp_ajax_website_optimiser_approve_server_mail', 'website_optimiser_approve_server_mail' );

/**
 * Handle AJAX request to reset server mail confirmation.
 */
function website_optimiser_reset_server_mail_approval() {
	if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
	}

	delete_option( 'website_optimiser_server_mail_approved' );
	delete_option( 'website_optimiser_server_mail_approved_by' );
	delete_option( 'website_optimiser_server_mail_approved_date' );
	delete_option( 'website_optimiser_server_mail_approved_note' );

	wp_die( wp_json_encode( array( 'success' => true, 'message' => 'Server mail confirmation reset' ) ) );
}
add_action( 'wp_ajax_website_optimiser_reset_server_mail_approval', 'website_optimiser_reset_server_mail_approval' );

/**
 * Render mail delivery section.
 */
function website_optimiser_render_smtp_email_section() {
	$status      = website_optimiser_check_smtp_email_status();
	$last_test   = $status['last_test'];
	$admin_mail  = $status['admin_email'];
	$server_mail = $status['server_mail'];
	?>
	<div class="seo-stat-item <?php echo esc_attr( $status['class'] ); ?>">
		<div class="stat-icon">✉️</div>
		<div class="stat-content">
			<h4><?php esc_html_e( 'Mail Delivery', 'website-optimiser' ); ?></h4>
			<div class="stat-status <?php echo esc_attr( $status['class'] ); ?>">
				<?php echo esc_html( $status['status'] ); ?>
			</div>
			<div class="stat-label">
				<?php echo esc_html( $status['message'] ); ?>

				<br><small><strong><?php esc_html_e( 'Transport:', 'website-optimiser' ); ?></strong> <?php echo esc_html( $status['transport_label'] ); ?></small>

				<?php if ( ! empty( $status['active_plugins'] ) ) : ?>
					<br><small><strong><?php esc_html_e( 'WordPress mail plugin:', 'website-optimiser' ); ?></strong> <?php echo esc_html( implode( ', ', $status['active_plugins'] ) ); ?> <?php esc_html_e( '(informational only)', 'website-optimiser' ); ?></small>
				<?php endif; ?>

				<?php if ( $status['wp_config_smtp'] ) : ?>
					<br><small><?php esc_html_e( 'SMTP constants detected in wp-config.php.', 'website-optimiser' ); ?></small>
				<?php endif; ?>

				<br><small><strong><?php esc_html_e( 'Admin email:', 'website-optimiser' ); ?></strong> <?php echo esc_html( $admin_mail ); ?></small>

				<?php if ( ! empty( $last_test['tested_at'] ) ) : ?>
					<br><small>
						<?php
						printf(
							/* translators: 1: date/time, 2: email address */
							esc_html__( 'Last test: %1$s to %2$s', 'website-optimiser' ),
							esc_html( date_i18n( 'M j, Y g:i A', (int) $last_test['tested_at'] ) ),
							esc_html( $last_test['recipient'] ?? '' )
						);
						?>
						<?php echo ! empty( $last_test['success'] ) ? esc_html__( '(passed)', 'website-optimiser' ) : esc_html__( '(failed)', 'website-optimiser' ); ?>
					</small>
				<?php endif; ?>

				<?php if ( ! empty( $server_mail['approved'] ) ) : ?>
					<br><br><small><strong><?php esc_html_e( 'Server mail confirmed by:', 'website-optimiser' ); ?></strong> <?php echo esc_html( $server_mail['approved_by'] ); ?></small>
					<br><small><strong><?php esc_html_e( 'Date:', 'website-optimiser' ); ?></strong> <?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $server_mail['approved_date'] ) ) ); ?></small>
					<?php if ( ! empty( $server_mail['approved_note'] ) ) : ?>
						<br><small><strong><?php esc_html_e( 'Note:', 'website-optimiser' ); ?></strong> <?php echo esc_html( $server_mail['approved_note'] ); ?></small>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<div class="stat-inline-form stat-smtp-test-form">
				<label for="website-optimiser-smtp-test-recipient" class="stat-inline-form-label">
					<?php esc_html_e( 'Test recipient', 'website-optimiser' ); ?>
				</label>
				<input
					type="email"
					id="website-optimiser-smtp-test-recipient"
					class="stat-inline-form-input"
					value="<?php echo esc_attr( $admin_mail ); ?>"
					<?php disabled( ! $status['admin_email_valid'] ); ?>
				>
			</div>
			<div class="stat-action">
				<button type="button" class="button button-primary button-small" id="website-optimiser-run-smtp-test" <?php disabled( ! $status['admin_email_valid'] ); ?>>
					<?php esc_html_e( 'Send Test Email', 'website-optimiser' ); ?>
				</button>

				<?php if ( ! empty( $server_mail['approved'] ) ) : ?>
					<button type="button" class="button button-small" onclick="resetServerMailApproval()">
						<?php esc_html_e( 'Reset Server Mail Confirmation', 'website-optimiser' ); ?>
					</button>
				<?php elseif ( ! $status['test_passed'] ) : ?>
					<div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
						<label style="display: block; margin-bottom: 8px; font-weight: 600;">
							<?php esc_html_e( 'Mail handled at server level?', 'website-optimiser' ); ?>
						</label>
						<div style="margin-bottom: 12px; font-size: 13px; color: #666;">
							<?php esc_html_e( 'Use this when SendGrid or SMTP is configured on hosting and not via a WordPress plugin.', 'website-optimiser' ); ?>
						</div>
						<label style="display: block; margin-bottom: 8px;">
							<input type="checkbox" id="server-mail-checkbox" style="margin-right: 5px;">
							<?php esc_html_e( 'Confirm server-level mail delivery is configured', 'website-optimiser' ); ?>
						</label>
						<textarea id="server-mail-note" placeholder="<?php esc_attr_e( 'Optional note (e.g. SendGrid configured on Cloudways)', 'website-optimiser' ); ?>" style="width: 100%; margin-bottom: 8px; min-height: 50px;" disabled></textarea>
						<input type="text" id="server-mail-approved-by-name" placeholder="<?php esc_attr_e( 'Your name', 'website-optimiser' ); ?>" style="width: 100%; margin-bottom: 8px;" disabled>
						<button type="button" class="button button-small" onclick="approveServerMail()" disabled id="server-mail-approve-btn">
							<?php esc_html_e( 'Confirm Server Mail', 'website-optimiser' ); ?>
						</button>
					</div>
				<?php endif; ?>

				<div id="website-optimiser-smtp-test-status" style="display:none; margin-top:8px;"></div>
			</div>
		</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var serverCheckbox = document.getElementById('server-mail-checkbox');
		var serverNote = document.getElementById('server-mail-note');
		var serverName = document.getElementById('server-mail-approved-by-name');
		var serverBtn = document.getElementById('server-mail-approve-btn');

		if (serverCheckbox && serverName && serverBtn) {
			serverCheckbox.addEventListener('change', function() {
				if (this.checked) {
					serverNote.disabled = false;
					serverName.disabled = false;
					serverName.focus();
					serverName.addEventListener('input', function() {
						serverBtn.disabled = this.value.trim() === '';
					});
				} else {
					serverNote.disabled = true;
					serverName.disabled = true;
					serverName.value = '';
					serverBtn.disabled = true;
				}
			});
		}
	});

	function approveServerMail() {
		var checkbox = document.getElementById('server-mail-checkbox');
		var nameField = document.getElementById('server-mail-approved-by-name');
		var noteField = document.getElementById('server-mail-note');
		if (!checkbox || !checkbox.checked) { alert('<?php echo esc_js( __( 'Please check the confirmation checkbox first.', 'website-optimiser' ) ); ?>'); return; }
		var approvedBy = nameField.value.trim();
		if (!approvedBy) { alert('<?php echo esc_js( __( 'Please enter your name.', 'website-optimiser' ) ); ?>'); nameField.focus(); return; }
		if (!confirm('<?php echo esc_js( __( 'Confirm that server-level mail delivery is configured for this site?', 'website-optimiser' ) ); ?>')) return;
		jQuery.post(ajaxurl, {
			action: 'website_optimiser_approve_server_mail',
			approved_by: approvedBy,
			note: noteField ? noteField.value.trim() : '',
			nonce: '<?php echo wp_create_nonce( 'meta_description_boy_nonce' ); ?>'
		}, function(response) {
			var result = JSON.parse(response);
			if (result.success) { alert('<?php echo esc_js( __( 'Server mail configuration confirmed.', 'website-optimiser' ) ); ?>'); location.reload(); }
			else { alert('Error: ' + result.message); }
		}).fail(function() { alert('<?php echo esc_js( __( 'Error processing request. Please try again.', 'website-optimiser' ) ); ?>'); });
	}

	function resetServerMailApproval() {
		if (!confirm('<?php echo esc_js( __( 'Reset the server mail confirmation? This will remove the current record.', 'website-optimiser' ) ); ?>')) return;
		jQuery.post(ajaxurl, {
			action: 'website_optimiser_reset_server_mail_approval',
			nonce: '<?php echo wp_create_nonce( 'meta_description_boy_nonce' ); ?>'
		}, function(response) {
			var result = JSON.parse(response);
			if (result.success) { alert('<?php echo esc_js( __( 'Server mail confirmation reset.', 'website-optimiser' ) ); ?>'); location.reload(); }
			else { alert('Error: ' + result.message); }
		}).fail(function() { alert('<?php echo esc_js( __( 'Error processing request. Please try again.', 'website-optimiser' ) ); ?>'); });
	}

	jQuery(function($) {
		$('#website-optimiser-run-smtp-test').on('click', function() {
			const $button = $(this);
			const $status = $('#website-optimiser-smtp-test-status');
			const recipient = $('#website-optimiser-smtp-test-recipient').val().trim();

			if (!recipient) {
				alert('<?php echo esc_js( __( 'Please enter a test email address.', 'website-optimiser' ) ); ?>');
				return;
			}

			$button.prop('disabled', true).text('<?php echo esc_js( __( 'Sending...', 'website-optimiser' ) ); ?>');
			$status.show().text('<?php echo esc_js( __( 'Sending test email...', 'website-optimiser' ) ); ?>');

			$.post(ajaxurl, {
				action: 'website_optimiser_run_smtp_test',
				recipient: recipient,
				nonce: '<?php echo esc_js( wp_create_nonce( 'website_optimiser_smtp_test_nonce' ) ); ?>'
			}).done(function(response) {
				if (response && response.success) {
					$status.text(response.data.message || '<?php echo esc_js( __( 'Test email sent. Refreshing...', 'website-optimiser' ) ); ?>');
					window.location.reload();
					return;
				}

				const message = response && response.data && response.data.message
					? response.data.message
					: '<?php echo esc_js( __( 'Test email failed.', 'website-optimiser' ) ); ?>';
				$status.text(message);
			}).fail(function() {
				$status.text('<?php echo esc_js( __( 'Test email failed. Please try again.', 'website-optimiser' ) ); ?>');
			}).always(function() {
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Send Test Email', 'website-optimiser' ) ); ?>');
			});
		});
	});
	</script>
	<?php
}
