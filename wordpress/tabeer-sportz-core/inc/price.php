<?php
/**
 * Export pricing.
 *
 * A price in this trade is never a bare number. It is a number bound to a
 * currency, a selling unit, an Incoterm and a named place, and it usually only
 * applies above a minimum quantity. Everything in this file exists to keep
 * those five things travelling together so a buyer cannot misread the offer.
 *
 * Every price rendered anywhere in the theme goes through cb_price_html(), so
 * a later WooCommerce bridge only has to replace one function.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is the whole site in quote-only mode?
 *
 * Clients ask for this constantly: their competitors read the site too.
 *
 * @return bool
 */
function cb_quote_only() {
	return ! cb_setting( 'price_public', 1 );
}

/**
 * The selling unit for a product, falling back to the sitewide default.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function cb_product_unit( $post_id ) {

	$unit = (string) get_post_meta( $post_id, 'cb_price_unit', true );

	if ( '' === $unit ) {
		$unit = (string) cb_setting( 'price_unit', 'piece' );
	}

	return isset( cb_units()[ $unit ] ) ? $unit : 'piece';
}

/**
 * Singular or plural label for a unit.
 *
 * @param string $unit  Unit key.
 * @param int    $count Quantity.
 * @return string
 */
function cb_unit_label( $unit, $count = 1 ) {

	$units = cb_units();

	if ( ! isset( $units[ $unit ] ) ) {
		return '';
	}

	return ( 1 === (int) $count ) ? $units[ $unit ]['one'] : $units[ $unit ]['many'];
}

/**
 * Short unit label, for the "/ pc" suffix after a price.
 *
 * @param string $unit Unit key.
 * @return string
 */
function cb_unit_abbr( $unit ) {

	$units = cb_units();

	return isset( $units[ $unit ] ) ? $units[ $unit ]['abbr'] : '';
}

/**
 * The currency code for a product.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function cb_product_currency( $post_id ) {

	$code = (string) get_post_meta( $post_id, 'cb_currency', true );

	if ( '' === $code ) {
		$code = (string) cb_setting( 'currency', 'USD' );
	}

	return $code ? $code : 'USD';
}

/**
 * The Incoterm for a product: own value, then sitewide default.
 *
 * An explicit 'none' on the product wins over the sitewide default, which is
 * how a client sells one line ex-works while everything else ships FOB.
 *
 * @param int $post_id Post ID.
 * @return string Incoterm key, or empty for none.
 */
function cb_product_incoterm( $post_id ) {

	$term = (string) get_post_meta( $post_id, 'cb_incoterm', true );

	if ( '' === $term ) {
		$term = (string) cb_setting( 'incoterm', 'FOB' );
	}

	if ( 'none' === $term ) {
		return '';
	}

	return isset( cb_incoterms()[ $term ] ) ? $term : '';
}

/**
 * The named place that follows the Incoterm.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function cb_product_place( $post_id ) {

	$place = (string) get_post_meta( $post_id, 'cb_incoterm_place', true );

	if ( '' === $place ) {
		$place = (string) cb_setting( 'incoterm_place', '' );
	}

	return $place;
}

/**
 * "FOB Karachi", with the term wrapped in an abbr so a retail visitor can find
 * out what it means without leaving the page.
 *
 * @param int  $post_id Post ID.
 * @param bool $rich    Allow the abbr element.
 * @return string
 */
function cb_incoterm_html( $post_id, $rich = true ) {

	$term = cb_product_incoterm( $post_id );

	if ( ! $term ) {
		return '';
	}

	$terms = cb_incoterms();
	$title = isset( $terms[ $term ]['title'] ) ? $terms[ $term ]['title'] : '';
	$place = cb_product_place( $post_id );

	if ( $rich && $title ) {
		$out = '<abbr class="price__term" title="' . esc_attr( $title ) . '">' . esc_html( $term ) . '</abbr>';
	} else {
		$out = esc_html( $term );
	}

	if ( $place ) {
		$out .= ' ' . esc_html( $place );
	}

	return $out;
}

/**
 * Format one money value.
 *
 * Deliberately not using number_format_i18n on the decimal part: an export
 * price list is read in a dozen countries and a swapped comma and dot is the
 * difference between 1,200 and 1.200.
 *
 * @param string $amount   Raw amount.
 * @param string $currency Currency code.
 * @return string
 */
function cb_money( $amount, $currency ) {

	if ( '' === $amount || null === $amount ) {
		return '';
	}

	$value = (float) $amount;

	// Whole numbers lose the trailing ".00": 12 reads better than 12.00 on a card.
	$decimals = ( abs( $value - round( $value ) ) < 0.005 ) ? 0 : 2;

	return $currency . ' ' . number_format( $value, $decimals, '.', ',' );
}

/**
 * The price line for a product.
 *
 * Returns an empty string when there is nothing to show, so callers can decide
 * whether to print "Price on request" or nothing at all.
 *
 * @param int  $post_id Post ID.
 * @param bool $rich    Allow markup.
 * @return string
 */
