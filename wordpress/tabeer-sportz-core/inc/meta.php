<?php
/**
 * Product meta: one field definition table, used everywhere.
 *
 * The meta boxes, the CSV importer, the spec table and the schema output all
 * read cb_fields(). Adding a field in one place adds it in all four.
 *
 * No ACF. ACF Pro cannot be redistributed with a theme that gets reused across
 * client sites, and its repeaters are the paid tier. Repeatable groups here are
 * stored as JSON in a single meta value, which imports and exports cleanly.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Incoterms offered in the price block.
 *
 * @return array<string,array<string,string>>
 */
function cb_incoterms() {

	return array(
		'none' => array(
			'label' => __( 'No term', 'crossbar-core' ),
			'title' => '',
		),
		'FOB'  => array(
			'label' => __( 'FOB - Free On Board', 'crossbar-core' ),
			'title' => __( 'Free On Board. The price covers delivery to the named port; the buyer pays freight and insurance from there.', 'crossbar-core' ),
		),
		'EXW'  => array(
			'label' => __( 'EXW - Ex Works', 'crossbar-core' ),
			'title' => __( 'Ex Works. The price is at the factory gate; the buyer arranges and pays for everything after that.', 'crossbar-core' ),
		),
		'CFR'  => array(
			'label' => __( 'CFR - Cost and Freight', 'crossbar-core' ),
			'title' => __( 'Cost and Freight. The price covers sea freight to the named destination port, but not insurance.', 'crossbar-core' ),
		),
		'CIF'  => array(
			'label' => __( 'CIF - Cost, Insurance and Freight', 'crossbar-core' ),
			'title' => __( 'Cost, Insurance and Freight. The price covers freight and insurance to the named destination port.', 'crossbar-core' ),
		),
		'DDP'  => array(
			'label' => __( 'DDP - Delivered Duty Paid', 'crossbar-core' ),
			'title' => __( 'Delivered Duty Paid. The price covers everything to the buyer door, duties included.', 'crossbar-core' ),
		),
	);
}

/**
 * Selling units.
 *
 * Gloves go out in pairs, balls in pieces, kits in sets. Getting this wrong
 * makes a quote wrong by a factor of two, so it is a field, not an assumption.
 *
 * @return array<string,array<string,string>>
 */
function cb_units() {

	return array(
		'piece'  => array(
			'one'  => __( 'piece', 'crossbar-core' ),
			'many' => __( 'pieces', 'crossbar-core' ),
			'abbr' => __( 'pc', 'crossbar-core' ),
		),
		'pair'   => array(
			'one'  => __( 'pair', 'crossbar-core' ),
			'many' => __( 'pairs', 'crossbar-core' ),
			'abbr' => __( 'pair', 'crossbar-core' ),
		),
		'set'    => array(
			'one'  => __( 'set', 'crossbar-core' ),
			'many' => __( 'sets', 'crossbar-core' ),
			'abbr' => __( 'set', 'crossbar-core' ),
		),
		'dozen'  => array(
			'one'  => __( 'dozen', 'crossbar-core' ),
			'many' => __( 'dozen', 'crossbar-core' ),
			'abbr' => __( 'dz', 'crossbar-core' ),
		),
		'carton' => array(
			'one'  => __( 'carton', 'crossbar-core' ),
			'many' => __( 'cartons', 'crossbar-core' ),
			'abbr' => __( 'ctn', 'crossbar-core' ),
		),
	);
}

/**
 * How a price is shown.
 *
 * @return array<string,string>
 */
function cb_price_modes() {

	return array(
		'quote' => __( 'Price on request (hide the number)', 'crossbar-core' ),
		'show'  => __( 'Show one price', 'crossbar-core' ),
		'from'  => __( 'Show "from" price', 'crossbar-core' ),
		'range' => __( 'Show a price range', 'crossbar-core' ),
	);
}

/**
 * Supply capability options.
 *
 * @return array<string,string>
 */
function cb_capabilities() {

	return array(
		'oem'       => __( 'OEM', 'crossbar-core' ),
		'odm'       => __( 'ODM', 'crossbar-core' ),
		'private'   => __( 'Private label', 'crossbar-core' ),
		'ownbrand'  => __( 'Own brand', 'crossbar-core' ),
	);
}

