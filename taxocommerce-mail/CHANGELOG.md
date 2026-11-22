# Changelog

## 0.1.1 — 2026-11-20
- Simplify mailbox setup to complete email/username, masked password and secure IMAP hostname; keep SMTP settings as optional advanced controls without removing send/reply.
- Publish an installable TaxoCommerce Mail ZIP and updated manifest in the two authorized test installations, allowing Core to discover/download it without changes to Core.
- Extend regression tests for three-field setup, package contents and manifest isolation. Issue #2890.
- No PRO changes, Gmail integration, automatic sync or additional mail features.


## 0.1.0 — 2026-11-20
- Initial independent multi-account hosting mail client, using IMAPS over verified TLS and authenticated SMTP (465/587).
- Store account credentials with AES-256-GCM protected by WordPress salts; no message copies stored locally.
- Administrator-only inbox, message reading, reply and compose; bounded read-only IMAP, per-account setup.
- Two check views and bundled user/technical guidance.
- Issue #2880. No Gmail, POP3, automatic sync or production deployment.
