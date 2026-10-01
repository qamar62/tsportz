<?php
/**
 * Admin editing screens for products, size charts and quote requests.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * The meta box groups, in the order they appear.
 *
 * The icon is a Dashicons name, not a codepoint. WordPress already loads that
 * font on every admin screen, so a name costs nothing and survives the day the
 * icon set is renumbered — which a hard-coded \f323 would not.
 *
 * @return array<string,array<string,string>>
 */
function cb_field_groups() {

	return array(
		'identity'  => array(
			'title'   => __( 'Product identity', 'crossbar-core' ),
			'context' => 'normal',
			'icon'    => 'tag',
			'intro'   => __( 'Who makes it and what it is called. The SKU is what the CSV importer matches on.', 'crossbar-core' ),
		),
		'spec'      => array(
			'title'   => __( 'Specification', 'crossbar-core' ),
			'context' => 'normal',
			'icon'    => 'clipboard',
			'intro'   => __( 'Everything a buyer compares before they ask for a price.', 'crossbar-core' ),
		),
		'price'     => array(
			'title'   => __( 'Orders and production', 'crossbar-core' ),
			'context' => 'normal',
			'icon'    => 'money-alt',
			'intro'   => __( 'Minimum orders, production lead time, monthly capacity and sample availability. Public pricing is disabled.', 'crossbar-core' ),
		),
		'logistics' => array(
			'title'   => __( 'Packing and files', 'crossbar-core' ),
			'context' => 'normal',
			'icon'    => 'archive',
			'intro'   => __( 'Carton data lets a buyer calculate freight themselves, which shortens the conversation.', 'crossbar-core' ),
		),
	);
}

/**
 * A meta box title with its icon.
 *
 * WordPress prints a meta box title unescaped, which is what lets core put a
 * help link in one, so the escaping is done here per part rather than left to
 * the caller.
 *
 * @param string $title Group title.
 * @param string $icon  Dashicons name without the "dashicons-" prefix.
 * @return string
 */
function cb_box_title( $title, $icon = 'admin-generic' ) {

	return sprintf(
		'<span class="cb-box__icon dashicons dashicons-%1$s" aria-hidden="true"></span><span class="cb-box__name">%2$s</span>',
		esc_attr( $icon ),
		esc_html( $title )
	);
}

/**
 * Mark this plugin's meta boxes so the stylesheet can reach them.
 *
 * A postbox carries no class of its own, and the header that has to show which
 * section is open is printed by core, outside anything this plugin renders.
 * This filter is the only supported way in.
 *
 * @param string $screen Post type.
 * @param string $id     Meta box ID.
 * @return void
 */
function cb_mark_box( $screen, $id ) {

	add_filter(
		'postbox_classes_' . $screen . '_' . $id,
		function ( $classes ) {
			$classes[] = 'cb-box';
			return $classes;
		}
	);
}

/**
 * Add the product meta boxes.
 *
 * @return void
 */
function cb_add_meta_boxes() {

	foreach ( cb_field_groups() as $key => $group ) {

		add_meta_box(
			'cb_box_' . $key,
			cb_box_title( $group['title'], isset( $group['icon'] ) ? $group['icon'] : 'admin-generic' ),
			'cb_render_box',
			'cb_product',
			$group['context'],
			'default',
			array( 'group' => $key )
		);

		cb_mark_box( 'cb_product', 'cb_box_' . $key );
	}

	add_meta_box(
		'cb_box_chart',
		cb_box_title( __( 'Chart', 'crossbar-core' ), 'editor-table' ),
		'cb_render_chart_box',
		'cb_chart',
		'normal',
		'high'
	);

	cb_mark_box( 'cb_chart', 'cb_box_chart' );

	add_meta_box(
		'cb_box_rfq',
		cb_box_title( __( 'Quote request', 'crossbar-core' ), 'email-alt' ),
		'cb_render_rfq_box',
		'cb_rfq',
		'normal',
		'high'
	);

	cb_mark_box( 'cb_rfq', 'cb_box_rfq' );
}
add_action( 'add_meta_boxes', 'cb_add_meta_boxes' );

