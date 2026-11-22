=== TaxoCommerce Accounting ===
Contributors: davidperez
Tags: woocommerce, accounting, invoices, vat, bookkeeping
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.1.34
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Independent WooCommerce fiscal register with purchase invoices, monthly VAT estimates and internal exports.

== Description ==

TaxoCommerce Accounting records fiscal data without changing WooCommerce orders, customers, supplier catalogues or invoice generation.

= Key Features =

* **Fixed assets:** new depreciable assets are registered in Assets with in-service date and useful life; existing fixed assets recorded in Expenses remain available there, without duplicating invoice, bank movement or VAT. Annual depreciation is read-only and separate from the VAT investment-goods rules.


* **Opening-loan report:** A loan already funded through an existing bank opening balance is displayed as an initial bank inflow in its loan-start month in Treasury reports, with no duplicate movement or bank balance change. A separate financial section in the Summary tab and additive `financial_summary` in the JSON export show the linked financing and outstanding loan balance. Fiscal sales and VAT are not increased.

* **Payment status clarity:** a formally validated invoice with its full payment linked displays as closed even when historical imported notes contain `[scope=review]`. Missing-payment warnings depend on the actual outstanding settlement; formal fiscal review warnings and notes remain unchanged. No bank or VAT entries are created or altered.

* **Correction to draft:** an already validated accounting entry can be returned to draft, with no change to its amounts or invoice reference, when an administrator supplies a correction reason. The status transition and editable Notes are recorded in the existing audit history; untouched drafts are not logged again. The operation does not modify WooCommerce, issued invoices, supplier files or actual Treasury movements.


* **Actionable fiscal review:** the monthly Summary links outstanding approval counts to their exact records, or to a filtered month list when several need attention. A separate incident count distinguishes verified invoice mismatches from zero-VAT and missing-document warnings; existing correction, validation and audited history controls are reused. Autocheck and ledger rows include contextual review links. No changes to approved invoices, WooCommerce orders, stock, VAT calculations or Treasury.


* **Expense classification:** expenses are current by default. Optionally mark an office computer or other durable business equipment as a fixed asset, enter its date put into service and useful life (1–50 years), and see yearly straight-line depreciation with prorated partial years under Expenses. Nonrecoverable input VAT is included in asset cost. Previously saved expenses remain current; changes use the audited correction form. This calculation does not book bank payments, alter invoice VAT or assert the applicable tax deduction; accounting and fiscal depreciation rules should be verified independently.


* **Rectifying invoices:** when an independent invoicing plugin supplies an issued, verified VAT or gross-amount rectification through the documented WordPress filter/action, reconcile a pristine sale entry to the new fiscal totals as a draft requiring approval. Keep the original invoice and WooCommerce order unchanged, preserve existing receipts and show any remaining amount due. Do not silently replace manually edited entries or create second sales/payments. If the corrected gross is less than already collected, human refund/reconciliation is required. Accounting works without Billing.
* **Loan financing:** Treasury can record a fixed annual nominal interest rate (TIN), duration (months), down payment, optional extra final balloon principal, and due dates. The forecast separates capital and interest; the last payment clears rounding differences. A loan disbursement only enters bank on explicit confirmation. Confirm actual instalments individually to create a linked Treasury bank/cash outflow and reduce outstanding debt. Previous manually managed loans remain editable. No external bank connection, automatic collection, contractual APR/TAE calculation, or automatic VAT return impact; consult the finance contract for fees and tax treatment.


* **Supplier purchases:** import/export CSV in **Received invoices** (goods purchased for resale). Download a CSV template, import bounded batches as unvalidated purchase drafts, then attach the original PDF or image to each record.
* **Service/operating expenses:** import/export CSV in **Expenses**, also accessible under **Treasury** for existing workflows. Domains, hosting, digital certificates, AI/software and similar bills are kept as unvalidated expense drafts, separate from supplier merchandise.
* Original receipt uploads accept PDF, JPG/JPEG, PNG and WEBP up to 10 MB each. Originals are encrypted in WordPress uploads under taxocommerce-accounting/encrypted-invoices and downloaded only by authorised users via nonce-protected admin actions. Multiple originals per entry are supported. They are **not** added to the public media library.
* CSVs import invoice metadata only; originals are attached to the corresponding purchase/expense row separately. Back up the plugin database tables and encrypted uploads together, and preserve the same WordPress auth/secure-auth salts to keep archived originals decryptable.

