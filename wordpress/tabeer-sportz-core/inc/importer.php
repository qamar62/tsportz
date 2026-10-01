<?php
/**
 * CSV product importer.
 *
 * A manufacturer has three hundred SKUs in a spreadsheet, not in their head.
 * Without an importer every new client site is a fortnight of typing, and a
 * theme that costs a fortnight to populate does not get reused. This is the
 * single feature that decides whether Crossbar is a product or a one-off.
 *
 * Rows are matched on SKU, so re-importing a corrected sheet updates rather
 * than duplicates.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the importer screen under Products.
 *
 * @return void
 */
function cb_import_menu() {

	add_submenu_page(
		'edit.php?post_type=cb_product',
		__( 'Import Products', 'crossbar-core' ),
		__( 'Import CSV', 'crossbar-core' ),
		'edit_others_posts',
		'cb-import',
		'cb_import_screen'
	);
}
add_action( 'admin_menu', 'cb_import_menu' );

/**
 * Columns the importer understands, beyond the cb_ meta keys.
 *
 * @return array<string,string>
 */
function cb_import_special() {

	return array(
		'title'         => __( 'Product name (required)', 'crossbar-core' ),
		'description'   => __( 'Main body copy', 'crossbar-core' ),
		'excerpt'       => __( 'Short summary, used on cards', 'crossbar-core' ),
		'category'      => __( 'Category term name', 'crossbar-core' ),
		'subtype'       => __( 'Sub-type term name', 'crossbar-core' ),
		'sport'         => __( 'Sport term names, comma separated', 'crossbar-core' ),
		'certification' => __( 'Certifications, comma separated', 'crossbar-core' ),
		'status'        => __( 'publish or draft', 'crossbar-core' ),
	);
}

/**
 * Normalise a header cell into a column key.
 *
 * Accepts "SKU", "sku", "cb_sku" and "SKU / model number" as the same column,
 * because a client will hand over a sheet with whatever headings they use.
 *
 * @param string $header Raw header cell.
 * @return string
 */
function cb_import_key( $header ) {

	$header = strtolower( trim( (string) $header ) );
	$header = preg_replace( '/[^a-z0-9]+/', '_', $header );
	$header = trim( (string) $header, '_' );

	if ( '' === $header ) {
		return '';
	}

	if ( isset( cb_import_special()[ $header ] ) ) {
		return $header;
	}

	if ( 0 === strpos( $header, 'cb_' ) && isset( cb_fields()[ $header ] ) ) {
		return $header;
	}

	if ( isset( cb_fields()[ 'cb_' . $header ] ) ) {
		return 'cb_' . $header;
	}

	// Last resort: match on the human label.
	foreach ( cb_fields() as $key => $args ) {
		if ( cb_import_key_from_label( $args['label'] ) === $header ) {
			return $key;
		}
	}

	return '';
}

/**
 * Slug form of a field label, for header matching.
 *
 * @param string $label Field label.
 * @return string
 */
function cb_import_key_from_label( $label ) {

	$label = strtolower( (string) $label );
	$label = preg_replace( '/[^a-z0-9]+/', '_', $label );

	return trim( (string) $label, '_' );
}

/**
 * The importer screen.
 *
 * @return void
 */
