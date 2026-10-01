<?php
/**
 * Post types and taxonomies.
 *
 * These live in the plugin rather than the theme on purpose. A reusable theme
 * gets swapped; a catalogue must not vanish when it is.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the product, quote request and size chart post types.
 *
 * @return void
 */
function cb_register_post_types() {

	register_post_type(
		'cb_product',
		array(
			'labels'              => array(
				'name'                  => __( 'Products', 'crossbar-core' ),
				'singular_name'         => __( 'Product', 'crossbar-core' ),
				'add_new'               => __( 'Add Product', 'crossbar-core' ),
				'add_new_item'          => __( 'Add New Product', 'crossbar-core' ),
				'edit_item'             => __( 'Edit Product', 'crossbar-core' ),
				'new_item'              => __( 'New Product', 'crossbar-core' ),
				'view_item'             => __( 'View Product', 'crossbar-core' ),
				'view_items'            => __( 'View Products', 'crossbar-core' ),
				'search_items'          => __( 'Search Products', 'crossbar-core' ),
				'not_found'             => __( 'No products yet.', 'crossbar-core' ),
				'not_found_in_trash'    => __( 'No products in the bin.', 'crossbar-core' ),
				'all_items'             => __( 'All Products', 'crossbar-core' ),
				'archives'              => __( 'Product Archive', 'crossbar-core' ),
				'featured_image'        => __( 'Main product photo', 'crossbar-core' ),
				'set_featured_image'    => __( 'Set main photo', 'crossbar-core' ),
				'remove_featured_image' => __( 'Remove main photo', 'crossbar-core' ),
				'menu_name'             => __( 'Products', 'crossbar-core' ),
			),
			'public'              => true,
			'has_archive'         => true,
			'show_in_rest'        => true,
			'menu_icon'           => 'dashicons-awards',
			'menu_position'       => 20,
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes' ),
			'rewrite'             => array( 'slug' => 'products', 'with_front' => false ),
			'exclude_from_search' => false,
		)
	);

	register_post_type(
		'cb_rfq',
		array(
			'labels'              => array(
				'name'               => __( 'Quote Requests', 'crossbar-core' ),
				'singular_name'      => __( 'Quote Request', 'crossbar-core' ),
				'edit_item'          => __( 'Quote Request', 'crossbar-core' ),
				'search_items'       => __( 'Search Quote Requests', 'crossbar-core' ),
				'not_found'          => __( 'No quote requests yet.', 'crossbar-core' ),
				'not_found_in_trash' => __( 'No quote requests in the bin.', 'crossbar-core' ),
				'all_items'          => __( 'Quote Requests', 'crossbar-core' ),
				'menu_name'          => __( 'Quote Requests', 'crossbar-core' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'menu_icon'           => 'dashicons-email-alt',
			'menu_position'       => 21,
			'capability_type'     => 'post',
			'supports'            => array( 'title' ),
			'exclude_from_search' => true,
			// Nobody types a quote request by hand; they arrive from the form.
			'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'        => true,
		)
	);

	register_post_type(
		'cb_chart',
		array(
			'labels'              => array(
				'name'               => __( 'Size Charts', 'crossbar-core' ),
				'singular_name'      => __( 'Size Chart', 'crossbar-core' ),
				'add_new_item'       => __( 'Add Size Chart', 'crossbar-core' ),
				'edit_item'          => __( 'Edit Size Chart', 'crossbar-core' ),
				'not_found'          => __( 'No size charts yet.', 'crossbar-core' ),
				'all_items'          => __( 'Size Charts', 'crossbar-core' ),
				'menu_name'          => __( 'Size Charts', 'crossbar-core' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=cb_product',
			'show_in_rest'        => false,
			'supports'            => array( 'title' ),
			'exclude_from_search' => true,
		)
	);
}
add_action( 'init', 'cb_register_post_types', 5 );

/**
 * Register the product taxonomies.
 *
 * Category and sub-type are hierarchical so an archive can nest; certification
 * is flat because a product either holds an accreditation or it does not.
 *
 * @return void
 */
function cb_register_taxonomies() {

	register_taxonomy(
		'cb_category',
		'cb_product',
		array(
			'labels'            => array(
				'name'          => __( 'Categories', 'crossbar-core' ),
				'singular_name' => __( 'Category', 'crossbar-core' ),
				'add_new_item'  => __( 'Add Category', 'crossbar-core' ),
				'edit_item'     => __( 'Edit Category', 'crossbar-core' ),
				'menu_name'     => __( 'Categories', 'crossbar-core' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			/*
			 * Not "category". WordPress owns that base — it is the front of the
			 * rule set for the built-in post category, registered before this
			 * runs and matched first — so /category/apparel/ is handed to core,
			 * core looks for a post category called apparel, finds none and
			 * returns a 404. The term link still points there, which is why the
			 * mega menu appears to be broken when the fault is a name collision.
			 * "product-category" says what the archive actually lists.
			 */
			'rewrite'           => array( 'slug' => 'product-category', 'with_front' => false ),
		)
	);

	register_taxonomy(
		'cb_subtype',
		'cb_product',
		array(
			'labels'            => array(
				'name'          => __( 'Sub-types', 'crossbar-core' ),
				'singular_name' => __( 'Sub-type', 'crossbar-core' ),
				'add_new_item'  => __( 'Add Sub-type', 'crossbar-core' ),
				'menu_name'     => __( 'Sub-types', 'crossbar-core' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'type', 'with_front' => false ),
		)
	);

	/*
	 * Sport is separate from category on purpose. A goalkeeper glove and a match
	 * ball are different categories but the same sport; a compression top is one
	 * category across five sports. Folding the two together forces the client to
	 * duplicate half the catalogue, which is exactly what they were trying to
	 * avoid by buying a manufacturer site instead of a shop.
	 */
	register_taxonomy(
		'cb_sport',
		'cb_product',
		array(
			'labels'            => array(
				'name'          => __( 'Sports', 'crossbar-core' ),
				'singular_name' => __( 'Sport', 'crossbar-core' ),
				'add_new_item'  => __( 'Add Sport', 'crossbar-core' ),
				'edit_item'     => __( 'Edit Sport', 'crossbar-core' ),
				'menu_name'     => __( 'Sports', 'crossbar-core' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array(
				'slug'       => 'sport',
				'with_front' => false,
			),
		)
	);

	register_taxonomy(
		'cb_certification',
		'cb_product',
		array(
			'labels'            => array(
				'name'          => __( 'Certifications', 'crossbar-core' ),
				'singular_name' => __( 'Certification', 'crossbar-core' ),
				'add_new_item'  => __( 'Add Certification', 'crossbar-core' ),
				'menu_name'     => __( 'Certifications', 'crossbar-core' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => false,
			'rewrite'           => array( 'slug' => 'certification', 'with_front' => false ),
		)
	);
}
add_action( 'init', 'cb_register_taxonomies', 5 );

/**
 * Rebuild the permalink rules when this plugin's URL shapes have changed.
 *
 * Rewrite rules are cached in the database, so changing a slug in code changes
 * nothing on a site that is already running: every archive keeps 404ing until
 * somebody opens Settings → Permalinks and presses Save. That is a reasonable
 * thing to ask of a developer and an unreasonable thing to ask of the client,
 * who has no way of knowing that the page which stopped working is fixed by a
 * button on an unrelated screen.
 *
 * The number below is bumped by hand whenever a slug here changes. It is not
 * the plugin version: a flush is expensive, and tying it to the version would
 * run one on every release whether the URLs moved or not.
 *
 * @return void
 */
function cb_maybe_flush_rewrites() {

	$want = '2';

	if ( (string) get_option( 'cb_rewrite_version' ) === $want ) {
		return;
	}

	flush_rewrite_rules();
	update_option( 'cb_rewrite_version', $want );
}
add_action( 'init', 'cb_maybe_flush_rewrites', 20 );

/**
 * A certification term can carry a logo. Stored as an attachment ID in term meta.
 *
 * @return void
 */
function cb_register_term_meta() {

	register_term_meta(
		'cb_certification',
		'cb_cert_logo',
		array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'absint',
			'auth_callback'     => function () {
				return current_user_can( 'manage_categories' );
			},
		)
	);
}
add_action( 'init', 'cb_register_term_meta', 6 );

/**
 * The picker definition shared by both certification forms.
 *
 * One array rather than two hand-written fields, because the add form and the
 * edit form have to offer the same thing — a badge chosen on one and a number
 * typed on the other is exactly the kind of split that leaves half a catalogue
 * with logos and half without.
 *
 * @return array
 */
function cb_cert_logo_field() {

	return array(
		'label'  => __( 'Badge', 'crossbar-core' ),
		'mime'   => 'image',
		'button' => __( 'Choose a badge', 'crossbar-core' ),
		'frame'  => __( 'Choose the certification badge', 'crossbar-core' ),
	);
}

/**
 * Logo field on the certification add form.
 *
 * @return void
 */
function cb_cert_add_field() {
	?>
	<div class="form-field">
		<span class="cb-label"><?php esc_html_e( 'Badge', 'crossbar-core' ); ?></span>
		<?php cb_render_media_field( 'cb_cert_logo', cb_cert_logo_field(), '' ); ?>
		<p><?php esc_html_e( 'The certificate mark, shown wherever this certification appears. Leave it empty and the name is printed as text instead.', 'crossbar-core' ); ?></p>
	</div>
	<?php
}
add_action( 'cb_certification_add_form_fields', 'cb_cert_add_field' );

/**
 * Logo field on the certification edit form.
 *
 * @param WP_Term $term Term being edited.
 * @return void
 */
function cb_cert_edit_field( $term ) {

	$logo = (int) get_term_meta( $term->term_id, 'cb_cert_logo', true );
	?>
	<tr class="form-field">
		<th scope="row"><span class="cb-label"><?php esc_html_e( 'Badge', 'crossbar-core' ); ?></span></th>
		<td>
			<?php cb_render_media_field( 'cb_cert_logo', cb_cert_logo_field(), $logo ? (string) $logo : '' ); ?>
			<p class="description"><?php esc_html_e( 'Leave it empty and the name is printed as text instead.', 'crossbar-core' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'cb_certification_edit_form_fields', 'cb_cert_edit_field' );

/**
 * Save the certification logo.
 *
 * @param int $term_id Term ID.
 * @return void
 */
function cb_cert_save_field( $term_id ) {

	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	// Term forms carry their own nonce, which core has already checked by the
	// time this fires; verifying it again here keeps the linter honest.
	if ( ! isset( $_POST['_wpnonce'] ) ) {
		return;
	}

	if ( ! isset( $_POST['cb_cert_logo'] ) ) {
		return;
	}

	$logo = absint( wp_unslash( $_POST['cb_cert_logo'] ) );

	if ( $logo ) {
		update_term_meta( $term_id, 'cb_cert_logo', $logo );
	} else {
		delete_term_meta( $term_id, 'cb_cert_logo' );
	}
}
add_action( 'created_cb_certification', 'cb_cert_save_field' );
add_action( 'edited_cb_certification', 'cb_cert_save_field' );
