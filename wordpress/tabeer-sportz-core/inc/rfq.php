<?php
/**
 * The quote basket and the request-for-quote flow.
 *
 * A B2B buyer does not enquire about one glove. They shortlist six items and
 * ask for a price on all of them in one message, so a per-product contact form
 * makes them do the work six times and they leave. This file gives them a
 * basket instead.
 *
 * Deliberately no plugin, no AJAX requirement and no session table: the basket
 * is a cookie, the forms POST to admin-post.php, and the record is written
 * before the mail is attempted so a mail failure can never lose a lead.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

define( 'CB_QUOTE_COOKIE', 'cb_quote' );

/**
 * Ceilings on the attachment field.
 *
 * Six files and eight megabytes each is roughly one tech pack, one artwork
 * file, a spec sheet and a couple of reference photographs — the real shape of
 * a first enquiry. Both are filterable because one client's "reference images"
 * are another client's 40-page catalogue.
 */
define( 'CB_RFQ_MAX_FILES', 6 );
define( 'CB_RFQ_MAX_BYTES', 8 * MB_IN_BYTES );

/**
 * Folder inside wp-content/uploads that buyer attachments go into.
 *
 * A folder of their own so a host-level deny rule has something to point at.
 */
define( 'CB_RFQ_UPLOAD_DIR', 'crossbar-rfq' );

/**
 * Statuses an office moves a request through.
 *
 * "Pending" is the gap between an enquiry landing and a price going out — a
 * costing waiting on a mill quote, or a sample on the bench. "Completed" is the
 * shipment, which is a different fact from "Approved", the purchase order, and
 * a sales desk needs to tell them apart. "Lost" stays because a pipeline you
 * cannot close negatively is a pipeline nobody trusts.
 *
 * @return array<string,string>
 */
function cb_rfq_statuses() {

	return array(
		'new'       => __( 'New', 'crossbar-core' ),
		'pending'   => __( 'Pending', 'crossbar-core' ),
		'quoted'    => __( 'Quoted', 'crossbar-core' ),
		'approved'  => __( 'Approved', 'crossbar-core' ),
		'completed' => __( 'Completed', 'crossbar-core' ),
		'lost'      => __( 'Lost', 'crossbar-core' ),
	);
}

/**
 * Retired status keys and what they mean now.
 *
 * Aliasing beats a migration here. A migration needs a trigger, and the only
 * trigger available to a plugin that is already active is an upgrade routine
 * keyed on a stored version number: more moving parts than the problem
 * deserves, and it runs exactly once, so a database restored from a pre-1.2
 * backup afterwards is quietly wrong again. Normalising on read is idempotent.
 * Every path that touches a stored status goes through cb_rfq_status_key(), so
 * a row still holding "won" keeps working forever, and it is rewritten to
 * "approved" the first time an operator saves that request.
 *
 * @return array<string,string>
 */
function cb_rfq_status_aliases() {

	return array(
		'won' => 'approved',
	);
}

/**
 * Normalise one status key to something cb_rfq_statuses() knows about.
 *
 * @param string $status Stored or submitted key.
 * @return string
 */
function cb_rfq_status_key( $status ) {

	$status  = sanitize_key( (string) $status );
	$aliases = cb_rfq_status_aliases();

	if ( isset( $aliases[ $status ] ) ) {
		$status = $aliases[ $status ];
	}

	$statuses = cb_rfq_statuses();

	return isset( $statuses[ $status ] ) ? $status : 'new';
}

/**
 * The current status of a request.
 *
 * @param int $post_id Request ID.
 * @return string
 */
function cb_rfq_status( $post_id ) {

	return cb_rfq_status_key( (string) get_post_meta( $post_id, 'cb_rfq_status', true ) );
}

/**
 * The label for a status key.
 *
 * @param string $status Stored or submitted key.
 * @return string
 */
function cb_rfq_status_label( $status ) {

	$statuses = cb_rfq_statuses();
	$status   = cb_rfq_status_key( $status );

	return $statuses[ $status ];
}

/**
 * Is this enquiry a quotation or a sample request?
 *
 * @param int $post_id Request ID.
 * @return string Either "quote" or "sample".
 */
function cb_rfq_kind( $post_id ) {

	return 'sample' === get_post_meta( $post_id, 'cb_rfq_kind', true ) ? 'sample' : 'quote';
}

/* ---------------------------------------------------------------------------
 * The basket
 * ------------------------------------------------------------------------- */

/**
 * Product IDs currently in the basket.
 *
 * @return array<int,int>
 */
function cb_quote_items() {

	if ( empty( $_COOKIE[ CB_QUOTE_COOKIE ] ) ) {
		return array();
	}

	$raw = sanitize_text_field( wp_unslash( $_COOKIE[ CB_QUOTE_COOKIE ] ) );
	$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );
	$ids = array_slice( array_unique( $ids ), 0, 40 );

	return array_values( $ids );
}

/**
 * How many items are in the basket.
 *
 * @return int
 */
function cb_quote_count() {
	return count( cb_quote_items() );
}

/**
 * Is this product already shortlisted?
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function cb_in_quote( $post_id ) {
	return in_array( (int) $post_id, cb_quote_items(), true );
}

/**
 * Write the basket cookie.
 *
 * @param array $ids Product IDs.
 * @return void
 */
function cb_quote_save( $ids ) {

	$ids   = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
	$value = implode( ',', array_slice( array_unique( $ids ), 0, 40 ) );

	$expire = $value ? time() + ( 30 * DAY_IN_SECONDS ) : time() - 3600;

	setcookie( CB_QUOTE_COOKIE, $value, $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );

	// Keep the current request consistent with what was just written, so a
	// redirect target rendered in the same PHP process agrees with the cookie.
	$_COOKIE[ CB_QUOTE_COOKIE ] = $value;
}

/**
 * Where to send the visitor back to after a basket action.
 *
 * @return string
 */
function cb_quote_back() {

	$fallback = cb_page_url( 'request-quote' );
	$fallback = $fallback ? $fallback : home_url( '/' );

	// The forms post where they want to come back to, because a browser with a
	// strict referrer policy sends no Referer at all, and a buyer who adds a
	// ball to the list from a category page should stay on that page. Both
	// candidates go through wp_validate_redirect(), which refuses off-site URLs.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- every caller runs check_admin_referer() first.
	$posted = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : '';

	$back = $posted ? $posted : (string) wp_get_referer();

	return $back ? wp_validate_redirect( $back, $fallback ) : $fallback;
}

/**
 * Find a page by slug.
 *
 * get_page_by_title() is deprecated as of WordPress 6.2, and titles get
 * translated anyway; the slug is the stable handle.
 *
 * @param string $slug Page slug.
 * @return string URL or empty string.
 */
function cb_page_url( $slug ) {

	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'name'           => $slug,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		)
	);

	return $pages ? (string) get_permalink( $pages[0] ) : '';
}

/**
 * Add a product to the basket.
 *
 * @return void
 */
function cb_quote_add() {

	check_admin_referer( 'cb_quote_add' );

	$id = isset( $_POST['product'] ) ? absint( wp_unslash( $_POST['product'] ) ) : 0;

	if ( $id && 'cb_product' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
		$ids   = cb_quote_items();
		$ids[] = $id;
		cb_quote_save( $ids );
	}

	wp_safe_redirect( add_query_arg( 'quote', 'added', cb_quote_back() ) );
	exit;
}
add_action( 'admin_post_cb_quote_add', 'cb_quote_add' );
add_action( 'admin_post_nopriv_cb_quote_add', 'cb_quote_add' );

