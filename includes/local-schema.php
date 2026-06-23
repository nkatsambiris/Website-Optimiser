<?php
/**
 * Local business schema settings and output for Website Optimiser.
 */

defined( 'ABSPATH' ) || exit;

const WEBSITE_OPTIMISER_LOCAL_SCHEMA_OPTION = 'website_optimiser_local_schema';

/**
 * Default local schema settings.
 *
 * @return array
 */
function website_optimiser_get_local_schema_defaults() {
	return array(
		'enabled'            => 0,
		'schema_type'        => 'LocalBusiness',
		'name'               => get_bloginfo( 'name' ),
		'url'                => home_url( '/' ),
		'telephone'          => '',
		'email'              => '',
		'street_address'     => '',
		'address_locality'   => '',
		'address_region'     => '',
		'postal_code'        => '',
		'address_country'    => '',
		'area_served'        => '',
		'description'        => '',
		'opening_hours'      => '',
		'offer_catalog_name' => '',
		'services'           => '',
		'price_range'        => '',
		'image_url'          => '',
		'same_as'            => '',
	);
}

/**
 * Available LocalBusiness schema types.
 *
 * @return array
 */
function website_optimiser_get_local_schema_types() {
	return array(
		'LocalBusiness'          => __( 'Local Business', 'website-optimiser' ),
		'AutomotiveBusiness'     => __( 'Automotive Business', 'website-optimiser' ),
		'Dentist'                => __( 'Dentist', 'website-optimiser' ),
		'Electrician'            => __( 'Electrician', 'website-optimiser' ),
		'FinancialService'       => __( 'Financial Service', 'website-optimiser' ),
		'FoodEstablishment'      => __( 'Food Establishment', 'website-optimiser' ),
		'HealthAndBeautyBusiness' => __( 'Health and Beauty Business', 'website-optimiser' ),
		'HomeAndConstructionBusiness' => __( 'Home and Construction Business', 'website-optimiser' ),
		'LegalService'           => __( 'Legal Service', 'website-optimiser' ),
		'MedicalBusiness'        => __( 'Medical Business', 'website-optimiser' ),
		'ProfessionalService'    => __( 'Professional Service', 'website-optimiser' ),
		'RealEstateAgent'        => __( 'Real Estate Agent', 'website-optimiser' ),
		'Store'                  => __( 'Store', 'website-optimiser' ),
	);
}

/**
 * Available areaServed schema types.
 *
 * @return array
 */
function website_optimiser_get_area_served_types() {
	return array(
		'City'               => __( 'City', 'website-optimiser' ),
		'AdministrativeArea' => __( 'Region / Administrative Area', 'website-optimiser' ),
		'State'              => __( 'State', 'website-optimiser' ),
		'Country'            => __( 'Country', 'website-optimiser' ),
		'Place'              => __( 'Place / Other', 'website-optimiser' ),
	);
}

/**
 * Get local schema settings merged with defaults.
 *
 * @return array
 */
function website_optimiser_get_local_schema_options() {
	$options = get_option( WEBSITE_OPTIMISER_LOCAL_SCHEMA_OPTION, array() );

	return wp_parse_args( is_array( $options ) ? $options : array(), website_optimiser_get_local_schema_defaults() );
}

/**
 * Sanitize local schema settings.
 *
 * @param array $input Unsanitized option value.
 * @return array
 */
function website_optimiser_sanitize_local_schema_options( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$defaults = website_optimiser_get_local_schema_defaults();
	$types    = website_optimiser_get_local_schema_types();

	$output = array(
		'enabled'            => ! empty( $input['enabled'] ) ? 1 : 0,
		'schema_type'        => ( isset( $input['schema_type'] ) && is_string( $input['schema_type'] ) && isset( $types[ $input['schema_type'] ] ) ) ? $input['schema_type'] : $defaults['schema_type'],
		'name'               => sanitize_text_field( $input['name'] ?? '' ),
		'url'                => esc_url_raw( $input['url'] ?? '' ),
		'telephone'          => sanitize_text_field( $input['telephone'] ?? '' ),
		'email'              => sanitize_email( $input['email'] ?? '' ),
		'street_address'     => sanitize_text_field( $input['street_address'] ?? '' ),
		'address_locality'   => sanitize_text_field( $input['address_locality'] ?? '' ),
		'address_region'     => sanitize_text_field( $input['address_region'] ?? '' ),
		'postal_code'        => sanitize_text_field( $input['postal_code'] ?? '' ),
		'address_country'    => sanitize_text_field( $input['address_country'] ?? '' ),
		'area_served'        => sanitize_textarea_field( $input['area_served'] ?? '' ),
		'description'        => sanitize_textarea_field( $input['description'] ?? '' ),
		'opening_hours'      => sanitize_textarea_field( $input['opening_hours'] ?? '' ),
		'offer_catalog_name' => sanitize_text_field( $input['offer_catalog_name'] ?? '' ),
		'services'           => sanitize_textarea_field( $input['services'] ?? '' ),
		'price_range'        => sanitize_text_field( $input['price_range'] ?? '' ),
		'image_url'          => esc_url_raw( $input['image_url'] ?? '' ),
		'same_as'            => sanitize_textarea_field( $input['same_as'] ?? '' ),
	);

	if ( '' === $output['url'] ) {
		$output['url'] = $defaults['url'];
	}

	return $output;
}