function cb_price_html( $post_id, $rich = true ) {

	$post_id = (int) $post_id;

	if ( cb_quote_only() ) {
		return '';
	}

	$mode = (string) get_post_meta( $post_id, 'cb_price_mode', true );

	if ( '' === $mode ) {
		$mode = 'quote';
	}

	if ( 'quote' === $mode ) {
		return '';
	}

	$price = (string) get_post_meta( $post_id, 'cb_price', true );

	if ( '' === $price ) {
		return '';
	}

	$currency = cb_product_currency( $post_id );
	$unit     = cb_product_unit( $post_id );
	$abbr     = cb_unit_abbr( $unit );

	$amount = cb_money( $price, $currency );

	if ( 'range' === $mode ) {
		$to = (string) get_post_meta( $post_id, 'cb_price_to', true );

		if ( '' !== $to && (float) $to > (float) $price ) {
			$amount .= ' – ' . cb_money( $to, $currency );
		}
	}

	if ( $rich ) {
		$out = '<span class="price__amount">' . esc_html( $amount ) . '</span>';
	} else {
		$out = $amount;
	}

	if ( 'from' === $mode ) {
		/* translators: %s: formatted price. */
		$out = $rich
			? '<span class="price__from">' . esc_html__( 'From', 'crossbar-core' ) . '</span> ' . $out
			: sprintf( __( 'From %s', 'crossbar-core' ), $out );
	}

	if ( $abbr ) {
		$out .= $rich
			? ' <span class="price__unit">/ ' . esc_html( $abbr ) . '</span>'
			: ' / ' . $abbr;
	}

	$term = cb_incoterm_html( $post_id, $rich );

	if ( $term ) {
		$out .= $rich
			? ' <span class="price__incoterm">' . $term . '</span>'
			: ' ' . wp_strip_all_tags( $term );
	}

	/**
	 * Filter the rendered price line.
	 *
	 * A WooCommerce bridge replaces the whole line here rather than editing
	 * templates.
	 *
	 * @param string $out     Rendered price.
	 * @param int    $post_id Post ID.
	 * @param bool   $rich    Whether markup is allowed.
	 */
	return apply_filters( 'cb_price_html', $out, $post_id, $rich );
}

/**
 * The MOQ line, as its own sentence.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function cb_moq_html( $post_id ) {

	$moq = (int) get_post_meta( $post_id, 'cb_moq', true );

	if ( ! $moq ) {
		return '';
	}

	$unit = cb_product_unit( $post_id );

	// Returned unescaped: every caller passes it through esc_html(), and
	// escaping here as well would print the entities rather than the text.
	return sprintf(
		/* translators: 1: quantity, 2: unit name. */
		__( 'MOQ %1$s %2$s', 'crossbar-core' ),
		number_format_i18n( $moq ),
		cb_unit_label( $unit, $moq )
	);
}

/**
 * Quantity price breaks, sorted ascending and cleaned of junk rows.
 *
 * @param int $post_id Post ID.
 * @return array<int,array<string,string>>
 */
function cb_tiers( $post_id ) {

	if ( cb_quote_only() ) {
		return array();
	}

	$rows = cb_rows( $post_id, 'cb_tiers' );
	$out  = array();

	foreach ( $rows as $row ) {

		$qty   = isset( $row['qty'] ) ? (int) preg_replace( '/[^0-9]/', '', (string) $row['qty'] ) : 0;
		$price = isset( $row['price'] ) ? cb_sanitize_decimal( $row['price'] ) : '';

		if ( ! $qty || '' === $price ) {
			continue;
		}

		$out[] = array(
			'qty'   => $qty,
			'price' => $price,
		);
	}

	usort(
		$out,
		function ( $a, $b ) {
			return $a['qty'] <=> $b['qty'];
		}
	);

	return $out;
}

/**
 * Render the price breaks as a small table.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function cb_tiers_html( $post_id ) {

	$tiers = cb_tiers( $post_id );

	if ( count( $tiers ) < 2 ) {
		return '';
	}

	$currency = cb_product_currency( $post_id );
	$unit     = cb_product_unit( $post_id );

	$out  = '<table class="tiers">';
	$out .= '<caption class="tiers__caption">' . esc_html__( 'Quantity price breaks', 'crossbar-core' ) . '</caption>';
	$out .= '<thead><tr>';
	$out .= '<th scope="col">' . esc_html__( 'Quantity', 'crossbar-core' ) . '</th>';
	$out .= '<th scope="col">' . esc_html__( 'Unit price', 'crossbar-core' ) . '</th>';
	$out .= '</tr></thead><tbody>';

	foreach ( $tiers as $i => $tier ) {

		if ( isset( $tiers[ $i + 1 ] ) ) {
			$range = sprintf(
				'%s – %s',
				number_format_i18n( $tier['qty'] ),
				number_format_i18n( $tiers[ $i + 1 ]['qty'] - 1 )
			);
		} else {
			// Unescaped, because the row is escaped once on the way out below.
			/* translators: %s: quantity. */
			$range = sprintf( __( '%s and above', 'crossbar-core' ), number_format_i18n( $tier['qty'] ) );
		}

		$out .= '<tr>';
		$out .= '<th scope="row">' . esc_html( $range ) . ' ' . esc_html( cb_unit_label( $unit, 2 ) ) . '</th>';
		$out .= '<td>' . esc_html( cb_money( $tier['price'], $currency ) ) . '</td>';
		$out .= '</tr>';
	}

	$out .= '</tbody></table>';

	$term = cb_incoterm_html( $post_id );

	if ( $term ) {
		$out .= '<p class="tiers__note">' . wp_kses( $term, array( 'abbr' => array( 'class' => array(), 'title' => array() ) ) ) . '</p>';
	}

	return $out;
}

/**
 * Does this product show a price at all?
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function cb_has_price( $post_id ) {
	return '' !== cb_price_html( $post_id, false );
}
