# Changelog

- Add bounded, manually invoked Phase 5B notification delivery with deterministic plain-text messages, exclusive event locking, and immutable success archival.
- Add the Phase 5A durable notification outbox boundary without delivery behavior.

## Unreleased

- Add the bounded authenticated native Admin2 Lead CSV export.

## Unreleased

- Add an opt-in, permission-gated, bounded native Admin2 Lead Index that reads
  only canonical primary records and returns the latest 100 without mutation.
- Add the opt-in Phase 3C.2 public JSON Lead capture endpoint with raw-body,
  Origin, idempotency, and local fixed-window abuse controls.
- Add opt-in native Grav Forms Lead capture backed by the Phase 3A validation
  and Phase 3B secure filesystem persistence services.

## 0.1.0-dev — Unreleased

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
