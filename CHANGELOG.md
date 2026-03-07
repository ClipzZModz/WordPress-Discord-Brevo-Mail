# Changelog

All notable changes to this project will be documented in this file.

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