function cb_import_screen() {

	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to import products.', 'crossbar-core' ) );
	}

	$report = null;
	$sample = cb_import_sample_path();

	if ( isset( $_POST['cb_import_nonce'] ) ) {

		$nonce = sanitize_text_field( wp_unslash( $_POST['cb_import_nonce'] ) );

		if ( wp_verify_nonce( $nonce, 'cb_import' ) ) {

			$dry = ! empty( $_POST['cb_dry'] );

			if ( ! empty( $_POST['cb_sample'] ) ) {
				// Resolved here, never taken from the request: a path arriving in
				// a POST field is a file-disclosure bug waiting to be written.
				$report = $sample
					? cb_import_file( $sample, $dry )
					: array( 'error' => __( 'The sample catalogue is not where it should be. Reinstall the theme, or upload the sheet by hand.', 'crossbar-core' ) );
			} else {
				$report = cb_import_run( $dry );
			}
		} else {
			$report = array( 'error' => __( 'That form expired. Please try again.', 'crossbar-core' ) );
		}
	}
	?>
	<div class="wrap cb-import">
		<h1><?php esc_html_e( 'Import products from CSV', 'crossbar-core' ); ?></h1>

		<p class="cb-lede"><?php esc_html_e( 'Upload a spreadsheet exported as CSV. The first row must be headings. Rows are matched on SKU, so importing a corrected sheet updates the existing products instead of creating duplicates.', 'crossbar-core' ); ?></p>

		<?php if ( $report ) : ?>
			<?php cb_import_report( $report ); ?>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'cb_import', 'cb_import_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cb_file"><?php esc_html_e( 'CSV file', 'crossbar-core' ); ?></label></th>
					<td><input type="file" name="cb_file" id="cb_file" accept=".csv,text/csv" required /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dry run', 'crossbar-core' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cb_dry" value="1" checked />
							<?php esc_html_e( 'Preview only — report what would happen without writing anything', 'crossbar-core' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Leave this ticked the first time. Untick it once the preview looks right.', 'crossbar-core' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Import', 'crossbar-core' ) ); ?>
		</form>

		<?php if ( $sample ) : ?>
			<h2><?php esc_html_e( 'Sample catalogue', 'crossbar-core' ); ?></h2>
			<p>
				<?php esc_html_e( 'A demonstration catalogue ships with the theme: a full spread of products with specifications, prices, MOQs and Incoterms filled in, so a new site can be shown to a client before any real data exists. It goes through the importer above, which means SKUs are matched the same way — running it twice updates the sample products rather than doubling them.', 'crossbar-core' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'These are invented products. Delete them before launch, or overwrite them by importing the real sheet with the same SKUs.', 'crossbar-core' ); ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( 'cb_import', 'cb_import_nonce' ); ?>
				<input type="hidden" name="cb_sample" value="1" />
				<p>
					<label>
						<input type="checkbox" name="cb_dry" value="1" />
						<?php esc_html_e( 'Preview only', 'crossbar-core' ); ?>
					</label>
				</p>
				<?php submit_button( __( 'Load the sample catalogue', 'crossbar-core' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Accepted columns', 'crossbar-core' ); ?></h2>
		<p><?php esc_html_e( 'Headings are matched loosely: "SKU", "sku" and "SKU / model number" all reach the same field. Columns the importer does not recognise are ignored, so an existing sheet can be uploaded as-is.', 'crossbar-core' ); ?></p>

		<table class="widefat striped cb-columns">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Column', 'crossbar-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Meaning', 'crossbar-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( cb_import_special() as $key => $label ) : ?>
					<tr>
						<td><code><?php echo esc_html( $key ); ?></code></td>
						<td><?php echo esc_html( $label ); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php foreach ( cb_fields() as $key => $args ) : ?>
					<tr>
						<td><code><?php echo esc_html( str_replace( 'cb_', '', $key ) ); ?></code></td>
						<td>
							<?php echo esc_html( $args['label'] ); ?>
							<?php if ( 'json' === $args['type'] ) : ?>
								<em><?php esc_html_e( '— rows separated by a semicolon, cells by a pipe. Example: 200 | 11.20 ; 500 | 9.80', 'crossbar-core' ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Show the outcome of a run.
 *
 * @param array $report Report data.
 * @return void
 */
function cb_import_report( $report ) {

	if ( ! empty( $report['error'] ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $report['error'] ) . '</p></div>';
		return;
	}

	$class = $report['dry'] ? 'notice-info' : 'notice-success';

	echo '<div class="notice ' . esc_attr( $class ) . '"><p><strong>';

	if ( $report['dry'] ) {
		echo esc_html__( 'Dry run — nothing was written.', 'crossbar-core' );
	} else {
		echo esc_html__( 'Import complete.', 'crossbar-core' );
	}

	echo '</strong></p><p>';

	printf(
		/* translators: 1: rows read, 2: products created, 3: products updated, 4: rows skipped. */
		esc_html__( '%1$d rows read. %2$d created, %3$d updated, %4$d skipped.', 'crossbar-core' ),
		(int) $report['rows'],
		(int) $report['created'],
		(int) $report['updated'],
		(int) $report['skipped']
	);

	echo '</p>';

	if ( ! empty( $report['columns'] ) ) {
		// Escape each name first: escaping after the implode would turn the
		// separators into visible "&lt;/code&gt;" text.
		$names = array_map( 'esc_html', $report['columns'] );

		echo '<p>' . esc_html__( 'Columns recognised: ', 'crossbar-core' ) . '<code>' . implode( '</code>, <code>', $names ) . '</code></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}

	if ( ! empty( $report['ignored'] ) ) {
		echo '<p>' . esc_html__( 'Columns ignored: ', 'crossbar-core' ) . esc_html( implode( ', ', $report['ignored'] ) ) . '</p>';
	}

	if ( ! empty( $report['notes'] ) ) {
		echo '<ul class="cb-import__notes">';

		foreach ( array_slice( $report['notes'], 0, 40 ) as $note ) {
			echo '<li>' . esc_html( $note ) . '</li>';
		}

		echo '</ul>';
	}

	echo '</div>';
}

/**
 * Read the uploaded file and import it.
 *
 * @param bool $dry Preview only.
 * @return array
 */
function cb_import_run( $dry ) {

	$report = cb_import_report_shell( $dry );

	if ( empty( $_FILES['cb_file']['tmp_name'] ) ) {
		$report['error'] = __( 'No file was received.', 'crossbar-core' );
		return $report;
	}

	$tmp = sanitize_text_field( wp_unslash( $_FILES['cb_file']['tmp_name'] ) );
	$name = isset( $_FILES['cb_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['cb_file']['name'] ) ) : '';

	if ( ! is_uploaded_file( $tmp ) ) {
		$report['error'] = __( 'That upload could not be verified.', 'crossbar-core' );
		return $report;
	}

	$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );

	if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) ) {
		$report['error'] = __( 'Please export the sheet as CSV and upload that.', 'crossbar-core' );
		return $report;
	}

	return cb_import_file( $tmp, $dry );
}

/**
 * An empty report.
 *
 * @param bool $dry Preview only.
 * @return array
 */
function cb_import_report_shell( $dry ) {

	return array(
		'dry'     => (bool) $dry,
		'rows'    => 0,
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
		'columns' => array(),
		'ignored' => array(),
		'notes'   => array(),
	);
}

/**
 * The sample catalogue that ships with the theme, if it is there.
 *
 * The file lives in the theme rather than in this plugin because it is
 * demonstration content for a particular design, and a plugin that survives a
 * redesign should not carry the old design's fictional products around with it.
 * A site that has replaced the theme simply gets no button.
 *
 * @return string Absolute path, or an empty string.
 */
function cb_import_sample_path() {

	$paths = array(
		get_stylesheet_directory() . '/sample-data/products.csv',
		get_template_directory() . '/sample-data/products.csv',
	);

	/**
	 * Filter the candidate paths for the bundled sample catalogue.
	 *
	 * @param array<int,string> $paths Absolute paths, most specific first.
	 */
	$paths = (array) apply_filters( 'cb_import_sample_paths', $paths );

	foreach ( $paths as $path ) {
		if ( $path && is_readable( $path ) ) {
			return (string) $path;
		}
	}

	return '';
}

/**
 * Read a CSV off disk and import it.
 *
 * @param string $path Absolute path to a readable CSV.
 * @param bool   $dry  Preview only.
 * @return array
 */
function cb_import_file( $path, $dry ) {

	$report = cb_import_report_shell( $dry );

	$handle = fopen( $path, 'r' );

	if ( ! $handle ) {
		$report['error'] = __( 'The file could not be opened.', 'crossbar-core' );
		return $report;
	}

	$headers = fgetcsv( $handle );

	if ( ! $headers ) {
		fclose( $handle );
		$report['error'] = __( 'The file appears to be empty.', 'crossbar-core' );
		return $report;
	}

	// A sheet saved from Excel often begins with a UTF-8 byte order mark, which
	// would otherwise turn the first heading into something unmatchable.
	$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $headers[0] );

	$map = array();

	foreach ( $headers as $i => $header ) {

		$key = cb_import_key( $header );

		if ( $key ) {
			$map[ $i ]           = $key;
			$report['columns'][] = $key;
		} elseif ( '' !== trim( (string) $header ) ) {
			$report['ignored'][] = trim( (string) $header );
		}
	}

	if ( ! in_array( 'title', $map, true ) && ! in_array( 'cb_sku', $map, true ) ) {
		fclose( $handle );
		$report['error'] = __( 'The sheet needs at least a "title" or a "sku" column.', 'crossbar-core' );
		return $report;
	}

	$limit = 2000;

	while ( ( $row = fgetcsv( $handle ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition

		if ( $report['rows'] >= $limit ) {
			$report['notes'][] = sprintf(
				/* translators: %d: row limit. */
				__( 'Stopped at %d rows. Split the sheet and import the rest separately.', 'crossbar-core' ),
				$limit
			);
			break;
		}

		// Skip a genuinely blank line without counting it as a row.
		if ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) {
			continue;
		}

		++$report['rows'];

		$data = array();

		foreach ( $map as $i => $key ) {
			$data[ $key ] = isset( $row[ $i ] ) ? (string) $row[ $i ] : '';
		}

		$outcome = cb_import_row( $data, $dry, $report['rows'] );

		if ( isset( $outcome['note'] ) ) {
			$report['notes'][] = $outcome['note'];
		}

		if ( isset( $report[ $outcome['result'] ] ) ) {
			++$report[ $outcome['result'] ];
		}
	}

	fclose( $handle );

	$report['columns'] = array_values( array_unique( $report['columns'] ) );

	return $report;
}

/**
 * Import one row.
 *
 * @param array $data   Column key => value.
 * @param bool  $dry    Preview only.
 * @param int   $number Row number, for messages.
 * @return array{result:string,note?:string}
 */
function cb_import_row( $data, $dry, $number ) {

	$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
	$sku   = isset( $data['cb_sku'] ) ? sanitize_text_field( $data['cb_sku'] ) : '';

	if ( '' === $title && '' === $sku ) {
		return array(
			'result' => 'skipped',
			/* translators: %d: row number. */
			'note'   => sprintf( __( 'Row %d has no name and no SKU — skipped.', 'crossbar-core' ), $number ),
		);
	}

	$existing = $sku ? cb_find_by_sku( $sku ) : 0;

	if ( '' === $title ) {
		$title = $existing ? get_the_title( $existing ) : $sku;
	}

	$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'publish';
	$status = in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ? $status : 'publish';

	if ( $dry ) {
		return array(
			'result' => $existing ? 'updated' : 'created',
			'note'   => $existing
				/* translators: 1: row number, 2: product name. */
				? sprintf( __( 'Row %1$d would update "%2$s".', 'crossbar-core' ), $number, $title )
				/* translators: 1: row number, 2: product name. */
				: sprintf( __( 'Row %1$d would create "%2$s".', 'crossbar-core' ), $number, $title ),
		);
	}

	$postarr = array(
		'post_type'   => 'cb_product',
		'post_title'  => $title,
		'post_status' => $status,
	);

	if ( isset( $data['description'] ) ) {
		$postarr['post_content'] = wp_kses_post( $data['description'] );
	}

	if ( isset( $data['excerpt'] ) ) {
		$postarr['post_excerpt'] = sanitize_textarea_field( $data['excerpt'] );
	}

	if ( $existing ) {
		$postarr['ID'] = $existing;
		$post_id       = wp_update_post( $postarr, true );
		$result        = 'updated';
	} else {
		$post_id = wp_insert_post( $postarr, true );
		$result  = 'created';
	}

	if ( is_wp_error( $post_id ) ) {
		return array(
			'result' => 'skipped',
			/* translators: 1: row number, 2: error message. */
			'note'   => sprintf( __( 'Row %1$d failed: %2$s', 'crossbar-core' ), $number, $post_id->get_error_message() ),
		);
	}

	foreach ( cb_fields() as $key => $args ) {

		if ( ! isset( $data[ $key ] ) ) {
			continue;
		}

		$raw = $data[ $key ];

		// Repeatable columns arrive as "a | b ; c | d" on one line.
		if ( 'json' === $args['type'] ) {
			$raw = str_replace( ';', "\n", $raw );
		}

		$clean = cb_sanitize_field( $key, $raw );

		if ( '' === $clean ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $clean );
		}
	}

	cb_import_terms( $post_id, $data );

	return array( 'result' => $result );
}

/**
 * Assign taxonomy terms from a row, creating them where needed.
 *
 * @param int   $post_id Post ID.
 * @param array $data    Row data.
 * @return void
 */
function cb_import_terms( $post_id, $data ) {

	$map = array(
		'category'      => 'cb_category',
		'subtype'       => 'cb_subtype',
		'sport'         => 'cb_sport',
		'certification' => 'cb_certification',
	);

	foreach ( $map as $column => $taxonomy ) {

		if ( ! isset( $data[ $column ] ) || '' === trim( $data[ $column ] ) ) {
			continue;
		}

		$names = array_filter( array_map( 'trim', explode( ',', $data[ $column ] ) ) );
		$ids   = array();

		foreach ( $names as $name ) {

			$term = term_exists( $name, $taxonomy );

			if ( ! $term ) {
				$term = wp_insert_term( $name, $taxonomy );
			}

			if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
				$ids[] = (int) $term['term_id'];
			}
		}

		if ( $ids ) {
			wp_set_object_terms( $post_id, $ids, $taxonomy, false );
		}
	}
}

/**
 * Find a product by SKU.
 *
 * @param string $sku SKU.
 * @return int Post ID or 0.
 */
function cb_find_by_sku( $sku ) {

	if ( '' === $sku ) {
		return 0;
	}

	$found = get_posts(
		array(
			'post_type'      => 'cb_product',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'cb_sku',
					'value'   => $sku,
					'compare' => '=',
				),
			),
		)
	);

	return $found ? (int) $found[0] : 0;
}
