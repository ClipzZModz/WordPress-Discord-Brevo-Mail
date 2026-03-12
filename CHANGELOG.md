# Changelog

All notable changes to this project will be documented in this file.

## [0.1.3] - 2026-03-12
### Changed
- Added stricter Discord webhook URL validation (HTTPS + Discord host + webhook path format).
- Added Discord payload guardrails to respect embed limits (field count, per-field length, and overall embed size).
- Added webhook retry behavior for transient failures, including HTTP 429 rate limits and 5xx server responses.
- Improved Discord attempt logging with per-attempt metadata for easier troubleshooting.
- Added channel filters to the logs page (`All Logs`, `Discord`, `Brevo`) for faster troubleshooting.
- Added per-row `View` action on logs to expand technical payload details for both Discord and Brevo entries.

## [0.1.0] - 2026-01-28
### Added
- Discord embed notifications for Contact Form 7 submissions.
- Brevo-only mail routing for `wp_mail`.
- Admin settings page for Discord and Brevo.
- Send log table and admin log viewer.
- GPL-2.0-or-later license file.

## [0.1.1] - 2026-01-28
### Added
- WPForms and Gravity Forms Discord embed notifications.
- Brevo defer mode when another SMTP plugin is active.

## [0.1.2] - 2026-03-07
### Changed
- Expanded Discord logging to record skipped reasons when Discord is disabled or misconfigured.
- Added explicit Discord webhook attempt logs before each outbound request.
- Improved Discord failure logs to include HTTP response details for webhook troubleshooting.
- Added skip diagnostics in CF7, WPForms, and Gravity Forms handlers when Discord cannot run.
