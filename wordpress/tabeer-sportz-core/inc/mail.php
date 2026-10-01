<?php
/**
 * Outgoing mail.
 *
 * WordPress has no SMTP settings. Settings > Writing offers "post via email",
 * which reads a mailbox to publish from and has nothing to do with sending;
 * everything WordPress sends goes through PHP's mail(), which on shared
 * hosting is either disabled, unauthenticated, or authenticated as a server
 * account nobody reads. The enquiry is written to the database either way, so
 * the failure is invisible: the form says thank you, the message sits in the
 * Messages screen, and no one is told it arrived.
 *
 * This file routes wp_mail() through an authenticated mailbox on the domain,
 * makes the envelope match that mailbox so SPF and DKIM pass, and writes down
 * the reason when a send fails instead of discarding it.
 *
 * Credentials come from a settings screen rather than a file. Constants in
 * wp-config.php win where they are defined, for anyone who would rather keep
 * a password out of the database.
 *
 * @package Crossbar_Core
 */

defined( 'ABSPATH' ) || exit;

const CB_MAIL_OPTION = 'cb_mail_settings';
const CB_MAIL_ERROR  = 'cb_mail_last_error';

/* ---------------------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------------------- */

/**
 * The stored settings, with constants layered on top.
 *
 * A constant beats the database because the point of using one is to keep the
 * password out of the database, and a stored value silently winning would
 * defeat that.
 *
 * @return array
 */
