=== TaxoCommerce Accounting ===
Contributors: davidperez
Tags: woocommerce, accounting, invoices, vat, bookkeeping
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Independent WooCommerce fiscal register with purchase invoices, monthly VAT estimates and internal exports.

== Description ==

TaxoCommerce Accounting records fiscal data without changing WooCommerce orders, customers, supplier catalogues or invoice generation.

* Configure a per-store first commercial date. All earlier test orders are excluded from new sales imports.
* Manually import paid-status WooCommerce orders in bounded batches of 25, with deduplicated draft entries.
* Capture supplier purchase invoices, business expenses, capital assets and special cross-border operations manually.
* Attach one or more original supplier PDF invoices to each saved purchase-side record (purchases, expenses, assets and intra-EU operations). Store original PDF bytes encrypted; authenticated, nonce-protected downloads only, never a public media URL. Maximum 10 MB per PDF.
* Invoices are uploaded manually after the purchase record exists. This is a secure document archive, NOT automatic invoice parsing, OCR, remote supplier integration or fiscal verification.
* Store taxable base, input/output VAT, total, tax classification, deductible percentage, document number and supplier data.
* Inspect monthly fiscal balances using validated domestic records only.
* Review indicative Model 303 figures and export internal CSV or JSON.
* Hacienda displays read-only fiscal period coverage: unvalidated records, exceptional treatments, missing document numbers and informational missing tax IDs. Empty periods are not assumed to be zero returns; use the sales and purchase review links.
* Tax filing obligations depend on the taxpayer. The screen links to AEAT Model 130 guidance for potentially eligible self-employed taxpayers; Models 130 and 200 are not treated as universally mandatory.
* Hacienda provides accumulated taxable sales and purchase bases, output and deductible input VAT, and an indicative VAT balance for the selected period. The totals refresh whenever the screen is opened but are not official AEAT form boxes.
* Optional hourly WP-Cron-based import of paid WooCommerce orders in small batches (three pages of 25 orders per run), disabled until the merchant enables it. Imported entries are draft and must be reconciled. Shows last check and full-scan time; WP-Cron is dependent on site traffic or a configured scheduler.
* Hacienda workspace: nine sub-tabs for four VAT books and Models 303, 390, 347, 349 and 200. Each sub-tab offers an authenticated internal working CSV/JSON download and an official AEAT information/filing link. Model 303 has a quarter selector; Model 390 shows indicative annual domestic figures. The new Hacienda VAT reports aggregate all ledger entries without the dashboard's 400-row display cap.
* Relevant VAT book and Model 303 tabs link to the AEAT's own EMPTY official XLSX workbook templates (legal-entity and general variants, published 2026). They are linked directly from AEAT; TaxoCommerce does not prefill these templates or claim automatic Pre303 compatibility.
* Run a read-only functional Autocheck with explicit incomplete-data errors.
* Standalone administration if TaxoCommerce Core is absent; nested TaxoCommerce administration when present.
* Uses a dedicated prefix-aware database table and WooCommerce's public order APIs (including HPOS).

Important: Importing all paid WooCommerce orders does not prove all invoices (including unpaid invoices, purchase bills and credit notes) are present. WooCommerce order creation dates may differ from fiscal invoice dates; manual reconciliation is mandatory. Accounting does not store per-tax-rate VAT detail for each invoice, previous return offsets, or all tax adjustments and cannot claim a complete Model 303 return.

Important: Hacienda's downloads are conspicuously labelled **PREPARATORY / NOT OFFICIAL** and are NOT an AEAT-compatible 303/390/347/349/200 electronic declaration or Pre303/LSI XLSX. These views do not perform tax ID validation, full accounting, partner threshold/exclusion checks, filing-calendar eligibility, or complete VAT regime calculations. No data is sent to AEAT; reports are for accountant review only. The financial overview is preliminary, NOT an official VAT form, an accounting system with double-entry books, tax/legal advice or a replacement for invoices. No forms are filed or submitted to the Spanish Tax Agency. Ensure applicability and deductible VAT with a qualified advisor before approving.

== Installation ==

