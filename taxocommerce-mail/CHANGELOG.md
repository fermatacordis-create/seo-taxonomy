# Changelog

## 0.1.3 — 2026-11-23 — Mail Lite edition and Tasks presentation (#2940)
- Identify this standalone free plugin as **TaxoCommerce Mail Lite** in the WordPress plugin header, family catalog and shared three-zone admin header, and remove the duplicated admin title.
- Preserve directory and plugin basename `taxocommerce-mail`, text domain, configured accounts, encrypted credentials, IMAP and SMTP behavior, and previous account options.
- Follow Tasks conventions for the two manuals and independent functionality/code checks, with the existing JSON exports and a concise last-run summary.
- Show a separate **Pro** information tab describing a future optional add-on; no hidden premium code, purchases, licences, or remote dependency.
- Document the Lite limitations honestly (last 12 inbox messages; no Sent folder management or mailbox deletion).
- Prepare the package for the official WordPress Plugin Check and test-only deployment; public WordPress.org SVN publication remains gated by repository approval and the dedicated manual workflow.


## 0.1.2 — 2026-11-22
- Add full gettext catalogs with 100 user-facing strings in Spanish, English, French, German and Dutch, covering mailbox administration, messages, Autocheck, user/technical guides and plugin branding.
- Bundle five PO/MO catalogs and POT extraction template, while preferring official WordPress language packs when present.
- Preserve mailbox accounts, encryption, IMAP/SMTP behavior, message storage policy and existing capabilities. Issue #2932.
- Test-only package publication; no production deployment.

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