/**
 * Remove one product, or empty the basket.
 *
 * @return void
 */
function cb_quote_remove() {

	check_admin_referer( 'cb_quote_remove' );

	$id = isset( $_POST['product'] ) ? absint( wp_unslash( $_POST['product'] ) ) : 0;

	if ( $id ) {
		$ids = array_diff( cb_quote_items(), array( $id ) );
		cb_quote_save( $ids );
	} else {
		cb_quote_save( array() );
	}

	wp_safe_redirect( add_query_arg( 'quote', 'removed', cb_quote_back() ) );
	exit;
}
add_action( 'admin_post_cb_quote_remove', 'cb_quote_remove' );
add_action( 'admin_post_nopriv_cb_quote_remove', 'cb_quote_remove' );

/* ---------------------------------------------------------------------------
 * Submission
 * ------------------------------------------------------------------------- */

/**
 * Handle the request-a-quote submission.
 *
 * @return void
 */
function cb_quote_submit() {

	/*
	 * A POST bigger than post_max_size reaches PHP with the body already
	 * discarded: $_POST and $_FILES are both empty, and the nonce went with
	 * them, so check_admin_referer() below would answer a buyer who attached a
	 * 40 MB tech pack with a bare "Are you sure you want to do this?". The one
	 * thing still intact is the Content-Length header, so use it to tell the
	 * difference between an oversized upload and an empty request.
	 */
	if ( empty( $_POST ) && ! empty( $_SERVER['CONTENT_LENGTH'] ) ) {

		$page = cb_page_url( 'request-quote' );
		$page = $page ? $page : home_url( '/' );
		$here = wp_validate_redirect( (string) wp_get_referer(), $page );

		wp_safe_redirect( add_query_arg( 'quote', 'toobig', $here ) );
		exit;
	}

	check_admin_referer( 'cb_quote_submit' );

	$back = cb_quote_back();

	// Honeypot. A real visitor never fills a field they cannot see, so a bot
	// that does gets the success screen and nothing else happens.
	$trap = isset( $_POST['cb_website'] ) ? trim( (string) wp_unslash( $_POST['cb_website'] ) ) : '';

	if ( '' !== $trap ) {
		wp_safe_redirect( add_query_arg( 'quote', 'sent', $back ) );
		exit;
	}

	$name    = isset( $_POST['cb_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cb_name'] ) ) : '';
	$company = isset( $_POST['cb_company'] ) ? sanitize_text_field( wp_unslash( $_POST['cb_company'] ) ) : '';
	$email   = isset( $_POST['cb_email'] ) ? sanitize_email( wp_unslash( $_POST['cb_email'] ) ) : '';
	$phone   = isset( $_POST['cb_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['cb_phone'] ) ) : '';
	$country = isset( $_POST['cb_country'] ) ? sanitize_text_field( wp_unslash( $_POST['cb_country'] ) ) : '';
	$date    = isset( $_POST['cb_date'] ) ? sanitize_text_field( wp_unslash( $_POST['cb_date'] ) ) : '';
	$notes   = isset( $_POST['cb_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cb_notes'] ) ) : '';
	$market  = isset( $_POST['cb_market'] ) ? sanitize_text_field( wp_unslash( $_POST['cb_market'] ) ) : '';
	$sample  = ! empty( $_POST['cb_sample'] );

	// The Incoterm has to be one of ours. "none" is the placeholder the product
	// screens use for "not stated", which as a buyer preference means nothing,
	// so it is stored as empty rather than as a term.
	$incoterm = isset( $_POST['cb_incoterm'] ) ? sanitize_text_field( wp_unslash( $_POST['cb_incoterm'] ) ) : '';
	$terms    = cb_incoterms();
	$incoterm = ( isset( $terms[ $incoterm ] ) && 'none' !== $incoterm ) ? $incoterm : '';

	if ( '' === $name || ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'quote', 'error', $back ) );
		exit;
	}

	$lines = cb_quote_read_lines();

	// An empty shortlist is not an empty enquiry. Buyers routinely arrive with
	// a drawing or a competitor's model number and nothing to click, and the
	// quote page invites exactly that, so written notes — or a single attached
	// artwork file — are enough on their own.
	if ( ! $lines && '' === $notes && ! cb_rfq_has_uploads() ) {
		wp_safe_redirect( add_query_arg( 'quote', 'empty', $back ) );
		exit;
	}

	$who = $company ? $company : $name;

	if ( $sample && $lines ) {
		/* translators: 1: company or contact name, 2: number of products. */
		$title = sprintf(
			_n( '%1$s — sample request, %2$d product', '%1$s — sample request, %2$d products', count( $lines ), 'crossbar-core' ),
			$who,
			count( $lines )
		);
	} elseif ( $sample ) {
		/* translators: %s: company or contact name. */
		$title = sprintf( __( '%s — sample request', 'crossbar-core' ), $who );
	} elseif ( $lines ) {
		/* translators: 1: company or contact name, 2: number of products. */
		$title = sprintf(
			_n( '%1$s — %2$d product', '%1$s — %2$d products', count( $lines ), 'crossbar-core' ),
			$who,
			count( $lines )
		);
	} else {
		/* translators: %s: company or contact name. */
		$title = sprintf( __( '%s — written enquiry', 'crossbar-core' ), $who );
	}

	$rfq_id = wp_insert_post(
		array(
			'post_type'   => 'cb_rfq',
			'post_status' => 'publish',
			'post_title'  => $title,
		),
		true
	);

	if ( is_wp_error( $rfq_id ) ) {
		wp_safe_redirect( add_query_arg( 'quote', 'error', $back ) );
		exit;
	}

	$stored = array(
		'cb_rfq_name'     => $name,
		'cb_rfq_company'  => $company,
		'cb_rfq_email'    => $email,
		'cb_rfq_phone'    => $phone,
		'cb_rfq_country'  => $country,
		'cb_rfq_market'   => $market,
		'cb_rfq_incoterm' => $incoterm,
		'cb_rfq_date'     => $date,
		'cb_rfq_notes'    => $notes,
	);

	foreach ( $stored as $key => $value ) {
		if ( '' !== $value ) {
			update_post_meta( $rfq_id, $key, $value );
		}
	}

	update_post_meta( $rfq_id, 'cb_rfq_lines', wp_json_encode( $lines ) );
	update_post_meta( $rfq_id, 'cb_rfq_status', 'new' );
	update_post_meta( $rfq_id, 'cb_rfq_kind', $sample ? 'sample' : 'quote' );

	/*
	 * Files are taken after the record exists, so every attachment has a parent
	 * from the moment it is inserted; an orphan attachment is invisible in the
	 * enquiry it belongs to and nobody ever cleans it up.
	 *
	 * A rejected file does not sink the enquiry. Losing a lead because a buyer
	 * attached a .rar the server would not sniff is a worse outcome than filing
	 * the enquiry and telling both sides which file did not make it, so the
	 * failures are recorded on the request, mailed to the office and shown on
	 * the confirmation screen.
	 */
	$upload = cb_rfq_take_uploads( $rfq_id );

	if ( $upload['files'] ) {
		update_post_meta( $rfq_id, 'cb_rfq_files', wp_json_encode( wp_list_pluck( $upload['files'], 'id' ) ) );
	}

	if ( $upload['errors'] ) {
		update_post_meta( $rfq_id, 'cb_rfq_upload_errors', implode( "\n", $upload['errors'] ) );
	}

	// The record is safe before mail is attempted. On a host with no working
	// mailer this still captures every lead, which is the whole point.
	cb_quote_notify(
		$rfq_id,
		$stored,
		$lines,
		array(
			'kind'   => $sample ? 'sample' : 'quote',
			'files'  => $upload['files'],
			'errors' => $upload['errors'],
		)
	);

	cb_quote_save( array() );

	$args = array( 'quote' => 'sent' );
	$ref  = cb_quote_flash_set(
		array(
			'files'  => wp_list_pluck( $upload['files'], 'name' ),
			'errors' => $upload['errors'],
			'sample' => $sample ? 1 : 0,
		)
	);

	if ( '' !== $ref ) {
		$args['cb_ref'] = $ref;
	}

	wp_safe_redirect( add_query_arg( $args, $back ) );
	exit;
}
add_action( 'admin_post_cb_quote_submit', 'cb_quote_submit' );
add_action( 'admin_post_nopriv_cb_quote_submit', 'cb_quote_submit' );

