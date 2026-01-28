<?php
/**
 * Plugin Name: WordPress Discord & Brevo Mail
 * Description: Sends Discord embeds for Contact Form 7 submissions and routes WordPress mail through Brevo when enabled.
 * Version: 0.1.0
 * Author: Jack Parlby
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WDBM_VERSION', '0.1.0' );
define( 'WDBM_OPTION_KEY', 'wdbm_settings' );
define( 'WDBM_LOG_TABLE', 'wdbm_logs' );

register_activation_hook( __FILE__, 'wdbm_activate' );

function wdbm_activate() {
	global $wpdb;

	$table_name = $wpdb->prefix . WDBM_LOG_TABLE;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at DATETIME NOT NULL,
		channel VARCHAR(20) NOT NULL,
		event VARCHAR(50) NOT NULL,
		status VARCHAR(20) NOT NULL,
		message TEXT NULL,
		payload LONGTEXT NULL,
		PRIMARY KEY  (id),
		KEY channel (channel),
		KEY event (event),
		KEY status (status),
		KEY created_at (created_at)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

add_action( 'admin_menu', 'wdbm_register_admin_menu' );
add_action( 'admin_init', 'wdbm_register_settings' );

function wdbm_register_admin_menu() {
	add_menu_page(
		'Discord & Brevo Mail',
		'Discord & Brevo',
		'manage_options',
		'wdbm-settings',
		'wdbm_render_settings_page',
		'dashicons-email-alt2'
	);

	add_submenu_page(
		'wdbm-settings',
		'Send Logs',
		'Send Logs',
		'manage_options',
		'wdbm-logs',
		'wdbm_render_logs_page'
	);
}

function wdbm_register_settings() {
	register_setting( 'wdbm_settings_group', WDBM_OPTION_KEY, 'wdbm_sanitize_settings' );

	add_settings_section(
		'wdbm_discord_section',
		'Discord',
		'__return_null',
		'wdbm-settings'
	);

	add_settings_field(
		'wdbm_discord_enabled',
		'Enable Discord',
		'wdbm_render_checkbox_field',
		'wdbm-settings',
		'wdbm_discord_section',
		array(
			'label_for' => 'discord_enabled',
		)
	);

	add_settings_field(
		'wdbm_discord_webhook',
		'Discord Webhook URL',
		'wdbm_render_text_field',
		'wdbm-settings',
		'wdbm_discord_section',
		array(
			'label_for' => 'discord_webhook',
			'type'      => 'url',
		)
	);

	add_settings_section(
		'wdbm_brevo_section',
		'Brevo',
		'__return_null',
		'wdbm-settings'
	);

	add_settings_field(
		'wdbm_brevo_defer_smtp',
		'Defer to other SMTP plugins',
		'wdbm_render_checkbox_field',
		'wdbm-settings',
		'wdbm_brevo_section',
		array(
			'label_for' => 'brevo_defer_smtp',
		)
	);

	add_settings_field(
		'wdbm_brevo_enabled',
		'Enable Brevo',
		'wdbm_render_checkbox_field',
		'wdbm-settings',
		'wdbm_brevo_section',
		array(
			'label_for' => 'brevo_enabled',
		)
	);

	add_settings_field(
		'wdbm_brevo_api_key',
		'Brevo API Key',
		'wdbm_render_text_field',
		'wdbm-settings',
		'wdbm_brevo_section',
		array(
			'label_for' => 'brevo_api_key',
			'type'      => 'password',
		)
	);

	add_settings_field(
		'wdbm_brevo_from_email',
		'From Email',
		'wdbm_render_text_field',
		'wdbm-settings',
		'wdbm_brevo_section',
		array(
			'label_for' => 'brevo_from_email',
			'type'      => 'email',
		)
	);

	add_settings_field(
		'wdbm_brevo_from_name',
		'From Name',
		'wdbm_render_text_field',
		'wdbm-settings',
		'wdbm_brevo_section',
		array(
			'label_for' => 'brevo_from_name',
			'type'      => 'text',
		)
	);

	add_settings_field(
		'wdbm_brevo_reply_to',
		'Reply-To',
		'wdbm_render_text_field',
		'wdbm-settings',
		'wdbm_brevo_section',
		array(
			'label_for' => 'brevo_reply_to',
			'type'      => 'email',
		)
	);
}

function wdbm_get_settings() {
	$defaults = array(
		'discord_enabled'   => 0,
		'discord_webhook'   => '',
		'brevo_defer_smtp'  => 0,
		'brevo_enabled'     => 0,
		'brevo_api_key'     => '',
		'brevo_from_email'  => '',
		'brevo_from_name'   => '',
		'brevo_reply_to'    => '',
	);

	$settings = get_option( WDBM_OPTION_KEY, array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return array_merge( $defaults, $settings );
}

function wdbm_sanitize_settings( $input ) {
	$clean = array();

	$clean['discord_enabled']  = isset( $input['discord_enabled'] ) ? 1 : 0;
	$clean['discord_webhook']  = isset( $input['discord_webhook'] ) ? esc_url_raw( $input['discord_webhook'] ) : '';

	$clean['brevo_defer_smtp'] = isset( $input['brevo_defer_smtp'] ) ? 1 : 0;
	$clean['brevo_enabled']    = isset( $input['brevo_enabled'] ) ? 1 : 0;
	$clean['brevo_api_key']    = isset( $input['brevo_api_key'] ) ? sanitize_text_field( $input['brevo_api_key'] ) : '';
	$clean['brevo_from_email'] = isset( $input['brevo_from_email'] ) ? sanitize_email( $input['brevo_from_email'] ) : '';
	$clean['brevo_from_name']  = isset( $input['brevo_from_name'] ) ? sanitize_text_field( $input['brevo_from_name'] ) : '';
	$clean['brevo_reply_to']   = isset( $input['brevo_reply_to'] ) ? sanitize_email( $input['brevo_reply_to'] ) : '';

	return $clean;
}

function wdbm_render_settings_page() {
	?>
	<div class="wrap">
		<h1>Discord & Brevo Mail</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'wdbm_settings_group' );
			do_settings_sections( 'wdbm-settings' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

function wdbm_render_checkbox_field( $args ) {
	$settings = wdbm_get_settings();
	$key = $args['label_for'];
	$value = ! empty( $settings[ $key ] ) ? 1 : 0;
	?>
	<input type="checkbox" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( WDBM_OPTION_KEY . '[' . $key . ']' ); ?>" value="1" <?php checked( 1, $value ); ?> />
	<?php
}

function wdbm_render_text_field( $args ) {
	$settings = wdbm_get_settings();
	$key = $args['label_for'];
	$type = isset( $args['type'] ) ? $args['type'] : 'text';
	$value = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
	?>
	<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( WDBM_OPTION_KEY . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
	<?php
}

function wdbm_render_logs_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . WDBM_LOG_TABLE;

	$per_page = 50;
	$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$offset = ( $page - 1 ) * $per_page;

	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		)
	);

	$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
	?>
	<div class="wrap">
		<h1>Send Logs</h1>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th>Date</th>
					<th>Channel</th>
					<th>Event</th>
					<th>Status</th>
					<th>Message</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr>
						<td colspan="5">No logs yet.</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><?php echo esc_html( $row->channel ); ?></td>
							<td><?php echo esc_html( $row->event ); ?></td>
							<td><?php echo esc_html( $row->status ); ?></td>
							<td><?php echo esc_html( $row->message ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					$base_url = remove_query_arg( array( 'paged' ) );
					echo paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%', $base_url ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $total_pages,
							'current'   => $page,
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

function wdbm_log_event( $channel, $event, $status, $message = '', $payload = array() ) {
	global $wpdb;
	$table_name = $wpdb->prefix . WDBM_LOG_TABLE;

	$wpdb->insert(
		$table_name,
		array(
			'created_at' => current_time( 'mysql' ),
			'channel'    => sanitize_text_field( $channel ),
			'event'      => sanitize_text_field( $event ),
			'status'     => sanitize_text_field( $status ),
			'message'    => sanitize_text_field( $message ),
			'payload'    => ! empty( $payload ) ? wp_json_encode( $payload ) : null,
		),
		array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		)
	);
}

function wdbm_discord_enabled() {
	$settings = wdbm_get_settings();
	return ! empty( $settings['discord_enabled'] ) && ! empty( $settings['discord_webhook'] );
}

function wdbm_send_discord_embed( $title, $description, $fields = array(), $color = 3447003, $event = 'unknown' ) {
	if ( ! wdbm_discord_enabled() ) {
		return false;
	}

	$settings = wdbm_get_settings();
	$webhook = $settings['discord_webhook'];

	$embed_fields = array();
	foreach ( $fields as $name => $value ) {
		$embed_fields[] = array(
			'name'   => wp_strip_all_tags( (string) $name ),
			'value'  => wp_strip_all_tags( (string) $value ),
			'inline' => false,
		);
	}

	$payload = array(
		'content' => '',
		'embeds'  => array(
			array(
				'title'       => $title,
				'description' => $description,
				'color'       => $color,
				'fields'      => $embed_fields,
				'timestamp'   => gmdate( 'c' ),
			),
		),
	);

	$response = wp_remote_post(
		$webhook,
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'timeout' => 10,
			'body'    => wp_json_encode( $payload ),
		)
	);

	if ( is_wp_error( $response ) ) {
		wdbm_log_event( 'discord', $event, 'failed', $response->get_error_message(), $payload );
		return false;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		wdbm_log_event( 'discord', $event, 'failed', 'HTTP ' . $code, $payload );
		return false;
	}

	wdbm_log_event( 'discord', $event, 'success', 'Sent', $payload );
	return true;
}

function wdbm_send_failure_embed( $context, $error_message ) {
	$fields = array(
		'Context' => $context,
		'Error'   => $error_message,
	);

	return wdbm_send_discord_embed(
		'Notification Failure',
		'A notification failed to send.',
		$fields,
		15158332,
		'failure'
	);
}

function wdbm_is_other_smtp_active() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugins = array(
		'wp-mail-smtp/wp_mail_smtp.php',
		'post-smtp/postman-smtp.php',
		'fluent-smtp/fluent-smtp.php',
		'easy-wp-smtp/easy-wp-smtp.php',
		'smtp-mailer/main.php',
	);

	foreach ( $plugins as $plugin ) {
		if ( is_plugin_active( $plugin ) || is_plugin_active_for_network( $plugin ) ) {
			return $plugin;
		}
	}

	return false;
}

// Contact Form 7 integration (contact form only events for now).
add_action( 'wpcf7_mail_sent', 'wdbm_handle_cf7_submission' );

function wdbm_handle_cf7_submission( $contact_form ) {
	if ( ! wdbm_discord_enabled() ) {
		return;
	}

	if ( ! class_exists( 'WPCF7_Submission' ) ) {
		return;
	}

	$submission = WPCF7_Submission::get_instance();
	if ( ! $submission ) {
		return;
	}

	$data = $submission->get_posted_data();
	$filtered = array();

	foreach ( $data as $key => $value ) {
		if ( strpos( $key, '_wpcf7' ) === 0 || $key === '_wpnonce' ) {
			continue;
		}

		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		$filtered[ $key ] = $value;
	}

	$fields = array(
		'Form Title' => $contact_form->title(),
		'Form ID'    => $contact_form->id(),
	);

	foreach ( $filtered as $key => $value ) {
		$fields[ $key ] = $value;
	}

	$sent = wdbm_send_discord_embed(
		'Contact Form Submission',
		'A new Contact Form 7 submission was received.',
		$fields,
		3066993,
		'contact_form_7'
	);

	if ( ! $sent ) {
		wdbm_send_failure_embed( 'Contact Form 7', 'Discord webhook failed for contact form submission.' );
	}
}

// WPForms integration.
add_action( 'wpforms_process_complete', 'wdbm_handle_wpforms_submission', 10, 4 );

function wdbm_handle_wpforms_submission( $fields, $entry, $form_data, $entry_id ) {
	if ( ! wdbm_discord_enabled() ) {
		return;
	}

	$form_title = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : 'WPForms Form';
	$form_id = isset( $form_data['id'] ) ? $form_data['id'] : '';

	$embed_fields = array(
		'Form Title' => $form_title,
		'Form ID'    => $form_id,
		'Entry ID'   => $entry_id,
	);

	foreach ( $fields as $field ) {
		if ( empty( $field['name'] ) ) {
			continue;
		}

		$value = isset( $field['value'] ) ? $field['value'] : '';
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}
		$embed_fields[ $field['name'] ] = $value;
	}

	$sent = wdbm_send_discord_embed(
		'Contact Form Submission',
		'A new WPForms submission was received.',
		$embed_fields,
		15844367,
		'wpforms'
	);

	if ( ! $sent ) {
		wdbm_send_failure_embed( 'WPForms', 'Discord webhook failed for WPForms submission.' );
	}
}

// Gravity Forms integration.
add_action( 'gform_after_submission', 'wdbm_handle_gravityforms_submission', 10, 2 );

function wdbm_handle_gravityforms_submission( $entry, $form ) {
	if ( ! wdbm_discord_enabled() ) {
		return;
	}

	$form_title = isset( $form['title'] ) ? $form['title'] : 'Gravity Forms Form';
	$form_id = isset( $form['id'] ) ? $form['id'] : '';

	$embed_fields = array(
		'Form Title' => $form_title,
		'Form ID'    => $form_id,
		'Entry ID'   => isset( $entry['id'] ) ? $entry['id'] : '',
	);

	if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
		foreach ( $form['fields'] as $field ) {
			if ( ! is_object( $field ) || empty( $field->label ) ) {
				continue;
			}

			$field_id = (string) $field->id;
			$value = rgar( $entry, $field_id );
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			if ( $value === '' || $value === null ) {
				continue;
			}
			$embed_fields[ $field->label ] = $value;
		}
	}

	$sent = wdbm_send_discord_embed(
		'Contact Form Submission',
		'A new Gravity Forms submission was received.',
		$embed_fields,
		10181046,
		'gravity_forms'
	);

	if ( ! $sent ) {
		wdbm_send_failure_embed( 'Gravity Forms', 'Discord webhook failed for Gravity Forms submission.' );
	}
}

// Brevo mailer integration.
add_filter( 'pre_wp_mail', 'wdbm_pre_wp_mail', 10, 2 );

function wdbm_pre_wp_mail( $return, $atts ) {
	$settings = wdbm_get_settings();
	if ( empty( $settings['brevo_enabled'] ) ) {
		return $return;
	}

	if ( ! empty( $settings['brevo_defer_smtp'] ) ) {
		$active_smtp = wdbm_is_other_smtp_active();
		if ( $active_smtp ) {
			wdbm_log_event( 'brevo', 'wp_mail', 'skipped', 'Deferred to ' . $active_smtp, $atts );
			return $return;
		}
	}

	$result = wdbm_send_brevo_email( $atts );
	if ( $result['success'] ) {
		return true;
	}

	$context = 'Brevo mail send';
	$error_message = $result['message'];
	wdbm_log_event( 'brevo', 'wp_mail', 'failed', $error_message, $atts );

	if ( wdbm_discord_enabled() ) {
		wdbm_send_failure_embed( $context, $error_message );
	}

	return false;
}

function wdbm_send_brevo_email( $atts ) {
	$settings = wdbm_get_settings();

	$to = isset( $atts['to'] ) ? $atts['to'] : array();
	$subject = isset( $atts['subject'] ) ? $atts['subject'] : '';
	$message = isset( $atts['message'] ) ? $atts['message'] : '';
	$headers = isset( $atts['headers'] ) ? $atts['headers'] : array();

	if ( ! is_array( $to ) ) {
		$to = array( $to );
	}

	$to_list = array();
	foreach ( $to as $recipient ) {
		$email = is_array( $recipient ) && isset( $recipient['email'] ) ? $recipient['email'] : $recipient;
		$email = sanitize_email( $email );
		if ( empty( $email ) ) {
			continue;
		}
		$to_list[] = array( 'email' => $email );
	}

	if ( empty( $to_list ) ) {
		return array(
			'success' => false,
			'message' => 'No valid recipients provided.',
		);
	}

	$from_email = $settings['brevo_from_email'];
	$from_name = $settings['brevo_from_name'];

	if ( empty( $from_email ) || empty( $from_name ) ) {
		return array(
			'success' => false,
			'message' => 'Brevo sender information is incomplete.',
		);
	}

	$payload = array(
		'sender' => array(
			'email' => $from_email,
			'name'  => $from_name,
		),
		'to'      => $to_list,
		'subject' => $subject,
		'htmlContent' => wpautop( $message ),
	);

	$reply_to = $settings['brevo_reply_to'];
	if ( ! empty( $reply_to ) ) {
		$payload['replyTo'] = array( 'email' => $reply_to );
	}

	$response = wp_remote_post(
		'https://api.brevo.com/v3/smtp/email',
		array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'api-key'      => $settings['brevo_api_key'],
			),
			'timeout' => 10,
			'body'    => wp_json_encode( $payload ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array(
			'success' => false,
			'message' => $response->get_error_message(),
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return array(
			'success' => false,
			'message' => 'Brevo HTTP ' . $code,
		);
	}

	wdbm_log_event( 'brevo', 'wp_mail', 'success', 'Sent', $payload );

	return array(
		'success' => true,
		'message' => 'Sent',
	);
}
