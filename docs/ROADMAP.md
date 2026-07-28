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

## Phase 3C transport checkpoints

- Phase 3C.1 is complete: native Grav Forms capture reuses the shared Phase 3A/3B pipeline.
- Phase 3C.2 owns exactly one anonymous POST JSON endpoint, raw-body parsing, Origin enforcement, direct-client fixed-window rate limiting, API idempotency, deterministic JSON responses, package integration, and synthetic regression tests.
- Phase 3C.2 does not add Forms behavior, frontend rendering or JavaScript, notifications, Admin2, ACL UI, CSV/export, themes, delivery, accounts, OAuth, CAPTCHA providers, or external rate-limit storage.
- Notifications remain Phase 3D. Full security/integration acceptance remains Phase 3E. Admin2 management, permissions, reads, search, status changes, retention execution, deletion, and CSV/export remain Phase 4.