/**
 * Render one field group.
 *
 * @param WP_Post $post Post being edited.
 * @param array   $box  Box arguments.
 * @return void
 */
function cb_render_box( $post, $box ) {

	$group  = isset( $box['args']['group'] ) ? $box['args']['group'] : '';
	$groups = cb_field_groups();

	wp_nonce_field( 'cb_save_product_' . $post->ID, 'cb_product_nonce' );

	if ( isset( $groups[ $group ]['intro'] ) ) {
		echo '<p class="cb-intro">' . esc_html( $groups[ $group ]['intro'] ) . '</p>';
	}

	echo '<div class="cb-fields">';

	foreach ( cb_fields() as $key => $args ) {

		if ( $args['group'] !== $group ) {
			continue;
		}

		cb_render_field( $post->ID, $key, $args );
	}

	echo '</div>';

	// TABEER SPORTZ uses quote-only pricing, so no public price preview is shown.
}

/**
 * How much room a field should be given.
 *
 * A text box is as wide as the screen unless something stops it, and an HS code
 * stretched across a 1600 pixel monitor reads as an invitation to type an essay
 * into it. Width is a property of what the field holds, not of the window, so
 * it is decided from the type here and can be overridden per field where the
 * type alone gets it wrong.
 *
 * full - takes the whole row: anything multi-line, a list of checkboxes, a
 *        picker full of thumbnails.
 * sm   - a number, a date, a yes/no: short answers that should look short.
 * md   - everything else.
 *
 * @param array $args Field definition.
 * @return string One of full, sm, md.
 */
function cb_field_width( $args ) {

	if ( ! empty( $args['width'] ) ) {
		return $args['width'];
	}

	if ( in_array( $args['type'], array( 'textarea', 'json', 'multicheck', 'media' ), true ) ) {
		return 'full';
	}

	if ( in_array( $args['type'], array( 'number', 'date', 'checkbox' ), true ) ) {
		return 'sm';
	}

	return 'md';
}

/**
 * Render a single field.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param array  $args    Field definition.
 * @return void
 */