/**
 * How a logo can be put onto the product.
 *
 * A buyer asking for "branding" means one of these, and which one changes both
 * the price and the lead time — embroidery is set-up plus stitch count, print
 * is screens, sublimation is free at the cutting stage but only on polyester.
 * So it is a list, not a sentence.
 *
 * @return array<string,string>
 */
function cb_logo_methods() {

	return array(
		'printed'      => __( 'Screen printed', 'crossbar-core' ),
		'sublimated'   => __( 'Sublimated', 'crossbar-core' ),
		'embroidered'  => __( 'Embroidered', 'crossbar-core' ),
		'embossed'     => __( 'Embossed / debossed', 'crossbar-core' ),
		'heat'         => __( 'Heat transfer', 'crossbar-core' ),
		'silicone'     => __( 'Silicone / 3D', 'crossbar-core' ),
		'woven'        => __( 'Woven label', 'crossbar-core' ),
		'laser'        => __( 'Laser etched', 'crossbar-core' ),
	);
}

/**
 * What the product is made for.
 *
 * Buyers shop by occasion far more than by material: a club buys match balls,
 * a school buys training balls, an agency buys promotional ones, and all three
 * are the same factory line with a different specification.
 *
 * @return array<string,string>
 */
function cb_applications() {

	return array(
		'match'       => __( 'Match', 'crossbar-core' ),
		'training'    => __( 'Training', 'crossbar-core' ),
		'promotional' => __( 'Promotional', 'crossbar-core' ),
		'club'        => __( 'Club / academy', 'crossbar-core' ),
		'school'      => __( 'School / college', 'crossbar-core' ),
		'retail'      => __( 'Retail', 'crossbar-core' ),
		'pro'         => __( 'Professional', 'crossbar-core' ),
	);
}

/**
 * The field table.
 *
 * group    - which meta box the field appears in
 * type     - text | number | textarea | select | checkbox | multicheck | json | post | url | date | media
 * width    - full | md | sm, overriding what the type would ask for on its own
 * sanitize - callback name used on save and on import
 *
 * @return array<string,array<string,mixed>>
 */
