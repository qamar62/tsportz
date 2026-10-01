<?php
/**
 * TABEER SPORTZ headless catalogue settings and editor simplifications.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keep manufacturing fields while removing public price inputs.
 *
 * MOQ, lead time, capacity and sample terms remain useful for quote requests.
 *
 * @param array $fields Crossbar field definitions.
 * @return array
 */
function ts_catalog_fields( $fields ) {
	$hidden = array(
		'cb_price_mode',
		'cb_price',
		'cb_price_to',
		'cb_currency',
		'cb_price_unit',
		'cb_incoterm',
		'cb_incoterm_place',
		'cb_tiers',
	);

	foreach ( $hidden as $key ) {
		unset( $fields[ $key ] );
	}

	return $fields;
}
add_filter( 'cb_fields', 'ts_catalog_fields', 20 );

/**
 * Sanitize the public company settings.
 *
 * @param mixed $input Submitted option.
 * @return array
 */
function ts_sanitize_catalog_settings( $input ) {
	$input = is_array( $input ) ? $input : array();

	return array(
		'company'      => sanitize_text_field( $input['company'] ?? 'TABEER SPORTZ' ),
		'phone'        => sanitize_text_field( $input['phone'] ?? '' ),
		'whatsapp'     => preg_replace( '/[^0-9]/', '', (string) ( $input['whatsapp'] ?? '' ) ),
		'email'        => sanitize_email( $input['email'] ?? '' ),
		'address'      => sanitize_textarea_field( $input['address'] ?? '' ),
		'city'         => sanitize_text_field( $input['city'] ?? 'Sialkot' ),
		'country'      => sanitize_text_field( $input['country'] ?? 'Pakistan' ),
		'price_public' => 0,
	);
}

/** Register settings before the admin page is rendered. */
function ts_register_catalog_settings() {
	register_setting(
		'ts_catalog',
		'ts_catalog_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ts_sanitize_catalog_settings',
			'default'           => array(
				'company'      => 'TABEER SPORTZ',
				'phone'        => '+92 335 3631555',
				'whatsapp'     => '923353631555',
				'email'        => '',
				'address'      => '',
				'city'         => 'Sialkot',
				'country'      => 'Pakistan',
				'price_public' => 0,
			),
		)
	);
}
add_action( 'admin_init', 'ts_register_catalog_settings' );

/** Add a compact settings page beneath Products. */
function ts_catalog_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=cb_product',
		__( 'Catalogue Settings', 'crossbar-core' ),
		__( 'Catalogue Settings', 'crossbar-core' ),
		'manage_options',
		'ts-catalog-settings',
		'ts_render_catalog_settings'
	);
}
add_action( 'admin_menu', 'ts_catalog_settings_menu' );

/** Render the settings page. */
function ts_render_catalog_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$values = wp_parse_args(
		(array) get_option( 'ts_catalog_settings', array() ),
		array(
			'company'  => 'TABEER SPORTZ',
			'phone'    => '+92 335 3631555',
			'whatsapp' => '923353631555',
			'email'    => '',
			'address'  => '',
			'city'     => 'Sialkot',
			'country'  => 'Pakistan',
		)
	);
	$fields = array(
		'company'  => __( 'Business name', 'crossbar-core' ),
		'phone'    => __( 'Public phone', 'crossbar-core' ),
		'whatsapp' => __( 'WhatsApp number', 'crossbar-core' ),
		'email'    => __( 'Public email', 'crossbar-core' ),
		'city'     => __( 'City', 'crossbar-core' ),
		'country'  => __( 'Country', 'crossbar-core' ),
	);
	?>
	<div class="wrap cb-admin">
		<h1><?php esc_html_e( 'TABEER SPORTZ Catalogue Settings', 'crossbar-core' ); ?></h1>
		<p><?php esc_html_e( 'These public details are available to the Next.js frontend through the TABEER REST API. Product prices are disabled sitewide.', 'crossbar-core' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'ts_catalog' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $label ) : ?>
					<tr>
						<th scope="row"><label for="ts-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input class="regular-text" id="ts-<?php echo esc_attr( $key ); ?>" name="ts_catalog_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $values[ $key ] ); ?>" type="<?php echo 'email' === $key ? 'email' : 'text'; ?>" /></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label for="ts-address"><?php esc_html_e( 'Address', 'crossbar-core' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="ts-address" name="ts_catalog_settings[address]"><?php echo esc_textarea( $values['address'] ); ?></textarea></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/** Set safe initial options without overwriting existing values. */
function ts_catalog_initial_settings() {
	if ( false === get_option( 'ts_catalog_settings', false ) ) {
		add_option(
			'ts_catalog_settings',
			array(
				'company'      => 'TABEER SPORTZ',
				'phone'        => '+92 335 3631555',
				'whatsapp'     => '923353631555',
				'email'        => '',
				'address'      => '',
				'city'         => 'Sialkot',
				'country'      => 'Pakistan',
				'price_public' => 0,
			)
		);
	}
}
// Run for REST and frontend requests too; add_option() makes this idempotent.
add_action( 'init', 'ts_catalog_initial_settings', 1 );
