<?php
/**
 * Structured data.
 *
 * Emitted by the plugin rather than by an SEO plugin, so a client site ranks
 * correctly the day it is switched on with nothing configured. If Rank Math or
 * Yoast is later installed, turn its Product schema off — two Product nodes on
 * one page is worse than none.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print a JSON-LD block.
 *
 * @param array $data Schema data.
 * @return void
 */
function cb_print_schema( $data ) {

	if ( ! $data ) {
		return;
	}

	// Slashes stay escaped on purpose. Without that, a product title or a
	// company name containing </script> would close this block and everything
	// after it would be parsed as markup — stored cross-site scripting on every
	// product page. Unicode is left unescaped so Arabic and Urdu company names
	// stay readable to anyone auditing the source.
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}

/**
 * Organization node, on every page.
 *
 * @return array
 */
function cb_schema_organization() {

	$data = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'name'     => cb_setting( 'company', get_bloginfo( 'name' ) ),
		'url'      => home_url( '/' ),
	);

	$logo = get_theme_mod( 'custom_logo' );

	if ( $logo ) {
		$src = wp_get_attachment_image_src( $logo, 'full' );

		if ( $src ) {
			$data['logo'] = $src[0];
		}
	}

	$phone   = cb_setting( 'phone' );
	$email   = cb_setting( 'email' );
	$address = cb_setting( 'address' );
	$city    = cb_setting( 'city' );
	$country = cb_setting( 'country' );

	if ( $phone || $email ) {
		$data['contactPoint'] = array_filter(
			array(
				'@type'       => 'ContactPoint',
				'contactType' => 'sales',
				'telephone'   => $phone,
				'email'       => $email,
			)
		);
	}

	if ( $address || $city ) {
		$data['address'] = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $address,
				'addressLocality' => $city,
				'addressCountry'  => $country,
			)
		);
	}

	$socials = array_filter(
		array(
			get_theme_mod( 'cb_instagram', '' ),
			get_theme_mod( 'cb_facebook', '' ),
			get_theme_mod( 'cb_linkedin', '' ),
		)
	);

	if ( $socials ) {
		$data['sameAs'] = array_values( $socials );
	}

	return $data;
}

/**
 * Product node.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function cb_schema_product( $post_id ) {

	$data = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Product',
		'name'        => get_the_title( $post_id ),
		'url'         => get_permalink( $post_id ),
		'description' => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
	);

	$sku = get_post_meta( $post_id, 'cb_sku', true );

	if ( $sku ) {
		$data['sku']   = $sku;
		$data['mpn']   = $sku;
	}

	$brand = get_post_meta( $post_id, 'cb_brand', true );

	if ( $brand ) {
		$data['brand'] = array(
			'@type' => 'Brand',
			'name'  => $brand,
		);
	}

	$material = get_post_meta( $post_id, 'cb_material', true );

	if ( $material ) {
		$data['material'] = $material;
	}

	$images = array();

	if ( has_post_thumbnail( $post_id ) ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'large' );

		if ( $src ) {
			$images[] = $src[0];
		}
	}

	foreach ( array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post_id, 'cb_gallery', true ) ) ) ) as $id ) {
		$src = wp_get_attachment_image_src( $id, 'large' );

		if ( $src ) {
			$images[] = $src[0];
		}
	}

	if ( $images ) {
		$data['image'] = array_values( array_unique( $images ) );
	}

	$terms = get_the_terms( $post_id, 'cb_category' );

	if ( $terms && ! is_wp_error( $terms ) ) {
		$data['category'] = $terms[0]->name;
	}

	// Only describe an offer when there is a real, public number behind it.
	// A quote-only product with a fabricated price is a manual action waiting
	// to happen, so the offers node is simply omitted instead.
	$mode  = (string) get_post_meta( $post_id, 'cb_price_mode', true );
	$price = (string) get_post_meta( $post_id, 'cb_price', true );

	if ( ! cb_quote_only() && 'quote' !== $mode && '' !== $price ) {

		$offer = array(
			'@type'         => 'Offer',
			'url'           => get_permalink( $post_id ),
			'priceCurrency' => cb_product_currency( $post_id ),
			'availability'  => 'https://schema.org/InStock',
		);

		$to = (string) get_post_meta( $post_id, 'cb_price_to', true );

		if ( 'range' === $mode && '' !== $to && (float) $to > (float) $price ) {
			$offer['@type']              = 'AggregateOffer';
			$offer['lowPrice']           = $price;
			$offer['highPrice']          = $to;
			$offer['offerCount']         = 2;
		} else {
			$offer['price'] = $price;
		}

		$data['offers'] = $offer;
	}

	return $data;
}

/**
 * Breadcrumb node.
 *
 * @return array
 */
