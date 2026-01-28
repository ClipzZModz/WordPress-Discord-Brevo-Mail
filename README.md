# WordPress Discord & Brevo Mail

WordPress plugin that:
- Sends Discord embed notifications for Contact Form 7 submissions.
- Routes all WordPress email through Brevo when enabled (exclusive mailer).
- Records successful and failed sends in a log table with an admin log view.

## What it does
Discord:
- Posts an embed to your Discord webhook on each Contact Form 7 submission.
- If a notification fails, it logs the failure and attempts to send a failure embed.

Brevo:
- Replaces the default WordPress mailer when enabled.
- Sends all `wp_mail` traffic through Brevo using your configured sender details.
- Logs success/failure for each email send.

## Configuration
- Place project in own own folder in wp-content/plugins
In wp-admin, set:
- Discord: enable + webhook URL.
- Brevo: enable + API key + from email + from name + reply-to.

## Logs
Send logs (success/failure) are stored in a dedicated table and viewable in wp-admin.

## License
GPL-2.0-or-later. See `LICENSE`.