function cb_fields() {

	$fields = array(

		/* ---- Identity ------------------------------------------------ */
		'cb_sku'          => array(
			'group'    => 'identity',
			'label'    => __( 'SKU / model number', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'Used by the CSV importer to match an existing product instead of creating a duplicate.', 'crossbar-core' ),
		),
		'cb_brand'        => array(
			'group'    => 'identity',
			'label'    => __( 'Brand / OEM name', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'cb_line'         => array(
			'group'    => 'identity',
			'label'    => __( 'Product line', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'cb_capability'   => array(
			'group'    => 'identity',
			'label'    => __( 'Supply capability', 'crossbar-core' ),
			'type'     => 'multicheck',
			'options'  => 'cb_capabilities',
			'sanitize' => 'cb_sanitize_keys',
		),
		'cb_featured'     => array(
			'group'    => 'identity',
			'label'    => __( 'Featured product', 'crossbar-core' ),
			'type'     => 'checkbox',
			'sanitize' => 'cb_sanitize_bool',
			'hint'     => __( 'Shown in the homepage featured band and filterable in the catalogue.', 'crossbar-core' ),
		),
		'cb_new_until'    => array(
			'group'    => 'identity',
			'label'    => __( 'Mark as new until', 'crossbar-core' ),
			'type'     => 'date',
			'sanitize' => 'cb_sanitize_date',
			/*
			 * A date rather than a checkbox because "new" is the one flag nobody
			 * ever remembers to turn off. A catalogue where every product has
			 * carried a NEW badge for three years is worse than one with no
			 * badges at all, so the badge expires on its own.
			 */
			'hint'     => __( 'YYYY-MM-DD. The NEW badge disappears by itself on that date. Leave empty for no badge.', 'crossbar-core' ),
		),
		'cb_origin'       => array(
			'group'    => 'identity',
			'label'    => __( 'Country of origin', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'Printed on the certificate of origin. Example: Pakistan.', 'crossbar-core' ),
		),

		/* ---- Specification ------------------------------------------- */
		'cb_method'       => array(
			'group'    => 'spec',
			'label'    => __( 'Manufacturing method', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'Example: thermo-bonded, cut and sew, seamless knit, injection moulded.', 'crossbar-core' ),
		),
		'cb_material'     => array(
			'group'    => 'spec',
			'label'    => __( 'Material / fabric', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'cb_construction' => array(
			'group'    => 'spec',
			'label'    => __( 'Construction', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'Thermo-bonded, hand-stitched, machine-stitched, sublimated, and so on.', 'crossbar-core' ),
		),
		'cb_weight'       => array(
			'group'    => 'spec',
			'label'    => __( 'Weight / size spec', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'Example: Size 5, 420-445 g, 68-70 cm circumference.', 'crossbar-core' ),
		),
		'cb_sizes'        => array(
			'group'    => 'spec',
			'label'    => __( 'Sizes available', 'crossbar-core' ),
			'type'     => 'json',
			'columns'  => array( 'size', 'stock' ),
			'sanitize' => 'cb_sanitize_rows',
			'hint'     => __( 'One per line as "size | in stock" — the stock note is optional.', 'crossbar-core' ),
		),
		'cb_colors'       => array(
			'group'    => 'spec',
			'label'    => __( 'Colours available', 'crossbar-core' ),
			'type'     => 'json',
			'columns'  => array( 'name', 'hex' ),
			'sanitize' => 'cb_sanitize_rows',
			'hint'     => __( 'One per line as "Electric Blue | #1B4DF5". The hex draws the swatch.', 'crossbar-core' ),
		),
		'cb_chart'        => array(
			'group'    => 'spec',
			'label'    => __( 'Size chart', 'crossbar-core' ),
			'type'     => 'post',
			'post_type' => 'cb_chart',
			'sanitize' => 'absint',
		),
		'cb_custom'       => array(
			'group'    => 'spec',
			'label'    => __( 'Customisation available', 'crossbar-core' ),
			'type'     => 'checkbox',
			'sanitize' => 'cb_sanitize_bool',
		),
		'cb_custom_notes' => array(
			'group'    => 'spec',
			'label'    => __( 'Customisation notes', 'crossbar-core' ),
			'type'     => 'textarea',
			'sanitize' => 'sanitize_textarea_field',
			'hint'     => __( 'Logo printing, name and number, club badge, custom colourways.', 'crossbar-core' ),
		),
		'cb_logo_app'     => array(
			'group'    => 'spec',
			'label'    => __( 'Logo application', 'crossbar-core' ),
			'type'     => 'multicheck',
			'options'  => 'cb_logo_methods',
			'sanitize' => 'cb_sanitize_keys',
		),
		'cb_print'        => array(
			'group'    => 'spec',
			'label'    => __( 'Printing method', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'Example: four-colour sublimation, single-colour screen print, DTF.', 'crossbar-core' ),
		),
		'cb_packaging'    => array(
			'group'    => 'spec',
			'label'    => __( 'Packaging', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'What the piece itself ships in. Example: individual polybag, printed box, custom packaging available.', 'crossbar-core' ),
		),
		'cb_application'  => array(
			'group'    => 'spec',
			'label'    => __( 'Application', 'crossbar-core' ),
			'type'     => 'multicheck',
			'options'  => 'cb_applications',
			'sanitize' => 'cb_sanitize_keys',
		),
		'cb_video'        => array(
			'group'    => 'spec',
			'label'    => __( 'Product video', 'crossbar-core' ),
			'type'     => 'url',
			'sanitize' => 'cb_sanitize_url',
			'hint'     => __( 'YouTube or Vimeo page URL, or a direct .mp4 link. Shown as the last gallery frame.', 'crossbar-core' ),
		),
		'cb_faq'          => array(
			'group'    => 'spec',
			'label'    => __( 'Product questions', 'crossbar-core' ),
			'type'     => 'json',
			'columns'  => array( 'q', 'a' ),
			'sanitize' => 'cb_sanitize_rows',
			'hint'     => __( 'One per line as "question | answer". These are published as FAQ structured data.', 'crossbar-core' ),
		),

		/* ---- Commercial ---------------------------------------------- */
		'cb_price_mode'   => array(
			'group'    => 'price',
			'label'    => __( 'Price display', 'crossbar-core' ),
			'type'     => 'select',
			'options'  => 'cb_price_modes',
			'default'  => 'quote',
			'sanitize' => 'cb_sanitize_key',
		),
		'cb_price'        => array(
			'group'    => 'price',
			'label'    => __( 'Price', 'crossbar-core' ),
			'type'     => 'number',
			'step'     => '0.01',
			'sanitize' => 'cb_sanitize_decimal',
		),
		'cb_price_to'     => array(
			'group'    => 'price',
			'label'    => __( 'Price (upper end of range)', 'crossbar-core' ),
			'type'     => 'number',
			'step'     => '0.01',
			'sanitize' => 'cb_sanitize_decimal',
		),
		'cb_currency'     => array(
			'group'    => 'price',
			'label'    => __( 'Currency', 'crossbar-core' ),
			'type'     => 'text',
			// Three characters. A box any wider than the answer is a box that
			// invites the wrong answer.
			'width'    => 'sm',
			'sanitize' => 'cb_sanitize_currency',
			'hint'     => __( 'Three letters. Leave empty to use the sitewide default.', 'crossbar-core' ),
		),
		'cb_price_unit'   => array(
			'group'    => 'price',
			'label'    => __( 'Priced per', 'crossbar-core' ),
			'type'     => 'select',
			'options'  => 'cb_unit_options',
			'sanitize' => 'cb_sanitize_key',
		),
		'cb_incoterm'     => array(
			'group'    => 'price',
			'label'    => __( 'Incoterm', 'crossbar-core' ),
			'type'     => 'select',
			'options'  => 'cb_incoterm_options',
			'sanitize' => 'cb_sanitize_incoterm',
			'hint'     => __( 'Leave empty to use the sitewide default. FOB is the usual term for export sports goods.', 'crossbar-core' ),
		),
		'cb_incoterm_place' => array(
			'group'    => 'price',
			'label'    => __( 'Named place / port', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'Renders after the term, as in "FOB Karachi". Leave empty to use the sitewide default.', 'crossbar-core' ),
		),
		'cb_moq'          => array(
			'group'    => 'price',
			'label'    => __( 'Minimum order quantity', 'crossbar-core' ),
			'type'     => 'number',
			'step'     => '1',
			'sanitize' => 'absint',
		),
		'cb_tiers'        => array(
			'group'    => 'price',
			'label'    => __( 'Quantity price breaks', 'crossbar-core' ),
			'type'     => 'json',
			'columns'  => array( 'qty', 'price' ),
			'sanitize' => 'cb_sanitize_rows',
			'hint'     => __( 'One per line as "minimum quantity | price". Example: 200 | 11.20', 'crossbar-core' ),
		),
		'cb_lead_time'    => array(
			'group'    => 'price',
			'label'    => __( 'Lead time', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'Example: 25-30 days after artwork approval.', 'crossbar-core' ),
		),
		'cb_capacity'     => array(
			'group'    => 'price',
			'label'    => __( 'Monthly capacity', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
		),
		'cb_sample'       => array(
			'group'    => 'price',
			'label'    => __( 'Samples available', 'crossbar-core' ),
			'type'     => 'checkbox',
			'sanitize' => 'cb_sanitize_bool',
		),
		'cb_sample_terms' => array(
			'group'    => 'price',
			'label'    => __( 'Sample terms', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'Example: USD 25 per sample, refunded against a bulk order.', 'crossbar-core' ),
		),

		/* ---- Logistics ------------------------------------------------ */
		'cb_hs_code'      => array(
			'group'    => 'logistics',
			'label'    => __( 'HS code', 'crossbar-core' ),
			'type'     => 'text',
			'width'    => 'sm',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'Six to ten digits. Customs classify the shipment from this.', 'crossbar-core' ),
		),
		'cb_pack_qty'     => array(
			'group'    => 'logistics',
			'label'    => __( 'Pieces per carton', 'crossbar-core' ),
			'type'     => 'number',
			'step'     => '1',
			'sanitize' => 'absint',
		),
		'cb_pack_dims'    => array(
			'group'    => 'logistics',
			'label'    => __( 'Carton dimensions', 'crossbar-core' ),
			'type'     => 'text',
			'sanitize' => 'sanitize_text_field',
			'hint'     => __( 'L x W x H in centimetres.', 'crossbar-core' ),
		),
		'cb_pack_net'     => array(
			'group'    => 'logistics',
			'label'    => __( 'Net weight (kg)', 'crossbar-core' ),
			'type'     => 'number',
			'step'     => '0.01',
			'sanitize' => 'cb_sanitize_decimal',
		),
		'cb_pack_gross'   => array(
			'group'    => 'logistics',
			'label'    => __( 'Gross weight (kg)', 'crossbar-core' ),
			'type'     => 'number',
			'step'     => '0.01',
			'sanitize' => 'cb_sanitize_decimal',
		),
		'cb_pack_cbm'     => array(
			'group'    => 'logistics',
			'label'    => __( 'Volume (CBM)', 'crossbar-core' ),
			'type'     => 'number',
			'step'     => '0.001',
			'sanitize' => 'cb_sanitize_decimal',
			'hint'     => __( 'Cubic metres per carton. Buyers cannot work out freight without it.', 'crossbar-core' ),
		),
		/*
		 * The three file fields.
		 *
		 * All three used to be text boxes you typed attachment ID numbers into,
		 * which asked an editor to open the Media Library in a second tab, find
		 * the number in the URL and copy it across without transposing a digit.
		 * They are pickers now: the same Media Library modal WordPress uses for
		 * the featured image, opened in place. What is stored has not changed —
		 * a comma-separated list of IDs — so every product entered the old way
		 * still reads correctly, and the CSV importer still writes the same
		 * column.
		 */
		'cb_spec_sheet'   => array(
			'group'    => 'logistics',
			'label'    => __( 'Spec sheet', 'crossbar-core' ),
			'type'     => 'media',
			'sanitize' => 'absint',
			'button'   => __( 'Choose the spec sheet', 'crossbar-core' ),
			'frame'    => __( 'Choose the spec sheet', 'crossbar-core' ),
			'hint'     => __( 'One file, usually a PDF. Shown as a download on the product page.', 'crossbar-core' ),
		),
		'cb_docs'         => array(
			'group'    => 'logistics',
			'label'    => __( 'Other documents', 'crossbar-core' ),
			'type'     => 'media',
			'multiple' => true,
			'sanitize' => 'cb_sanitize_ids',
			'button'   => __( 'Add documents', 'crossbar-core' ),
			'frame'    => __( 'Add documents', 'crossbar-core' ),
			'hint'     => __( 'Test reports, material certificates, care instructions — anything a buyer downloads. Add as many as you like.', 'crossbar-core' ),
		),
		'cb_gallery'      => array(
			'group'    => 'logistics',
			'label'    => __( 'Product gallery', 'crossbar-core' ),
			'type'     => 'media',
			'multiple' => true,
			'mime'     => 'image',
			'sanitize' => 'cb_sanitize_ids',
			'button'   => __( 'Add images', 'crossbar-core' ),
			'frame'    => __( 'Add gallery images', 'crossbar-core' ),
			'hint'     => __( 'The first image here becomes the product photo shown on cards and listings, unless a featured image has been set. Drag a thumbnail to reorder, or select one and use the arrow keys.', 'crossbar-core' ),
		),
	);

	/**
	 * Filter the product field table.
	 *
	 * @param array $fields Field definitions.
	 */
	return apply_filters( 'cb_fields', $fields );
}

/**
 * Select options for the unit dropdown.
 *
 * @return array<string,string>
 */
function cb_unit_options() {

	$out = array( '' => __( 'Use sitewide default', 'crossbar-core' ) );

	foreach ( cb_units() as $key => $unit ) {
		$out[ $key ] = $unit['one'];
	}

	return $out;
}

/**
 * Select options for the incoterm dropdown.
 *
 * @return array<string,string>
 */
function cb_incoterm_options() {

	$out = array( '' => __( 'Use sitewide default', 'crossbar-core' ) );

	foreach ( cb_incoterms() as $key => $term ) {
		$out[ $key ] = $term['label'];
	}

	return $out;
}

/* ---------------------------------------------------------------------------
 * Sanitisers
 * ------------------------------------------------------------------------- */

/**
 * A checkbox, stored as 1 or empty rather than a loose boolean so that an
 * unchecked box and a never-saved box look the same to every reader.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function cb_sanitize_bool( $value ) {
	return $value ? '1' : '';
}

/**
 * A single lowercase key.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function cb_sanitize_key( $value ) {
	return sanitize_key( (string) $value );
}

/**
 * A comma separated list of keys.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function cb_sanitize_keys( $value ) {

	if ( is_array( $value ) ) {
		$parts = $value;
	} else {
		$parts = explode( ',', (string) $value );
	}

	$parts = array_filter( array_map( 'sanitize_key', array_map( 'trim', $parts ) ) );

	return implode( ',', array_unique( $parts ) );
}

/**
 * A comma separated list of attachment IDs.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function cb_sanitize_ids( $value ) {

	$parts = is_array( $value ) ? $value : explode( ',', (string) $value );
	$parts = array_filter( array_map( 'absint', $parts ) );

	return implode( ',', $parts );
}

/**
 * A URL, or nothing.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function cb_sanitize_url( $value ) {
	return esc_url_raw( trim( (string) $value ) );
}

/**
 * A YYYY-MM-DD date, or nothing.
 *
 * Anything that is not a real calendar date is discarded rather than guessed
 * at: a badge that appears because "31/02" parsed as March is worse than no
 * badge.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function cb_sanitize_date( $value ) {

	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	// Accept the two formats a spreadsheet actually produces as well as the
	// date input's own ISO output.
	$value = str_replace( '/', '-', $value );

	if ( ! preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m ) ) {
		return '';
	}

	if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return '';
	}

	return sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] );
}

/**
 * A three letter currency code.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function cb_sanitize_currency( $value ) {

	$value = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) );

	return substr( $value, 0, 3 );
}

/**
 * An incoterm key, or empty to inherit the sitewide default.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function cb_sanitize_incoterm( $value ) {

	$value = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) );

	if ( 'NONE' === $value ) {
		return 'none';
	}

	return isset( cb_incoterms()[ $value ] ) ? $value : '';
}

/**
 * A decimal, stored as a plain string so that no locale ever turns 1.5 into 1,5.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function cb_sanitize_decimal( $value ) {

	$value = str_replace( ',', '.', (string) $value );
	$value = preg_replace( '/[^0-9.]/', '', $value );

	if ( '' === $value ) {
		return '';
	}

	// Keep only the first dot: "1.2.3" is a typo, not a number.
	$parts = explode( '.', $value );
	$value = array_shift( $parts );

	if ( $parts ) {
		$value .= '.' . implode( '', $parts );
	}

	return (string) round( (float) $value, 4 );
}

/**
 * Turn a pipe-and-newline block into JSON rows.
 *
 * Repeatable fields are a textarea rather than a JavaScript repeater on
 * purpose: it pastes straight out of a spreadsheet column, which is how these
 * lists actually reach a client's hands.
 *
 * @param mixed $value Raw textarea value or an already-decoded array.
 * @param array $args  Field definition, for the column names.
 * @return string JSON.
 */
function cb_sanitize_rows( $value, $args = array() ) {

	$columns = isset( $args['columns'] ) ? $args['columns'] : array( 'a', 'b' );

	if ( is_array( $value ) ) {

		$lines = $value;

	} else {

		/*
		 * This has to survive being run twice on the same value.
		 *
		 * Every field is registered with register_post_meta() and a
		 * sanitize_callback, so WordPress runs the sanitiser again inside
		 * update_post_meta() — after the importer and the meta box have
		 * already run it themselves. On a plain text field that is harmless,
		 * because sanitize_text_field() of a sanitised string is the same
		 * string. On a repeater it was not: the second pass saw the JSON this
		 * function had just produced, treated the whole document as one line
		 * with no pipe in it, and stored it as the first cell of a single row.
		 * That is why sizes and colours rendered as a dictionary — the value
		 * really was one, nested inside another.
		 *
		 * Decoding first makes the function idempotent, which is the property
		 * a sanitiser needs to have anyway.
		 */
		$decoded = json_decode( (string) $value, true );

		if ( is_array( $decoded ) ) {
			$lines = $decoded;
		} else {
			$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
		}
	}

	$rows = array();

	foreach ( $lines as $line ) {

		if ( is_array( $line ) ) {
			$cells = array_values( $line );
		} else {
			$line = trim( (string) $line );

			if ( '' === $line ) {
				continue;
			}

			$cells = array_map( 'trim', explode( '|', $line ) );
		}

		$row = array();

		foreach ( $columns as $i => $column ) {
			$row[ $column ] = isset( $cells[ $i ] ) ? sanitize_text_field( (string) $cells[ $i ] ) : '';
		}

		if ( '' === $row[ $columns[0] ] ) {
			continue;
		}

		$rows[] = $row;
	}

	return $rows ? wp_json_encode( $rows ) : '';
}

/**
 * Read a JSON field back as rows.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return array<int,array<string,string>>
 */
function cb_rows( $post_id, $key ) {

	$raw = get_post_meta( $post_id, $key, true );

	if ( ! $raw ) {
		return array();
	}

	$rows = json_decode( $raw, true );

	if ( ! is_array( $rows ) ) {
		return array();
	}

	/*
	 * Unwrap a value that was sanitised twice.
	 *
	 * Before cb_sanitize_rows() was made idempotent, a second pass would take
	 * the JSON of the first pass, find no pipe in it, and store the whole
	 * document as the first cell of a single row. Rows saved in that window are
	 * still in the database and there is no reason to make anyone re-import to
	 * read them, so the shape is recognised here: one row whose first cell is
	 * itself a JSON array. Nested more than once, the loop keeps peeling; the
	 * counter is only there so a malformed value cannot spin.
	 */
	$guard = 0;

	while ( 1 === count( $rows ) && $guard < 5 ) {

		$first = reset( $rows );

		if ( ! is_array( $first ) ) {
			break;
		}

		$inner = json_decode( (string) reset( $first ), true );

		if ( ! is_array( $inner ) || ! $inner ) {
			break;
		}

		$rows = $inner;
		++$guard;
	}

	return $rows;
}

/**
 * Turn rows back into the textarea format the editor types in.
 *
 * @param array $rows Rows.
 * @return string
 */
function cb_rows_to_text( $rows ) {

	$lines = array();

	foreach ( $rows as $row ) {
		$cells = array_values( (array) $row );
		$cells = array_map( 'strval', $cells );

		// Drop trailing empties so a one-column row does not end in " | ".
		while ( $cells && '' === end( $cells ) ) {
			array_pop( $cells );
		}

		if ( $cells ) {
			$lines[] = implode( ' | ', $cells );
		}
	}

	return implode( "\n", $lines );
}

/**
 * Run a field's sanitiser.
 *
 * @param string $key   Meta key.
 * @param mixed  $value Raw value.
 * @return mixed
 */
function cb_sanitize_field( $key, $value ) {

	$fields = cb_fields();

	if ( ! isset( $fields[ $key ] ) ) {
		return '';
	}

	$args     = $fields[ $key ];
	$callback = isset( $args['sanitize'] ) ? $args['sanitize'] : 'sanitize_text_field';

	if ( 'cb_sanitize_rows' === $callback ) {
		return cb_sanitize_rows( $value, $args );
	}

	return is_callable( $callback ) ? call_user_func( $callback, $value ) : sanitize_text_field( (string) $value );
}

/* ---------------------------------------------------------------------------
 * Readers
 *
 * Templates should never call get_post_meta() directly for these. Going
 * through a function is what lets the storage change later — a multicheck
 * becoming a taxonomy, say — without touching thirty template files.
 * ------------------------------------------------------------------------- */

/**
 * Turn a stored comma list of keys into readable labels.
 *
 * @param int      $post_id  Post ID.
 * @param string   $key      Meta key.
 * @param callable $options  Callback returning the key => label map.
 * @return array<int,string>
 */
function cb_labels( $post_id, $key, $options ) {

	$stored = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $post_id, $key, true ) ) ) );

	if ( ! $stored ) {
		return array();
	}

	$map = is_callable( $options ) ? call_user_func( $options ) : array();
	$out = array();

	foreach ( $stored as $slug ) {
		if ( isset( $map[ $slug ] ) ) {
			$out[] = $map[ $slug ];
		}
	}

	return $out;
}

/**
 * Is this product still inside its "new" window?
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function cb_is_new( $post_id = 0 ) {

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$until   = (string) get_post_meta( $post_id, 'cb_new_until', true );

	if ( '' === $until ) {
		return false;
	}

	// Site time, not server time. A badge that expires at the wrong midnight is
	// a support ticket nobody can reproduce.
	return current_time( 'Y-m-d' ) <= $until;
}

/**
 * Is this product flagged as featured?
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function cb_is_featured( $post_id = 0 ) {

	$post_id = $post_id ? (int) $post_id : get_the_ID();

	return (bool) get_post_meta( $post_id, 'cb_featured', true );
}

/**
 * The product FAQ rows.
 *
 * @param int $post_id Post ID.
 * @return array<int,array<string,string>>
 */
function cb_faq_rows( $post_id = 0 ) {

	$post_id = $post_id ? (int) $post_id : get_the_ID();

	return cb_rows( $post_id, 'cb_faq' );
}

/**
 * Attachment IDs for the downloadable documents.
 *
 * @param int $post_id Post ID.
 * @return array<int,int>
 */
function cb_documents( $post_id = 0 ) {

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$ids     = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post_id, 'cb_docs', true ) ) ) );
	$sheet   = (int) get_post_meta( $post_id, 'cb_spec_sheet', true );

	// The spec sheet is the document buyers ask for first, so it leads the list
	// even though it is stored in its own field.
	if ( $sheet ) {
		array_unshift( $ids, $sheet );
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Work out how to embed whatever was pasted into the video field.
 *
 * Returns an array with a `kind` of `iframe`, `file` or nothing, plus the URL
 * to use. YouTube and Vimeo watch URLs are rewritten to their privacy-friendly
 * embed hosts, because an OEM site with a tracking cookie nobody consented to
 * is a problem for the client, not for the video.
 *
 * @param int $post_id Post ID.
 * @return array{kind:string,src:string}
 */
function cb_video( $post_id = 0 ) {

	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$url     = trim( (string) get_post_meta( $post_id, 'cb_video', true ) );
	$none    = array(
		'kind' => '',
		'src'  => '',
	);

	if ( '' === $url ) {
		return $none;
	}

	if ( preg_match( '#youtu\.be/([A-Za-z0-9_-]{6,})#', $url, $m ) || preg_match( '#youtube\.com/(?:watch\?v=|embed/|shorts/)([A-Za-z0-9_-]{6,})#', $url, $m ) ) {
		return array(
			'kind' => 'iframe',
			'src'  => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0',
		);
	}

	if ( preg_match( '#vimeo\.com/(?:video/)?(\d+)#', $url, $m ) ) {
		return array(
			'kind' => 'iframe',
			'src'  => 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1',
		);
	}

	if ( preg_match( '/\.(mp4|webm|ogv)(\?.*)?$/i', $url ) ) {
		return array(
			'kind' => 'file',
			'src'  => $url,
		);
	}

	return $none;
}

/**
 * Register every product field for REST and revision support.
 *
 * @return void
 */
function cb_register_meta() {

	foreach ( cb_fields() as $key => $args ) {

		$type = 'string';

		if ( 'number' === $args['type'] && isset( $args['step'] ) && '1' === $args['step'] ) {
			$type = 'integer';
		}

		register_post_meta(
			'cb_product',
			$key,
			array(
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $value ) use ( $key ) {
					return cb_sanitize_field( $key, $value );
				},
				// show_in_rest is on, so this has to be per-post. The blanket
				// edit_posts capability would let any Contributor rewrite the
				// price of any product straight through the REST endpoint.
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
}
add_action( 'init', 'cb_register_meta', 7 );
