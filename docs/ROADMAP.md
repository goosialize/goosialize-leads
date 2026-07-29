# Roadmap

## Phase 0: Foundation — Complete

- Establish repository guidance, product scope, architectural principles, and roadmap.
- Confirm compatibility and non-goals before implementation.

## Phase 1: Reference implementation audit — Complete

- Review official Grav 2.0 documentation and the official Grav 2.0.12 source.
- Identify supported plugin lifecycle, routing, storage, permissions, and native Admin2 extension points.
- Record verified constraints and testable architectural decisions.

## Phase 2: Standalone plugin skeleton — Complete

- Create the minimum installable plugin structure and metadata.
- Establish plugin-owned configuration, routes, templates, translations, and Admin2 extension entry points.
- Verify clean activation and deactivation without theme dependencies or unrelated changes.
- Phase 2A — Complete: the standalone plugin skeleton and plugin-owned metadata, configuration, and translations are established.
- Phase 2B — Complete: clean enabled and disabled loading passes in isolated Grav 2.0.12.
- Phase 2C — Complete: the deterministic nine-file package build and genuine offline GPM direct installation pass; the package SHA-256 is `87f4ffda0bb8ceaac09d79d696a7364dae8cb5fe7323b4f3fe16bfa6f823ae6b`.
- Phase 2D — Complete: exactly the inert `onApiRegisterRoutes` and `onTwigTemplatePaths` subscriptions exist, zero functional HTTP routes are registered, and the plugin-owned Twig and native Admin2 component entry points pass.
- Real `GpmController` component discovery and page-script serving pass. Disabled route and Twig runtime inactivity pass, while installed Admin2 filesystem discovery remains available as documented.
- Phase 2B, Phase 2C, and Phase 2D regression suites pass. All Phase 2 implementation acceptance criteria were accepted at main commit `4170a1f267221f9ddb4e0317207264b9805e9d48`.
- Phase 2 remains non-functional by design: it adds no Lead capture, functional API route, controller, Lead model or entity, persistence, storage schema, migration, Forms or Email processing, delivery or session behavior, permissions, Admin2 Leads management or sidebar item, theme dependency, Grav core, API plugin, or compiled Admin2 modification, Shadow DOM, imitation Admin2 controls, licensing, or marketplace publication.
- Phase 3 — Secure capture and storage is the next main Phase; all functional Lead behavior and later lifecycle, security, management, delivery, packaging, and release work remain assigned to Phase 3 or later.

## Phase 3: Secure capture and storage

- Implement theme-independent forms, server-side validation, normalization, and consent capture.
- Implement protected filesystem storage, atomic writes, identifiers, and duplicate protection.
- Add email notifications and foundational automated tests.

### Phase 3B implementation boundary

