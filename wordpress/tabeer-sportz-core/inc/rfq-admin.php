<?php
/**
 * The quote request desk.
 *
 * inc/rfq.php owns the enquiry itself: the basket, the submission, the record
 * and the columns that describe one row. This file owns the view a sales
 * manager opens on Monday morning — how many are sitting at each stage, what
 * came in this month, and which products people keep asking about.
 *
 * Two things are split across the pair on purpose. The list table's columns are
 * declared next to the data they print, in rfq.php; making them sortable and
 * filterable is a screen behaviour and lives here. Each filter is still
 * registered exactly once.
 *
 * Nothing here writes. The only state-changing action on the screen is the CSV
 * export, which already exists in rfq.php and is linked rather than rebuilt.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------------
 * Numbers
 * ------------------------------------------------------------------------- */

/**
 * How many live requests sit at each status.
 *
 * One grouped query rather than a WP_Query per card: six meta queries and six
 * joins to print six integers is the kind of thing that makes an admin screen
 * feel slow for no reason a user can see.
 *
 * @return array<string,int>
 */
function cb_rfq_status_counts() {

	global $wpdb;

	$counts = array_fill_keys( array_keys( cb_rfq_statuses() ), 0 );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an aggregate with no core API; the result is a set of counters read once per page load.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.meta_value AS status, COUNT( p.ID ) AS total
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
			WHERE p.post_type = %s AND p.post_status = %s
			GROUP BY pm.meta_value",
			'cb_rfq_status',
			'cb_rfq',
			'publish'
		)
	);

	foreach ( (array) $rows as $row ) {

		/*
		 * cb_rfq_status_key() does two jobs here. A NULL from the LEFT JOIN is a
		 * request written before the status meta existed and comes back as
		 * "new", which is what the list screen shows it as; and a stored "won"
		 * is folded onto "approved", so the card total and the filtered list
		 * behind it agree.
		 */
		$key = cb_rfq_status_key( (string) $row->status );

		$counts[ $key ] += (int) $row->total;
	}

	return $counts;
}

/**
 * Requests received in the last N days.
 *
 * @param int $days Window in days.
 * @return int
 */
function cb_rfq_recent_count( $days = 30 ) {

	$days = max( 1, (int) $days );

	$recent = new WP_Query(
		array(
			'post_type'              => 'cb_rfq',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'date_query'             => array(
				array(
					'after' => $days . ' days ago',
				),
			),
		)
	);

	return (int) $recent->found_posts;
}

/**
 * The products buyers ask about most often.
 *
 * This is the number a sales manager actually wants: not what sells, but what
 * gets enquired about, which is the thing that tells them what to photograph
 * next and what to keep in stock as a sample.
 *
 * @param int $limit How many to return.
 * @return array<int,array<string,mixed>>
 */