function cb_render_field( $post_id, $key, $args ) {

	$value = get_post_meta( $post_id, $key, true );
	$width = cb_field_width( $args );

	// --wide is kept as the name for a full-row field because the size chart
	// screen writes it by hand and there is nothing to gain from renaming it.
	$class = 'cb-field cb-field--' . $width . ( 'full' === $width ? ' cb-field--wide' : '' );

	echo '<div class="' . esc_attr( $class ) . '">';

	/*
	 * A media picker's only real input is hidden, and a <label for> pointing at
	 * a hidden input is a label that does nothing when clicked — so that one
	 * field gets a heading instead, and the button below carries its own name.
	 */
	if ( 'media' === $args['type'] ) {
		echo '<span class="cb-label">' . esc_html( $args['label'] ) . '</span>';
	} else {
		echo '<label for="' . esc_attr( $key ) . '">' . esc_html( $args['label'] ) . '</label>';
	}

	switch ( $args['type'] ) {

		case 'textarea':
			printf(
				'<textarea id="%1$s" name="%1$s" rows="3">%2$s</textarea>',
				esc_attr( $key ),
				esc_textarea( (string) $value )
			);
			break;

		case 'json':
			printf(
				'<textarea id="%1$s" name="%1$s" rows="4" class="cb-rows">%2$s</textarea>',
				esc_attr( $key ),
				esc_textarea( cb_rows_to_text( cb_rows( $post_id, $key ) ) )
			);
			break;

		case 'number':
			printf(
				'<input type="number" id="%1$s" name="%1$s" value="%2$s" step="%3$s" min="0" />',
				esc_attr( $key ),
				esc_attr( (string) $value ),
				esc_attr( isset( $args['step'] ) ? $args['step'] : '1' )
			);
			break;

		case 'url':
			printf(
				'<input type="url" id="%1$s" name="%1$s" value="%2$s" inputmode="url" placeholder="https://" />',
				esc_attr( $key ),
				esc_attr( (string) $value )
			);
			break;

		case 'date':
			// A native date picker, with the pattern as a fallback for a browser
			// that has none and would otherwise accept whatever the visitor's
			// locale prefers.
			printf(
				'<input type="date" id="%1$s" name="%1$s" value="%2$s" pattern="\d{4}-\d{2}-\d{2}" placeholder="YYYY-MM-DD" />',
				esc_attr( $key ),
				esc_attr( (string) $value )
			);
			break;

		case 'checkbox':
			printf(
				'<span class="cb-check"><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s /> <span>%3$s</span></span>',
				esc_attr( $key ),
				checked( $value, '1', false ),
				esc_html__( 'Yes', 'crossbar-core' )
			);
			break;

		case 'select':
			$options = is_callable( $args['options'] ) ? call_user_func( $args['options'] ) : array();
			$current = ( '' === $value && isset( $args['default'] ) ) ? $args['default'] : $value;

			echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';

			foreach ( $options as $option_key => $label ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $option_key ),
					selected( $current, $option_key, false ),
					esc_html( $label )
				);
			}

			echo '</select>';
			break;

		case 'multicheck':
			$options = is_callable( $args['options'] ) ? call_user_func( $args['options'] ) : array();
			$current = array_filter( explode( ',', (string) $value ) );

			echo '<span class="cb-multi">';

			foreach ( $options as $option_key => $label ) {
				printf(
					'<span class="cb-check"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> <span>%4$s</span></span>',
					esc_attr( $key ),
					esc_attr( $option_key ),
					checked( in_array( $option_key, $current, true ), true, false ),
					esc_html( $label )
				);
			}

			echo '</span>';
			break;

		case 'media':
			cb_render_media_field( $key, $args, (string) $value );
			break;

		case 'post':
			$posts = get_posts(
				array(
					'post_type'      => $args['post_type'],
					'posts_per_page' => 100,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);

			echo '<select id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">';
			echo '<option value="0">' . esc_html__( 'None', 'crossbar-core' ) . '</option>';

			foreach ( $posts as $item ) {
				printf(
					'<option value="%1$d" %2$s>%3$s</option>',
					(int) $item->ID,
					selected( (int) $value, $item->ID, false ),
					esc_html( get_the_title( $item ) )
				);
			}

			echo '</select>';
			break;

		default:
			printf(
				'<input type="text" id="%1$s" name="%1$s" value="%2$s" />',
				esc_attr( $key ),
				esc_attr( (string) $value )
			);
	}

	if ( ! empty( $args['hint'] ) ) {
		echo '<span class="cb-hint">' . esc_html( $args['hint'] ) . '</span>';
	}

	echo '</div>';
}

/**
 * A Media Library picker.
 *
 * The stored value is still a comma-separated list of attachment IDs, exactly
 * what the text box it replaces held, so nothing already entered has to be
 * migrated and the CSV importer keeps writing the same column. The hidden input
 * is the only thing that submits; the thumbnails above it are a view of it.
 *
 * Everything renders server-side as well as from JavaScript. If the media modal
 * fails to load for any reason the editor still sees what is attached and can
 * still remove items — a picker that goes blank without its script would look
 * like the images had been lost.
 *
 * @param string $key   Meta key, used as the input name.
 * @param array  $args  Field definition.
 * @param string $value Stored value, a comma-separated list of IDs.
 * @return void
 */