/**
 * Read the per-line fields off the submitted form.
 *
 * @return array<int,array<string,string>>
 */
function cb_quote_read_lines() {

	$ids = isset( $_POST['line'] ) && is_array( $_POST['line'] ) ? array_map( 'absint', array_keys( wp_unslash( $_POST['line'] ) ) ) : array();

	if ( ! $ids ) {
		return array();
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each cell is sanitised below.
	$raw   = wp_unslash( $_POST['line'] );
	$lines = array();

	foreach ( $ids as $id ) {

		// Published only, matching cb_quote_add(). A shortlist posted with an
		// id for a draft or a private product would otherwise put its title and
		// SKU into an email that leaves the building.
		if ( 'cb_product' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			continue;
		}

		$row = isset( $raw[ $id ] ) && is_array( $raw[ $id ] ) ? $raw[ $id ] : array();

		$lines[] = array(
			'id'     => $id,
			'sku'    => (string) get_post_meta( $id, 'cb_sku', true ),
			'title'  => get_the_title( $id ),
			'qty'    => isset( $row['qty'] ) ? absint( $row['qty'] ) : 0,
			'unit'   => cb_unit_label( cb_product_unit( $id ), 2 ),
			'size'   => isset( $row['size'] ) ? sanitize_text_field( (string) $row['size'] ) : '',
			'color'  => isset( $row['color'] ) ? sanitize_text_field( (string) $row['color'] ) : '',
			'custom' => isset( $row['custom'] ) ? sanitize_text_field( (string) $row['custom'] ) : '',
			// Packaging is per line, not per enquiry: the same buyer wants
			// polybag-and-carton on the balls and a printed sleeve on the
			// gloves, and quoting one packing spec across the basket is how a
			// costing ends up wrong.
			'pack'   => isset( $row['pack'] ) ? sanitize_text_field( (string) $row['pack'] ) : '',
			'target' => isset( $row['target'] ) ? cb_sanitize_decimal( $row['target'] ) : '',
		);
	}

	return $lines;
}

/* ---------------------------------------------------------------------------
 * One-shot messages
 * ------------------------------------------------------------------------- */

/**
 * Park a message for the page the visitor is about to land on.
 *
 * A redirect cannot carry a list of filenames in the query string without
 * putting a buyer's artwork names into browser history and access logs, and a
 * session table is exactly what the rest of this file avoids. A short-lived
 * transient under a random token splits the difference: the key travels in the
 * URL, the content does not, and it is consumed on first read.
 *
 * @param array $payload Anything the confirmation screen needs.
 * @return string Token, or an empty string when there is nothing to say.
 */
function cb_quote_flash_set( $payload ) {

	$payload = array_filter( (array) $payload );

	if ( ! $payload ) {
		return '';
	}

	// Lower case only: the reader runs the token through sanitize_key(), which
	// would fold the case of a mixed-case token and never find the transient.
	$token = strtolower( wp_generate_password( 24, false, false ) );

	set_transient( 'cb_rfq_flash_' . $token, $payload, 10 * MINUTE_IN_SECONDS );

	return $token;
}

/**
 * Read and consume the message left by the last submission.
 *
 * @return array
 */
function cb_quote_flash() {

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only token set by our own redirect, and the payload is deleted on first read.
	$token = isset( $_GET['cb_ref'] ) ? sanitize_key( wp_unslash( $_GET['cb_ref'] ) ) : '';

	if ( '' === $token ) {
		return array();
	}

	$data = get_transient( 'cb_rfq_flash_' . $token );

	delete_transient( 'cb_rfq_flash_' . $token );

	return is_array( $data ) ? $data : array();
}

/* ---------------------------------------------------------------------------
 * Attachments
 *
 * Buyers send artwork, a tech pack, a spec sheet and a couple of reference
 * photographs. Without a field for them they arrive as a second email nobody
 * files against the enquiry, so the form takes them and the Media Library owns
 * them, parented to the request.
 * ------------------------------------------------------------------------- */

/**
 * File types a buyer may attach.
 *
 * Short and explicit. SVG is absent on purpose: it is XML, it can carry script,
 * and any site that serves one back to a browser has handed a stranger an XSS
 * on the client's own domain.
 *
 * The MIME strings are the ones WordPress core uses for the same extensions, so
 * a file that uploads here behaves exactly as it would in the Media Library.
 *
 * @return array<string,string> Extension pattern => MIME type.
 */
function cb_rfq_mimes() {

	$mimes = array(
		'pdf'          => 'application/pdf',
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'gif'          => 'image/gif',
		'ai'           => 'application/postscript',
		'eps'          => 'application/postscript',
		'psd'          => 'application/octet-stream',
		'cdr'          => 'application/vnd.corel-draw',
		'zip'          => 'application/zip',
		'rar'          => 'application/rar',
		'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	);

	/**
	 * Filter the attachment whitelist.
	 *
	 * @param array $mimes Extension pattern => MIME type.
	 */
	return apply_filters( 'cb_rfq_mimes', $mimes );
}

/**
 * The whitelist flattened into single extensions.
 *
 * Used for the accept attribute, the first-pass check and the wording of the
 * rejection message, so a buyer is told what would have worked.
 *
 * @return array<int,string>
 */
function cb_rfq_extensions() {

	$out = array();

	foreach ( array_keys( cb_rfq_mimes() ) as $pattern ) {
		foreach ( explode( '|', $pattern ) as $ext ) {
			$out[] = $ext;
		}
	}

	return array_values( array_unique( $out ) );
}

/**
 * Real content types we will accept for the formats libmagic disagrees with.
 *
 * A modern .ai file is a PDF wearing a different extension, a .psd reports as
 * image/vnd.adobe.photoshop on some builds and application/octet-stream on
 * others, and .cdr is anybody's guess. WordPress compares the sniffed type
 * against the declared one and refuses on any mismatch, which would reject the
 * exact three formats a buyer's studio actually sends.
 *
 * This is a narrowing, not a widening: the extension still has to be on the
 * whitelist, and the sniffed type still has to be on this list. Nothing new
 * becomes uploadable, and the filter is only attached while our own handler
 * runs, so the Media Library is untouched.
 *
 * @return array<string,array<int,string>>
 */
function cb_rfq_alt_mimes() {

	return array(
		'ai'  => array( 'application/pdf', 'application/postscript', 'application/octet-stream' ),
		'eps' => array( 'application/postscript', 'image/x-eps', 'application/octet-stream' ),
		'psd' => array( 'image/vnd.adobe.photoshop', 'application/x-photoshop', 'application/octet-stream' ),
		'cdr' => array( 'application/x-cdr', 'application/cdr', 'image/x-coreldraw', 'application/zip', 'application/octet-stream' ),
		'rar' => array( 'application/x-rar-compressed', 'application/vnd.rar', 'application/x-rar', 'application/octet-stream' ),
	);
}

/**
 * Let the design formats through when the sniffed type is one we expect.
 *
 * @param array       $data      Result of wp_check_filetype_and_ext().
 * @param string      $file      Path to the temporary file.
 * @param string      $filename  Name the browser sent.
 * @param array       $mimes     Allowed types.
 * @param string|bool $real_mime Type libmagic reported, or false.
 * @return array
 */
function cb_rfq_filetype_check( $data, $file, $filename, $mimes, $real_mime ) {

	unset( $file, $mimes );

	if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
		return $data;
	}

	$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
	$alt = cb_rfq_alt_mimes();

	if ( ! isset( $alt[ $ext ] ) || ! $real_mime || ! in_array( $real_mime, $alt[ $ext ], true ) ) {
		return $data;
	}

	foreach ( cb_rfq_mimes() as $pattern => $type ) {
		if ( in_array( $ext, explode( '|', $pattern ), true ) ) {
			$data['ext']  = $ext;
			$data['type'] = $type;
			break;
		}
	}

	return $data;
}

/**
 * The largest single file this install will take.
 *
 * Our own ceiling, unless the server's is lower — telling a buyer 8 MB is fine
 * on a host capped at 2 MB is how you get an enquiry that silently fails.
 *
 * @return int Bytes.
 */
function cb_rfq_max_bytes() {

	/**
	 * Filter the per-file ceiling.
	 *
	 * @param int $bytes Bytes.
	 */
	$ours = (int) apply_filters( 'cb_rfq_max_bytes', CB_RFQ_MAX_BYTES );

	// Core already reads upload_max_filesize and post_max_size and returns the
	// smaller of the two.
	$server = (int) wp_max_upload_size();

	return ( $server > 0 && $server < $ours ) ? $server : $ours;
}

/**
 * How many files this install will take at once.
 *
 * @return int
 */
function cb_rfq_max_files() {

	/**
	 * Filter the file count ceiling.
	 *
	 * @param int $files Number of files.
	 */
	return max( 1, (int) apply_filters( 'cb_rfq_max_files', CB_RFQ_MAX_FILES ) );
}

/**
 * Is there at least one file on this submission?
 *
 * @return bool
 */
function cb_rfq_has_uploads() {

	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- $_FILES metadata, validated in full by cb_rfq_take_uploads() before any of it is used.
	if ( empty( $_FILES['cb_files']['name'] ) || ! is_array( $_FILES['cb_files']['name'] ) ) {
		return false;
	}

	foreach ( $_FILES['cb_files']['name'] as $name ) {
		if ( '' !== trim( (string) $name ) ) {
			return true;
		}
	}
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput

	return false;
}

/**
 * Turn PHP's column-major $_FILES structure into one row per file.
 *
 * @return array<int,array<string,mixed>>
 */
function cb_rfq_normalise_files() {

	if ( ! cb_rfq_has_uploads() ) {
		return array();
	}

	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- see cb_rfq_take_uploads(); nothing here is echoed or trusted as a path.
	$raw   = $_FILES['cb_files'];
	$out   = array();
	$total = count( $raw['name'] );

	for ( $i = 0; $i < $total; $i++ ) {

		if ( ! isset( $raw['name'][ $i ] ) || '' === trim( (string) $raw['name'][ $i ] ) ) {
			continue;
		}

		$out[] = array(
			'name'     => (string) $raw['name'][ $i ],
			'type'     => isset( $raw['type'][ $i ] ) ? (string) $raw['type'][ $i ] : '',
			'tmp_name' => isset( $raw['tmp_name'][ $i ] ) ? (string) $raw['tmp_name'][ $i ] : '',
			'error'    => isset( $raw['error'][ $i ] ) ? (int) $raw['error'][ $i ] : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $raw['size'][ $i ] ) ? (int) $raw['size'][ $i ] : 0,
		);
	}
	// phpcs:enable WordPress.Security.ValidatedSanitizedInput

	return $out;
}