/**
 * Register local schema settings and submenu.
 */
function website_optimiser_register_local_schema_settings() {
	register_setting(
		'website_optimiser_local_schema_options',
		WEBSITE_OPTIMISER_LOCAL_SCHEMA_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'website_optimiser_sanitize_local_schema_options',
			'default'           => website_optimiser_get_local_schema_defaults(),
		)
	);

	add_settings_section(
		'website_optimiser_local_schema_section',
		__( 'Local Business Schema', 'website-optimiser' ),
		'website_optimiser_local_schema_section_cb',
		'website-optimiser-local-schema'
	);

	add_settings_field(
		'website_optimiser_local_schema_fields',
		__( 'Schema Settings', 'website-optimiser' ),
		'website_optimiser_local_schema_fields_cb',
		'website-optimiser-local-schema',
		'website_optimiser_local_schema_section'
	);
}
add_action( 'admin_init', 'website_optimiser_register_local_schema_settings' );

/**
 * Add Local Schema settings page.
 */
function website_optimiser_add_local_schema_menu() {
	add_submenu_page(
		'website-optimisation',
		__( 'Local Schema', 'website-optimiser' ),
		__( 'Local Schema', 'website-optimiser' ),
		'manage_options',
		'website-optimiser-local-schema',
		'website_optimiser_render_local_schema_page'
	);
}
add_action( 'admin_menu', 'website_optimiser_add_local_schema_menu', 20 );

/**
 * Enqueue assets needed by the Local Schema settings page.
 *
 * @param string $hook Current admin page hook.
 */
function website_optimiser_enqueue_local_schema_admin_assets( $hook ) {
	if ( false === strpos( $hook, 'website-optimiser-local-schema' ) ) {
		return;
	}

	wp_enqueue_script( 'jquery' );
}
add_action( 'admin_enqueue_scripts', 'website_optimiser_enqueue_local_schema_admin_assets' );

/**
 * Settings section intro.
 */
function website_optimiser_local_schema_section_cb() {
	echo '<p>' . esc_html__( 'Configure LocalBusiness JSON-LD. When enabled, Website Optimiser prints the schema in wp_head on the front end.', 'website-optimiser' ) . '</p>';
}

/**
 * Render local schema settings page.
 */
