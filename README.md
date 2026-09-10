# Clever Forms

**Clever Forms** is a modern WordPress form builder from **CleverForge** and **AI for Social Change, LLC**.

> **Status:** public pre-release development. Current development version: **0.9.3-dev**. The first production release will be **1.0.0** after the WordPress.org release-readiness checklist is complete.

Clever Forms is designed for organizations that need more than a basic contact form: applications, registrations, intake workflows, signatures, uploads, PDFs, notifications, conditional behavior, entry management, webhooks, and extensibility.

## Why Clever Forms

- Visual drag-and-drop form building
- Responsive multi-column layouts
- Multi-page forms and progress indicators
- Conditional field display
- File uploads and protected entry files
- Electronic signatures
- Entry records and CSV export
- PDF generation
- Admin and submitter notifications
- Merge tags and conditional email routing
- Webhooks and REST/developer API foundations
- JSON form import/export
- Privacy exporter/eraser integration
- Add-on API for optional integrations

## Installation

This repository is the public development mirror for **Clever Forms Core**.

For development builds:

1. Download or clone this repository.
2. Place the repository contents in `wp-content/plugins/clever-forms/`.
3. Activate **Clever Forms** in WordPress.
4. Create a form under **Clever Forms** and embed it with the generated shortcode.

Primary shortcode:

```text
[clever_form id="123"]
```

A WordPress.org installation link will be added after the plugin is approved and published.

## Security and data handling

Clever Forms is being designed for workflows that may contain sensitive information. The current development line includes server-side validation for configured choices, strict required-upload handling, protected signature storage, authenticated private-file downloads, nonces, sanitization, capability checks, and privacy tooling.

Production deployments should configure a private directory outside the web root when sensitive files are collected:

```php
define( 'CLEVER_FORMS_PRIVATE_DIR', '/srv/private/clever-forms' );
```

Security issues should be reported privately; see [SECURITY.md](SECURITY.md).

## Extensibility

Clever Forms Core exposes hooks and an add-on registration API so integrations can remain separate from the free Core plugin.

Planned and separately distributed integrations include Salesforce, Microsoft Teams, Constant Contact, Stripe, analytics, advanced anti-spam, and other business workflow services. Commercial add-on source is intentionally not part of this public Core repository.

## WordPress.org roadmap

Before 1.0.0, Core must pass the release gates for:

- WordPress Plugin Check
- PHP syntax and WordPress Coding Standards
- current WordPress/PHP compatibility
- accessibility and browser testing
- private-file behavior on Apache and Nginx
- privacy/export/erase behavior
- deterministic production packaging
- final WordPress.org `readme.txt` and marketing assets

The public 1.0.0 release will be created only after these gates pass.

## Contributing

Contributions and bug reports are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

Clever Forms Core is licensed under the **GNU General Public License v2.0 or later**. See [LICENSE.txt](LICENSE.txt).

## Links

- CleverForge: https://cleverforge.ai/
- Issues: https://github.com/cleverforge/clever-forms-core/issues
- WordPress.org: link will be added after approval

---

Built by **CleverForge** and **AI for Social Change, LLC**.
