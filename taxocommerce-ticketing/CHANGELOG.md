# Changelog

## 0.1.2 — 2026-11-20
- Fix false positive `created` message for honeypot rejection; report failure without generating a ticket.
- Preserve inserted message ID before a separate SQL update and surface submission outcomes through privacy-minimal diagnostic events.
- Add staff Activity log limited to 150 recent events (UTC timestamp, fixed event code and ticket ID; no IP, email or messages), JSON export.
- Split Autocheck into Functionality Check (synthetic ticket/message/attachment create-read-delete with cleanup and real counts) and independent 5-speed Code Check (PHP, WordPress, security), with JSON exports.
- Add WordPress-admin User Manual and Technical Manual; retain plugin independence.
- GitHub Issue #2874. Tests and WordPress.org Plugin Check required before merge and test-site deployment.


## 0.1.1 — 2026-11-20
- Prepared dual-site first installation, Core extension management and public plugin information page on Focazul (Issue #2871).


## Unreleased — WordPress.org preflight corrections
- Replace computed gettext domains with literal plugin text domains.
- Clarify ordered placeholders and translator comments for WordPress Plugin Check.
- Strengthen request sanitization, safely bound nonce-check annotations and add the language directory.
- Follow-up PR #2867, Issue #2862.

## 0.1.0 — 2026-11-20
- Initial independent Ticketing plugin and dedicated private database schema.
- Customer form shortcode, editable labels, request categories, branding and email routing.
- Ticket statuses, priorities, assignees, private and public messages, protected attachments.
- Private customer reply link and staff notifications.
- Optional integration hook for future Tasks adapter, no Core dependency.
- References #2862.
- #2870: first test-site delivery of the complete, independent plugin to STAGING and Focazul, with no automatic activation and no production rollout.
