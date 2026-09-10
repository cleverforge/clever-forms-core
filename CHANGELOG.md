# Changelog

All notable changes to Clever Forms Core will be documented here.

## Unreleased

Release-candidate preparation for the first public WordPress.org submission.

### Added

- Public Core repository and contribution/security documentation.
- Release-readiness process for WordPress.org publication.

### Changed

- Public distribution is being separated from the private Core + commercial add-on development monorepo.

## 0.9.3-dev

### Added

- Visual form-builder preview with desktop, tablet, and mobile views.
- Field editor modal with explicit save/cancel behavior.
- Server-side choice validation for dropdown, radio, checkbox, and multi-select values.
- Portable private-file storage support through `CLEVER_FORMS_PRIVATE_DIR`.
- Authenticated access paths for private uploads, PDFs, and signatures.
- Strict required-upload validation.
- Encrypted secret helper for separately distributed integrations.
- Opt-in data deletion on uninstall.

### Improved

- Signature storage and validation.
- Submission validation and private-file handling.
- Privacy and data-retention controls.
- Core add-on registration hooks.

## 0.9.1

- Reworked signature capture and protected PNG storage.
- Improved notification and confirmation paths.
- Refined add-on registration hooks.

## 0.9.0

- Added privacy exporter and eraser support.
- Added submission rate limiting.
- Hardened webhook, import, export, REST, and upload handling.
