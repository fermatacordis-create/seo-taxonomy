=== TaxoCommerce Mail ===
Contributors: davidperezmartorell
Tags: email, imap, smtp, webmail, multiple-accounts
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage several hosting email accounts from the WordPress administration interface.

== Description ==

* Configure separate mailboxes from your hosting provider using IMAPS with certificate verification.
* Read and reply to messages in the inbox; send plain-text email using authenticated SMTP with TLS.
* Each account keeps its own inbox; unlimited accounts are not claimed (maximum 20).
* Credentials are encrypted at rest using AES-256-GCM and key material derived from WordPress authentication salts.
* Access is restricted to WordPress administrators in this release.
* Read-only IMAP operations do not mark emails as read, move or delete them.
* Emails remain on the original mail server. No inbox copies, message bodies or attachments are stored in WordPress.
* Independent of TaxoCommerce Core, Tasks and WooCommerce; optional Core module discovery.
* No Gmail OAuth, external service, POP3, automatic syncing, remote-image fetching, bulk sending or attachment upload.

== Installation ==

1. Activate TaxoCommerce Mail.
2. Open WordPress > Mail > Accounts and add a mailbox. Use your provider's exact SSL settings.
3. Enter the mailbox's complete email address (username), password and secure IMAP hostname (TLS port 993). The password field is masked and never prefilled.
4. Optionally expand Advanced details to configure a separate SMTP host/port or display name; these are not required for reading mail.
5. Test IMAP and open Inbox. SMTP sends remain available but should be tested separately.

To install an inactive or missing plugin from the TaxoCommerce family, Core can discover a local verified Mail ZIP in the site's uploads/taxocommerce-core/plugins package directory. No other TaxoCommerce plugin is required for Mail to run.

At Webempresa, obtain the correct hostnames under WePanel > Email > Email Clients or configuration details. The plugin cannot create server mailboxes.

== Frequently Asked Questions ==

= Is PHP's native IMAP extension necessary? =
No. The plugin uses secure IMAP sockets and does not require the native PHP IMAP extension.

= Will WordPress store my emails? =
No. It stores only account settings, encrypted passwords and brief diagnostic state. The mail server retains the messages.

= Does it change WooCommerce notifications? =
No. SMTP configured for a mailbox in this plugin is used only by this plugin, not by WordPress wp_mail() or WooCommerce.

= Can I share mailboxes with employees? =
No. In this initial version, only administrators can access mail and manage accounts.

== Changelog ==

= 0.1.1 =
* Simple three-field hosting mailbox setup; previous advanced SMTP options preserved.
* Publish the independent Mail installable ZIP through the existing Core package catalogue on the two authorized test sites.


= 0.1.0 =
* Initial version with multi-account hosting mail, private inbox, SMTP, settings and checks.
