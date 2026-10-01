<?php
/**
 * Stable, frontend-friendly REST API for the headless Next.js site.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

/** Return a normalized media object. */
function ts_rest_media( $attachment_id ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id ) {
		return null;
	}

	$full = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( ! $full ) {
		return null;
	}

	$medium = wp_get_attachment_image_src( $attachment_id, 'medium_large' );
	return array(
		'id'     => $attachment_id,
		'url'    => esc_url_raw( $full[0] ),
		'width'  => (int) $full[1],
		'height' => (int) $full[2],
		'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		'medium' => $medium ? esc_url_raw( $medium[0] ) : esc_url_raw( $full[0] ),
	);
}

/** Return simple public terms for a product. */
function ts_rest_terms( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array();
	}

	return array_values(
		array_map(
			function ( $term ) {
				return array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			},
			$terms
		)
	);
}

/** Decode a plugin JSON-row field for the API. */
function ts_rest_rows( $post_id, $key ) {
	return function_exists( 'cb_rows' ) ? cb_rows( $post_id, $key ) : array();
}

/** Build the public product contract used by Next.js. */
function ts_rest_product( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'cb_product' !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}

	$gallery_ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post->ID, 'cb_gallery', true ) ) ) );
	$gallery     = array_values( array_filter( array_map( 'ts_rest_media', $gallery_ids ) ) );
	$image       = ts_rest_media( get_post_thumbnail_id( $post->ID ) );
	if ( ! $image && $gallery ) {
		$image = $gallery[0];
	}

	$specs = array(
		'sku'                 => (string) get_post_meta( $post->ID, 'cb_sku', true ),
		'brand'               => (string) get_post_meta( $post->ID, 'cb_brand', true ),
		'product_line'        => (string) get_post_meta( $post->ID, 'cb_line', true ),
		'origin'              => (string) get_post_meta( $post->ID, 'cb_origin', true ),
		'manufacturing'       => (string) get_post_meta( $post->ID, 'cb_method', true ),
		'material'            => (string) get_post_meta( $post->ID, 'cb_material', true ),
		'construction'        => (string) get_post_meta( $post->ID, 'cb_construction', true ),
		'weight'              => (string) get_post_meta( $post->ID, 'cb_weight', true ),
		'customizable'        => (bool) get_post_meta( $post->ID, 'cb_custom', true ),
		'customization_notes' => (string) get_post_meta( $post->ID, 'cb_custom_notes', true ),
		'printing'            => (string) get_post_meta( $post->ID, 'cb_print', true ),
		'packaging'           => (string) get_post_meta( $post->ID, 'cb_packaging', true ),
		'moq'                 => (int) get_post_meta( $post->ID, 'cb_moq', true ),
		'lead_time'           => (string) get_post_meta( $post->ID, 'cb_lead_time', true ),
		'monthly_capacity'    => (string) get_post_meta( $post->ID, 'cb_capacity', true ),
		'samples_available'   => (bool) get_post_meta( $post->ID, 'cb_sample', true ),
		'sample_terms'        => (string) get_post_meta( $post->ID, 'cb_sample_terms', true ),
	);

	return array(
		'id'             => (int) $post->ID,
		'slug'           => $post->post_name,
		'title'          => get_the_title( $post ),
		'excerpt'        => get_the_excerpt( $post ),
		'description'    => apply_filters( 'the_content', $post->post_content ),
		'image'          => $image,
		'gallery'        => $gallery,
		'featured'       => function_exists( 'cb_is_featured' ) ? cb_is_featured( $post->ID ) : false,
		'is_new'         => function_exists( 'cb_is_new' ) ? cb_is_new( $post->ID ) : false,
		'categories'     => ts_rest_terms( $post->ID, 'cb_category' ),
		'sports'         => ts_rest_terms( $post->ID, 'cb_sport' ),
		'subtypes'       => ts_rest_terms( $post->ID, 'cb_subtype' ),
		'certifications' => ts_rest_terms( $post->ID, 'cb_certification' ),
		'sizes'          => ts_rest_rows( $post->ID, 'cb_sizes' ),
		'colors'         => ts_rest_rows( $post->ID, 'cb_colors' ),
		'faq'            => ts_rest_rows( $post->ID, 'cb_faq' ),
		'specifications' => array_filter(
			$specs,
			function ( $value ) {
				return '' !== $value && null !== $value && false !== $value && 0 !== $value;
			}
		),
		'updated_at'     => get_post_modified_time( DATE_ATOM, true, $post ),
	);
}

/** List products with filters needed by the catalogue. */
function ts_rest_products( WP_REST_Request $request ) {
	$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ?: 24 ) ) );
	$page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
	$args     = array(
		'post_type'      => 'cb_product',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $page,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	);

	if ( $request->get_param( 'search' ) ) {
		$args['s'] = sanitize_text_field( $request->get_param( 'search' ) );
	}

	if ( rest_sanitize_boolean( $request->get_param( 'featured' ) ) ) {
		$args['meta_query'] = array(
			array(
				'key'   => 'cb_featured',
				'value' => '1',
			),
		);
	}

	$tax_query = array();
	foreach ( array( 'category' => 'cb_category', 'sport' => 'cb_sport', 'subtype' => 'cb_subtype' ) as $parameter => $taxonomy ) {
		if ( $request->get_param( $parameter ) ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => sanitize_title( $request->get_param( $parameter ) ),
			);
		}
	}
	if ( $tax_query ) {
		$args['tax_query'] = $tax_query;
	}

	$query    = new WP_Query( $args );
	$products = array_values( array_filter( array_map( 'ts_rest_product', $query->posts ) ) );
	$response = rest_ensure_response(
		array(
			'items'      => $products,
			'page'       => $page,
			'per_page'   => $per_page,
			'total'      => (int) $query->found_posts,
			'total_pages'=> (int) $query->max_num_pages,
		)
	);
	$response->header( 'X-WP-Total', (int) $query->found_posts );
	$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );
	return $response;
}