function website_optimiser_render_local_schema_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Local Schema', 'website-optimiser' ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'website_optimiser_local_schema_options' );
			do_settings_sections( 'website-optimiser-local-schema' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Render all local schema inputs.
 */
function website_optimiser_local_schema_fields_cb() {
	$options = website_optimiser_get_local_schema_options();
	$types   = website_optimiser_get_local_schema_types();
	$area_types = website_optimiser_get_area_served_types();
	$area_rows  = website_optimiser_parse_area_served( $options['area_served'] );
	$name    = WEBSITE_OPTIMISER_LOCAL_SCHEMA_OPTION;
	$nonce   = wp_create_nonce( 'website_optimiser_local_schema_nonce' );

	if ( empty( $area_rows ) ) {
		$area_rows = array(
			array(
				'@type' => 'City',
				'name'  => '',
			),
		);
	}
	?>
	<style>
		.website-optimiser-schema-grid {
			display: grid;
			grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
			gap: 16px 24px;
			max-width: 980px;
		}
		.website-optimiser-schema-field label {
			display: block;
			font-weight: 600;
			margin-bottom: 5px;
		}
		.website-optimiser-schema-field input:not([type="checkbox"]),
		.website-optimiser-schema-field select,
		.website-optimiser-schema-field textarea {
			width: 100%;
			max-width: 100%;
		}
		.website-optimiser-schema-field .website-optimiser-schema-checkbox-label {
			display: inline-flex;
			align-items: center;
			gap: 8px;
		}
		.website-optimiser-schema-area-row {
			display: grid;
			grid-template-columns: minmax(0, 1fr) 220px auto;
			gap: 8px;
			align-items: center;
			margin-bottom: 8px;
		}
		.website-optimiser-schema-area-row .button-link-delete {
			text-decoration: none;
		}
		.website-optimiser-schema-field.full {
			grid-column: 1 / -1;
		}
		.website-optimiser-schema-actions {
			display: flex;
			align-items: center;
			gap: 10px;
			margin-top: 8px;
		}
		@media (max-width: 782px) {
			.website-optimiser-schema-grid {
				grid-template-columns: 1fr;
			}
			.website-optimiser-schema-area-row {
				grid-template-columns: 1fr;
			}
		}
	</style>
	<div class="website-optimiser-schema-grid">
		<div class="website-optimiser-schema-field full">
			<label class="website-optimiser-schema-checkbox-label">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( 1, (int) $options['enabled'] ); ?>>
				<?php esc_html_e( 'Enable LocalBusiness schema output', 'website-optimiser' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'When enabled, the generated JSON-LD is added to wp_head across the public site.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-type"><?php esc_html_e( 'Business Type', 'website-optimiser' ); ?></label>
			<select id="website-optimiser-schema-type" name="<?php echo esc_attr( $name ); ?>[schema_type]">
				<?php foreach ( $types as $type => $label ) : ?>
					<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $options['schema_type'], $type ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Choose the closest match for the business. Use Local Business if none of the specific types fit.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-name"><?php esc_html_e( 'Business Name', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-name" type="text" name="<?php echo esc_attr( $name ); ?>[name]" value="<?php echo esc_attr( $options['name'] ); ?>">
			<p class="description"><?php esc_html_e( 'Use the real trading name as it appears on the website and business profiles.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-url"><?php esc_html_e( 'Website URL', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-url" type="url" name="<?php echo esc_attr( $name ); ?>[url]" value="<?php echo esc_attr( $options['url'] ); ?>">
			<p class="description"><?php esc_html_e( 'Use the live canonical homepage URL. Local development URLs are fine for testing only.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-phone"><?php esc_html_e( 'Telephone', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-phone" type="text" name="<?php echo esc_attr( $name ); ?>[telephone]" value="<?php echo esc_attr( $options['telephone'] ); ?>">
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-email"><?php esc_html_e( 'Email', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-email" type="email" name="<?php echo esc_attr( $name ); ?>[email]" value="<?php echo esc_attr( $options['email'] ); ?>">
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-street"><?php esc_html_e( 'Street Address', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-street" type="text" name="<?php echo esc_attr( $name ); ?>[street_address]" value="<?php echo esc_attr( $options['street_address'] ); ?>">
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-locality"><?php esc_html_e( 'Suburb / Locality', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-locality" type="text" name="<?php echo esc_attr( $name ); ?>[address_locality]" value="<?php echo esc_attr( $options['address_locality'] ); ?>">
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-region"><?php esc_html_e( 'State / Region', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-region" type="text" name="<?php echo esc_attr( $name ); ?>[address_region]" value="<?php echo esc_attr( $options['address_region'] ); ?>">
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-postal"><?php esc_html_e( 'Postcode', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-postal" type="text" name="<?php echo esc_attr( $name ); ?>[postal_code]" value="<?php echo esc_attr( $options['postal_code'] ); ?>">
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-country"><?php esc_html_e( 'Country Code', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-country" type="text" name="<?php echo esc_attr( $name ); ?>[address_country]" value="<?php echo esc_attr( $options['address_country'] ); ?>" placeholder="AU">
			<p class="description"><?php esc_html_e( 'Use the two-letter country code where possible, for example AU instead of Australia.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-price"><?php esc_html_e( 'Price Range', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-price" type="text" name="<?php echo esc_attr( $name ); ?>[price_range]" value="<?php echo esc_attr( $options['price_range'] ); ?>" placeholder="$$">
		</div>

		<div class="website-optimiser-schema-field">
			<label for="website-optimiser-schema-image"><?php esc_html_e( 'Image / Logo URL', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-image" type="url" name="<?php echo esc_attr( $name ); ?>[image_url]" value="<?php echo esc_attr( $options['image_url'] ); ?>">
			<p class="description"><?php esc_html_e( 'Use a real public logo or representative business image URL from the live site.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-schema-field full">
			<label for="website-optimiser-schema-area"><?php esc_html_e( 'Areas Served', 'website-optimiser' ); ?></label>
			<input type="hidden" id="website-optimiser-schema-area" name="<?php echo esc_attr( $name ); ?>[area_served]" value="<?php echo esc_attr( $options['area_served'] ); ?>">
			<div id="website-optimiser-schema-area-rows">
				<?php foreach ( $area_rows as $area ) : ?>
					<div class="website-optimiser-schema-area-row">
						<input type="text" class="website-optimiser-schema-area-name" value="<?php echo esc_attr( $area['name'] ); ?>" placeholder="<?php esc_attr_e( 'Greater Geelong', 'website-optimiser' ); ?>">
						<select class="website-optimiser-schema-area-type" aria-label="<?php esc_attr_e( 'Area type', 'website-optimiser' ); ?>">
							<?php foreach ( $area_types as $area_type => $area_label ) : ?>
								<option value="<?php echo esc_attr( $area_type ); ?>" <?php selected( $area['@type'], $area_type ); ?>><?php echo esc_html( $area_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="button-link-delete website-optimiser-remove-area"><?php esc_html_e( 'Remove', 'website-optimiser' ); ?></button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button" id="website-optimiser-add-area"><?php esc_html_e( 'Add Area', 'website-optimiser' ); ?></button>
			<p class="description"><?php esc_html_e( 'Add real service areas and choose the closest type. For example: Geelong as City, Regional Victoria as Region / Administrative Area, Victoria as State, AU as Country. Avoid duplicating the same place with multiple types unless each entry is genuinely useful.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-schema-field full">
			<label for="website-optimiser-schema-description"><?php esc_html_e( 'Business Description', 'website-optimiser' ); ?></label>
			<textarea id="website-optimiser-schema-description" name="<?php echo esc_attr( $name ); ?>[description]" rows="5"><?php echo esc_textarea( $options['description'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Write a concise, specific summary of what the business does, who it serves, and the main services offered. Avoid generic text like "providing various services to its community". Add real services first so the AI has useful context.', 'website-optimiser' ); ?></p>
			<div class="website-optimiser-schema-actions">
				<button type="button" class="button" id="website-optimiser-generate-schema-description"><?php esc_html_e( 'Generate Description with AI', 'website-optimiser' ); ?></button>
				<span id="website-optimiser-schema-ai-status" class="description"></span>
			</div>
		</div>

		<div class="website-optimiser-schema-field full">
			<label for="website-optimiser-schema-hours"><?php esc_html_e( 'Opening Hours', 'website-optimiser' ); ?></label>
			<textarea id="website-optimiser-schema-hours" name="<?php echo esc_attr( $name ); ?>[opening_hours]" rows="4" placeholder="Monday-Friday 08:00-17:00&#10;Saturday 09:00-12:00"><?php echo esc_textarea( $options['opening_hours'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'One per line in 24-hour time, for example "Monday-Friday 08:00-17:00" or "Saturday 09:00-12:00". Leave blank if opening hours should not be included.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-schema-field full">
			<label for="website-optimiser-schema-catalog"><?php esc_html_e( 'Offer Catalog Name', 'website-optimiser' ); ?></label>
			<input id="website-optimiser-schema-catalog" type="text" name="<?php echo esc_attr( $name ); ?>[offer_catalog_name]" value="<?php echo esc_attr( $options['offer_catalog_name'] ); ?>" placeholder="<?php esc_attr_e( 'Services', 'website-optimiser' ); ?>">
		</div>

		<div class="website-optimiser-schema-field full">
			<label for="website-optimiser-schema-services"><?php esc_html_e( 'Services', 'website-optimiser' ); ?></label>
			<textarea id="website-optimiser-schema-services" name="<?php echo esc_attr( $name ); ?>[services]" rows="5" placeholder="Pump Sales&#10;Pump Repairs&#10;Pump Servicing"><?php echo esc_textarea( $options['services'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'One real service per line. Replace placeholders before publishing. If you do not have accurate services yet, leave this blank rather than publishing generic entries.', 'website-optimiser' ); ?></p>
		</div>

		<div class="website-optimiser-schema-field full">
			<label for="website-optimiser-schema-same-as"><?php esc_html_e( 'SameAs URLs', 'website-optimiser' ); ?></label>
			<textarea id="website-optimiser-schema-same-as" name="<?php echo esc_attr( $name ); ?>[same_as]" rows="4" placeholder="https://www.facebook.com/example&#10;https://www.linkedin.com/company/example"><?php echo esc_textarea( $options['same_as'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'One exact public profile URL per line, such as Facebook, LinkedIn, Instagram, Google Business Profile, or industry profiles. Do not use placeholder URLs.', 'website-optimiser' ); ?></p>
		</div>
	</div>

	<script>
	jQuery(function($) {
		var areaTypes = <?php echo wp_json_encode( $area_types ); ?>;

		function buildAreaRow(areaName, areaType) {
			var $row = $('<div class="website-optimiser-schema-area-row"></div>');
			var $name = $('<input type="text" class="website-optimiser-schema-area-name">').attr('placeholder', '<?php echo esc_js( __( 'Geelong', 'website-optimiser' ) ); ?>').val(areaName || '');
			var $type = $('<select class="website-optimiser-schema-area-type"></select>').attr('aria-label', '<?php echo esc_js( __( 'Area type', 'website-optimiser' ) ); ?>');

			$.each(areaTypes, function(value, label) {
				$type.append($('<option></option>').val(value).text(label).prop('selected', value === (areaType || 'City')));
			});

			$row.append($name, $type, $('<button type="button" class="button-link-delete website-optimiser-remove-area"><?php echo esc_js( __( 'Remove', 'website-optimiser' ) ); ?></button>'));
			return $row;
		}

		function syncAreaServed() {
			var lines = [];

			$('#website-optimiser-schema-area-rows .website-optimiser-schema-area-row').each(function() {
				var areaName = $(this).find('.website-optimiser-schema-area-name').val().trim();
				var areaType = $(this).find('.website-optimiser-schema-area-type').val() || 'City';

				if (areaName) {
					lines.push(areaType + '|' + areaName);
				}
			});

			$('#website-optimiser-schema-area').val(lines.join("\n"));
		}

		$('#website-optimiser-add-area').on('click', function() {
			$('#website-optimiser-schema-area-rows').append(buildAreaRow('', 'City'));
		});

		$('#website-optimiser-schema-area-rows').on('input change', '.website-optimiser-schema-area-name, .website-optimiser-schema-area-type', syncAreaServed);

		$('#website-optimiser-schema-area-rows').on('click', '.website-optimiser-remove-area', function() {
			$(this).closest('.website-optimiser-schema-area-row').remove();
			if (!$('#website-optimiser-schema-area-rows .website-optimiser-schema-area-row').length) {
				$('#website-optimiser-schema-area-rows').append(buildAreaRow('', 'City'));
			}
			syncAreaServed();
		});

		$('form').on('submit', syncAreaServed);
		syncAreaServed();

		$('#website-optimiser-generate-schema-description').on('click', function() {
			var $button = $(this);
			var $status = $('#website-optimiser-schema-ai-status');
			var $description = $('#website-optimiser-schema-description');

			syncAreaServed();

			$button.prop('disabled', true).text('<?php echo esc_js( __( 'Generating...', 'website-optimiser' ) ); ?>');
			$status.text('<?php echo esc_js( __( 'Asking Gemini for a concise local business description.', 'website-optimiser' ) ); ?>');

			$.post(ajaxurl, {
				action: 'website_optimiser_generate_local_schema_description',
				nonce: '<?php echo esc_js( $nonce ); ?>',
				business_name: $('#website-optimiser-schema-name').val(),
				schema_type: $('#website-optimiser-schema-type').val(),
				area_served: $('#website-optimiser-schema-area').val(),
				services: $('#website-optimiser-schema-services').val()
			}).done(function(response) {
				if (response.success && response.data.description) {
					$description.val(response.data.description);
					$status.text('<?php echo esc_js( __( 'Description generated. Review and save the settings.', 'website-optimiser' ) ); ?>');
				} else {
					$status.text(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Could not generate description.', 'website-optimiser' ) ); ?>');
				}
			}).fail(function() {
				$status.text('<?php echo esc_js( __( 'Network error. Please try again.', 'website-optimiser' ) ); ?>');
			}).always(function() {
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Generate Description with AI', 'website-optimiser' ) ); ?>');
			});
		});
	});
	</script>
	<?php
}

/**
 * Parse textarea lines.
 *
 * @param string $value Raw textarea value.
 * @return array
 */
function website_optimiser_local_schema_lines( $value ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
	$lines = array_map( 'trim', is_array( $lines ) ? $lines : array() );

	return array_values( array_filter( $lines, static function ( $line ) {
		return '' !== $line;
	} ) );
}

/**
 * Parse "Type|Name" area lines into schema objects.
 *
 * @param string $value Raw textarea value.
 * @return array
 */
function website_optimiser_parse_area_served( $value ) {
	$areas         = array();
	$allowed_types = array( 'City', 'AdministrativeArea', 'State', 'Country', 'Place' );

	foreach ( website_optimiser_local_schema_lines( $value ) as $line ) {
		$type = 'City';
		$name = $line;

		if ( false !== strpos( $line, '|' ) ) {
			list( $maybe_type, $maybe_name ) = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( in_array( $maybe_type, $allowed_types, true ) && '' !== $maybe_name ) {
				$type = $maybe_type;
				$name = $maybe_name;
			}
		}

		$areas[] = array(
			'@type' => $type,
			'name'  => $name,
		);
	}

	return $areas;
}

/**
 * Parse opening hours lines into OpeningHoursSpecification objects.
 *
 * @param string $value Raw textarea value.
 * @return array
 */
function website_optimiser_parse_opening_hours( $value ) {
	$hours = array();
	$days  = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );

	foreach ( website_optimiser_local_schema_lines( $value ) as $line ) {
		if ( ! preg_match( '/^([A-Za-z]+)(?:\s*-\s*([A-Za-z]+))?\s+((?:[01][0-9]|2[0-3]):[0-5][0-9])\s*-\s*((?:[01][0-9]|2[0-3]):[0-5][0-9])$/', $line, $matches ) ) {
			continue;
		}

		$start_day = ucfirst( strtolower( $matches[1] ) );
		$end_day   = isset( $matches[2] ) && '' !== $matches[2] ? ucfirst( strtolower( $matches[2] ) ) : '';

		if ( ! in_array( $start_day, $days, true ) ) {
			continue;
		}

		$day_of_week = $start_day;
		if ( '' !== $end_day && in_array( $end_day, $days, true ) ) {
			$start_index = array_search( $start_day, $days, true );
			$end_index   = array_search( $end_day, $days, true );
			if ( false !== $start_index && false !== $end_index && $end_index >= $start_index ) {
				$day_of_week = array_slice( $days, $start_index, ( $end_index - $start_index ) + 1 );
			}
		}

		$hours[] = array(
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => $day_of_week,
			'opens'     => $matches[3],
			'closes'    => $matches[4],
		);
	}

	return $hours;
}

/**
 * Build LocalBusiness schema array.
 *
 * @param array|null $options Optional settings.
 * @return array
 */
function website_optimiser_build_local_schema( $options = null ) {
	$options = is_array( $options ) ? wp_parse_args( $options, website_optimiser_get_local_schema_defaults() ) : website_optimiser_get_local_schema_options();

	$schema = array(
		'@context' => 'https://schema.org',
		'@type'    => $options['schema_type'],
		'@id'      => trailingslashit( $options['url'] ) . '#business',
		'name'     => $options['name'],
		'url'      => $options['url'],
	);

	$scalar_fields = array(
		'telephone'   => 'telephone',
		'email'       => 'email',
		'description' => 'description',
		'price_range' => 'priceRange',
		'image_url'   => 'image',
	);

	foreach ( $scalar_fields as $option_key => $schema_key ) {
		if ( '' !== $options[ $option_key ] ) {
			$schema[ $schema_key ] = $options[ $option_key ];
		}
	}

	$address = array_filter(
		array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $options['street_address'],
			'addressLocality' => $options['address_locality'],
			'addressRegion'   => $options['address_region'],
			'postalCode'      => $options['postal_code'],
			'addressCountry'  => $options['address_country'],
		),
		static function ( $value ) {
			return '' !== $value;
		}
	);

	if ( count( $address ) > 1 ) {
		$schema['address'] = $address;
	}

	$areas = website_optimiser_parse_area_served( $options['area_served'] );
	if ( ! empty( $areas ) ) {
		$schema['areaServed'] = $areas;
	}

	$opening_hours = website_optimiser_parse_opening_hours( $options['opening_hours'] );
	if ( ! empty( $opening_hours ) ) {
		$schema['openingHoursSpecification'] = $opening_hours;
	}

	$services = website_optimiser_local_schema_lines( $options['services'] );
	if ( ! empty( $services ) ) {
		$schema['hasOfferCatalog'] = array(
			'@type'           => 'OfferCatalog',
			'name'            => '' !== $options['offer_catalog_name'] ? $options['offer_catalog_name'] : __( 'Services', 'website-optimiser' ),
			'itemListElement' => array_map(
				static function ( $service ) {
					return array(
						'@type'       => 'Offer',
						'itemOffered' => array(
							'@type'       => 'Service',
							'name'        => $service,
							'serviceType' => $service,
						),
					);
				},
				$services
			),
		);
	}

	$same_as = array_filter( array_map( 'esc_url_raw', website_optimiser_local_schema_lines( $options['same_as'] ) ) );
	if ( ! empty( $same_as ) ) {
		$schema['sameAs'] = array_values( $same_as );
	}

	return $schema;
}

/**
 * Whether local schema has enough information to output.
 *
 * @param array|null $options Optional settings.
 * @return bool
 */
function website_optimiser_local_schema_is_ready( $options = null ) {
	$options = is_array( $options ) ? wp_parse_args( $options, website_optimiser_get_local_schema_defaults() ) : website_optimiser_get_local_schema_options();

	return ! empty( $options['enabled'] ) && '' !== trim( $options['name'] ) && '' !== trim( $options['url'] );
}

/**
 * Output schema in wp_head.
 */
function website_optimiser_output_local_schema() {
	if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
		return;
	}

	$options = website_optimiser_get_local_schema_options();
	if ( ! website_optimiser_local_schema_is_ready( $options ) ) {
		return;
	}

	$schema = website_optimiser_build_local_schema( $options );
	$json   = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

	if ( false === $json ) {
		return;
	}

	echo "\n<script type=\"application/ld+json\" class=\"website-optimiser-local-schema\">\n" . $json . "\n</script>\n";
}
add_action( 'wp_head', 'website_optimiser_output_local_schema', 20 );

/**
 * Check LocalBusiness schema status for dashboard and abilities.
 *
 * @return array
 */
function website_optimiser_check_local_schema_status() {
	$options  = website_optimiser_get_local_schema_options();
	$resolved = get_option( 'website_optimiser_local_schema_resolved', false );

	if ( empty( $options['enabled'] ) && ! $resolved ) {
		return array(
			'enabled' => false,
			'ready'   => false,
			'status'  => __( 'Not Enabled', 'website-optimiser' ),
			'message' => __( 'Configure and enable LocalBusiness schema for this website.', 'website-optimiser' ),
			'class'   => 'status-warning',
		);
	}

	if ( $resolved ) {
		return array(
			'enabled'     => ! empty( $options['enabled'] ),
			'ready'       => website_optimiser_local_schema_is_ready( $options ),
			'schema_type' => $options['schema_type'] ?? '',
			'status'      => __( 'Manually Resolved', 'website-optimiser' ),
			'message'     => __( 'Local Schema has been manually marked as resolved.', 'website-optimiser' ),
			'class'       => 'status-good',
		);
	}

	if ( ! website_optimiser_local_schema_is_ready( $options ) ) {
		return array(
			'enabled' => true,
			'ready'   => false,
			'status'  => __( 'Enabled, Missing Required Fields', 'website-optimiser' ),
			'message' => __( 'Local schema is enabled but needs at least a business name and URL.', 'website-optimiser' ),
			'class'   => 'status-warning',
		);
	}

	return array(
		'enabled'     => true,
		'ready'       => true,
		'schema_type' => $options['schema_type'],
		'status'      => __( 'Local Schema Enabled', 'website-optimiser' ),
		'message'     => __( 'LocalBusiness JSON-LD is configured and will be added to wp_head.', 'website-optimiser' ),
		'class'       => 'status-good',
	);
}

/**
 * Handle AJAX request to manually resolve Local Schema.
 */
function website_optimiser_approve_local_schema() {
	if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
	}

	$approved_by = sanitize_text_field( $_POST['approved_by'] ?? '' );
	if ( empty( $approved_by ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Name is required' ) ) );
	}

	update_option( 'website_optimiser_local_schema_resolved', true );
	update_option( 'website_optimiser_local_schema_resolved_by', $approved_by );
	update_option( 'website_optimiser_local_schema_resolved_date', current_time( 'mysql' ) );

	wp_die( wp_json_encode( array( 'success' => true, 'message' => 'Local Schema marked as resolved' ) ) );
}
add_action( 'wp_ajax_website_optimiser_approve_local_schema', 'website_optimiser_approve_local_schema' );

/**
 * Handle AJAX request to reset Local Schema manual resolution.
 */
function website_optimiser_reset_local_schema_approval() {
	if ( ! check_ajax_referer( 'meta_description_boy_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( wp_json_encode( array( 'success' => false, 'message' => 'Unauthorized' ) ) );
	}

	delete_option( 'website_optimiser_local_schema_resolved' );
	delete_option( 'website_optimiser_local_schema_resolved_by' );
	delete_option( 'website_optimiser_local_schema_resolved_date' );

	wp_die( wp_json_encode( array( 'success' => true, 'message' => 'Local Schema resolution reset' ) ) );
}
add_action( 'wp_ajax_website_optimiser_reset_local_schema_approval', 'website_optimiser_reset_local_schema_approval' );

/**
 * Render LocalBusiness schema dashboard card.
 */
function website_optimiser_render_local_schema_section() {
	$status       = website_optimiser_check_local_schema_status();
	$settings_url = admin_url( 'admin.php?page=website-optimiser-local-schema' );
	$ls_resolved      = get_option( 'website_optimiser_local_schema_resolved', false );
	$ls_resolved_by   = get_option( 'website_optimiser_local_schema_resolved_by', '' );
	$ls_resolved_date = get_option( 'website_optimiser_local_schema_resolved_date', '' );
	?>
	<div class="seo-stat-item <?php echo esc_attr( $ls_resolved ? 'status-good' : $status['class'] ); ?>">
		<div class="stat-icon">LD</div>
		<div class="stat-content">
			<h4><?php esc_html_e( 'Local Schema', 'website-optimiser' ); ?></h4>
			<div class="stat-status <?php echo esc_attr( $ls_resolved ? 'status-good' : $status['class'] ); ?>">
				<?php echo esc_html( $ls_resolved ? 'Manually Resolved' : $status['status'] ); ?>
			</div>
			<div class="stat-label">
				<?php echo esc_html( $ls_resolved ? 'Local Schema has been manually marked as resolved.' : $status['message'] ); ?>
				<?php if ( ! empty( $status['schema_type'] ) ) : ?>
					<br><small><strong><?php esc_html_e( 'Type:', 'website-optimiser' ); ?></strong> <?php echo esc_html( $status['schema_type'] ); ?></small>
				<?php endif; ?>
				<?php if ( $ls_resolved ) : ?>
					<br><br><small><strong>Resolved by:</strong> <?php echo esc_html( $ls_resolved_by ); ?></small>
					<br><small><strong>Date:</strong> <?php echo esc_html( date( 'M j, Y g:i A', strtotime( $ls_resolved_date ) ) ); ?></small>
				<?php endif; ?>
			</div>
			<div class="stat-action">
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-small">
					<?php esc_html_e( 'Configure Schema', 'website-optimiser' ); ?>
				</a>
				<?php if ( $ls_resolved ) : ?>
					<button type="button" class="button button-small" onclick="resetLocalSchemaApproval()">
						Reset Resolution
					</button>
				<?php else : ?>
					<div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border-left: 4px solid #0073aa;">
						<label style="display: block; margin-bottom: 8px; font-weight: 600;">
							Manually mark Local Schema as resolved?
						</label>
						<div style="margin-bottom: 12px; font-size: 13px; color: #666;">
							Use this if you have reviewed the local schema configuration and are satisfied with the current setup, or schema is managed elsewhere.
						</div>
						<label style="display: block; margin-bottom: 8px;">
							<input type="checkbox" id="local-schema-resolve-checkbox" style="margin-right: 5px;">
							Confirm that Local Schema has been reviewed and any issues addressed
						</label>
						<input type="text" id="local-schema-resolved-by-name" placeholder="Your name" style="width: 100%; margin-bottom: 8px;" disabled>
						<button type="button" class="button button-small" onclick="approveLocalSchema()" disabled id="local-schema-resolve-btn">
							Mark as Resolved
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var lsCheckbox = document.getElementById('local-schema-resolve-checkbox');
		var lsName = document.getElementById('local-schema-resolved-by-name');
		var lsBtn = document.getElementById('local-schema-resolve-btn');
		if (lsCheckbox && lsName && lsBtn) {
			lsCheckbox.addEventListener('change', function() {
				if (this.checked) {
					lsName.disabled = false;
					lsName.focus();
					lsName.addEventListener('input', function() {
						lsBtn.disabled = this.value.trim() === '';
					});
				} else {
					lsName.disabled = true;
					lsName.value = '';
					lsBtn.disabled = true;
				}
			});
		}
	});

	function approveLocalSchema() {
		var checkbox = document.getElementById('local-schema-resolve-checkbox');
		var nameField = document.getElementById('local-schema-resolved-by-name');
		if (!checkbox || !checkbox.checked) { alert('Please check the confirmation checkbox first.'); return; }
		var approvedBy = nameField.value.trim();
		if (!approvedBy) { alert('Please enter your name.'); nameField.focus(); return; }
		if (!confirm('Are you sure you want to mark Local Schema as resolved? This confirmation will be tracked.')) return;
		jQuery.post(ajaxurl, {
			action: 'website_optimiser_approve_local_schema',
			approved_by: approvedBy,
			nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
		}, function(response) {
			var result = JSON.parse(response);
			if (result.success) { alert('Local Schema marked as resolved.'); location.reload(); }
			else { alert('Error: ' + result.message); }
		}).fail(function() { alert('Error processing request. Please try again.'); });
	}

	function resetLocalSchemaApproval() {
		if (!confirm('Are you sure you want to reset the Local Schema resolution? This will remove the current confirmation.')) return;
		jQuery.post(ajaxurl, {
			action: 'website_optimiser_reset_local_schema_approval',
			nonce: '<?php echo wp_create_nonce('meta_description_boy_nonce'); ?>'
		}, function(response) {
			var result = JSON.parse(response);
			if (result.success) { alert('Local Schema resolution reset.'); location.reload(); }
			else { alert('Error: ' + result.message); }
		}).fail(function() { alert('Error processing request. Please try again.'); });
	}
	</script>
	<?php
}

/**
 * Generate a suggested local schema description with Gemini.
 *
 * @param array $input Business inputs.
 * @return string|WP_Error
 */
function website_optimiser_generate_local_schema_description( $input ) {
	if ( ! website_optimiser_ai_features_enabled() ) {
		return new WP_Error( 'ai_disabled', __( 'AI features are disabled on this site.', 'website-optimiser' ) );
	}

	$api_key = website_optimiser_get_gemini_api_key();
	if ( empty( $api_key ) ) {
		return new WP_Error( 'api_key_missing', __( 'Google Gemini API key not configured.', 'website-optimiser' ) );
	}

	$business_name = sanitize_text_field( $input['business_name'] ?? get_bloginfo( 'name' ) );
	$schema_type   = sanitize_text_field( $input['schema_type'] ?? 'LocalBusiness' );
	$area_served   = sanitize_textarea_field( $input['area_served'] ?? '' );
	$services      = sanitize_textarea_field( $input['services'] ?? '' );

	$prompt = sprintf(
		"Write a concise LocalBusiness schema description for this business. Use one sentence, no markdown, no quotation marks, and keep it under 320 characters.\n\nBusiness name: %s\nBusiness type: %s\nAreas served: %s\nServices: %s",
		$business_name,
		$schema_type,
		$area_served,
		$services
	);

	$response = wp_remote_post(
		website_optimiser_get_gemini_api_url( $api_key ),
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'contents' => array(
						array(
							'parts' => array(
								array(
									'text' => $prompt,
								),
							),
						),
					),
				)
			),
			'timeout' => 30,
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'gemini_request_error', sprintf( __( 'Gemini API Request Error: %s', 'website-optimiser' ), $response->get_error_message() ) );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $response_code ) {
		$body          = wp_remote_retrieve_body( $response );
		$error_data    = json_decode( $body, true );
		$error_message = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'HTTP ' . $response_code;

		return new WP_Error( 'gemini_response_error', sprintf( __( 'Gemini API Response Error: %s', 'website-optimiser' ), $error_message ) );
	}

	$data        = json_decode( wp_remote_retrieve_body( $response ), true );
	$description = isset( $data['candidates'][0]['content']['parts'][0]['text'] )
		? trim( $data['candidates'][0]['content']['parts'][0]['text'] )
		: '';

	$description = trim( $description, "\"' \t\n\r\0\x0B" );

	if ( '' === $description ) {
		return new WP_Error( 'gemini_error', __( 'Unexpected error generating local schema description.', 'website-optimiser' ) );
	}

	return $description;
}

/**
 * AJAX handler for local schema description generation.
 */
function website_optimiser_ajax_generate_local_schema_description() {
	if ( ! check_ajax_referer( 'website_optimiser_local_schema_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized request.', 'website-optimiser' ) ) );
	}

	$result = website_optimiser_generate_local_schema_description( wp_unslash( $_POST ) );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'description' => $result ) );
}
add_action( 'wp_ajax_website_optimiser_generate_local_schema_description', 'website_optimiser_ajax_generate_local_schema_description' );