- Contract branch: `docs/phase-3b-secure-persistence-contract`; implementation branch: `feat/phase-3b-secure-lead-storage`; future implementation subject: `feat: add secure Lead filesystem repository`.
- Output is exactly the normative nineteen-path manifest in `docs/PHASE_3_SECURE_CAPTURE_STORAGE_PLAN.md`: seven packaged runtime classes, one repository-only unit test, one repository-only integration test, and ten exact modifications.
- `Storage\FilesystemLeadRepository` is the sole filesystem owner; `Application\LeadPersistenceCoordinator` is the sole Phase 3B orchestrator; required external versioned HMAC keys are handled only by `Security\IdempotencyKeyRing`.
- Completion requires exact `0700/0600` modes, contained non-symlink paths, one lock, full write/flush/file-fsync, hard-link no-replace publication, five-attempt collision handling, canonical record/sidecar bytes, replay/recovery/concurrency, immediate current-operation cleanup, exhaustive redacted failure mapping, and all six Phase 3B markers.
- The package grows from 17 to exactly 24 packaged and installed files. Two independent implementation builds must be byte-identical; their shared SHA is recorded only after those bytes exist.
- Phase 3B guarantees process-visible atomic publication, not directory-entry persistence across power loss. It performs no scheduled/post-crash scan and registers no cleanup CLI. Those maintenance capabilities are Phase 7.
- Phase 3C.1 owns standard server-rendered Grav Forms capture only. Its documentation branch is `docs/phase-3c-forms-capture-contract`, implementation branch is `feat/phase-3c-forms-capture`, and future subject is `feat: add Grav Forms Lead capture`.
- Phase 3C.1 adds exactly three packaged runtime classes and two repository-only tests, modifies the exact thirteen paths in the normative contract—including the existing Phase 3B unit regression oracle—changes 18 paths total, and grows the package/installed tree from 24 to 27 files. The Phase 3B test preserves every older assertion while adding the exact `IdempotencyKeyRing::deriveFormsIdempotencyKey()` public-API and behavior oracle; it remains development-only. Phase 3C.1 uses Forms 9.1.14 `onFormProcessed` action `goosialize_leads_capture`, built-in nonce validation, `Form::getUniqueId()`, standard POST/303, and no renderer, template, JavaScript, XHR, JSON API, notification, or direct filesystem access.
- Phase 3C.2 separately owns the public JSON API, raw-body enforcement, duplicate/depth/byte handling, Origin/proxy policy, endpoint abuse control, rate limiting, API statuses/bodies, and XHR/AJAX. It requires a separate complete contract.
- Phase 3D owns notification; Phase 4 owns Admin2, ACL, search, edits, deletion, and CSV; Phase 5 owns lead-magnet delivery; Phase 7 owns migration, retention execution, recovery, and scheduled/post-crash maintenance.
- Phase 3B completes only after the exact unit/integration commands, package/install/reflection checks, Phase 3A regression, Phase 2B/2C/2D regressions, clean pre-commit review, one approved commit, separate merge review, and local fast-forward merge pass.

## Phase 4: Native Admin2 management

- Phase 4A.1 owns a native declarative read-only bounded Lead Index. Admin2 2.0.15 fetches one collection and filters client-side; it has no native pagination or interactive sorting. The backend returns at most the latest 100 records in fixed `created_at` descending, `id` ascending order and rejects storage beyond its exact scan bound.
- Phase 4A.1 has one read ACL and native loading/empty/error states, with no JavaScript, web component, Shadow DOM, custom control, row action, detail, mutation, or export. Documentation branch: `docs/phase-4a1-bounded-admin2-lead-index-contract`; implementation branch: `feat/phase-4a1-bounded-admin2-lead-index`; implementation subject: `feat: add bounded Admin2 Lead Index`.
- Phase 4A.2 owns native Lead detail only after a separately proven architecture and remains blocked because Admin2 2.0.15 has no source-proven native Lead-detail composition API.
- Phase 4B.1 status mutation is `BLOCKED_BY_ADMIN2_2_0_15`. The native resource-table editor sends only the edited scalar, with no revision transport, explicit save lifecycle or reusable general plugin nonce/CSRF lifecycle. Authentication alone must never expose a mutation endpoint.
- Phase 4B.2 reversible delete/restore is also `BLOCKED_BY_ADMIN2_2_0_15` because it requires the same secure native mutation lifecycle.
- The Phase 4A.1 index remains read-only; primary records remain immutable and no mutable status metadata exists. Custom JavaScript, web components, Shadow DOM, legacy Admin, imitation controls and compiled Admin2 changes are forbidden blocker workarounds.
- Phase 4B can be reconsidered only after one supported native workflow source-proves explicit save, Lead-ID and revision transport, authentication and ACL, general plugin nonce/CSRF creation/transport/verification before persistence, conflict handling, and native success/error feedback without custom components.
- Phase 4C.1 owns the approved native authenticated bounded CSV export. It exports only the deterministic latest-100 backend collection through Admin2 2.0.15's built-in resource-table blob-download action, with a dedicated ACL, fixed CSV format and injection protection, no client-filter claim, and no plugin JavaScript or mutation. Contract branch: `docs/phase-4c1-admin2-csv-export-contract`; implementation branch: `feat/phase-4c1-admin2-csv-export`; implementation subject: `feat: add bounded Admin2 Lead CSV export`.
- Notifications remain later.
- Test accessibility, authorization, bounded reads, source compatibility, deterministic packaging, and all completed regressions.

