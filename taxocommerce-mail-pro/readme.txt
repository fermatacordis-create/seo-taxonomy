=== TaxoCommerce Mail Pro ===
Contributors: davidperezmartorell
Tags: imap, smtp, email, folders, webmail
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Independent Pro mail administration for hosting accounts: Inbox, Sent, folders, search, moving selected messages, Trash and restore.

== Description ==

TaxoCommerce Mail Pro is a separate standalone plugin. It does not require TaxoCommerce Mail Lite, TaxoCommerce Core, WooCommerce or any other TaxoCommerce extension. No Lite options, accounts or email messages are modified.

= Features =
* Up to 20 mailboxes, configured with full address, encrypted password and secure IMAP/SMTP hosting servers.
* Read Inbox, Sent, Trash and existing server-side folders. Select a mailbox from the account selector.
* Search on the IMAP server and navigate messages in pages of 20.
* Read and reply without changing the read/unread status. HTML is displayed as escaped plain text; remote images are not loaded.
* Send plain text with the bundled WordPress PHPMailer (TLS SMTP 465 or STARTTLS 587). After SMTP acceptance, append an on-server copy to Sent when a usable Sent folder exists. If archiving fails, show a separate warning.
* Create or rename personal folders with strict validation. System folders cannot be renamed.
* Select up to 20 messages and move them to an existing folder, move to Trash or restore from Trash to Inbox.
* Mutating operations are administrator-only, nonce-validated, explicitly confirmed and restricted to listed server folders. IMAP MOVE (RFC 6851) is mandatory for moving; no unsafe COPY+EXPUNGE fallback.
* No permanent deletion, background synchronization, attachment uploads, Gmail OAuth, global WordPress SMTP modification or WooCommerce email changes.
* Functionality and code self-checks, JSON report export, user and technical manuals, localized admin shell.

= Limitations =
Pro 0.1.0 is a test release. The IMAP engine is the independently packaged protocol transport adapted from Mail Lite, not yet a vendored third-party library. Live SMTP and IMAP folder mutation must be tested against dedicated accounts before commercial distribution. Folder creation uses conservative ASCII names; existing server folders can contain encoded international names. Servers without RFC MOVE will reject moving or trashing without touching messages. No backups or permanent deletion are provided. All emails remain on the provider's server.

== Installation ==

1. Install the separate `taxocommerce-mail-pro` ZIP and activate it.
2. Open Mail Pro > Accounts. Configure the full email address, hidden password and secure IMAP host (TLS 993); the SMTP host defaults to the same host and port 465.
3. Test IMAP and send a controlled SMTP message to an account you own.
4. Confirm Sent and Trash are available on your server; open Inbox, Sent, Folders and Trash. Move and restore on an expendable test mailbox before regular use.
5. Review the User Manual, Technical Manual, Functionality Check and Code Check tabs.

The Lite and Pro editions may coexist: their option keys, classes, admin menu slugs and folder names differ. Account details are configured independently, not migrated silently.

== Frequently Asked Questions ==

= Does Pro require Lite or Core? =
No. It is completely independent. It integrates optionally into the Core menu and extension catalog when Core is present.

= Will moving selected emails affect the original inbox? =
Yes. These are explicit server-side IMAP operations. Use a disposable mailbox for initial testing. Pro does not permanently delete mail.

= How are Sent messages stored? =
After a successful SMTP send, Pro attempts to append a copy to the IMAP Sent folder. If the folder is absent or the command fails, it displays a warning rather than claiming the message was not sent.

= Can any WordPress user see the email? =
No. Only administrators with `manage_options` may use this first version.

== Changelog ==

= 0.1.0 =
* Initial standalone Pro version with encrypted accounts, multi-folder browser, server search, paginated listing, safe IMAP MOVE/Trash/restore and Sent archive.
* Strictly test-site delivery only; live cross-provider mailbox testing pending.