function cb_schema_breadcrumb() {

	$items = array(
		array(
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => __( 'Home', 'crossbar-core' ),
			'item'     => home_url( '/' ),
		),
	);

	if ( is_singular( 'cb_product' ) ) {

		$archive = get_post_type_archive_link( 'cb_product' );

		if ( $archive ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => __( 'Products', 'crossbar-core' ),
				'item'     => $archive,
			);
		}

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => count( $items ) + 1,
			'name'     => get_the_title(),
			'item'     => get_permalink(),
		);

	} elseif ( is_tax( array( 'cb_category', 'cb_subtype', 'cb_certification' ) ) ) {

		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {

			$link = get_term_link( $term );

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => $term->name,
				'item'     => is_wp_error( $link ) ? home_url( '/' ) : $link,
			);
		}
	} else {
		return array();
	}

	return array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);
}

/**
 * FAQPage node.
 *
 * Two things can fill this: the FAQ rows on a product, which the plugin owns,
 * and any band a theme draws on another view. The second sort arrives through
 * the filter rather than by this file reaching into a theme mod, because a
 * plugin that reads a setting called crossbar_something stops working the day
 * the site is re-skinned, and the whole point of keeping content in here is that
 * switching theme changes nothing.
 *
 * Google only credits FAQ markup when the same questions and answers are
 * visible on the page, so a caller adding rows here is promising they are also
 * printing them. The theme's band and this node read the same setting through
 * the same parser, which is how that promise is kept.
 *
 * @return array
 */
function cb_schema_faq() {

	$rows = array();

	if ( is_singular( 'cb_product' ) && function_exists( 'cb_faq_rows' ) ) {
		$rows = cb_faq_rows( get_the_ID() );
	}

	/**
	 * Filter the question and answer pairs published for this view.
	 *
	 * Each row is an array whose first cell is the question and whose second is
	 * the answer.
	 *
	 * @param array<int,array<int|string,string>> $rows Rows.
	 */
	$rows = apply_filters( 'cb_schema_faq_rows', $rows );

	$items = array();

	foreach ( (array) $rows as $row ) {

		$cells    = array_values( (array) $row );
		$question = isset( $cells[0] ) ? trim( wp_strip_all_tags( (string) $cells[0] ) ) : '';
		$answer   = isset( $cells[1] ) ? trim( wp_strip_all_tags( (string) $cells[1] ) ) : '';

		// A question with no answer is not a FAQ entry, and shipping one is how
		// a page earns a structured data warning rather than a rich result.
		if ( '' === $question || '' === $answer ) {
			continue;
		}

		$items[] = array(
			'@type'          => 'Question',
			'name'           => $question,
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $answer,
			),
		);
	}

	if ( ! $items ) {
		return array();
	}

	return array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $items,
	);
}

/**
 * Article node for a blog post.
 *
 * The knowledge centre is where this catalogue argues for itself — stitch
 * counts, glove sizing, what a certificate of origin is for — and those pages
 * are the ones that get shared and cited, so they are the ones that most need
 * an author, a date and an image attached in a form a search engine reads.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function cb_schema_article( $post_id ) {

	$post = get_post( $post_id );

	if ( ! $post ) {
		return array();
	}

	$data = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'Article',
		'headline'         => wp_trim_words( get_the_title( $post_id ), 20, '' ),
		'url'              => get_permalink( $post_id ),
		'datePublished'    => get_the_date( DATE_W3C, $post_id ),
		'dateModified'     => get_the_modified_date( DATE_W3C, $post_id ),
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => get_permalink( $post_id ),
		),
		'publisher'        => array(
			'@type' => 'Organization',
			'name'  => cb_setting( 'company', get_bloginfo( 'name' ) ),
		),
	);

	$excerpt = wp_strip_all_tags( get_the_excerpt( $post_id ) );

	if ( $excerpt ) {
		$data['description'] = $excerpt;
	}

	$author = get_the_author_meta( 'display_name', (int) $post->post_author );

	if ( $author ) {
		$data['author'] = array(
			'@type' => 'Person',
			'name'  => $author,
		);
	}

	if ( has_post_thumbnail( $post_id ) ) {

		$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), 'full' );

		if ( $src ) {
			$data['image'] = $src[0];
		}
	}

	$logo = get_theme_mod( 'custom_logo' );

	if ( $logo ) {

		$src = wp_get_attachment_image_src( $logo, 'full' );

		if ( $src ) {
			$data['publisher']['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $src[0],
			);
		}
	}

	return $data;
}

/**
 * Output the schema for the current view.
 *
 * @return void
 */
function cb_output_schema() {

	if ( is_admin() || is_feed() ) {
		return;
	}

	cb_print_schema( cb_schema_organization() );

	if ( is_singular( 'cb_product' ) ) {
		cb_print_schema( cb_schema_product( get_the_ID() ) );
	}

	if ( is_singular( 'post' ) ) {
		cb_print_schema( cb_schema_article( get_the_ID() ) );
	}

	cb_print_schema( cb_schema_faq() );

	cb_print_schema( cb_schema_breadcrumb() );
}
add_action( 'wp_head', 'cb_output_schema', 20 );