## Phase 5: Notification delivery

- Implement controlled delivery of Lead-accepted notifications.
- Prevent replay abuse and information leakage.
- Document configuration and failure behavior.
- Phase 5A owns only durable immutable `lead.accepted` outbox-event creation after successful Lead persistence. It performs no delivery or network operation.
- Phase 5B owns one manually invoked, bounded, single-attempt CLI delivery worker using explicit at-least-once semantics. It has no scheduler, daemon, automatic retry or capture-time delivery.
- Phase 5C.1 owns separate durable delivery state, bounded manual retries, deterministic backoff, operator-only uncertain-delivery reconciliation, immutable dead-letter movement, concurrency and crash recovery. It never claims exactly-once delivery.
- Phase 5C.2 is contract-complete: one implementation branch uses Grav 2.0.12 core `onSchedulerInitialized` plus a bounded foreground process job invoking the existing delivery command, while CLI and native Admin2 expose one secure read-only operational inventory. It adds no optional scheduler plugin, global correctness lock, polling, automatic reconciliation or uncertain retry. Contract branch: `docs/phase-5c2-scheduling-visibility-contract`; implementation branch: `feat/phase-5c2-scheduling-visibility`; future subject: `feat: add scheduled delivery and operational visibility`.
- The Phase 5C.2 initial-eligible correction classifies both a pending event without state and the exact durable Phase 5C.1 initial `eligible` revision-1/zero-attempt state as `pending_first_attempt`; eligible retry requires proven prior attempt history. The inventory remains read-only and existing conflict, recovery and terminal precedence is unchanged.
- The exact future manifest is 9 new plus 19 modified paths, 28 total. Package/install become 82, runtime/command types 68, unit tests 10 and integration tests 12. Scheduling and visibility remain one checkpoint because they share one closed configuration/ACL/inventory oracle while unit and integration suites preserve test isolation.
- Phase 4A.1 and Phase 4C.1 remain complete; Phase 4B.1 and Phase 4B.2 remain `BLOCKED_BY_ADMIN2_2_0_15`.

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

## Phase 3C transport checkpoints

- Phase 3C.1 is complete: native Grav Forms capture reuses the shared Phase 3A/3B pipeline.
- Phase 3C.2 owns exactly one anonymous POST JSON endpoint, raw-body parsing, Origin enforcement, direct-client fixed-window rate limiting, API idempotency, deterministic JSON responses, package integration, and synthetic regression tests.
- Phase 3C.2 does not add Forms behavior, frontend rendering or JavaScript, notifications, Admin2, ACL UI, CSV/export, themes, delivery, accounts, OAuth, CAPTCHA providers, or external rate-limit storage.
- Notifications remain Phase 3D. Full security/integration acceptance remains Phase 3E. Admin2 management, permissions, reads, search, status changes, retention execution, deletion, and CSV/export remain Phase 4.
# v1.0.0 release-readiness execution contract

The future implementation branch is exactly `release/v1.0.0-readiness`. It
changes release metadata, documentation and verification only; it adds no
runtime class, command, route, permission, scheduler job, configuration field,
provider or mutation.

## Required packaged documentation

The following nineteen new packaged paths are required:

