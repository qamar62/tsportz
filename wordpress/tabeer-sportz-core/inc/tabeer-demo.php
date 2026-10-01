<?php
/**
 * Explicit demo catalogue seeder for local and staging environments.
 *
 * Nothing is inserted on plugin activation. An administrator must choose to
 * seed the catalogue from Products > Demo Catalogue.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

/** Product fixtures shared by the admin screen and seeder. */
function ts_demo_catalogue() {
	return array(
		array( 'american-football-uniform', 'American Football Uniform', 'Team Uniforms', 'American Football', 'Built for the intensity of every down, with a team look that stands out.', 'Performance polyester with reinforced stretch zones', 'Cut and sew with sublimated panels', 'XS-5XL', 30 ),
		array( 'soccer-uniform', 'Soccer Uniform', 'Team Uniforms', 'Soccer', 'A sharp, unified look made for ninety minutes and beyond.', 'Lightweight moisture-management polyester', 'Full sublimation or cut and sew', 'Youth 20-28 | Adult XS-5XL', 30 ),
		array( 'tracksuit', 'Tracksuit', 'Training & Apparel', 'Multi-sport', 'From warm-up to travel, a coordinated essential for every team.', 'Polyester tricot or bonded interlock', 'Panelled jacket and trouser set', 'Youth 20-28 | Adult XS-5XL', 30 ),
		array( 'baseball-uniform', 'Baseball Uniform', 'Team Uniforms', 'Baseball', 'Classic diamond style with a confident, contemporary finish.', 'Performance polyester', 'Button-front or pullover construction', 'Youth and adult custom sizing', 30 ),
		array( 'basketball-uniform', 'Basketball Uniform', 'Team Uniforms', 'Basketball', 'Light, expressive kits for fast breaks and big moments.', 'Breathable polyester mesh', 'Reversible or single-layer construction', 'Youth 20-28 | Adult XS-5XL', 30 ),
		array( 'football-soccer-ball', 'Football / Soccer Ball', 'Equipment', 'Soccer', 'The center of every session, match and memorable goal.', 'PU or TPU cover with reinforced bladder', 'Machine stitched, hand stitched or thermo bonded', 'Size 3, 4 and 5', 100 ),
		array( 'goalkeeper-gloves', 'Goalkeeper Gloves', 'Equipment', 'Soccer', 'Confident grip and a striking look between the posts.', 'Latex palm with breathable textile body', 'Negative, roll finger or flat cut', 'Sizes 4-11', 50 ),
		array( 'ice-hockey-uniform', 'Ice Hockey Uniform', 'Team Uniforms', 'Ice Hockey', 'A powerful on-ice identity for teams that play all in.', 'Durable polyester mesh and interlock', 'Sublimated hockey jersey and socks', 'Youth and adult hockey sizing', 30 ),
		array( 'polo-shirts', 'Polo Shirts', 'Training & Apparel', 'Multi-sport', 'A polished off-field staple for coaches, staff and players.', 'Polyester pique, cotton pique or blended fabric', 'Cut and sew polo with rib collar', 'XS-5XL', 50 ),
		array( 'windbreaker-jackets', 'Windbreaker Jackets', 'Training & Apparel', 'Multi-sport', 'An easy outer layer for the sidelines, the commute and the elements.', 'Lightweight coated polyester', 'Mesh-lined or unlined shell', 'XS-5XL', 30 ),
		array( 'sports-shorts', 'Sports Shorts', 'Training & Apparel', 'Multi-sport', 'Move freely through training days and game days.', 'Quick-dry performance polyester', 'Elastic waist athletic short', 'Youth and adult custom sizing', 50 ),
		array( 'bjj-gears', 'BJJ Gears', 'Combat Sports', 'Brazilian Jiu-Jitsu', 'Purposeful gear for the discipline and energy of the mat.', 'Pearl weave cotton and ripstop', 'Reinforced gi jacket and trousers', 'A0-A5', 30 ),
		array( 'polo-uniform', 'Polo Uniform', 'Team Uniforms', 'Polo', 'A refined team presence inspired by the pace of polo.', 'Breathable performance knit', 'Technical polo shirt and white trousers', 'XS-5XL', 30 ),
	);
}

