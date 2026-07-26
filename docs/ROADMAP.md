# Roadmap

## Phase 0: Foundation

- Establish repository guidance, product scope, architectural principles, and roadmap.
- Confirm compatibility and non-goals before implementation.

## Phase 1: Reference implementation audit

- Review official Grav 2.0 documentation and the official Grav 2.0.12 source.
- Identify supported plugin lifecycle, routing, storage, permissions, and native Admin2 extension points.
- Record verified constraints and testable architectural decisions.

## Phase 2: Standalone plugin skeleton

- Create the minimum installable plugin structure and metadata.
- Establish plugin-owned configuration, routes, templates, translations, and Admin2 extension entry points.
- Verify clean activation and deactivation without theme dependencies or unrelated changes.

## Phase 3: Secure capture and storage

- Implement theme-independent forms, server-side validation, normalization, and consent capture.
- Implement protected filesystem storage, atomic writes, identifiers, and duplicate protection.
- Add email notifications and foundational automated tests.

## Phase 4: Native Admin2 management

- Build the Leads page through official Admin2 extension mechanisms and native components.
- Add permission-gated viewing, search, combined filters, statuses, CSV export, updates, and deletion.
- Test accessibility, authorization, and Admin2 compatibility.

## Phase 5: Lead magnet delivery

- Implement controlled delivery of lead magnets or protected resources.
- Prevent direct-path bypasses, replay abuse, and information leakage.
- Document configuration and failure behavior.

## Phase 6: Permissions and security hardening

- Finalize the capability model and enforce it on every route and operation.
- Review request forgery, injection, escaping, privacy, retention, audit, and sensitive-data handling.
- Add adversarial and concurrency test coverage.

## Phase 7: Clean-install and upgrade testing

- Test fresh installation on Grav CMS 2.0.12.
- Test upgrades, data-format migrations, interrupted operations, recovery, and removal.
- Confirm that lead data survives upgrades and unrelated files remain untouched.

## Phase 8: Marketplace packaging and documentation

- Prepare the distributable package without development-only artifacts or secrets.
- Complete installation, configuration, permissions, override, operations, privacy, and troubleshooting documentation.
- Validate applicable marketplace metadata and packaging requirements.

## Phase 9: Version 1.0.0 release candidate

- Run the complete automated and manual test matrix.
- Resolve release-blocking defects and verify all acceptance criteria.
- Produce a reviewable release candidate; publishing remains subject to explicit approval.
