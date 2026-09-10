# Security Policy

Clever Forms Core may be used for workflows that collect personal information, uploaded documents, signatures, and other sensitive data. Security reports should not be posted publicly when they could expose users or installations.

## Supported versions

Clever Forms is currently in pre-release development. Security fixes are applied to the current development line until the first stable `1.0.0` release.

## Reporting a vulnerability

Please report suspected vulnerabilities privately to CleverForge rather than opening a public GitHub issue. Include the affected version, reproduction steps, impact, and any proposed mitigation if known.

Do not include real credentials, private form submissions, personal information, or customer data in a report.

## Security design goals

Core is being hardened around:

- capability and nonce checks for administrative actions;
- server-side validation and sanitization;
- choice allowlisting for select/radio/checkbox/multiselect inputs;
- private storage for uploads, signatures, and generated PDFs;
- authenticated private-file delivery;
- strict required-upload validation;
- safe outbound HTTP requests and explicit administrator configuration;
- WordPress privacy exporter/eraser integration;
- opt-in data deletion on uninstall.

Security-sensitive behavior must pass the public release checklist before `1.0.0`.