/** Insert or update the safe demo fixtures. */
function ts_seed_demo_catalogue() {
	$created = 0;
	$updated = 0;

	foreach ( ts_demo_catalogue() as $index => $item ) {
		list( $slug, $title, $category, $sport, $excerpt, $material, $construction, $sizes, $moq ) = $item;
		$existing = get_page_by_path( $slug, OBJECT, 'cb_product' );
		$postarr   = array(
			'post_type'    => 'cb_product',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
			'post_content' => sprintf(
				'<p>%1$s</p><p>Designed for clubs, teams and private-label buyers looking for a coordinated product that can be adapted to their identity.</p>',
				esc_html( $excerpt )
			),
			'menu_order'   => $index + 1,
		);
		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
			$post_id       = wp_update_post( $postarr, true );
			++$updated;
		} else {
			$post_id = wp_insert_post( $postarr, true );
			++$created;
		}
		if ( is_wp_error( $post_id ) ) {
			continue;
		}

		wp_set_object_terms( $post_id, $category, 'cb_category' );
		wp_set_object_terms( $post_id, $sport, 'cb_sport' );
		$meta = array(
			'_ts_demo_product' => 1,
			'cb_sku'           => 'TS-' . str_pad( (string) ( $index + 1 ), 3, '0', STR_PAD_LEFT ),
			'cb_brand'         => 'TABEER SPORTZ',
			'cb_line'          => 'Custom Teamwear',
			'cb_featured'      => $index < 6 ? 1 : 0,
			'cb_origin'        => 'Pakistan',
			'cb_method'        => $construction,
			'cb_material'      => $material,
			'cb_construction'  => $construction,
			'cb_sizes'         => function_exists( 'cb_sanitize_rows' ) ? cb_sanitize_rows( $sizes, array( 'columns' => array( 'size', 'stock' ) ) ) : wp_json_encode( array( array( 'size' => $sizes, 'stock' => '' ) ) ),
			'cb_colors'        => function_exists( 'cb_sanitize_rows' ) ? cb_sanitize_rows( "Teal | #27D6BD\nBlack | #101515\nCustom colour |", array( 'columns' => array( 'name', 'hex' ) ) ) : '',
			'cb_custom'        => 1,
			'cb_custom_notes'  => 'Team colours, names, numbers, badges, sponsor marks and private labeling available.',
			'cb_logo_app'      => 'sublimation,embroidery,screen-print,heat-transfer',
			'cb_print'         => 'Sublimation, embroidery, screen print or heat transfer according to the product.',
			'cb_packaging'     => 'Individual polybag with export carton. Custom packaging available.',
			'cb_moq'           => $moq,
			'cb_lead_time'     => 'Approximately 25-35 days after artwork and sample approval.',
			'cb_capacity'      => 'Confirmed according to product and order quantity.',
			'cb_sample'        => 1,
			'cb_sample_terms'  => 'Sample availability and cost confirmed with the inquiry.',
			'cb_faq'           => function_exists( 'cb_sanitize_rows' ) ? cb_sanitize_rows( "Can this product be customized? | Yes, team colours, branding and player details can be discussed.\nWhat is the minimum order? | The starting test quantity is listed in the specifications; final MOQ depends on customization.", array( 'columns' => array( 'q', 'a' ) ) ) : '',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	return array( 'created' => $created, 'updated' => $updated );
}

/** Add the explicit seed screen below Products. */
function ts_demo_catalogue_menu() {
	add_submenu_page(
		'edit.php?post_type=cb_product',
		__( 'Demo Catalogue', 'crossbar-core' ),
		__( 'Demo Catalogue', 'crossbar-core' ),
		'manage_options',
		'ts-demo-catalogue',
		'ts_render_demo_catalogue'
	);
}
add_action( 'admin_menu', 'ts_demo_catalogue_menu' );

/** Render the seed action with an accurate update warning. */
function ts_render_demo_catalogue() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$done = isset( $_GET['seeded'] ) ? absint( $_GET['seeded'] ) : 0;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'TABEER SPORTZ Demo Catalogue', 'crossbar-core' ); ?></h1>
		<?php if ( $done ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'The 13 demo products are ready.', 'crossbar-core' ); ?></p></div><?php endif; ?>
		<p><?php esc_html_e( 'Creates the 13 starter products used by the Next.js prototype. Running it again refreshes those products by slug and does not create duplicates. Product images are left empty so real photography can be selected from the Media Library.', 'crossbar-core' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ts_seed_demo_catalogue" />
			<?php wp_nonce_field( 'ts_seed_demo_catalogue' ); ?>
			<?php submit_button( __( 'Create or refresh demo products', 'crossbar-core' ), 'primary' ); ?>
		</form>
	</div>
	<?php
}

/** Handle the administrator-only seed action. */
function ts_handle_demo_catalogue() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to seed the catalogue.', 'crossbar-core' ) );
	}
	check_admin_referer( 'ts_seed_demo_catalogue' );
	ts_seed_demo_catalogue();
	wp_safe_redirect( admin_url( 'edit.php?post_type=cb_product&page=ts-demo-catalogue&seeded=1' ) );
	exit;
}
add_action( 'admin_post_ts_seed_demo_catalogue', 'ts_handle_demo_catalogue' );
