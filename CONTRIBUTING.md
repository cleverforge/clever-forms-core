# Contributing to Clever Forms

Thank you for helping improve Clever Forms Core.

## Before contributing

Clever Forms is currently approaching its first public stable release. Changes should prioritize reliability, WordPress compatibility, security, accessibility, and maintainability over adding broad new feature scope.

## Issues

Use GitHub Issues for reproducible bugs, compatibility problems, accessibility issues, documentation gaps, and focused feature proposals.

For security vulnerabilities, follow `SECURITY.md` and do not disclose exploitable details publicly.

## Pull requests

A pull request should:

1. address one focused problem;
2. explain the user impact;
3. describe how the change was tested;
4. preserve WordPress coding and security conventions;
5. avoid bundling unrelated commercial add-on code;
6. update documentation or changelog information when behavior changes.

## Development requirements

Core targets WordPress 6.5+ and PHP 8.1+ during the current development line. Before merge, changes should pass PHP syntax checks and the project's WordPress Coding Standards configuration. Release candidates will also be validated with WordPress Plugin Check and runtime/browser testing.

## Public Core boundary

This repository contains Clever Forms Core only. Commercial integrations, licensing services, private infrastructure, customer-specific code, and proprietary operational documentation do not belong in this repository.

## License

By contributing, you agree that your contribution may be distributed under the same GPL-compatible license as Clever Forms Core.
