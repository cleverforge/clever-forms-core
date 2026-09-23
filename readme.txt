=== Clever Forms ===
Contributors: cleverforge
Tags: forms, form builder, entries, pdf, signatures, webhooks
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.9.3-dev
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build responsive WordPress forms with entries, conditional logic, multi-page layouts, signatures, uploads, PDFs, notifications, webhooks, and an add-on API.

== Description ==
Clever Forms is developed by CleverForge and AI for Social Change, LLC.

Clever Forms Core is designed to remain useful without a paid license. Commercial integrations and specialized services are distributed as separate add-on plugins.

Core features include:
* Multiple forms with unique shortcodes.
* Drag-and-drop field ordering and field duplication.
* Multi-page and multi-column responsive layouts.
* Conditional field visibility.
* Save and continue in the current browser, with optional auto-save.
* Submission preview.
* Entry storage and CSV export.
* Secure file uploads and file renaming templates.
* Electronic signature capture with touch, pen, and mouse support.
* Secure signature image storage and administrator preview.
* PDF generation and optional email attachment.
* Administrator notifications and submitter confirmations.
* Conditional email routing.
* Merge tags for form and field values.
* Outbound JSON webhooks.
* Form import and export.
* Privacy exporter and eraser support.
* Opt-in data deletion on uninstall.
* Developer hooks and an external add-on registry.

External services are contacted only when an administrator explicitly enables and configures a webhook or a separately installed integration. Site administrators are responsible for disclosing those data flows in their privacy policy.

== Installation ==
1. In WordPress, go to Plugins > Add New > Upload Plugin.
2. Upload the Clever Forms ZIP file.
3. Activate Clever Forms.
4. Go to Clever Forms > Add New to create a form, or Clever Forms > Templates to start from a template.
5. Place `[clever_form id="123"]` on a page, replacing 123 with the form ID.

== Frequently Asked Questions ==
= Does Clever Forms Core require a subscription? =
No. Core functionality is not license-gated. Commercial add-ons can be installed separately and may require their own subscription or activation code.

= Where are uploaded files and signatures stored? =
Private entry files are stored below the WordPress uploads directory in a protected Clever Forms directory. They are not added to the public Media Library by default.

= Does Clever Forms send data to external services automatically? =
No. External transmission occurs only when a site administrator configures a webhook or activates a separate integration add-on.

== Upgrade Notice ==
= 0.9.1 =
Development build with a rebuilt signature field, cleaner commercial add-on architecture, confirmation emails, and core feature consolidation.

== Changelog ==

= 0.9.3-dev =
* Added side-by-side live visual preview with desktop, tablet, and mobile preview widths.
* Redesigned field editor with field name, field type, top-right close control, and unsaved-change protection.
* Added Behavioral Health Employment Application template covering the complete reference application workflow.

= 0.9.1 =
* Rebuilt electronic signature capture using pointer events for mouse, touch, and pen input.
* Preserves signatures across responsive canvas resizing.
* Saves signatures as protected PNG files instead of retaining large base64 images in entry metadata.
* Added secure administrator signature previews.
* Added submitter confirmation emails using form merge tags.
* Added required file validation and stronger signature validation.
* Improved browser Save & Continue to restore checkbox, radio, and multi-value fields.
* Added automatic form draft saving as a Core option.
* Added field duplication in the form builder.
* Consolidated working form enhancements into Core instead of presenting unfinished modules.
* Replaced Core license gating with a clean external add-on registry.
* Added documentation for commercial add-on development and distribution.
* Removed legacy compatibility code from the development line.
* Fixed duplicate submission hooks and duplicate filter execution.

= 0.9.0 =
* Added privacy exporter and eraser support.
* Added opt-in data deletion on uninstall.
* Added submission rate limiting without retaining raw IP addresses.
* Hardened webhook, import, export, REST, and upload handling.