1. Install WooCommerce and activate TaxoCommerce Accounting.
2. Find Accounting under TaxoCommerce if Core exists; otherwise find the Accounting menu.
3. Configure your first real commercial date in Settings before importing orders.
4. Import paid WooCommerce orders page by page. Entries remain drafts.
5. Record actual supplier invoices and expense receipts manually.
6. From the purchase/expense row, attach the original supplier PDF, then verify that its filename appears with a protected download link.
7. Under Hacienda select the applicable tax/book sub-tab and the year (plus quarter for 303); download the working CSV/JSON and use the official AEAT link for the separate authenticated filing process. Verify applicability and correct records independently before filing.
8. Check financial and tax treatment; validate eligible records, then inspect reports and exports. A paper-only invoice may still be tracked as pending digital documentation.

All entries and their encrypted invoice PDF files are retained when the plugin is deactivated or uninstalled; this protects fiscal records. PDF files are encrypted using a key derived from WordPress authentication salts: back up the uploads folder, the plugin tables and the unchanged WordPress salts together. If salts change without a coordinated document re-encryption, old PDFs become unreadable. CSV/JSON ledger exports contain metadata/amounts only, not original PDF documents. The installed server must support OpenSSL and writable WordPress uploads.

== Frequently Asked Questions ==

= Can I submit Hacienda working CSV/JSON downloads to AEAT? =
No. They are internal preparatory reports, NOT valid return files or official Excel books. AEAT uses specific model-by-model schemas and identification; the tabs include external links to AEAT for official preparation and submission. For Model 303, eligible taxpayers may import an AEAT-format XLSX VAT book via Pre303/LSI; TaxoCommerce does not generate that official XLSX yet. SII provides web services for eligible taxpayers, but this plugin does not connect to them.


= Can supplier PDF invoices be attached and recovered? =
Yes. Upload a PDF of up to 10 MB to an existing manual purchase or expense entry. The original is encrypted in the store-specific WordPress uploads directory, and access uses the administrator account with nonce protection. It is not published to the Media Library or included in exports. No PDF data is extracted automatically.

= Does it automatically modify or issue WooCommerce invoices? =
No. Invoice emission and series remain with the shop's existing invoicing solution.

= Can I use it without any other TaxoCommerce plugin? =
Yes. Only WooCommerce is required. TaxoCommerce Core is optional.

= Does the Model 303 screen file my return? =
No. It is a working summary of validated domestic records, not an official AEAT filing.

= Does it treat every business expense as deductible? =
No. Input VAT defaults to 0% deductible and records start in draft. A qualified professional should verify the tax treatment.

== Changelog ==

= 0.1.5 =
* Highlight the active main and Tax Agency tabs in the Accounting admin page with an accessible blue background and white text. Issue #2720.


= 0.1.4 =
* Added continuously recalculated VAT-period totals and opt-in bounded automatic WooCommerce draft imports with progress and reminders about missing tax return inputs. Fixed Autocheck result rendering. Issue #2707.

= 0.1.3 =
* Added fiscal preparation completeness counters and review links for Hacienda, including quarterly Model 303 periods. No extra PDF, official tax-return formats or filing integrations. Issue #2702.

= 0.1.2 =
* Added Hacienda dashboard with nine sub-tabs, secure non-fileable CSV/JSON preparatory exports, 303 quarter and 390 annual domestic summaries without the old 400-entry limit, links to AEAT and compatibility with the old Tax returns URL. No tax submission. Issue #2687.


= 0.1.1 =
* Attach, archive and download original supplier PDF invoices per purchase/expense record through private encrypted storage; preserve old ledger and exports. Issue #2681.


= 0.1.0 =
* Initial standalone accounting register, fiscal summaries, draft WooCommerce import, manual invoices, CSV/JSON exports and functional Autocheck.

== Privacy ==

Stores customer names, invoice numbers and supplier tax identifiers in an administrator-only database table, plus encrypted supplier PDF evidence in WordPress uploads. PDF ciphertext may reside in web-accessible filesystem paths but never exposes the original document without the encryption key. Downloads require manager capability and nonce. Does not transmit data externally.
