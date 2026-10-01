<?php
/**
 * Plugin Name:       TABEER SPORTZ Core
 * Description:       Headless sports manufacturing catalogue, product editor, REST API and quote requests for TABEER SPORTZ.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            TABEER SPORTZ
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       crossbar-core
 * Domain Path:       /languages
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

define( 'CB_CORE_VERSION', '2.0.0' );
define( 'CB_CORE_FILE', __FILE__ );
define( 'CB_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CB_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once CB_CORE_PATH . 'inc/post-types.php';
require_once CB_CORE_PATH . 'inc/meta.php';
require_once CB_CORE_PATH . 'inc/meta-boxes.php';
require_once CB_CORE_PATH . 'inc/price.php';
require_once CB_CORE_PATH . 'inc/rfq.php';
require_once CB_CORE_PATH . 'inc/rfq-admin.php';
require_once CB_CORE_PATH . 'inc/schema.php';
require_once CB_CORE_PATH . 'inc/importer.php';
require_once CB_CORE_PATH . 'inc/mail.php';
require_once CB_CORE_PATH . 'inc/tabeer-settings.php';
require_once CB_CORE_PATH . 'inc/tabeer-rest.php';
require_once CB_CORE_PATH . 'inc/tabeer-demo.php';

/**
 * Load translations.
 *
 * @return void
 */
function cb_core_load_textdomain() {
	load_plugin_textdomain( 'crossbar-core', false, dirname( plugin_basename( CB_CORE_FILE ) ) . '/languages' );
}
add_action( 'init', 'cb_core_load_textdomain' );

/**
 * Admin stylesheet for the product and RFQ screens.
 *
 * Loaded only where it is needed: a plugin that dumps CSS onto every admin
 * page is a plugin that eventually breaks somebody else's screen.
 *
 * @param string $hook Current admin page.
 * @return void
 */
function cb_core_admin_assets( $hook ) {

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$types  = array( 'cb_product', 'cb_rfq', 'cb_chart' );

	// The category and certification terms both carry a picture, so the term
	// screens need the picker too. The theme's category image field is rendered
	// there and is a caller of this plugin's own picker.
	$taxes = array( 'cb_category', 'cb_certification' );

	$on_post_screen = $screen && in_array( $screen->post_type, $types, true );
	// Both bases: "edit-tags" carries the add form in the left column, "term" is
	// the single term being edited. The picker appears on each.
	$on_term_screen = $screen && in_array( $screen->base, array( 'edit-tags', 'term' ), true ) && in_array( $screen->taxonomy, $taxes, true );
	$on_tools       = ( 'cb_product_page_cb-import' === $hook );

	if ( ! $on_post_screen && ! $on_term_screen && ! $on_tools ) {
		return;
	}

	wp_enqueue_style( 'cb-core-admin', CB_CORE_URL . 'assets/admin.css', array(), CB_CORE_VERSION );
	wp_enqueue_script( 'cb-core-admin', CB_CORE_URL . 'assets/admin.js', array(), CB_CORE_VERSION, true );

	/*
	 * The gallery, the spec sheet, the documents and the two term pictures are
	 * all picked through the same Media Library modal WordPress opens for the
	 * featured image, which means wp.media has to be on the page. Not loaded
	 * anywhere else: it is a large dependency, and the quote screen, the chart
	 * screen and the importer have no file fields at all.
	 */
	$editing_product = $screen && 'cb_product' === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true );

	if ( $editing_product || $on_term_screen ) {
		wp_enqueue_media();
	}

	// The live price preview has to say exactly what the front end will say, so
	// it is fed the same units, terms and defaults the PHP renderer uses rather
	// than a second copy of the lists hard-coded in JavaScript.
	$units = array();

	foreach ( cb_units() as $key => $unit ) {
		$units[ $key ] = $unit['abbr'];
	}

	$terms = array();

	foreach ( cb_incoterms() as $key => $term ) {
		$terms[ $key ] = $term['title'];
	}

	wp_localize_script(
		'cb-core-admin',
		'cbCore',
		array(
			'units'     => $units,
			'terms'     => $terms,
			'currency'  => (string) cb_setting( 'currency' ),
			'unit'      => (string) cb_setting( 'price_unit' ),
			'incoterm'  => (string) cb_setting( 'incoterm' ),
			'place'     => (string) cb_setting( 'incoterm_place' ),
			'quoteOnly' => cb_quote_only() ? 1 : 0,
			'i18n'      => array(
				'request'   => __( 'Price on request', 'crossbar-core' ),
				'from'      => __( 'From', 'crossbar-core' ),
				'siteQuote' => __( 'Price on request — the whole site is in quote-only mode.', 'crossbar-core' ),
				'confirm'   => __( 'This will write to the catalogue. Run a dry run first if you have not already. Continue?', 'crossbar-core' ),
				'use'       => __( 'Use these files', 'crossbar-core' ),
				/* translators: %s: attachment title. */
				'remove'    => __( 'Remove %s', 'crossbar-core' ),
				'removeAll' => __( 'Remove every file from this field?', 'crossbar-core' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'cb_core_admin_assets' );

/**
 * Flush rewrite rules once, on activation, after the post types exist.
 *
 * @return void
 */
function cb_core_activate() {
	cb_register_post_types();
	cb_register_taxonomies();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'cb_core_activate' );

/**
 * Tidy up the rewrite rules on deactivation. Content is deliberately left
 * alone: deactivating a plugin should never delete a client's catalogue.
 *
 * @return void
 */
function cb_core_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cb_core_deactivate' );

/**
 * Read a plugin option with a default.
 *
 * The theme writes these through the Customizer; the plugin reads them so it
 * can still price things correctly if the theme is swapped out.
 *
 * @param string $key     Option key without the cb_ prefix.
 * @param mixed  $default Fallback.
 * @return mixed
 */
function cb_setting( $key, $default = '' ) {

	$map = array(
		'currency'       => 'USD',
		'incoterm'       => 'FOB',
		'incoterm_place' => '',
		'price_unit'     => 'piece',
		'price_public'   => 0,
		'company'        => '',
		'phone'          => '',
		'whatsapp'       => '',
		'email'          => '',
		'address'        => '',
		'city'           => '',
		'country'        => '',
	);

	$fallback = array_key_exists( $key, $map ) ? $map[ $key ] : $default;
	$settings = get_option( 'ts_catalog_settings', array() );
	$value    = is_array( $settings ) && array_key_exists( $key, $settings )
		? $settings[ $key ]
		: get_theme_mod( 'cb_' . $key, $fallback );

	if ( '' === $value && '' !== $fallback ) {
		$value = $fallback;
	}

	/**
	 * Filter a Crossbar setting.
	 *
	 * @param mixed  $value Setting value.
	 * @param string $key   Setting key.
	 */
	return apply_filters( 'cb_setting', $value, $key );
}