| Path | Responsibility |
|---|---|
| `docs/INSTALLATION.md` | Offline and normal fresh installation and dependency checks |
| `docs/CONFIGURATION.md` | Every functional, notification and scheduler setting |
| `docs/FORMS_INTEGRATION.md` | Grav Forms action, fields, success and failure behavior |
| `docs/JSON_API_INTEGRATION.md` | Request, Origin, rate-limit, response and error contract |
| `docs/ADMIN2_LEAD_INDEX.md` | Native read-only index and filters |
| `docs/CSV_EXPORT.md` | ACL, bounds and spreadsheet-safety behavior |
| `docs/NOTIFICATION_DELIVERY.md` | Manual versus scheduled delivery and Email dependency |
| `docs/SCHEDULER.md` | Registration, enablement, bounds and disablement |
| `docs/RETRY_DEAD_LETTER.md` | Retryable, waiting, eligible and dead-letter states |
| `docs/RECONCILIATION.md` | Explicit uncertain-delivery operator decisions |
| `docs/CLI_REFERENCE.md` | Exact delivery, reconciliation and status commands |
| `docs/PERMISSIONS.md` | Default-deny read, export and operations ACLs |
| `docs/OPERATIONAL_STATUS.md` | Redacted CLI and native Admin2 inventory |
| `docs/UPGRADE.md` | Supported baseline, backup and preservation checks |
| `docs/UNINSTALL_DATA_RETENTION.md` | Code-only removal and separate data deletion warning |
| `docs/SECURITY.md` | Threat model and enforced security invariants |
| `docs/TROUBLESHOOTING.md` | Redacted diagnosis without record or credential disclosure |
| `docs/EXAMPLES.md` | The seventeen mandatory synthetic scenarios |
| `docs/RELEASE_NOTES_1.0.0.md` | Closed scope, compatibility and known blocked/deferred work |

Each path is packaged documentation, has version impact `1.0.0`, changes
operator documentation, is exercised by the new release-readiness integration
suite, and is part of the ZIP/manifest/installed-tree integrity checks.

## Synthetic acceptance scenarios

`docs/EXAMPLES.md` and the release-readiness suite must use synthetic values to
prove exactly: (1) Grav Form capture, (2) JSON capture, (3) notification
disabled, (4) Email unavailable, (5) scheduled delivery enabled, (6) manual CLI
delivery, (7) retry waiting, (8) retry eligible, (9) permanent pre-transport
dead letter, (10) uncertain delivery, (11) confirm-delivered reconciliation,
(12) explicit duplicate-risk retry, (13) explicit dead-letter reconciliation,
(14) read-only Admin2 operations, (15) CSV export, (16) backup and upgrade, and
(17) uninstall with data preserved.

## Mandatory browser gate

Publication is blocked until a human verifies in the pinned browser/runtime:
plugin discovery; native Admin2 configuration; Lead index and read-only
filters; ACL-protected CSV control; native read-only operations page; absence
of Shadow DOM, iframe and legacy Admin v1 UI; denial for unauthorized users;
correct scheduler enabled/disabled fields; no plugin console errors; and
desktop/tablet responsiveness. This contract records
`MANUAL_BROWSER_ACCEPTANCE_REQUIRED_BEFORE_RELEASE=YES` and
`MANUAL_BROWSER_ACCEPTANCE_PERFORMED=NO`.

## Closed workflow

The only allowed sequence is implementation on the release branch; automated
validation; manual browser acceptance; exact staging; commit with
`release: prepare Goosialize Leads 1.0.0`; fast-forward-only merge to `main`;
post-merge rebuild and smoke tests; final distributable ZIP; final SHA-256
recording; and annotated `v1.0.0` tag creation only after every gate passes.

The acceptance record must emit exact `PASS` values for scope closure, version
consistency, fresh install, upgrade/data preservation, uninstall retention,
configuration safety, documentation, all synthetic scenarios, security,
compatibility, full regressions, deterministic package, offline install, manual
browser acceptance, artifact integrity and clean Git state. It must also record
`NO` for real send, network access, secrets, real data, remote/push and
premature tag creation.