function cb_mail_settings() {

	$stored = get_option( CB_MAIL_OPTION, array() );

	$settings = wp_parse_args(
		is_array( $stored ) ? $stored : array(),
		array(
			'enabled'   => false,
			'host'      => '',
			'port'      => 465,
			'secure'    => 'ssl',
			'user'      => '',
			'pass'      => '',
			'from'      => '',
			'from_name' => '',
		)
	);

	$constants = array(
		'enabled'   => 'CB_SMTP_ENABLED',
		'host'      => 'CB_SMTP_HOST',
		'port'      => 'CB_SMTP_PORT',
		'secure'    => 'CB_SMTP_SECURE',
		'user'      => 'CB_SMTP_USER',
		'pass'      => 'CB_SMTP_PASS',
		'from'      => 'CB_SMTP_FROM',
		'from_name' => 'CB_SMTP_FROM_NAME',
	);

	foreach ( $constants as $key => $constant ) {
		if ( defined( $constant ) ) {
			$settings[ $key ] = constant( $constant );
		}
	}

	$settings['enabled'] = (bool) $settings['enabled'];
	$settings['port']    = (int) $settings['port'];

	// The envelope address defaults to the mailbox being authenticated as,
	// because those are the two that have to agree.
	if ( '' === $settings['from'] ) {
		$settings['from'] = $settings['user'];
	}

	if ( '' === $settings['from_name'] ) {
		$settings['from_name'] = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	return $settings;
}

/**
 * Which settings are pinned by a constant, so the screen can say so.
 *
 * @return string[] Setting keys.
 */
function cb_mail_locked_keys() {

	$locked = array();

	foreach (
		array(
			'enabled'   => 'CB_SMTP_ENABLED',
			'host'      => 'CB_SMTP_HOST',
			'port'      => 'CB_SMTP_PORT',
			'secure'    => 'CB_SMTP_SECURE',
			'user'      => 'CB_SMTP_USER',
			'pass'      => 'CB_SMTP_PASS',
			'from'      => 'CB_SMTP_FROM',
			'from_name' => 'CB_SMTP_FROM_NAME',
		) as $key => $constant
	) {
		if ( defined( $constant ) ) {
			$locked[] = $key;
		}
	}

	return $locked;
}

/**
 * Whether there is enough here to attempt a connection.
 *
 * @param array|null $settings Settings, or null to read them.
 * @return bool
 */
function cb_mail_is_configured( $settings = null ) {

	$settings = null === $settings ? cb_mail_settings() : $settings;

	return $settings['enabled']
		&& '' !== $settings['host']
		&& '' !== $settings['user']
		&& '' !== $settings['pass'];
}

/**
 * The name of a dedicated mail plugin already handling delivery, if any.
 *
 * Two things hooking phpmailer_init both believing they own the connection is
 * a bad afternoon, and telling somebody their mail is unconfigured while WP
 * Mail SMTP is quietly delivering it is worse than saying nothing. Detected by
 * class, not by folder name, so a renamed directory still counts and an
 * installed-but-deactivated plugin does not.
 *
 * @return string Plugin name, or an empty string.
 */
function cb_mail_other_smtp_plugin() {

	$known = array(
		'WPMailSMTP\\Core'              => 'WP Mail SMTP',
		'FluentMail\\App\\Application'  => 'FluentSMTP',
		'PostmanEmailLogs'              => 'Post SMTP',
		'EasyWPSMTP\\Core'              => 'Easy WP SMTP',
		'MailPoet\\Mailer\\Mailer'      => 'MailPoet',
	);

	foreach ( $known as $class => $name ) {
		if ( class_exists( $class ) ) {
			return $name;
		}
	}

	if ( function_exists( 'wp_mail_smtp' ) ) {
		return 'WP Mail SMTP';
	}

	return '';
}

/* ---------------------------------------------------------------------------
 * Transport
 * ------------------------------------------------------------------------- */

/**
 * Point PHPMailer at the SMTP server.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $mailer Mailer, by reference.
 * @return void
 */
function cb_mail_configure( $mailer ) {

	$settings = cb_mail_settings();

	if ( ! cb_mail_is_configured( $settings ) ) {
		return;
	}

	$mailer->isSMTP();
	$mailer->Host       = $settings['host']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$mailer->Port       = $settings['port']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$mailer->SMTPAuth   = true;              // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$mailer->Username   = $settings['user']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$mailer->Password   = $settings['pass']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$mailer->CharSet    = 'UTF-8';           // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$mailer->SMTPSecure = 'none' === $settings['secure'] ? '' : $settings['secure']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

	if ( 'none' === $settings['secure'] ) {
		$mailer->SMTPAutoTLS = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	// The envelope sender has to be the authenticated mailbox. Hostinger, like
	// most providers, rejects a From on a different address with a 550 rather
	// than quietly rewriting it, and a rejected notification is a lost order.
	if ( is_email( $settings['from'] ) ) {
		$mailer->setFrom( $settings['from'], $settings['from_name'], false );
		$mailer->Sender = $settings['from']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
}
add_action( 'phpmailer_init', 'cb_mail_configure' );

/**
 * The address WordPress puts in From.
 *
 * Left alone unless SMTP is actually configured, so a site without it keeps
 * whatever its host expects rather than being handed an address the server is
 * not authorised to send for.
 *
 * @param string $from Default address.
 * @return string
 */
function cb_mail_from( $from ) {

	$settings = cb_mail_settings();

	if ( cb_mail_is_configured( $settings ) && is_email( $settings['from'] ) ) {
		return $settings['from'];
	}

	return $from;
}
add_filter( 'wp_mail_from', 'cb_mail_from' );

/**
 * The name beside it.
 *
 * @param string $name Default name.
 * @return string
 */
function cb_mail_from_name( $name ) {

	$settings = cb_mail_settings();

	if ( cb_mail_is_configured( $settings ) && '' !== $settings['from_name'] ) {
		return $settings['from_name'];
	}

	return $name;
}
add_filter( 'wp_mail_from_name', 'cb_mail_from_name' );

/* ---------------------------------------------------------------------------
 * Failures
 * ------------------------------------------------------------------------- */

/**
 * Keep the reason a send failed.
 *
 * Without this the only trace of a broken mailbox is enquiries that never
 * arrive, which looks from the outside like a quiet week.
 *
 * @param WP_Error $error The failure.
 * @return void
 */
function cb_mail_record_failure( $error ) {

	if ( ! is_wp_error( $error ) ) {
		return;
	}

	$data = $error->get_error_data();
	$to   = array();

	if ( is_array( $data ) && ! empty( $data['to'] ) ) {
		$to = (array) $data['to'];
	}

	update_option(
		CB_MAIL_ERROR,
		array(
			'time'    => time(),
			'message' => $error->get_error_message(),
			'to'      => implode( ', ', array_map( 'sanitize_email', $to ) ),
		),
		false
	);
}
add_action( 'wp_mail_failed', 'cb_mail_record_failure' );

/**
 * Forget the last failure once something has gone out.
 *
 * @return void
 */
function cb_mail_clear_failure() {

	if ( get_option( CB_MAIL_ERROR ) ) {
		delete_option( CB_MAIL_ERROR );
	}
}
add_action( 'wp_mail_succeeded', 'cb_mail_clear_failure' );

/**
 * Warn in the admin when mail is broken or was never set up.
 *
 * Shown only on the screens where someone is looking at enquiries, so it does
 * not become wallpaper on every page of the dashboard.
 *
 * @return void
 */
function cb_mail_admin_notice() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$where  = $screen ? $screen->id : '';
	$posts  = array( 'edit-cb_message', 'edit-cb_rfq', 'edit-cb_subscriber', 'settings_page_cb-mail', 'dashboard' );

	if ( ! in_array( $where, $posts, true ) ) {
		return;
	}

	$link = '<a href="' . esc_url( admin_url( 'options-general.php?page=cb-mail' ) ) . '">' . esc_html__( 'Crossbar Mail settings', 'crossbar-core' ) . '</a>';

	$failure = get_option( CB_MAIL_ERROR );

	if ( is_array( $failure ) && ! empty( $failure['message'] ) ) {

		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p><p>%3$s</p></div>',
			esc_html__( 'The last notification could not be sent.', 'crossbar-core' ),
			esc_html( $failure['message'] ),
			wp_kses_post(
				sprintf(
					/* translators: %s: link to the settings screen. */
					__( 'Enquiries are still being saved. Check %s.', 'crossbar-core' ),
					$link
				)
			)
		);

		return;
	}

	// Nothing to warn about if this screen is the settings screen, if SMTP is
	// set up here, or if a dedicated mail plugin is already doing the job.
	if ( 'settings_page_cb-mail' === $where || cb_mail_is_configured() || cb_mail_other_smtp_plugin() ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		wp_kses_post(
			sprintf(
				/* translators: %s: link to the settings screen. */
				__( 'Crossbar is saving enquiries but not emailing them to anyone: no outgoing mailbox has been set up yet. Open %s to add one.', 'crossbar-core' ),
				$link
			)
		)
	);
}
add_action( 'admin_notices', 'cb_mail_admin_notice' );

/* ---------------------------------------------------------------------------
 * The settings screen
 * ------------------------------------------------------------------------- */

/**
 * Register the page.
 *
 * @return void
 */
function cb_mail_menu() {

	add_options_page(
		__( 'Crossbar Mail', 'crossbar-core' ),
		__( 'Crossbar Mail', 'crossbar-core' ),
		'manage_options',
		'cb-mail',
		'cb_mail_screen'
	);
}
add_action( 'admin_menu', 'cb_mail_menu' );

/**
 * Save the form, or send a test.
 *
 * A hand-written handler rather than the settings API, because the password
 * field has to keep its stored value when submitted blank and the test button
 * has to run after the save in the same request — otherwise the first test
 * after a change is a test of the previous settings.
 *
 * @return void
 */
function cb_mail_handle_post() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to change mail settings.', 'crossbar-core' ) );
	}

	check_admin_referer( 'cb_mail_save' );

	$existing = get_option( CB_MAIL_OPTION, array() );
	$existing = is_array( $existing ) ? $existing : array();

	$secure = isset( $_POST['cb_mail_secure'] ) ? sanitize_key( wp_unslash( $_POST['cb_mail_secure'] ) ) : 'ssl';

	if ( ! in_array( $secure, array( 'ssl', 'tls', 'none' ), true ) ) {
		$secure = 'ssl';
	}

	$port = isset( $_POST['cb_mail_port'] ) ? absint( wp_unslash( $_POST['cb_mail_port'] ) ) : 465;

	if ( $port < 1 || $port > 65535 ) {
		$port = 465;
	}

	$settings = array(
		'enabled'   => ! empty( $_POST['cb_mail_enabled'] ),
		'host'      => isset( $_POST['cb_mail_host'] ) ? sanitize_text_field( wp_unslash( $_POST['cb_mail_host'] ) ) : '',
		'port'      => $port,
		'secure'    => $secure,
		'user'      => isset( $_POST['cb_mail_user'] ) ? sanitize_text_field( wp_unslash( $_POST['cb_mail_user'] ) ) : '',
		'from'      => isset( $_POST['cb_mail_from'] ) ? sanitize_email( wp_unslash( $_POST['cb_mail_from'] ) ) : '',
		'from_name' => isset( $_POST['cb_mail_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cb_mail_from_name'] ) ) : '',
	);

	// An empty password field means "leave it alone", not "erase it". The field
	// is never populated with the stored value, so a save that carried the box
	// contents through would wipe the password every time anything else on the
	// screen was edited.
	$sent_pass = isset( $_POST['cb_mail_pass'] ) ? (string) wp_unslash( $_POST['cb_mail_pass'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a password is taken verbatim; sanitising would silently change it.

	$settings['pass'] = '' === $sent_pass ? (string) ( isset( $existing['pass'] ) ? $existing['pass'] : '' ) : $sent_pass;

	// A field pinned by a constant is rendered disabled, and a disabled input is
	// not submitted. Writing the empty result back would erase the stored value
	// behind it, so the day the constant came out of wp-config.php the settings
	// would be blank rather than what they had been.
	foreach ( cb_mail_locked_keys() as $key ) {
		if ( array_key_exists( $key, $existing ) ) {
			$settings[ $key ] = $existing[ $key ];
		} else {
			unset( $settings[ $key ] );
		}
	}

	update_option( CB_MAIL_OPTION, $settings, false );
	delete_option( CB_MAIL_ERROR );

	$result = 'saved';

	if ( isset( $_POST['cb_mail_test'] ) ) {
		$result = cb_mail_send_test() ? 'tested' : 'failed';
	}

	wp_safe_redirect( add_query_arg( 'cb_mail', $result, admin_url( 'options-general.php?page=cb-mail' ) ) );
	exit;
}
add_action( 'admin_post_cb_mail_save', 'cb_mail_handle_post' );

/**
 * Send a test message to the notification address.
 *
 * @return bool
 */
function cb_mail_send_test() {

	$to = cb_mail_notification_address();

	if ( ! is_email( $to ) ) {
		update_option(
			CB_MAIL_ERROR,
			array(
				'time'    => time(),
				'message' => __( 'There is no valid notification address to send a test to.', 'crossbar-core' ),
				'to'      => '',
			),
			false
		);

		return false;
	}

	$body = array(
		__( 'This is a test from the Crossbar Mail settings screen.', 'crossbar-core' ),
		'',
		/* translators: %s: site address. */
		sprintf( __( 'Site: %s', 'crossbar-core' ), home_url( '/' ) ),
		/* translators: %s: date and time. */
		sprintf( __( 'Sent: %s', 'crossbar-core' ), wp_date( 'Y-m-d H:i:s' ) ),
		'',
		__( 'If you are reading this, contact form enquiries and quote requests will reach you at this address.', 'crossbar-core' ),
	);

	return (bool) wp_mail(
		$to,
		/* translators: %s: site name. */
		sprintf( __( '[%s] Mail test', 'crossbar-core' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
		implode( "\n", $body ),
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);
}

/**
 * Where enquiry notifications go.
 *
 * The theme's Customizer address if the theme is providing one, the site
 * administrator otherwise. Kept here so the settings screen and the test send
 * agree with what the forms actually do.
 *
 * @return string
 */
function cb_mail_notification_address() {

	if ( function_exists( 'crossbar_setting' ) ) {

		$address = (string) crossbar_setting( 'email', '' );

		if ( is_email( $address ) ) {
			return $address;
		}
	}

	return (string) get_option( 'admin_email' );
}

/**
 * Draw the screen.
 *
 * @return void
 */
function cb_mail_screen() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = cb_mail_settings();
	$locked   = cb_mail_locked_keys();
	$to       = cb_mail_notification_address();
	$failure  = get_option( CB_MAIL_ERROR );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only, set by our own redirect.
	$state = isset( $_GET['cb_mail'] ) ? sanitize_key( wp_unslash( $_GET['cb_mail'] ) ) : '';

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Crossbar Mail', 'crossbar-core' ); ?></h1>

		<p class="description" style="max-width:44em">
			<?php esc_html_e( 'WordPress hands its mail to the server, which on shared hosting usually cannot send for your domain. Point it at a mailbox on the domain instead and enquiries will arrive rather than disappear.', 'crossbar-core' ); ?>
		</p>

		<?php if ( 'tested' === $state ) : ?>
			<div class="notice notice-success"><p>
				<?php
				printf(
					/* translators: %s: email address. */
					esc_html__( 'The server accepted a test message for %s. Check that mailbox, including its spam folder.', 'crossbar-core' ),
					'<code>' . esc_html( $to ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
				?>
			</p></div>
		<?php elseif ( 'saved' === $state ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Settings saved.', 'crossbar-core' ); ?></p></div>
		<?php endif; ?>

		<?php $rival = cb_mail_other_smtp_plugin(); ?>

		<?php if ( '' !== $rival ) : ?>
			<div class="notice notice-info">
				<p>
					<?php
					printf(
						/* translators: %s: plugin name. */
						esc_html__( '%s is active and is already handling outgoing mail. Leave the box below unticked and configure it there instead — two plugins setting up the same connection will fight over it. Everything else on this screen, including the test send and the record of the last failure, works either way.', 'crossbar-core' ),
						'<strong>' . esc_html( $rival ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( is_array( $failure ) && ! empty( $failure['message'] ) ) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'The last send failed.', 'crossbar-core' ); ?></strong></p>
				<p><code><?php echo esc_html( $failure['message'] ); ?></code></p>
			</div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enquiries are sent to', 'crossbar-core' ); ?></th>
				<td>
					<code><?php echo esc_html( $to ); ?></code>
					<p class="description">
						<?php esc_html_e( 'Set under Appearance > Customize > Contact details. Falls back to the site administrator address.', 'crossbar-core' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cb_mail_save">
			<?php wp_nonce_field( 'cb_mail_save' ); ?>

			<table class="form-table" role="presentation">

				<tr>
					<th scope="row"><?php esc_html_e( 'Send through SMTP', 'crossbar-core' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cb_mail_enabled" value="1"
								<?php checked( $settings['enabled'] ); ?>
								<?php disabled( in_array( 'enabled', $locked, true ) ); ?>>
							<?php esc_html_e( 'Use the mailbox below instead of the server default', 'crossbar-core' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="cb_mail_host"><?php esc_html_e( 'SMTP host', 'crossbar-core' ); ?></label></th>
					<td>
						<input name="cb_mail_host" id="cb_mail_host" type="text" class="regular-text"
							value="<?php echo esc_attr( $settings['host'] ); ?>"
							placeholder="smtp.hostinger.com"
							<?php disabled( in_array( 'host', $locked, true ) ); ?>>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="cb_mail_port"><?php esc_html_e( 'Port and encryption', 'crossbar-core' ); ?></label></th>
					<td>
						<input name="cb_mail_port" id="cb_mail_port" type="number" min="1" max="65535" class="small-text"
							value="<?php echo esc_attr( (string) $settings['port'] ); ?>"
							<?php disabled( in_array( 'port', $locked, true ) ); ?>>

						<select name="cb_mail_secure" <?php disabled( in_array( 'secure', $locked, true ) ); ?>>
							<option value="ssl" <?php selected( $settings['secure'], 'ssl' ); ?>><?php esc_html_e( 'SSL — port 465', 'crossbar-core' ); ?></option>
							<option value="tls" <?php selected( $settings['secure'], 'tls' ); ?>><?php esc_html_e( 'STARTTLS — port 587', 'crossbar-core' ); ?></option>
							<option value="none" <?php selected( $settings['secure'], 'none' ); ?>><?php esc_html_e( 'None', 'crossbar-core' ); ?></option>
						</select>

						<p class="description"><?php esc_html_e( 'Hostinger mailboxes take SSL on 465. The two have to match: 465 with STARTTLS, or 587 with SSL, will hang until the connection times out.', 'crossbar-core' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="cb_mail_user"><?php esc_html_e( 'Mailbox', 'crossbar-core' ); ?></label></th>
					<td>
						<input name="cb_mail_user" id="cb_mail_user" type="text" class="regular-text"
							value="<?php echo esc_attr( $settings['user'] ); ?>"
							placeholder="info@<?php echo esc_attr( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>"
							<?php disabled( in_array( 'user', $locked, true ) ); ?>>
						<p class="description"><?php esc_html_e( 'The full email address, not just the part before the @.', 'crossbar-core' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="cb_mail_pass"><?php esc_html_e( 'Mailbox password', 'crossbar-core' ); ?></label></th>
					<td>
						<?php if ( in_array( 'pass', $locked, true ) ) : ?>
							<p><em><?php esc_html_e( 'Set by the CB_SMTP_PASS constant in wp-config.php.', 'crossbar-core' ); ?></em></p>
						<?php else : ?>
							<input name="cb_mail_pass" id="cb_mail_pass" type="password" class="regular-text"
								value="" autocomplete="new-password"
								placeholder="<?php echo '' === $settings['pass'] ? esc_attr__( 'not set', 'crossbar-core' ) : esc_attr__( 'saved — leave blank to keep it', 'crossbar-core' ); ?>">
							<p class="description"><?php esc_html_e( 'Never shown again once saved. Leave the box empty to keep the current password.', 'crossbar-core' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="cb_mail_from"><?php esc_html_e( 'Send as', 'crossbar-core' ); ?></label></th>
					<td>
						<input name="cb_mail_from" id="cb_mail_from" type="email" class="regular-text"
							value="<?php echo esc_attr( $settings['from'] ); ?>"
							<?php disabled( in_array( 'from', $locked, true ) ); ?>>
						<input name="cb_mail_from_name" id="cb_mail_from_name" type="text" class="regular-text"
							value="<?php echo esc_attr( $settings['from_name'] ); ?>"
							<?php disabled( in_array( 'from_name', $locked, true ) ); ?>>
						<p class="description"><?php esc_html_e( 'Leave the address matching the mailbox above. Most providers reject a message that claims to come from somewhere else, and the sender of an enquiry is carried in Reply-To so you can still answer them directly.', 'crossbar-core' ); ?></p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'crossbar-core' ); ?></button>
				<button type="submit" name="cb_mail_test" value="1" class="button"><?php esc_html_e( 'Save and send a test', 'crossbar-core' ); ?></button>
			</p>
		</form>
	</div>
	<?php
}