* In the Treasury tab, import/export CSV of ordinary expenses (domains, hosting, certificates, postal costs, software, and fees), downloading a blank template or exporting the current financial year. Imported rows enter the existing Expenses ledger as drafts requiring fiscal validation. CSV does not imply payment: actual cash/bank movements can be linked to the invoice later using the Record payment/receipt control. Prior-activity expenses retain their original dates. Repeated documents are skipped, each upload is limited to 500 rows / 2 MB, and the CSV exporter guards spreadsheet-formula injection. Manually attach original expense PDFs through the existing secure expense document controls.
* Optional **Invoice already paid** confirmation when saving or validating an invoice: select the actual bank/cash account, payment date and amount so Treasury posts the movement immediately. In Treasury, **Reconcile previously paid invoices** posts selected historical payments after explicit confirmation, with editable payment dates and duplicate protection. Never select unpaid invoices. CSV imports remain drafts and never assume payments.
* Confirm full or partial settlement of each invoice using its payment/receipt control, choosing the actual bank or cash account, date and amount. Every confirmed settlement creates one linked Treasury entry; draft imports and fiscal validation alone never move funds. Review older manual Treasury movements first to avoid duplicate historical payments.
* Optional confirmed WooCommerce bank receipts: choose a real bank account and minimum payment date in Accounting settings. An issued invoice, matching order totals and a WooCommerce payment date are required. Disabled until configured; manually posted receipts must be reconciled before enabling.
* Reconcile paid supplier invoices **and already received customer sales** from Treasury. Select only confirmed payments/receipts and their actual bank/cash dates. Fiscal validation alone does not move money.
* Convert a verified opening balance into an actual dated cash/bank receipt, retaining the same account balance and preventing duplicate funding. A starting loan must still be recorded independently in the loan register.
* Track separate cash and multiple bank accounts in the Treasury tab: opening balances, manual receipts/payments, atomic internal transfers, current balances, and a monthly cash-flow table. These figures do not imply that a booked invoice is collected or paid.
* Track loans with original amount, lender, monthly instalment, next payment date and manually confirmed outstanding balance; no inferred interest or automatic debiting.
* Configure a per-store first commercial date. All earlier test orders are excluded from new sales imports.
* Manually import paid-status WooCommerce orders in bounded batches of 25, with deduplicated draft entries.
* Optionally receive a new issued invoice through a standard WordPress action when TaxoCommerce Billing is active, and read its immutable base, VAT, total and number into a new draft or untouched imported draft. Never adjust an issued invoice or approved ledger entry. New and existing draft sales display the matching invoice number automatically; manual entry remains possible with other invoicing systems. Proformas, quotes and refunds are not automatically associated. New imported amounts require fiscal review and validation. Conflicting issued-invoice amounts block approval.
* Capture supplier purchase invoices, business expenses, capital assets and special cross-border operations manually. Supplier VAT deduction percentage is explicitly required (not prefilled at 0), with guidance and live preview of invoice total and deductible input VAT; 100% is only appropriate if the input VAT is legally fully deductible.
* Domestic WooCommerce sale drafts with positive base and zero VAT prompt an additional acknowledgement before fiscal validation; the plugin does not invent tax rates or overwrite issued invoices.
* Attach one or more original supplier PDF invoices to each saved purchase-side record (purchases, expenses, assets and intra-EU operations). Store original PDF bytes encrypted; authenticated, nonce-protected downloads only, never a public media URL. Maximum 10 MB per PDF.
* Invoices are uploaded manually after the purchase record exists. This is a secure document archive, NOT automatic invoice parsing, OCR, remote supplier integration or fiscal verification.
* Store taxable base, input/output VAT, total, tax classification, deductible percentage, document number and supplier data.
* Correct previously validated accounting entries through a permission-protected editor with a mandatory reason and persistent before/after audit history. A correction returns the entry to draft and removes it from validated tax totals until it is checked again. It NEVER changes an issued invoice, its original PDF or a WooCommerce order; an erroneous issued invoice needs a separate legally valid rectification.
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

= 0.1.34 =
* Localize 156 outstanding administrative strings across Accounting, Hacienda, Treasury, purchases and corrections in five languages; regenerate MO catalogs.


= 0.1.30 =
* Localize incident review, accounting corrections and payment/receipt controls in five languages.


= 0.1.21 =
* Localize expense type and fixed-asset expense form in five languages.


= 0.1.19 =
* Localize the Treasury administration tab in five languages.


= 0.1.18 =
* Translate Treasury controls and help text in five languages and compile their MO catalogs.


= 0.1.12 =
* Distinct bulk CSV import/export of supplier merchandise purchases and operating/service expenses, linked to encrypted PDF/JPG/PNG/WEBP originals in uploads. Issue #2795.


= 0.1.8 =
* Require deliberate supplier VAT deduction percentage and show invoice/deductible VAT preview. Warn and require explicit confirmation before approving domestic WooCommerce zero-VAT sales. No automatic adjustment to issued documents. Issue #2773.


= 0.1.7 =
* Audited correction workflow for validated entries: automatic total, compulsory reason, immutable history, draft re-review, no original invoice/order changes. Issue #2771.


= 0.1.6 =
* Import WooCommerce ISP/VIES orders as treatment=intra with VAT ID. Issue #2761.
* Optionally associate WooCommerce sales with actual issued Billing invoices by exact order ID, without requiring Billing or changing prior validated ledger data. Includes regression tests. Issue #2767.


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