function cb_render_media_field( $key, $args, $value ) {

	$multiple = ! empty( $args['multiple'] );
	$mime     = isset( $args['mime'] ) ? $args['mime'] : '';
	$ids      = array_filter( array_map( 'absint', explode( ',', $value ) ) );

	printf(
		'<div class="cb-media%1$s" data-field="%2$s" data-multiple="%3$d" data-mime="%4$s" data-frame="%5$s" data-button="%6$s">',
		$mime ? ' cb-media--images' : ' cb-media--files',
		esc_attr( $key ),
		$multiple ? 1 : 0,
		esc_attr( $mime ),
		esc_attr( isset( $args['frame'] ) ? $args['frame'] : $args['label'] ),
		esc_attr( isset( $args['button'] ) ? $args['button'] : __( 'Choose a file', 'crossbar-core' ) )
	);

	/*
	 * A list rather than a row of divs, and one that keeps announcing its length
	 * as items come and go: "gallery, 4 items" is the one thing a screen reader
	 * user cannot get from the thumbnails.
	 */
	echo '<ul class="cb-media__list" role="list">';

	foreach ( $ids as $id ) {
		cb_render_media_item( $id );
	}

	echo '</ul>';

	// Printed always, hidden by the script the moment there is something in the
	// list, so an empty field explains itself instead of showing a bare button.
	echo '<p class="cb-media__empty"' . ( $ids ? ' hidden' : '' ) . '>';
	echo esc_html(
		$mime
			? __( 'No images yet.', 'crossbar-core' )
			: __( 'No files yet.', 'crossbar-core' )
	);
	echo '</p>';

	echo '<p class="cb-media__actions">';

	printf(
		'<button type="button" class="button cb-media__add">%s</button>',
		esc_html( isset( $args['button'] ) ? $args['button'] : __( 'Choose a file', 'crossbar-core' ) )
	);

	printf(
		'<button type="button" class="button-link cb-media__clear"%s>%s</button>',
		$ids ? '' : ' hidden',
		esc_html( $multiple ? __( 'Remove all', 'crossbar-core' ) : __( 'Remove', 'crossbar-core' ) )
	);

	echo '</p>';

	printf(
		'<input type="hidden" id="%1$s" name="%1$s" value="%2$s" class="cb-media__value" />',
		esc_attr( $key ),
		esc_attr( implode( ',', $ids ) )
	);

	echo '</div>';
}

/**
 * One thumbnail in a media picker.
 *
 * Kept as its own function because assets/admin.js builds this same shape when
 * an item is picked, and the two have to agree — a mismatch would show up only
 * after a save, as a row of tiles that suddenly restyled themselves.
 *
 * @param int $id Attachment ID.
 * @return void
 */
function cb_render_media_item( $id ) {

	$id = absint( $id );

	if ( ! $id || ! get_post( $id ) ) {
		return;
	}

	$is_image = wp_attachment_is_image( $id );
	$name     = get_the_title( $id );

	printf(
		'<li class="cb-media__item" data-id="%d" draggable="true" tabindex="0">',
		$id
	);

	if ( $is_image ) {
		echo wp_get_attachment_image( $id, 'thumbnail', false, array( 'alt' => '' ) );
	} else {
		echo '<span class="cb-media__doc" aria-hidden="true">';
		echo esc_html( strtoupper( pathinfo( (string) get_attached_file( $id ), PATHINFO_EXTENSION ) ) );
		echo '</span>';
	}

	printf( '<span class="cb-media__name">%s</span>', esc_html( $name ) );

	printf(
		'<button type="button" class="cb-media__remove" aria-label="%s"><span aria-hidden="true">&times;</span></button>',
		/* translators: %s: attachment title. */
		esc_attr( sprintf( __( 'Remove %s', 'crossbar-core' ), $name ) )
	);

	echo '</li>';
}

