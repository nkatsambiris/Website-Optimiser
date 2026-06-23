<?php
/**
 * Search engine visibility check for Website Optimiser.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check whether the site allows search engine indexing.
 *
 * WordPress stores this as the blog_public option (Settings → Reading).
 * When disabled, WordPress adds noindex to the entire site.
 *
 * @return array
 */
function website_optimiser_check_search_engine_visibility_status() {
	$blog_public     = (int) get_option( 'blog_public', 1 );
	$allows_indexing = 1 === $blog_public;
	$settings_url    = admin_url( 'options-reading.php' );

	if ( $allows_indexing ) {
		return array(
			'allows_indexing' => true,
			'blog_public'     => $blog_public,
			'settings_url'    => $settings_url,
			'status'          => __( 'Indexing Enabled', 'website-optimiser' ),
			'message'         => __( 'Search engines are allowed to index this site.', 'website-optimiser' ),
			'class'           => 'status-good',
		);
	}

	return array(
		'allows_indexing' => false,
		'blog_public'     => $blog_public,
		'settings_url'    => $settings_url,
		'status'          => __( 'Indexing Disabled', 'website-optimiser' ),
		'message'         => __( '“Discourage search engines from indexing this site” is enabled. Disable this before launch or the site will not appear in search results.', 'website-optimiser' ),
		'class'           => 'status-error',
	);
}

/**
 * Render search engine visibility section.
 */
function website_optimiser_render_search_engine_visibility_section() {
	$status = website_optimiser_check_search_engine_visibility_status();
	?>
	<div class="seo-stat-item <?php echo esc_attr( $status['class'] ); ?>">
		<div class="stat-icon">🔍</div>
		<div class="stat-content">
			<h4><?php esc_html_e( 'Search Engine Visibility', 'website-optimiser' ); ?></h4>
			<div class="stat-status <?php echo esc_attr( $status['class'] ); ?>">
				<?php echo esc_html( $status['status'] ); ?>
			</div>
			<div class="stat-label">
				<?php echo esc_html( $status['message'] ); ?>
				<br><small>
					<?php
					echo esc_html(
						$status['allows_indexing']
							? __( 'Settings → Reading → Search engine visibility is unchecked.', 'website-optimiser' )
							: __( 'Settings → Reading → “Discourage search engines from indexing this site” is checked.', 'website-optimiser' )
					);
					?>
				</small>
			</div>
			<div class="stat-action">
				<a href="<?php echo esc_url( $status['settings_url'] ); ?>" class="button button-small<?php echo $status['allows_indexing'] ? '' : ' button-primary'; ?>">
					<?php esc_html_e( 'Reading Settings', 'website-optimiser' ); ?>
				</a>
			</div>
		</div>
	</div>
	<?php
}