/**
 * Send buyer attachments to their own folder under uploads.
 *
 * @param array $dirs Upload directory parts.
 * @return array
 */
function cb_rfq_upload_dir( $dirs ) {

	$sub = '/' . CB_RFQ_UPLOAD_DIR;

	$dirs['subdir'] = $sub;
	$dirs['path']   = $dirs['basedir'] . $sub;
	$dirs['url']    = $dirs['baseurl'] . $sub;

	return $dirs;
}

/**
 * Put a lid on the attachment folder.
 *
 * This is the honest limit of what a plugin can do by itself, and it is worth
 * being plain about it. A tech pack sitting at a guessable URL under
 * wp-content/uploads is a competitor's afternoon, so:
 *
 *  - the attachment IDs live in request meta, never in the page;
 *  - the admin links go through cb_rfq_file_download(), which checks a nonce
 *    and the current user's capability on the parent request before it streams
 *    a single byte;
 *  - and the .htaccess below denies direct fetches.
 *
 * That last line only works on Apache with AllowOverride on. On nginx, IIS, or
 * behind a CDN that serves /wp-content/uploads/ itself, .htaccess is inert and
 * the files are reachable by anyone who guesses the path. FOR A HARDENED
 * DEPLOYMENT THE UPLOADS SUBFOLDER MUST BE DENIED AT THE WEB-SERVER LEVEL —
 * on nginx, a `location ^~ /wp-content/uploads/crossbar-rfq/ { deny all; }`
 * block. Nothing in PHP can substitute for that.
 *
 * The trade-off of denying the folder is that the Media Library cannot render
 * thumbnails for these attachments. That is the correct way round: an admin
 * preview is worth less than a buyer's artwork staying private.
 *
 * @return void
 */