/**
 * Show what the price block will actually say.
 *
 * Editors get the incoterm wrong far less often when they can see the sentence
 * their choices produce.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function cb_render_price_preview( $post_id ) {

	$preview = cb_price_html( $post_id, false );

	echo '<p class="cb-preview"><strong>' . esc_html__( 'Front-end preview:', 'crossbar-core' ) . '</strong> ';
	echo '<span class="cb-preview__out">' . esc_html( $preview ? wp_strip_all_tags( $preview ) : __( 'Price on request', 'crossbar-core' ) ) . '</span>';
	echo '</p>';
	echo '<p class="cb-hint">' . esc_html__( 'Save the product to refresh this line. A sitewide "quote only" switch in the Customizer overrides every product at once.', 'crossbar-core' ) . '</p>';
}

/**
 * Save the product fields.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function cb_save_product( $post_id, $post ) {

	if ( 'cb_product' !== $post->post_type ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['cb_product_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['cb_product_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'cb_save_product_' . $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( cb_fields() as $key => $args ) {

		// A meta box that is not on screen must not wipe the field it owns.
		// Checkboxes are the exception: absent genuinely means unchecked, so
		// they are only cleared when their own group was submitted.
		if ( ! isset( $_POST[ $key ] ) ) {

			if ( in_array( $args['type'], array( 'checkbox', 'multicheck' ), true ) ) {
				delete_post_meta( $post_id, $key );
			}

			continue;
		}

		$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on the next line.

		$clean = cb_sanitize_field( $key, $raw );

		// Only a truly empty value clears the field. Treating "0" as empty as
		// well would make a deliberate zero — a free sample, a zero MOQ —
		// impossible to save, and every reader already treats a stored 0 as
		// falsy anyway, so nothing is gained by deleting it.
		if ( '' === $clean ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $clean );
		}
	}

	cb_promote_gallery_image( $post_id );
}
add_action( 'save_post', 'cb_save_product', 10, 2 );

/**
 * Make the first gallery image the featured image, if there is not one already.
 *
 * Somebody who uploads three photographs of a product has said what it looks
 * like, and expects the first one to be the picture of it. Nothing in
 * WordPress makes that happen: the featured image is a separate field in a
 * separate box, and a product without one falls back to generated artwork on
 * every card, every archive and every admin list column while the detail page
 * shows the real photographs — which reads as a bug rather than as an unset
 * field.
 *
 * Doing it here rather than in the theme fixes it once for every reader,
 * including the ones this plugin does not own. It only ever fills a gap: a
 * featured image that has been chosen deliberately is never replaced, and
 * clearing the gallery does not clear the thumbnail it once set, because a
 * save should not delete a picture the client can see.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function cb_promote_gallery_image( $post_id ) {

	if ( has_post_thumbnail( $post_id ) ) {
		return;
	}

	$raw = (string) get_post_meta( $post_id, 'cb_gallery', true );

	foreach ( explode( ',', $raw ) as $id ) {

		$id = absint( $id );

		if ( $id && wp_attachment_is_image( $id ) ) {
			set_post_thumbnail( $post_id, $id );
			return;
		}
	}
}

/* ---------------------------------------------------------------------------
 * Size charts
 * ------------------------------------------------------------------------- */

/**
 * Register size chart meta.
 *
 * @return void
 */
function cb_register_chart_meta() {

	$auth = function ( $allowed, $meta_key, $post_id ) {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', $post_id );
	};

	register_post_meta(
		'cb_chart',
		'cb_chart_head',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		'cb_chart',
		'cb_chart_body',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			// Plain comma-separated rows, printed cell by cell through
			// esc_html(). Registering wp_kses_post here would let markup in
			// through the meta API that the edit screen strips, so the two
			// routes would disagree about what a chart may contain.
			'sanitize_callback' => 'sanitize_textarea_field',
			'auth_callback'     => $auth,
		)
	);
}
add_action( 'init', 'cb_register_chart_meta', 7 );

/**
 * Render the size chart editor.
 *
 * @param WP_Post $post Post being edited.
 * @return void
 */
function cb_render_chart_box( $post ) {

	wp_nonce_field( 'cb_save_chart_' . $post->ID, 'cb_chart_nonce' );

	$head = get_post_meta( $post->ID, 'cb_chart_head', true );
	$body = get_post_meta( $post->ID, 'cb_chart_body', true );
	?>
	<p class="cb-intro"><?php esc_html_e( 'One chart can serve fifty products. Attach it from the Specification box on each product.', 'crossbar-core' ); ?></p>

	<div class="cb-fields">
		<div class="cb-field cb-field--wide">
			<label for="cb_chart_head"><?php esc_html_e( 'Column headings', 'crossbar-core' ); ?></label>
			<input type="text" id="cb_chart_head" name="cb_chart_head" value="<?php echo esc_attr( (string) $head ); ?>" />
			<span class="cb-hint"><?php esc_html_e( 'Comma separated. Example: Size, Chest (cm), Length (cm)', 'crossbar-core' ); ?></span>
		</div>

		<div class="cb-field cb-field--wide">
			<label for="cb_chart_body"><?php esc_html_e( 'Rows', 'crossbar-core' ); ?></label>
			<textarea id="cb_chart_body" name="cb_chart_body" rows="8" class="cb-rows"><?php echo esc_textarea( (string) $body ); ?></textarea>
			<span class="cb-hint"><?php esc_html_e( 'One row per line, cells separated by a comma. Example: M, 100, 71', 'crossbar-core' ); ?></span>
		</div>
	</div>
	<?php
}

