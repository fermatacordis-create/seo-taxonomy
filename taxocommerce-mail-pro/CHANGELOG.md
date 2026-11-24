# Changelog

## 0.1.0 — 2026-11-23
- Initial independent TaxoCommerce Mail Pro plugin. Separate account option `tcmailpro_accounts`, classes `TCMAILPRO_*`, and menu page `taxocommerce-mail-pro`.
- Uses the same bounded TLS IMAP/isolated PHPMailer approach as the free Mail Lite source, augmented with server folders, pages, IMAP text search, nonce-guarded actions, safe RFC 6851 UID MOVE, Trash, restoring to Inbox, and read-only message viewing.
- Save accepted SMTP MIME to server Sent folder using IMAP APPEND, with explicit warning when the append fails; messages are not stored by WordPress.
- Includes separate functionality/code checks, JSON export, administrative manual tabs and native WordPress branding.
- Deliberately no permanent deletion, silent migration, cron, background sync, attachment downloads or public endpoints.
- This is a staged test candidate; not an audited third-party IMAP library and not commercially published. Issue #2944.
