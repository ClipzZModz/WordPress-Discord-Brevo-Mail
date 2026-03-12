<?php
/**
 * Plugin Name: WordPress Discord & Brevo Mail
 * Description: Sends Discord embeds for Contact Form 7 submissions and routes WordPress mail through Brevo when enabled.
 * Version: 0.1.3
 * Author: Jack Parlby
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WDBM_VERSION', '0.1.3' );
define( 'WDBM_OPTION_KEY', 'wdbm_settings' );
define( 'WDBM_LOG_TABLE', 'wdbm_logs' );
define( 'WDBM_DISCORD_MAX_FIELDS', 25 );
define( 'WDBM_DISCORD_MAX_TITLE', 256 );
define( 'WDBM_DISCORD_MAX_DESCRIPTION', 4096 );
define( 'WDBM_DISCORD_MAX_FIELD_NAME', 256 );
define( 'WDBM_DISCORD_MAX_FIELD_VALUE', 1024 );
define( 'WDBM_DISCORD_MAX_TOTAL_CHARS', 6000 );

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
	if ( ! empty( $clean['discord_webhook'] ) && ! wdbm_is_valid_discord_webhook( $clean['discord_webhook'] ) ) {
		$clean['discord_webhook'] = '';
	}

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
	$channel = isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : 'all';
	$allowed_channels = array( 'all', 'discord', 'brevo' );
	if ( ! in_array( $channel, $allowed_channels, true ) ) {
		$channel = 'all';
	}

	$where_sql = '';
	$where_args = array();
	if ( $channel !== 'all' ) {
		$where_sql = ' WHERE channel = %s';
		$where_args[] = $channel;
	}

	$count_sql = "SELECT COUNT(*) FROM {$table_name}{$where_sql}";
	if ( ! empty( $where_args ) ) {
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $where_args ) );
	} else {
		$total = (int) $wpdb->get_var( $count_sql );
	}

	$list_sql = "SELECT * FROM {$table_name}{$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
	$list_args = $where_args;
	$list_args[] = $per_page;
	$list_args[] = $offset;
	$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ) );

	$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
	$base_url = remove_query_arg( array( 'paged', 'channel' ) );
	$channel_links = array(
		'all'     => add_query_arg( 'channel', 'all', $base_url ),
		'discord' => add_query_arg( 'channel', 'discord', $base_url ),
		'brevo'   => add_query_arg( 'channel', 'brevo', $base_url ),
	);
	?>
	<div class="wrap">
		<h1>Send Logs</h1>
		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( $channel_links['all'] ); ?>" class="<?php echo ( 'all' === $channel ) ? 'current' : ''; ?>">All Logs</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( $channel_links['discord'] ); ?>" class="<?php echo ( 'discord' === $channel ) ? 'current' : ''; ?>">Discord</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( $channel_links['brevo'] ); ?>" class="<?php echo ( 'brevo' === $channel ) ? 'current' : ''; ?>">Brevo</a>
			</li>
		</ul>
		<style>
			.wdbm-log-details {
				display: none;
				background: #f6f7f7;
			}
			.wdbm-log-details.open {
				display: table-row;
			}
			.wdbm-log-details pre {
				margin: 0;
				padding: 12px;
				background: #fff;
				border: 1px solid #dcdcde;
				white-space: pre-wrap;
				word-break: break-word;
			}
		</style>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th>Date</th>
					<th>Channel</th>
					<th>Event</th>
					<th>Status</th>
					<th>Message</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr>
						<td colspan="6">No logs yet.</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$details_id = 'wdbm-log-details-' . absint( $row->id );
						$decoded_payload = ! empty( $row->payload ) ? json_decode( $row->payload, true ) : null;
						if ( is_array( $decoded_payload ) ) {
							$payload_text = wp_json_encode( $decoded_payload, JSON_PRETTY_PRINT );
						} elseif ( is_string( $row->payload ) && $row->payload !== '' ) {
							$payload_text = $row->payload;
						} else {
							$payload_text = '';
						}
						?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><?php echo esc_html( $row->channel ); ?></td>
							<td><?php echo esc_html( $row->event ); ?></td>
							<td><?php echo esc_html( $row->status ); ?></td>
							<td><?php echo esc_html( $row->message ); ?></td>
							<td>
								<?php if ( '' !== $payload_text ) : ?>
									<button type="button" class="button button-small wdbm-view-log" data-target="<?php echo esc_attr( $details_id ); ?>">View</button>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( '' !== $payload_text ) : ?>
							<tr id="<?php echo esc_attr( $details_id ); ?>" class="wdbm-log-details">
								<td colspan="6">
									<strong>Technical Details</strong>
									<pre><?php echo esc_html( $payload_text ); ?></pre>
								</td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					echo paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%', $base_url ),
							'add_args'  => array( 'channel' => $channel ),
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
	<script>
		document.addEventListener('click', function(event) {
			var button = event.target.closest('.wdbm-view-log');
			if (!button) {
				return;
			}
			var targetId = button.getAttribute('data-target');
			if (!targetId) {
				return;
			}
			var detailRow = document.getElementById(targetId);
			if (!detailRow) {
				return;
			}
			var isOpen = detailRow.classList.toggle('open');
			button.textContent = isOpen ? 'Hide' : 'View';
		});
	</script>
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
	$discord_status = wdbm_get_discord_status();
	return ! empty( $discord_status['enabled'] );
}

function wdbm_is_valid_discord_webhook( $url ) {
	if ( empty( $url ) || ! wp_http_validate_url( $url ) ) {
		return false;
	}

	$parts = wp_parse_url( $url );
	if ( empty( $parts['scheme'] ) || strtolower( $parts['scheme'] ) !== 'https' ) {
		return false;
	}

	$host = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	$allowed_hosts = array(
		'discord.com',
		'discordapp.com',
		'ptb.discord.com',
		'canary.discord.com',
	);
	if ( ! in_array( $host, $allowed_hosts, true ) ) {
		return false;
	}

	$path = isset( $parts['path'] ) ? $parts['path'] : '';
	return (bool) preg_match( '#^/api(?:/v\d+)?/webhooks/\d+/[A-Za-z0-9_-]+$#', $path );
}

function wdbm_discord_string_length( $value ) {
	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $value );
	}

	return strlen( $value );
}

function wdbm_discord_string_slice( $value, $max_length ) {
	if ( $max_length <= 0 ) {
		return '';
	}

	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $value, 0, $max_length );
	}

	return substr( $value, 0, $max_length );
}

function wdbm_discord_trim_to_limit( $value, $max_length ) {
	$value = wp_strip_all_tags( (string) $value );
	if ( wdbm_discord_string_length( $value ) <= $max_length ) {
		return $value;
	}

	if ( $max_length <= 3 ) {
		return wdbm_discord_string_slice( $value, $max_length );
	}

	return wdbm_discord_string_slice( $value, $max_length - 3 ) . '...';
}

function wdbm_discord_embed_total_chars( $embed ) {
	$total = 0;
	$total += wdbm_discord_string_length( isset( $embed['title'] ) ? (string) $embed['title'] : '' );
	$total += wdbm_discord_string_length( isset( $embed['description'] ) ? (string) $embed['description'] : '' );

	if ( ! empty( $embed['fields'] ) && is_array( $embed['fields'] ) ) {
		foreach ( $embed['fields'] as $field ) {
			$total += wdbm_discord_string_length( isset( $field['name'] ) ? (string) $field['name'] : '' );
			$total += wdbm_discord_string_length( isset( $field['value'] ) ? (string) $field['value'] : '' );
		}
	}

	return $total;
}

function wdbm_build_discord_payload( $title, $description, $fields, $color ) {
	$embed_fields = array();
	$field_count = 0;
	$omitted_fields = 0;

	foreach ( $fields as $name => $value ) {
		if ( $field_count >= WDBM_DISCORD_MAX_FIELDS ) {
			$omitted_fields++;
			continue;
		}

		$field_name = wdbm_discord_trim_to_limit( $name, WDBM_DISCORD_MAX_FIELD_NAME );
		$field_value = wdbm_discord_trim_to_limit( $value, WDBM_DISCORD_MAX_FIELD_VALUE );
		if ( $field_name === '' ) {
			$field_name = 'Field';
		}
		if ( $field_value === '' ) {
			$field_value = '(empty)';
		}

		$embed_fields[] = array(
			'name'   => $field_name,
			'value'  => $field_value,
			'inline' => false,
		);
		$field_count++;
	}

	$embed = array(
		'title'       => wdbm_discord_trim_to_limit( $title, WDBM_DISCORD_MAX_TITLE ),
		'description' => wdbm_discord_trim_to_limit( $description, WDBM_DISCORD_MAX_DESCRIPTION ),
		'color'       => (int) $color,
		'fields'      => $embed_fields,
		'timestamp'   => gmdate( 'c' ),
	);

	$total_chars = wdbm_discord_embed_total_chars( $embed );
	if ( $total_chars > WDBM_DISCORD_MAX_TOTAL_CHARS ) {
		$overflow = $total_chars - WDBM_DISCORD_MAX_TOTAL_CHARS;
		$description_length = wdbm_discord_string_length( $embed['description'] );
		if ( $description_length > 0 ) {
			$new_description_length = max( 0, $description_length - $overflow );
			$embed['description'] = wdbm_discord_trim_to_limit( $embed['description'], $new_description_length );
		}
	}

	while ( wdbm_discord_embed_total_chars( $embed ) > WDBM_DISCORD_MAX_TOTAL_CHARS && ! empty( $embed['fields'] ) ) {
		array_pop( $embed['fields'] );
		$omitted_fields++;
	}

	if ( $omitted_fields > 0 ) {
		$suffix = ' (Additional fields omitted: ' . $omitted_fields . ')';
		$max_description = WDBM_DISCORD_MAX_DESCRIPTION - wdbm_discord_string_length( $suffix );
		$embed['description'] = wdbm_discord_trim_to_limit( $embed['description'], max( 0, $max_description ) ) . $suffix;
	}

	while ( wdbm_discord_embed_total_chars( $embed ) > WDBM_DISCORD_MAX_TOTAL_CHARS && ! empty( $embed['fields'] ) ) {
		array_pop( $embed['fields'] );
	}

	return array(
		'content' => '',
		'embeds'  => array( $embed ),
	);
}

function wdbm_discord_get_retry_delay_ms( $response ) {
	$default_delay_ms = 1000;
	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		return $default_delay_ms;
	}

	$data = json_decode( $body, true );
	if ( ! is_array( $data ) || ! isset( $data['retry_after'] ) ) {
		return $default_delay_ms;
	}

	$retry_after = (float) $data['retry_after'];
	if ( $retry_after <= 0 ) {
		return $default_delay_ms;
	}

	// Some APIs return seconds; others return milliseconds.
	if ( $retry_after < 100 ) {
		return (int) ceil( $retry_after * 1000 );
	}

	return (int) ceil( $retry_after );
}

function wdbm_discord_sleep_ms( $delay_ms ) {
	$delay_ms = max( 0, (int) $delay_ms );
	if ( $delay_ms > 0 ) {
		usleep( $delay_ms * 1000 );
	}
}

function wdbm_get_discord_status() {
	$settings = wdbm_get_settings();

	if ( empty( $settings['discord_enabled'] ) ) {
		return array(
			'enabled' => false,
			'message' => 'Discord disabled in settings.',
		);
	}

	if ( empty( $settings['discord_webhook'] ) ) {
		return array(
			'enabled' => false,
			'message' => 'Discord webhook URL is missing.',
		);
	}

	if ( ! wdbm_is_valid_discord_webhook( $settings['discord_webhook'] ) ) {
		return array(
			'enabled' => false,
			'message' => 'Discord webhook URL is invalid. Use a full Discord webhook URL.',
		);
	}

	return array(
		'enabled' => true,
		'message' => 'Discord is enabled and ready.',
	);
}

function wdbm_send_discord_embed( $title, $description, $fields = array(), $color = 3447003, $event = 'unknown' ) {
	$discord_status = wdbm_get_discord_status();
	if ( ! $discord_status['enabled'] ) {
		wdbm_log_event( 'discord', $event, 'skipped', $discord_status['message'] );
		return false;
	}

	$settings = wdbm_get_settings();
	$webhook = $settings['discord_webhook'];

	$payload = wdbm_build_discord_payload( $title, $description, $fields, $color );
	$max_attempts = 3;

	for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
		$attempt_payload = $payload;
		$attempt_payload['attempt'] = $attempt;
		wdbm_log_event( 'discord', $event, 'attempt', 'Sending webhook request.', $attempt_payload );

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
			$attempt_payload['error'] = $response->get_error_message();
			if ( $attempt < $max_attempts ) {
				wdbm_discord_sleep_ms( 750 );
				continue;
			}
			wdbm_log_event( 'discord', $event, 'failed', $response->get_error_message(), $attempt_payload );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			wdbm_log_event( 'discord', $event, 'success', 'Sent', $attempt_payload );
			return true;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$attempt_payload['response_code'] = $code;
		if ( ! empty( $response_body ) ) {
			$attempt_payload['response_body'] = wp_strip_all_tags( (string) $response_body );
		}

		if ( $code === 429 && $attempt < $max_attempts ) {
			$delay_ms = min( 5000, max( 250, wdbm_discord_get_retry_delay_ms( $response ) ) );
			$attempt_payload['retry_delay_ms'] = $delay_ms;
			wdbm_log_event( 'discord', $event, 'failed', 'HTTP 429 (rate limited), retrying.', $attempt_payload );
			wdbm_discord_sleep_ms( $delay_ms );
			continue;
		}

		if ( $code >= 500 && $attempt < $max_attempts ) {
			wdbm_log_event( 'discord', $event, 'failed', 'HTTP ' . $code . ' (server error), retrying.', $attempt_payload );
			wdbm_discord_sleep_ms( 1000 );
			continue;
		}

		wdbm_log_event( 'discord', $event, 'failed', 'HTTP ' . $code, $attempt_payload );
		return false;
	}

	wdbm_log_event( 'discord', $event, 'failed', 'Webhook send failed after retries.', $payload );
	return false;
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
	$discord_status = wdbm_get_discord_status();
	if ( ! $discord_status['enabled'] ) {
		wdbm_log_event( 'discord', 'contact_form_7', 'skipped', $discord_status['message'] );
		return;
	}

	if ( ! class_exists( 'WPCF7_Submission' ) ) {
		wdbm_log_event( 'discord', 'contact_form_7', 'skipped', 'WPCF7_Submission class not available.' );
		return;
	}

	$submission = WPCF7_Submission::get_instance();
	if ( ! $submission ) {
		wdbm_log_event( 'discord', 'contact_form_7', 'skipped', 'No Contact Form 7 submission instance found.' );
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
	$discord_status = wdbm_get_discord_status();
	if ( ! $discord_status['enabled'] ) {
		wdbm_log_event( 'discord', 'wpforms', 'skipped', $discord_status['message'] );
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
	$discord_status = wdbm_get_discord_status();
	if ( ! $discord_status['enabled'] ) {
		wdbm_log_event( 'discord', 'gravity_forms', 'skipped', $discord_status['message'] );
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