/** Return one product by slug. */
function ts_rest_single_product( WP_REST_Request $request ) {
	$post = get_page_by_path( sanitize_title( $request['slug'] ), OBJECT, 'cb_product' );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return new WP_Error( 'ts_product_not_found', __( 'Product not found.', 'crossbar-core' ), array( 'status' => 404 ) );
	}
	return rest_ensure_response( ts_rest_product( $post ) );
}

/** Return the safe public business settings. */
function ts_rest_settings() {
	return rest_ensure_response(
		array(
			'business_name' => cb_setting( 'company', 'TABEER SPORTZ' ),
			'phone'         => cb_setting( 'phone', '+92 335 3631555' ),
			'whatsapp'      => cb_setting( 'whatsapp', '923353631555' ),
			'email'         => cb_setting( 'email', '' ),
			'address'       => cb_setting( 'address', '' ),
			'city'          => cb_setting( 'city', 'Sialkot' ),
			'country'       => cb_setting( 'country', 'Pakistan' ),
			'pricing'       => 'quote_only',
		)
	);
}

/** Save a lightweight headless quote request. */
function ts_rest_create_inquiry( WP_REST_Request $request ) {
	if ( trim( (string) $request->get_param( 'website' ) ) ) {
		return rest_ensure_response( array( 'received' => true ) );
	}

	$name       = sanitize_text_field( $request->get_param( 'name' ) );
	$email      = sanitize_email( $request->get_param( 'email' ) );
	$phone      = sanitize_text_field( $request->get_param( 'phone' ) );
	$company    = sanitize_text_field( $request->get_param( 'company' ) );
	$country    = sanitize_text_field( $request->get_param( 'country' ) );
	$message    = sanitize_textarea_field( $request->get_param( 'message' ) );
	$product_id = absint( $request->get_param( 'product_id' ) );
	$quantity   = absint( $request->get_param( 'quantity' ) );

	if ( ! $name || ! is_email( $email ) || ! $message ) {
		return new WP_Error( 'ts_invalid_inquiry', __( 'Name, a valid email and message are required.', 'crossbar-core' ), array( 'status' => 400 ) );
	}

	$remote = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
	$key    = 'ts_inquiry_' . md5( $remote . '|' . strtolower( $email ) );
	if ( get_transient( $key ) ) {
		return new WP_Error( 'ts_inquiry_rate_limited', __( 'Please wait before sending another inquiry.', 'crossbar-core' ), array( 'status' => 429 ) );
	}
	set_transient( $key, 1, MINUTE_IN_SECONDS );

	$who   = $company ?: $name;
	$lines = array();
	if ( $product_id && 'cb_product' === get_post_type( $product_id ) && 'publish' === get_post_status( $product_id ) ) {
		$lines[] = array(
			'id'     => $product_id,
			'sku'    => (string) get_post_meta( $product_id, 'cb_sku', true ),
			'title'  => get_the_title( $product_id ),
			'qty'    => $quantity,
			'unit'   => 'pieces',
			'size'   => '',
			'color'  => '',
			'custom' => '',
			'pack'   => '',
			'target' => '',
		);
	}

	$title = $lines ? sprintf( '%s — %s', $who, $lines[0]['title'] ) : sprintf( '%s — website inquiry', $who );
	$id    = wp_insert_post(
		array(
			'post_type'   => 'cb_rfq',
			'post_status' => 'publish',
			'post_title'  => $title,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'ts_inquiry_failed', __( 'The inquiry could not be saved.', 'crossbar-core' ), array( 'status' => 500 ) );
	}

	$meta = array(
		'cb_rfq_name'    => $name,
		'cb_rfq_company' => $company,
		'cb_rfq_email'   => $email,
		'cb_rfq_phone'   => $phone,
		'cb_rfq_country' => $country,
		'cb_rfq_notes'   => $message,
		'cb_rfq_lines'   => wp_json_encode( $lines ),
		'cb_rfq_status'  => 'new',
		'cb_rfq_kind'    => 'quote',
	);
	foreach ( $meta as $meta_key => $value ) {
		if ( '' !== $value ) {
			update_post_meta( $id, $meta_key, $value );
		}
	}

	return new WP_REST_Response( array( 'received' => true, 'reference' => (int) $id ), 201 );
}

/** Register versioned routes so the frontend contract can evolve safely. */
function ts_register_rest_routes() {
	register_rest_route( 'tabeer/v1', '/products', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'ts_rest_products',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'tabeer/v1', '/products/(?P<slug>[a-z0-9-]+)', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'ts_rest_single_product',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'tabeer/v1', '/settings', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'ts_rest_settings',
		'permission_callback' => '__return_true',
	) );
	register_rest_route( 'tabeer/v1', '/inquiries', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'ts_rest_create_inquiry',
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', 'ts_register_rest_routes' );