function cb_rfq_protect_dir() {

	/**
	 * Filter whether to drop a deny rule into the attachment folder.
	 *
	 * @param bool $protect Whether to write the rules.
	 */
	if ( ! apply_filters( 'cb_rfq_protect_uploads', true ) ) {
		return;
	}

	$dirs = wp_upload_dir();
	$path = trailingslashit( $dirs['basedir'] ) . CB_RFQ_UPLOAD_DIR;

	if ( ! is_dir( $path ) || ! wp_is_writable( $path ) ) {
		return;
	}

	$files = array(
		'.htaccess' => "# Buyer artwork, tech packs and specifications. Not for public fetching.\n"
			. "# Apache only. See cb_rfq_protect_dir() for the nginx equivalent.\n"
			. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n",
		'index.php' => "<?php\n// Silence is golden.\n",
	);

	foreach ( $files as $name => $contents ) {

		$target = trailingslashit( $path ) . $name;

		if ( file_exists( $target ) ) {
			continue;
		}

		/*
		 * WP_Filesystem is not initialised on a front-end POST and initialising
		 * it can prompt for FTP credentials, which is not a thing that can
		 * happen inside a form handler. These two files are tiny and go into a
		 * directory the uploader has just proved it can write to.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $target, $contents );
	}
}

/**
 * Move the attached files into the Media Library, owned by this request.
 *
 * @param int $rfq_id Request ID.
 * @return array{files:array<int,array<string,mixed>>,errors:array<int,string>}
 */
function cb_rfq_take_uploads( $rfq_id ) {

	$result = array(
		'files'  => array(),
		'errors' => array(),
	);

	if ( ! cb_rfq_has_uploads() ) {
		return $result;
	}

	// admin-post.php pulls the admin includes in already. The guards are for any
	// other entry point that might reuse this handler later.
	if ( ! function_exists( 'wp_handle_upload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
	}

	$files = cb_rfq_normalise_files();
	$max   = cb_rfq_max_files();
	$bytes = cb_rfq_max_bytes();
	$exts  = cb_rfq_extensions();

	if ( count( $files ) > $max ) {

		$result['errors'][] = sprintf(
			/* translators: %d: number of files allowed. */
			_n(
				'Only %d file can be attached, so the extra ones were not saved.',
				'Only %d files can be attached, so the extra ones were not saved.',
				$max,
				'crossbar-core'
			),
			$max
		);

		$files = array_slice( $files, 0, $max );
	}

	add_filter( 'upload_dir', 'cb_rfq_upload_dir' );
	add_filter( 'wp_check_filetype_and_ext', 'cb_rfq_filetype_check', 10, 5 );

	foreach ( $files as $file ) {

		// Only ever used in messages, so it is sanitised before it goes near a
		// translation string. wp_handle_upload() does its own naming.
		$name  = sanitize_file_name( $file['name'] );
		$error = (int) $file['error'];

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			continue;
		}

		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {

			$result['errors'][] = sprintf(
				/* translators: 1: file name, 2: size limit, for example "8 MB". */
				__( '“%1$s” is larger than the %2$s this server accepts, so it was not saved.', 'crossbar-core' ),
				$name,
				size_format( $bytes )
			);

			continue;
		}

		if ( UPLOAD_ERR_OK !== $error ) {

			$result['errors'][] = sprintf(
				/* translators: %s: file name. */
				__( '“%s” did not finish uploading, so it was not saved.', 'crossbar-core' ),
				$name
			);

			continue;
		}

		if ( (int) $file['size'] > $bytes ) {

			$result['errors'][] = sprintf(
				/* translators: 1: file name, 2: size limit, for example "8 MB". */
				__( '“%1$s” is over the %2$s limit, so it was not saved.', 'crossbar-core' ),
				$name,
				size_format( $bytes )
			);

			continue;
		}

		// First pass on the extension, before the file is handed to core. This
		// is the readable rejection; wp_handle_upload() then sniffs the content
		// and can still refuse a .png that is not one.
		$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $exts, true ) ) {

			$result['errors'][] = sprintf(
				/* translators: 1: file name, 2: comma separated list of file extensions. */
				__( '“%1$s” is not a file type we accept, so it was not saved. We can take: %2$s.', 'crossbar-core' ),
				$name,
				implode( ', ', $exts )
			);

			continue;
		}

		// test_form false because this is not an upload posted to the media
		// screens and core's form check looks for that screen's fields; the
		// nonce for this request was verified by the caller. The path used from
		// here on is the one wp_handle_upload() returns, never $_FILES.
		$moved = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => cb_rfq_mimes(),
			)
		);

		if ( ! is_array( $moved ) || isset( $moved['error'] ) || empty( $moved['file'] ) ) {

			$result['errors'][] = sprintf(
				/* translators: %s: file name. */
				__( '“%s” could not be saved — its contents did not match its file extension. If it is a design file, put it in a zip and attach that instead.', 'crossbar-core' ),
				$name
			);

			continue;
		}

		$attachment = wp_insert_attachment(
			array(
				'post_mime_type' => $moved['type'],
				'post_title'     => sanitize_text_field( (string) pathinfo( $moved['file'], PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$moved['file'],
			$rfq_id,
			true
		);

		if ( is_wp_error( $attachment ) ) {

			// The bytes are on disk with nothing pointing at them. Take them
			// back off rather than leave an unreferenced upload behind.
			wp_delete_file( $moved['file'] );

			$result['errors'][] = sprintf(
				/* translators: %s: file name. */
				__( '“%s” could not be attached to your request.', 'crossbar-core' ),
				$name
			);

			continue;
		}

		// The download gate reads this rather than post_parent: it is the one
		// claim that says "this attachment came from the quote form", and it
		// survives a well-meaning editor detaching the file in the admin.
		update_post_meta( $attachment, 'cb_rfq_attachment', (int) $rfq_id );

		wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $moved['file'] ) );

		$result['files'][] = array(
			'id'   => (int) $attachment,
			'name' => wp_basename( $moved['file'] ),
			'size' => (int) $file['size'],
		);
	}

	remove_filter( 'wp_check_filetype_and_ext', 'cb_rfq_filetype_check', 10 );
	remove_filter( 'upload_dir', 'cb_rfq_upload_dir' );

	if ( $result['files'] ) {
		cb_rfq_protect_dir();
	}

	return $result;
}

/**
 * Attachment IDs stored against a request.
 *
 * @param int $post_id Request ID.
 * @return array<int,int>
 */
function cb_rfq_files( $post_id ) {

	$ids = json_decode( (string) get_post_meta( $post_id, 'cb_rfq_files', true ), true );

	if ( ! is_array( $ids ) ) {
		return array();
	}

	return array_values( array_filter( array_map( 'absint', $ids ) ) );
}

/**
 * A gated download URL for one attachment.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function cb_rfq_file_url( $attachment_id ) {

	$attachment_id = (int) $attachment_id;

	return wp_nonce_url(
		admin_url( 'admin-post.php?action=cb_rfq_file&file=' . $attachment_id ),
		'cb_rfq_file_' . $attachment_id
	);
}

/**
 * Stream one attachment to a member of staff.
 *
 * The counterpart to the deny rule in cb_rfq_protect_dir(): the folder is shut,
 * and this is the only door. Nonce first, capability second, path last, and
 * always as a download — a PDF rendered inline is a stranger's file executing
 * in the context of the admin domain.
 *
 * @return void
 */