/**
 * Save the size chart.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function cb_save_chart( $post_id, $post ) {

	if ( 'cb_chart' !== $post->post_type ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['cb_chart_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['cb_chart_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'cb_save_chart_' . $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$head = isset( $_POST['cb_chart_head'] ) ? sanitize_text_field( wp_unslash( $_POST['cb_chart_head'] ) ) : '';
	$body = isset( $_POST['cb_chart_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cb_chart_body'] ) ) : '';

	update_post_meta( $post_id, 'cb_chart_head', $head );
	update_post_meta( $post_id, 'cb_chart_body', $body );
}
add_action( 'save_post', 'cb_save_chart', 10, 2 );

/* ---------------------------------------------------------------------------
 * Admin columns
 * ------------------------------------------------------------------------- */

/**
 * Product list columns.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function cb_product_columns( $columns ) {

	$out = array();

	foreach ( $columns as $key => $label ) {

		$out[ $key ] = $label;

		if ( 'title' === $key ) {
			$out['cb_sku']   = __( 'SKU', 'crossbar-core' );
			$out['cb_price'] = __( 'Price', 'crossbar-core' );
			$out['cb_moq']   = __( 'MOQ', 'crossbar-core' );
		}
	}

	return $out;
}
add_filter( 'manage_cb_product_posts_columns', 'cb_product_columns' );

/**
 * Product column output.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function cb_product_column( $column, $post_id ) {

	if ( 'cb_sku' === $column ) {
		$sku = get_post_meta( $post_id, 'cb_sku', true );
		echo $sku ? '<code>' . esc_html( $sku ) . '</code>' : '&mdash;';
		return;
	}

	if ( 'cb_price' === $column ) {
		$mode = get_post_meta( $post_id, 'cb_price_mode', true );

		if ( 'quote' === $mode || '' === $mode ) {
			echo '<span class="cb-pill cb-pill--quote">' . esc_html__( 'On request', 'crossbar-core' ) . '</span>';
			return;
		}

		echo esc_html( wp_strip_all_tags( cb_price_html( $post_id, false ) ) );
		return;
	}

	if ( 'cb_moq' === $column ) {
		$moq = (int) get_post_meta( $post_id, 'cb_moq', true );

		if ( ! $moq ) {
			echo '&mdash;';
			return;
		}

		echo esc_html( number_format_i18n( $moq ) . ' ' . cb_unit_label( cb_product_unit( $post_id ), $moq ) );
	}
}
add_action( 'manage_cb_product_posts_custom_column', 'cb_product_column', 10, 2 );

/**
 * Make SKU and price sortable.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function cb_product_sortable( $columns ) {

	$columns['cb_sku']   = 'cb_sku';
	$columns['cb_price'] = 'cb_price';

	return $columns;
}
add_filter( 'manage_edit-cb_product_sortable_columns', 'cb_product_sortable' );

/**
 * Apply the sort.
 *
 * A plain meta_key would inner-join and quietly hide every product that has no
 * price yet, which is the last thing you want in an admin list. The OR pair
 * keeps those rows and lets the named clause drive the ordering.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function cb_product_sort( $query ) {

	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( 'cb_product' !== $query->get( 'post_type' ) ) {
		return;
	}

	$orderby = $query->get( 'orderby' );

	if ( ! in_array( $orderby, array( 'cb_sku', 'cb_price' ), true ) ) {
		return;
	}

	$type = ( 'cb_price' === $orderby ) ? 'DECIMAL(12,2)' : 'CHAR';

	$query->set(
		'meta_query',
		array(
			'relation' => 'OR',
			'has'      => array(
				'key'     => $orderby,
				'compare' => 'EXISTS',
				'type'    => $type,
			),
			'missing'  => array(
				'key'     => $orderby,
				'compare' => 'NOT EXISTS',
			),
		)
	);

	$query->set( 'orderby', 'has' );
}
add_action( 'pre_get_posts', 'cb_product_sort' );
