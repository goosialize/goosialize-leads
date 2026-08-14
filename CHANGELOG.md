# 1.0.3

## 2026-08-14

1. [](#improved)
   * Corrected README wording to reflect the published 1.0.2 release and use
     durable GitHub Releases guidance.
   * Replaced patch-specific installation instructions with a
     version-neutral release ZIP contract.
   * Aligned packaged documentation for the existing Grav GPM submission.
   * Declared API, Admin2 and Email dependencies and set Grav compatibility to
     `>=2.0.12`.
   * Secured idempotency key configuration as nested password/redacted values.
   * Enabled keyless default Grav Forms capture while preserving fail-closed
     keyed public capture.
   * Derived public API paths from API route configuration.
   * No storage schema changed, and no migration is required.

# 1.0.2

## 2026-08-13

1. [](#improved)
   * Added Composer homepage, author and verified public support metadata.
   * Included the canonical public integration contract in the package and
     aligned it with the shipped version 1 capability.
   * Corrected public release-status and packaged-documentation references.
   * Added 1.0.2 maintenance release notes without changing runtime behavior.

# 1.0.1

## 2026-08-13

1. [](#improved)
   * Added the packaged MIT license required for Grav repository distribution.
   * Added complete Grav plugin identity metadata for discovery, support and
     licensing.
   * Aligned release status documentation with the published 1.0.0 release.
   * Converted the changelog to Grav's repository-compatible release format.

# 1.0.0

## 2026-08-01

### Added

- Standalone, theme-independent Lead capture and storage for Grav CMS 2.
- Canonical filesystem persistence with atomic no-replace publication,
  restrictive permissions and deterministic collision handling.
- Durable idempotency with exact replay protection and conflicting-payload
  rejection.
- Opt-in native Grav Forms capture through `goosialize_leads_capture`.
- Opt-in bounded public JSON capture at
  `POST /api/v1/goosialize-leads/capture`.
- Version 1 plugin-to-plugin public capture service
  `goosialize-leads.public-capture.v1` with capability identifier
  `goosialize-leads.capture`.
- Permission-gated Admin2 latest-100 Lead workspace with Edit, status workflow,
  Active/Inactive state, reversible Delete and Restore.
- Bounded native Admin2 CSV export with spreadsheet-formula escaping.
- Durable notification outbox and bounded delivery through the optional Grav
  Email plugin.
- Retry, dead-letter and duplicate-risk delivery state.
- Revision-safe operator reconciliation.
- Optional Grav core scheduler registration under
  `goosialize-leads-notification-delivery`.
- Human-readable and JSON notification operational status.
- CLI commands `deliver-notifications`, `reconcile-notification` and
  `notification-status`.
- Permission boundaries `api.goosialize_leads.read`,
  `api.goosialize_leads.write`, `api.goosialize_leads.delete`,
  `api.goosialize_leads.export` and `api.goosialize_leads.operations`.
- Nineteen packaged installation, configuration, integration, operations,
  security, troubleshooting, examples and release documentation guides.
- Development-only release-readiness integration test and mandatory manual
  browser acceptance checklist.

### Changed

- Set plugin, Composer and package-builder version metadata to `1.0.0`.
- Consolidated the README around supported 1.0.0 capabilities, compatibility,
  release status and documentation navigation.
- Replaced historical fixed-count package oracles with deterministic manifest,
  required-runtime, duplicate, source-existence and archive-identity checks.
- Added exact-ID Lead lookup without widening the latest-100 index.
- Added immutable metadata sidecars with optimistic revisions and Restore-only
  handling for deleted Leads.

### Security

- Optional capture, Admin2, CSV, notification and scheduling facilities remain
  disabled by default.
- Public JSON capture enforces exact Origin allowlisting, body and JSON-depth
  bounds, mandatory idempotency and a local fixed-window rate limit.
- Operational output redacts secrets, configured recipients, raw Lead
  payloads, filesystem paths and raw exceptions.
- Retry and reconciliation fail closed on invalid state, stale revision,
  ambiguous delivery or storage conflict.
- Lead data is retained across plugin disablement, replacement and uninstall
  unless separately removed through an explicit irreversible administrative
  action.

### Admin2 data model

Primary Lead records remain immutable. Admin2 workflow changes are stored in
bounded, validated metadata sidecars and use optimistic revision checks.
Deleted Leads cannot be edited or mutated except through Restore.

### Release gate

Automated release-readiness verification and human browser acceptance pass.
The annotated `v1.0.0` tag remains unauthorized pending repository alignment,
deterministic package verification, staged-diff review, the milestone commit
and explicit release authorization.

See `docs/RELEASE_NOTES_1.0.0.md` for the complete release scope and operational
notes.

# 0.1.0-dev

## Unreleased

- Established the project foundation.
- Completed the reference implementation audit.
- Added the standalone plugin skeleton.
- Added an isolated Grav 2.0.12 plugin discovery and clean-load test harness.
- Added deterministic ZIP package generation from an explicit release allowlist.
- Verified clean local ZIP installation and plugin loading on the approved Grav 2.0.12 image without claiming Lead functionality or marketplace release.
- Added an inert route-provider entry point that registers zero routes.
- Added inert plugin-owned Twig and native Admin2 component-mode entry points.
- Updated clean-load and package regressions for the two-subscription, nine-file contract.
- Added the Phase 3A handwritten autoloader and immutable Lead validation, command, ID, record, and canonical-serialization primitives.
- Added Phase 3B secure filesystem persistence primitives, idempotency key-ring and sidecar handling, hard-link no-replace publication, collision orchestration, and immediate temporary cleanup without adding capture routes or later-phase functionality.