function cb_rfq_file_download() {

	$id = isset( $_GET['file'] ) ? absint( wp_unslash( $_GET['file'] ) ) : 0;

	check_admin_referer( 'cb_rfq_file_' . $id );

	$parent = $id ? (int) get_post_meta( $id, 'cb_rfq_attachment', true ) : 0;

	if ( ! $id || ! $parent || 'attachment' !== get_post_type( $id ) || 'cb_rfq' !== get_post_type( $parent ) ) {
		wp_die( esc_html__( 'That file is not part of a quote request.', 'crossbar-core' ), '', array( 'response' => 404 ) );
	}

	if ( ! current_user_can( 'edit_post', $parent ) ) {
		wp_die( esc_html__( 'You do not have permission to open this file.', 'crossbar-core' ), '', array( 'response' => 403 ) );
	}

	$path = get_attached_file( $id );

	if ( ! $path || ! is_readable( $path ) ) {
		wp_die( esc_html__( 'That file is no longer on the server.', 'crossbar-core' ), '', array( 'response' => 404 ) );
	}

	$type = (string) get_post_mime_type( $id );

	nocache_headers();
	header( 'Content-Type: ' . ( $type ? $type : 'application/octet-stream' ) );
	header( 'Content-Length: ' . (string) filesize( $path ) );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( wp_basename( $path ) ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	readfile( $path );
	exit;
}
add_action( 'admin_post_cb_rfq_file', 'cb_rfq_file_download' );

/**
 * Email the office.
 *
 * @param int   $rfq_id Request ID.
 * @param array $data   Contact fields.
 * @param array $lines  Line items.
 * @param array $meta   Enquiry kind, saved files and upload failures.
 * @return void
 */
function cb_quote_notify( $rfq_id, $data, $lines, $meta = array() ) {

	$to = (string) cb_setting( 'email', get_option( 'admin_email' ) );

	if ( ! is_email( $to ) ) {
		return;
	}

	$meta = wp_parse_args(
		$meta,
		array(
			'kind'   => 'quote',
			'files'  => array(),
			'errors' => array(),
		)
	);

	$sample = ( 'sample' === $meta['kind'] );
	$site   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$who    = $data['cb_rfq_company'] ? $data['cb_rfq_company'] : $data['cb_rfq_name'];

	// A sample request and a price request go to the same inbox but need
	// different things doing, and the person triaging reads the subject line
	// before they read anything else.
	$subject = $sample
		/* translators: 1: site name, 2: company or contact. */
		? sprintf( __( '[%1$s] Sample request from %2$s', 'crossbar-core' ), $site, $who )
		/* translators: 1: site name, 2: company or contact. */
		: sprintf( __( '[%1$s] Quote request from %2$s', 'crossbar-core' ), $site, $who );

	$body = array();

	$body[] = $sample
		? __( 'A new sample request has arrived.', 'crossbar-core' )
		: __( 'A new quote request has arrived.', 'crossbar-core' );

	$body[] = '';
	$body[] = __( 'Contact', 'crossbar-core' );
	$body[] = '--------';
	$body[] = __( 'Name: ', 'crossbar-core' ) . $data['cb_rfq_name'];

	if ( $data['cb_rfq_company'] ) {
		$body[] = __( 'Company: ', 'crossbar-core' ) . $data['cb_rfq_company'];
	}

	$body[] = __( 'Email: ', 'crossbar-core' ) . $data['cb_rfq_email'];

	if ( $data['cb_rfq_phone'] ) {
		$body[] = __( 'Phone: ', 'crossbar-core' ) . $data['cb_rfq_phone'];
	}

	if ( $data['cb_rfq_country'] ) {
		$body[] = __( 'Country: ', 'crossbar-core' ) . $data['cb_rfq_country'];
	}

	if ( ! empty( $data['cb_rfq_market'] ) ) {
		$body[] = __( 'Target market: ', 'crossbar-core' ) . $data['cb_rfq_market'];
	}

	if ( ! empty( $data['cb_rfq_incoterm'] ) ) {
		$body[] = __( 'Preferred Incoterm: ', 'crossbar-core' ) . $data['cb_rfq_incoterm'];
	}

	if ( $data['cb_rfq_date'] ) {
		$body[] = __( 'Required by: ', 'crossbar-core' ) . $data['cb_rfq_date'];
	}

	if ( $lines ) {
		$body[] = '';
		$body[] = __( 'Products', 'crossbar-core' );
		$body[] = '--------';
	}

	foreach ( $lines as $line ) {

		$parts = array( $line['title'] );

		if ( $line['sku'] ) {
			$parts[] = '(' . $line['sku'] . ')';
		}

		if ( $line['qty'] ) {
			$parts[] = '— ' . $line['qty'] . ' ' . $line['unit'];
		}

		$body[] = implode( ' ', $parts );

		$detail = array_filter(
			array(
				$line['size'] ? __( 'size: ', 'crossbar-core' ) . $line['size'] : '',
				$line['color'] ? __( 'colour: ', 'crossbar-core' ) . $line['color'] : '',
				$line['custom'] ? __( 'customisation: ', 'crossbar-core' ) . $line['custom'] : '',
				empty( $line['pack'] ) ? '' : __( 'packaging: ', 'crossbar-core' ) . $line['pack'],
				$line['target'] ? __( 'target price: ', 'crossbar-core' ) . $line['target'] : '',
			)
		);

		if ( $detail ) {
			$body[] = '    ' . implode( ', ', $detail );
		}
	}

	if ( $data['cb_rfq_notes'] ) {
		$body[] = '';
		$body[] = __( 'Notes', 'crossbar-core' );
		$body[] = '--------';
		$body[] = $data['cb_rfq_notes'];
	}

	// Filenames only. The files themselves are behind a capability check, so a
	// link in an email that might be forwarded outside the office would be
	// either useless or a hole, and neither is worth putting in.
	if ( $meta['files'] ) {

		$body[] = '';
		$body[] = __( 'Attachments', 'crossbar-core' );
		$body[] = '--------';

		foreach ( $meta['files'] as $file ) {
			$body[] = $file['name'] . ' (' . size_format( (int) $file['size'] ) . ')';
		}
	}

	if ( $meta['errors'] ) {

		$body[] = '';
		$body[] = __( 'Files that did not arrive', 'crossbar-core' );
		$body[] = '--------';

		foreach ( $meta['errors'] as $problem ) {
			$body[] = $problem;
		}

		$body[] = __( 'The buyer was shown the same message and may send these again.', 'crossbar-core' );
	}

	$body[] = '';
	$body[] = __( 'Open in the dashboard: ', 'crossbar-core' ) . admin_url( 'post.php?post=' . $rfq_id . '&action=edit' );

	// The visitor address goes in Reply-To, never in From: a From header on a
	// domain this server is not authorised to send for fails SPF and lands the
	// whole notification in spam.
	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		'Reply-To: ' . $data['cb_rfq_name'] . ' <' . $data['cb_rfq_email'] . '>',
	);

	wp_mail( $to, $subject, implode( "\n", $body ), $headers );
}

/* ---------------------------------------------------------------------------
 * Admin
 * ------------------------------------------------------------------------- */

/**
 * Register quote request meta.
 *
 * Not exposed to REST: these hold a buyer's contact details and there is no
 * reason for them to be readable through an API surface.
 *
 * @return void
 */