function cb_rfq_top_products( $limit = 5 ) {

	$limit  = max( 1, (int) $limit );
	$cached = get_transient( 'cb_rfq_top_products' );

	if ( is_array( $cached ) ) {
		return array_slice( $cached, 0, $limit );
	}

	global $wpdb;

	/*
	 * Line items are JSON in a single meta value, so there is no way to have the
	 * database count them; they have to be walked in PHP. Capped at the most
	 * recent 500 enquiries, which is a year of trading for this client and keeps
	 * the walk bounded however long the site runs.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in the transient set below.
	$rows = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = %s
			ORDER BY p.post_date DESC
			LIMIT 500",
			'cb_rfq_lines',
			'cb_rfq',
			'publish'
		)
	);

	$tally = array();

	foreach ( (array) $rows as $json ) {

		$lines = json_decode( (string) $json, true );

		if ( ! is_array( $lines ) ) {
			continue;
		}

		foreach ( $lines as $line ) {

			$id = ( is_array( $line ) && isset( $line['id'] ) ) ? absint( $line['id'] ) : 0;

			if ( ! $id ) {
				continue;
			}

			// Keyed on the stored ID and titled from the catalogue, so a product
			// renamed halfway through the year is still one product, not two.
			if ( ! isset( $tally[ $id ] ) ) {
				$tally[ $id ] = array(
					'id'    => $id,
					'title' => (string) get_the_title( $id ),
					'count' => 0,
				);
			}

			$tally[ $id ]['count']++;
		}
	}

	uasort(
		$tally,
		function ( $a, $b ) {
			return $b['count'] - $a['count'];
		}
	);

	$tally = array_values( $tally );

	// Fifteen minutes. Nobody decides anything on a most-requested list that is
	// a quarter of an hour stale, and repeating the walk on every page load is
	// work spent for no answer that changed.
	set_transient( 'cb_rfq_top_products', $tally, 15 * MINUTE_IN_SECONDS );

	return array_slice( $tally, 0, $limit );
}

/* ---------------------------------------------------------------------------
 * The screen
 * ------------------------------------------------------------------------- */

/**
 * Add the dashboard under Quote Requests.
 *
 * Gated on edit_posts, which is the capability that opens the request list
 * itself; a member of staff who can read enquiries can read a count of them.
 * The export link inside is held to edit_others_posts, matching rfq.php.
 *
 * @return void
 */
function cb_rfq_dashboard_menu() {

	add_submenu_page(
		'edit.php?post_type=cb_rfq',
		__( 'Quote Request Dashboard', 'crossbar-core' ),
		__( 'Dashboard', 'crossbar-core' ),
		'edit_posts',
		'cb-rfq-dashboard',
		'cb_rfq_dashboard_screen'
	);
}
add_action( 'admin_menu', 'cb_rfq_dashboard_menu' );

/**
 * A link to the request list, filtered to one status.
 *
 * @param string $status Status key.
 * @return string
 */
function cb_rfq_status_link( $status ) {

	return add_query_arg(
		array(
			'post_type' => 'cb_rfq',
			'cb_status' => $status,
		),
		admin_url( 'edit.php' )
	);
}

/**
 * Render the dashboard.
 *
 * @return void
 */
function cb_rfq_dashboard_screen() {

	// add_submenu_page() already refuses the page to anyone without this, but a
	// callback that assumes its own menu is the only way in is a callback that
	// breaks the day somebody calls it from somewhere else.
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to view quote requests.', 'crossbar-core' ) );
	}

	$counts   = cb_rfq_status_counts();
	$statuses = cb_rfq_statuses();
	$recent   = cb_rfq_recent_count( 30 );
	$top      = cb_rfq_top_products( 5 );
	$total    = array_sum( $counts );
	?>
	<div class="wrap cb-dash">

		<h1><?php esc_html_e( 'Quote Requests', 'crossbar-core' ); ?></h1>

		<p class="cb-lede">
			<?php esc_html_e( 'Where every live enquiry currently sits. Each card opens the request list filtered to that stage.', 'crossbar-core' ); ?>
		</p>

		<div class="cb-cards">
			<?php foreach ( $statuses as $key => $label ) : ?>
				<a class="cb-card cb-card--<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( cb_rfq_status_link( $key ) ); ?>">
					<span class="cb-card__count"><?php echo esc_html( number_format_i18n( $counts[ $key ] ) ); ?></span>
					<span class="cb-card__label"><?php echo esc_html( $label ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="cb-dash__grid">

			<div class="cb-panel">
				<h2><?php esc_html_e( 'Last 30 days', 'crossbar-core' ); ?></h2>

				<p class="cb-stat"><?php echo esc_html( number_format_i18n( $recent ) ); ?></p>

				<p class="cb-hint">
					<?php
					printf(
						/* translators: %s: total number of quote requests on record. */
						esc_html__( 'Enquiries received in the last thirty days, out of %s on record.', 'crossbar-core' ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</p>

				<?php if ( current_user_can( 'edit_others_posts' ) ) : ?>
					<p>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cb_rfq_export' ), 'cb_rfq_export' ) ); ?>">
							<?php esc_html_e( 'Export CSV', 'crossbar-core' ); ?>
						</a>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=cb_rfq' ) ); ?>">
							<?php esc_html_e( 'All requests', 'crossbar-core' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>

			<div class="cb-panel">
				<h2><?php esc_html_e( 'Most requested products', 'crossbar-core' ); ?></h2>

				<?php if ( ! $top ) : ?>

					<p class="cb-hint"><?php esc_html_e( 'No product lines have been quoted for yet. Written enquiries do not count towards this.', 'crossbar-core' ); ?></p>

				<?php else : ?>

					<table class="widefat striped cb-top">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Product', 'crossbar-core' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Enquiries', 'crossbar-core' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $top as $product ) :

								$edit  = get_edit_post_link( $product['id'] );
								$title = '' !== $product['title'] ? $product['title'] : __( '(product deleted)', 'crossbar-core' );
								?>
								<tr>
									<td>
										<?php if ( $edit && '' !== $product['title'] ) : ?>
											<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $title ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $title ); ?>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( number_format_i18n( $product['count'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<p class="cb-hint"><?php esc_html_e( 'Counted across the line items of the most recent 500 enquiries, refreshed every fifteen minutes.', 'crossbar-core' ); ?></p>

				<?php endif; ?>
			</div>

		</div>
	</div>
	<?php
}

/* ---------------------------------------------------------------------------
 * The request list
 * ------------------------------------------------------------------------- */

/**
 * Make Status and Received sortable.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function cb_rfq_sortable_columns( $columns ) {

	$columns['cb_status'] = 'cb_status';
	$columns['date']      = 'date';

	return $columns;
}
add_filter( 'manage_edit-cb_rfq_sortable_columns', 'cb_rfq_sortable_columns' );

/**
 * The status currently being filtered on, if it is one of ours.
 *
 * @return string Status key, or an empty string.
 */
function cb_rfq_requested_status() {

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only list filter; it changes nothing and a nonce on a bookmarkable URL would expire in the bookmark.
	$requested = isset( $_GET['cb_status'] ) ? sanitize_key( wp_unslash( $_GET['cb_status'] ) ) : '';

	$statuses = cb_rfq_statuses();

	return isset( $statuses[ $requested ] ) ? $requested : '';
}

/**
 * Teach the request list about cb_status, and about sorting on it.
 *
 * Both are handled in one pass because they both want to say something about
 * the same meta key, and two callbacks each calling $query->set( 'meta_query' )
 * would have the second quietly discard the first.
 *
 * @param WP_Query $query The query about to run.
 * @return void
 */
function cb_rfq_admin_list_query( $query ) {

	global $pagenow;

	if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
		return;
	}

	if ( 'cb_rfq' !== $query->get( 'post_type' ) ) {
		return;
	}

	$filter  = cb_rfq_requested_status();
	$sorting = ( 'cb_status' === $query->get( 'orderby' ) );

	if ( '' === $filter && ! $sorting ) {
		return;
	}

	$meta_query = array( 'relation' => 'AND' );

	if ( '' !== $filter ) {

		// The alias is honoured on the way in as well as on the way out, or a
		// card counting an old "won" row would link to a list that omits it.
		$values = array( $filter );

		foreach ( cb_rfq_status_aliases() as $old => $new ) {
			if ( $new === $filter ) {
				$values[] = $old;
			}
		}

		$clause = array(
			'key'     => 'cb_rfq_status',
			'value'   => $values,
			'compare' => 'IN',
		);

		if ( 'new' === $filter ) {

			// A request stored before this meta existed has never been picked
			// up by anybody, which is precisely what New means.
			$meta_query[] = array(
				'relation' => 'OR',
				$clause,
				array(
					'key'     => 'cb_rfq_status',
					'compare' => 'NOT EXISTS',
				),
			);

		} else {
			$meta_query[] = $clause;
		}
	}

	if ( $sorting ) {

		$order = ( 'asc' === strtolower( (string) $query->get( 'order' ) ) ) ? 'ASC' : 'DESC';

		$meta_query['cb_rfq_sort'] = array(
			'key'     => 'cb_rfq_status',
			'compare' => 'EXISTS',
		);

		// Date as the tie-break, so two Quoted requests still read newest first
		// rather than in whatever order the join happened to emit them.
		$query->set(
			'orderby',
			array(
				'cb_rfq_sort' => $order,
				'date'        => 'DESC',
			)
		);
	}

	$query->set( 'meta_query', $meta_query );
}
add_action( 'pre_get_posts', 'cb_rfq_admin_list_query' );

/**
 * A status dropdown above the request list.
 *
 * The dashboard cards link straight to cb_status, and without a control that
 * reflects it the filter is invisible to anyone who arrives from a bookmark.
 *
 * @param string $post_type Post type being listed.
 * @return void
 */
function cb_rfq_status_dropdown( $post_type ) {

	if ( 'cb_rfq' !== $post_type ) {
		return;
	}

	$current = cb_rfq_requested_status();
	?>
	<label class="screen-reader-text" for="cb_status"><?php esc_html_e( 'Filter by status', 'crossbar-core' ); ?></label>
	<select name="cb_status" id="cb_status">
		<option value=""><?php esc_html_e( 'All statuses', 'crossbar-core' ); ?></option>
		<?php foreach ( cb_rfq_statuses() as $key => $label ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}
add_action( 'restrict_manage_posts', 'cb_rfq_status_dropdown' );

/**
 * A link to the dashboard above the request list.
 *
 * @param string $which Top or bottom of the table.
 * @return void
 */
function cb_rfq_dashboard_button( $which ) {

	$screen = get_current_screen();

	if ( 'top' !== $which || ! $screen || 'cb_rfq' !== $screen->post_type ) {
		return;
	}

	printf(
		'<a href="%1$s" class="button">%2$s</a> ',
		esc_url( admin_url( 'edit.php?post_type=cb_rfq&page=cb-rfq-dashboard' ) ),
		esc_html__( 'Dashboard', 'crossbar-core' )
	);
}
add_action( 'manage_posts_extra_tablenav', 'cb_rfq_dashboard_button' );