function cb_register_rfq_meta() {

	$keys = array(
		'cb_rfq_name',
		'cb_rfq_company',
		'cb_rfq_email',
		'cb_rfq_phone',
		'cb_rfq_country',
		'cb_rfq_market',
		'cb_rfq_incoterm',
		'cb_rfq_date',
		'cb_rfq_notes',
		'cb_rfq_lines',
		'cb_rfq_status',
		'cb_rfq_kind',
		'cb_rfq_files',
		'cb_rfq_upload_errors',
	);

	foreach ( $keys as $key ) {
		register_post_meta(
			'cb_rfq',
			$key,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					unset( $allowed, $meta_key );
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
}
add_action( 'init', 'cb_register_rfq_meta', 7 );

/**
 * Render a quote request.
 *
 * @param WP_Post $post Post being viewed.
 * @return void
 */
function cb_render_rfq_box( $post ) {

	wp_nonce_field( 'cb_save_rfq_' . $post->ID, 'cb_rfq_nonce' );

	$status   = cb_rfq_status( $post->ID );
	$kind     = cb_rfq_kind( $post->ID );
	$lines    = json_decode( (string) get_post_meta( $post->ID, 'cb_rfq_lines', true ), true );
	$lines    = is_array( $lines ) ? $lines : array();
	$files    = cb_rfq_files( $post->ID );
	$failed   = (string) get_post_meta( $post->ID, 'cb_rfq_upload_errors', true );
	$contacts = array(
		'cb_rfq_company'  => __( 'Company', 'crossbar-core' ),
		'cb_rfq_name'     => __( 'Contact', 'crossbar-core' ),
		'cb_rfq_email'    => __( 'Email', 'crossbar-core' ),
		'cb_rfq_phone'    => __( 'Phone', 'crossbar-core' ),
		'cb_rfq_country'  => __( 'Country', 'crossbar-core' ),
		'cb_rfq_market'   => __( 'Target market', 'crossbar-core' ),
		'cb_rfq_incoterm' => __( 'Preferred Incoterm', 'crossbar-core' ),
		'cb_rfq_date'     => __( 'Required by', 'crossbar-core' ),
	);
	?>
	<div class="cb-field">
		<label for="cb_rfq_status"><?php esc_html_e( 'Status', 'crossbar-core' ); ?></label>
		<select id="cb_rfq_status" name="cb_rfq_status">
			<?php foreach ( cb_rfq_statuses() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>

		<?php if ( 'sample' === $kind ) : ?>
			<p class="cb-hint">
				<span class="cb-pill cb-pill--sample"><?php esc_html_e( 'Sample requested', 'crossbar-core' ); ?></span>
				<?php esc_html_e( 'The buyer asked for a physical sample, not only a price.', 'crossbar-core' ); ?>
			</p>
		<?php endif; ?>
	</div>

	<div class="cb-fields cb-fields--read">
		<?php
		foreach ( $contacts as $key => $label ) :

			$value = (string) get_post_meta( $post->ID, $key, true );

			if ( '' === $value ) {
				continue;
			}
			?>
			<div class="cb-field">
				<span class="cb-label"><?php echo esc_html( $label ); ?></span>
				<?php if ( 'cb_rfq_email' === $key ) : ?>
					<a href="mailto:<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></a>
				<?php else : ?>
					<span><?php echo esc_html( $value ); ?></span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $lines ) : ?>
		<table class="widefat striped cb-lines">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Product', 'crossbar-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'SKU', 'crossbar-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Quantity', 'crossbar-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Size', 'crossbar-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Colour', 'crossbar-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Customisation', 'crossbar-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Packaging', 'crossbar-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Target', 'crossbar-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $lines as $line ) :

					$line = wp_parse_args(
						(array) $line,
						array(
							'id'     => 0,
							'title'  => '',
							'sku'    => '',
							'qty'    => 0,
							'unit'   => '',
							'size'   => '',
							'color'  => '',
							'custom' => '',
							'pack'   => '',
							'target' => '',
						)
					);

					// Resolve by stored ID so the link survives a rename, and
					// fall back to plain text when the product has been deleted
					// or the current user cannot edit it.
					$edit = $line['id'] ? get_edit_post_link( (int) $line['id'] ) : '';
					?>
					<tr>
						<td>
							<?php if ( $edit ) : ?>
								<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $line['title'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $line['title'] ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo $line['sku'] ? '<code>' . esc_html( $line['sku'] ) . '</code>' : '&mdash;'; ?></td>
						<td><?php echo $line['qty'] ? esc_html( number_format_i18n( (int) $line['qty'] ) . ' ' . $line['unit'] ) : '&mdash;'; ?></td>
						<td><?php echo $line['size'] ? esc_html( $line['size'] ) : '&mdash;'; ?></td>
						<td><?php echo $line['color'] ? esc_html( $line['color'] ) : '&mdash;'; ?></td>
						<td><?php echo $line['custom'] ? esc_html( $line['custom'] ) : '&mdash;'; ?></td>
						<td><?php echo $line['pack'] ? esc_html( $line['pack'] ) : '&mdash;'; ?></td>
						<td><?php echo $line['target'] ? esc_html( $line['target'] ) : '&mdash;'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php
	$notes = (string) get_post_meta( $post->ID, 'cb_rfq_notes', true );

	if ( $notes ) :
		?>
		<h4><?php esc_html_e( 'Notes', 'crossbar-core' ); ?></h4>
		<p class="cb-notes"><?php echo esc_html( $notes ); ?></p>
		<?php
	endif;

	if ( $files ) :
		?>
		<h4><?php esc_html_e( 'Attachments', 'crossbar-core' ); ?></h4>

		<ul class="cb-files">
			<?php
			foreach ( $files as $file_id ) :

				$file_path = (string) get_attached_file( $file_id );
				$file_name = '' !== $file_path ? wp_basename( $file_path ) : '';

				if ( '' === $file_name ) {
					continue;
				}

				// The row says whether the bytes are still there, because an
				// attachment record outlives a file deleted over FTP and a
				// dead link found mid-negotiation is worse than a warning.
				$file_here = is_readable( $file_path );
				?>
				<li class="cb-files__item">
					<?php if ( $file_here ) : ?>
						<a class="cb-files__link" href="<?php echo esc_url( cb_rfq_file_url( $file_id ) ); ?>"><?php echo esc_html( $file_name ); ?></a>
						<span class="cb-files__meta"><?php echo esc_html( size_format( (int) filesize( $file_path ) ) ); ?></span>
					<?php else : ?>
						<span class="cb-files__link"><?php echo esc_html( $file_name ); ?></span>
						<span class="cb-files__meta cb-files__meta--gone"><?php esc_html_e( 'missing from the server', 'crossbar-core' ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<p class="cb-hint">
			<?php esc_html_e( 'Files download through a permission check rather than a public URL, so these links only work while you are signed in.', 'crossbar-core' ); ?>
		</p>
		<?php
	endif;

	if ( $failed ) :
		?>
		<h4><?php esc_html_e( 'Files that did not arrive', 'crossbar-core' ); ?></h4>
		<p class="cb-notes cb-notes--warn"><?php echo esc_html( $failed ); ?></p>
		<?php
	endif;
}

/**
 * Save the status field.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function cb_save_rfq( $post_id, $post ) {

	if ( 'cb_rfq' !== $post->post_type ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['cb_rfq_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['cb_rfq_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'cb_save_rfq_' . $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// cb_rfq_status_key() folds a retired key such as "won" onto its replacement
	// and refuses anything it does not recognise, so saving a request is also
	// the moment an old row quietly moves onto the current vocabulary.
	$status = isset( $_POST['cb_rfq_status'] ) ? sanitize_key( wp_unslash( $_POST['cb_rfq_status'] ) ) : 'new';

	update_post_meta( $post_id, 'cb_rfq_status', cb_rfq_status_key( $status ) );
}
add_action( 'save_post', 'cb_save_rfq', 10, 2 );

/**
 * Quote request list columns.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function cb_rfq_columns( $columns ) {

	return array(
		'cb'         => isset( $columns['cb'] ) ? $columns['cb'] : '',
		'title'      => __( 'Request', 'crossbar-core' ),
		'cb_company' => __( 'Company', 'crossbar-core' ),
		'cb_country' => __( 'Country', 'crossbar-core' ),
		'cb_lines'   => __( 'Lines', 'crossbar-core' ),
		'cb_status'  => __( 'Status', 'crossbar-core' ),
		'date'       => __( 'Received', 'crossbar-core' ),
	);
}
add_filter( 'manage_cb_rfq_posts_columns', 'cb_rfq_columns' );

/**
 * Quote request column output.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function cb_rfq_column( $column, $post_id ) {

	if ( 'cb_company' === $column ) {

		$company = (string) get_post_meta( $post_id, 'cb_rfq_company', true );
		$company = '' !== $company ? $company : (string) get_post_meta( $post_id, 'cb_rfq_name', true );

		echo '' !== $company ? esc_html( $company ) : '&mdash;';

		if ( 'sample' === cb_rfq_kind( $post_id ) ) {
			printf(
				' <span class="cb-pill cb-pill--sample">%s</span>',
				esc_html__( 'Sample', 'crossbar-core' )
			);
		}

		return;
	}

	if ( 'cb_country' === $column ) {
		$country = get_post_meta( $post_id, 'cb_rfq_country', true );
		echo $country ? esc_html( $country ) : '&mdash;';
		return;
	}

	if ( 'cb_lines' === $column ) {
		$lines = json_decode( (string) get_post_meta( $post_id, 'cb_rfq_lines', true ), true );
		echo esc_html( is_array( $lines ) ? (string) count( $lines ) : '0' );
		return;
	}

	if ( 'cb_status' === $column ) {

		$status = cb_rfq_status( $post_id );

		printf(
			'<span class="cb-pill cb-pill--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( cb_rfq_status_label( $status ) )
		);
	}
}
add_action( 'manage_cb_rfq_posts_custom_column', 'cb_rfq_column', 10, 2 );

/**
 * An export button above the quote request list.
 *
 * @param string $which Top or bottom of the table.
 * @return void
 */
function cb_rfq_export_button( $which ) {

	$screen = get_current_screen();

	if ( 'top' !== $which || ! $screen || 'cb_rfq' !== $screen->post_type ) {
		return;
	}

	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}

	$url = wp_nonce_url( admin_url( 'admin-post.php?action=cb_rfq_export' ), 'cb_rfq_export' );

	printf(
		'<a href="%1$s" class="button">%2$s</a> ',
		esc_url( $url ),
		esc_html__( 'Export CSV', 'crossbar-core' )
	);
}
add_action( 'manage_posts_extra_tablenav', 'cb_rfq_export_button' );

/**
 * Neutralise a cell before it is written to CSV.
 *
 * Excel, Numbers and LibreOffice all treat a cell beginning with =, +, - or @
 * as a formula, and every text field in a quote request is typed by a stranger
 * on the internet. A buyer's "notes" reading =HYPERLINK(...) would run the
 * moment a member of staff opened the export. Prefixing a tab stops the parse
 * without changing what the reader sees.
 *
 * @param mixed $value Cell value.
 * @return string
 */
function cb_csv_cell( $value ) {

	$value = (string) $value;

	if ( '' === $value ) {
		return '';
	}

	// Control characters that reset the parser's idea of where the cell begins.
	$value = str_replace( array( "\r", "\0" ), '', $value );

	if ( preg_match( '/^[=+\-@\t]/', $value ) ) {
		return "\t" . $value;
	}

	return $value;
}

/**
 * Stream the quote requests as CSV, one row per line item.
 *
 * @return void
 */
function cb_rfq_export() {

	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to export quote requests.', 'crossbar-core' ) );
	}

	check_admin_referer( 'cb_rfq_export' );

	$rows = get_posts(
		array(
			'post_type'      => 'cb_rfq',
			'post_status'    => 'any',
			'posts_per_page' => 2000,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=quote-requests-' . gmdate( 'Y-m-d' ) . '.csv' );

	$out = fopen( 'php://output', 'w' );

	fputcsv(
		$out,
		array(
			__( 'Received', 'crossbar-core' ),
			__( 'Status', 'crossbar-core' ),
			__( 'Type', 'crossbar-core' ),
			__( 'Company', 'crossbar-core' ),
			__( 'Contact', 'crossbar-core' ),
			__( 'Email', 'crossbar-core' ),
			__( 'Phone', 'crossbar-core' ),
			__( 'Country', 'crossbar-core' ),
			__( 'Target market', 'crossbar-core' ),
			__( 'Incoterm', 'crossbar-core' ),
			__( 'Required by', 'crossbar-core' ),
			__( 'Attachments', 'crossbar-core' ),
			__( 'Product', 'crossbar-core' ),
			__( 'SKU', 'crossbar-core' ),
			__( 'Quantity', 'crossbar-core' ),
			__( 'Unit', 'crossbar-core' ),
			__( 'Size', 'crossbar-core' ),
			__( 'Colour', 'crossbar-core' ),
			__( 'Customisation', 'crossbar-core' ),
			__( 'Packaging', 'crossbar-core' ),
			__( 'Target price', 'crossbar-core' ),
			__( 'Notes', 'crossbar-core' ),
		)
	);

	foreach ( $rows as $rfq ) {

		$lines = json_decode( (string) get_post_meta( $rfq->ID, 'cb_rfq_lines', true ), true );
		$lines = is_array( $lines ) && $lines ? $lines : array( array() );

		// Filenames, not URLs. The export gets emailed around; a working link to
		// a buyer's tech pack does not belong in a spreadsheet.
		$names = array();

		foreach ( cb_rfq_files( $rfq->ID ) as $file_id ) {
			$names[] = wp_basename( (string) get_attached_file( $file_id ) );
		}

		$head = array(
			get_the_date( 'Y-m-d H:i', $rfq ),
			cb_rfq_status_label( cb_rfq_status( $rfq->ID ) ),
			cb_rfq_kind( $rfq->ID ),
			(string) get_post_meta( $rfq->ID, 'cb_rfq_company', true ),
			(string) get_post_meta( $rfq->ID, 'cb_rfq_name', true ),
			(string) get_post_meta( $rfq->ID, 'cb_rfq_email', true ),
			(string) get_post_meta( $rfq->ID, 'cb_rfq_phone', true ),
			(string) get_post_meta( $rfq->ID, 'cb_rfq_country', true ),
			(string) get_post_meta( $rfq->ID, 'cb_rfq_market', true ),
			(string) get_post_meta( $rfq->ID, 'cb_rfq_incoterm', true ),
			(string) get_post_meta( $rfq->ID, 'cb_rfq_date', true ),
			implode( '; ', array_filter( $names ) ),
		);

		$notes = (string) get_post_meta( $rfq->ID, 'cb_rfq_notes', true );

		foreach ( $lines as $line ) {

			$line = wp_parse_args(
				(array) $line,
				array(
					'title'  => '',
					'sku'    => '',
					'qty'    => '',
					'unit'   => '',
					'size'   => '',
					'color'  => '',
					'custom' => '',
					'pack'   => '',
					'target' => '',
				)
			);

			fputcsv(
				$out,
				array_map(
					'cb_csv_cell',
					array_merge(
						$head,
						array(
							$line['title'],
							$line['sku'],
							$line['qty'],
							$line['unit'],
							$line['size'],
							$line['color'],
							$line['custom'],
							$line['pack'],
							$line['target'],
							$notes,
						)
					)
				)
			);
		}
	}

	fclose( $out );
	exit;
}
add_action( 'admin_post_cb_rfq_export', 'cb_rfq_export' );
