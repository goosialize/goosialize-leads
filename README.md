# Goosialize Leads

Phase 5C.2 adds optional bounded Grav core scheduling plus read-only notification operational visibility through `notification-status` and a native Admin2 resource table. Scheduling is disabled by default; manual delivery and reconciliation remain separate CLI authorities.

Phase 5C.1 adds opt-in durable delivery state, deterministic bounded manual
retries, immutable dead-letter recovery, and operator-only reconciliation of
uncertain delivery outcomes. It adds no scheduler and does not claim
exactly-once delivery.

Phase 5A adds an optional, disabled-by-default durable filesystem notification outbox. It records one immutable `lead.accepted` event after successful Lead persistence; it performs no notification delivery or network access.

Phase 5B adds the explicitly invoked bounded command `php bin/plugin goosialize-leads deliver-notifications --limit=10`. It is disabled by default, uses the optional Grav Email plugin for transport configuration, and stores no SMTP/provider credentials. Delivery is single-attempt and at-least-once: a process termination after provider acceptance but before durable archival can cause a later manual run to deliver the same notification again. Scheduling, retries, reconciliation, dead-letter handling, and operational visibility remain Phase 5C.

Phase 4C.1 adds a permission-gated native Admin2 export of the deterministic latest-100 Lead summary collection. CSV export is disabled by default, uses no plugin-owned Admin2 JavaScript, and does not modify Lead storage.

Phase 3C.1 adds opt-in, server-rendered Grav Forms capture. Configure
`forms.enabled`, list eligible lowercase form names in `forms.forms`, and add the
`goosialize_leads_capture` process action to those forms. Public JSON and XHR
capture remain out of scope.

Goosialize Leads is planned as a standalone commercial plugin for Grav CMS 2.0.12. Its objective is to capture, securely store, deliver, organize, and manage leads directly inside Grav and native Admin2.

## Current status

**Phase 4A.1 bounded native Admin2 Lead Index.** The plugin includes secure
Forms and public-API capture plus an opt-in, permission-gated, read-only native
Admin2 resource table. The index reads only canonical primary Lead records,
scans at most 10,000 records, and returns the latest 100 in fixed order. It has
no detail, mutation, export, custom Admin2 JavaScript, or sidecar dependency.

> Goosialize Leads is locally package-installable but remains non-functional, is not marketplace-ready, and is not production-ready.

## Planned MVP

The first release is planned to provide:

- Standalone, theme-independent lead capture forms with documented template overrides.
- Secure filesystem storage with atomic writes and duplicate protection.
- Consent capture and email notifications.
- Lead magnet or protected resource delivery.
- A native Admin2 Leads management page using official extension points.
- Search, combined filters, statuses, CSV export, and permission-gated update and delete operations.
- Automated and manual test scenarios.
- Clean installation on Grav CMS 2.0.12 and marketplace-ready packaging and documentation.

## Compatibility target

- Grav CMS 2.0.12.
- Native Grav Admin2 components and official Admin2 extension mechanisms.
- No dependency on the Goosialize theme or any specific frontend theme.

## Development principles

- Do not modify Grav core, patch the API plugin, or replace compiled Admin2 files.
- Keep all plugin behavior and assets within plugin-owned boundaries.
- Prioritize security, permissions, data integrity, and upgrade safety.
- Preserve lead data through plugin upgrades.
- Keep installation, upgrade, and removal clean and isolated.
- Verify lifecycle and API assumptions against official Grav 2.0 documentation and Grav 2.0.12 source.

## Current repository layout

```text
.
├── .gitignore
├── AGENTS.md
├── CHANGELOG.md
├── README.md
├── blueprints.yaml
├── autoload.php
├── classes
│   ├── Application
│   │   ├── CaptureCommand.php
│   │   └── LeadPersistenceCoordinator.php
│   ├── Domain
│   │   ├── LeadIdGenerator.php
│   │   └── LeadRecord.php
│   ├── Security
│   │   └── IdempotencyKeyRing.php
│   ├── Storage
│   │   ├── FilesystemLeadRepository.php
│   │   ├── LeadRepository.php
│   │   ├── PersistenceRequest.php
│   │   ├── PersistenceResult.php
│   │   └── StorageException.php
│   └── Validation
│       ├── LeadInputValidator.php
│       ├── LeadNormalizer.php
│       ├── ValidationError.php
│       └── ValidationResult.php
├── composer.json
├── goosialize-leads.php
├── goosialize-leads.yaml
├── languages
│   └── en.yaml
├── packaging
│   └── package-files.txt
├── scripts
│   └── build-plugin-package.sh
├── tests
│   ├── integration
│       ├── clean-grav-plugin-load.sh
│       ├── installable-plugin-package.sh
│       ├── phase-2d-entry-points.sh
│       └── phase-3b-secure-storage.sh
│   └── unit
│       ├── phase-3a-lead-data-validation.php
│       └── phase-3b-secure-persistence.php
└── docs
    ├── ARCHITECTURE_PRINCIPLES.md
    ├── CLEAN_GRAV_PLUGIN_LOAD_TEST.md
    ├── DECOUPLING_PLAN.md
    ├── DEPENDENCY_MAP.md
    ├── INSTALLABLE_PLUGIN_PACKAGE_TEST.md
    ├── OFFICIAL_VERIFICATION_LOG.md
    ├── PHASE_2D_ENTRY_POINT_TEST.md
    ├── PROJECT_SCOPE.md
    ├── PUBLIC_INTEGRATION_CONTRACT.md
    ├── REFERENCE_IMPLEMENTATION_AUDIT.md
    ├── REUSE_MATRIX.md
    └── ROADMAP.md
```

## Local package build and clean-install test

```bash
scripts/build-plugin-package.sh /tmp/goosialize-leads-package
GRAV_TEST_IMAGE=lscr.io/linuxserver/grav:2.0.12 tests/integration/installable-plugin-package.sh
GRAV_TEST_IMAGE=lscr.io/linuxserver/grav:2.0.12 tests/integration/phase-2d-entry-points.sh
GRAV_TEST_IMAGE=lscr.io/linuxserver/grav:2.0.12 tests/integration/phase-8-public-capture-capability.sh
```

The manifest is `packaging/package-files.txt`; the complete isolation and safety contract is documented in `docs/INSTALLABLE_PLUGIN_PACKAGE_TEST.md`. Build output must remain outside this repository.

## Native Admin2 Lead Index

Set `admin2_index.enabled: true` and grant `api.goosialize_leads.read` to an
authenticated API/Admin2 user. The page title always states
`Leads — latest 100`; filtering is native and client-side over that bounded
collection. The index is disabled by default and never changes Lead data.

## Public JSON capture

Phase 3C.2 adds an opt-in `POST /api/v1/goosialize-leads/capture` endpoint when the local API plugin is installed and `public_api.enabled` is true. It requires JSON, an exact allowed Origin, a mandatory `Idempotency-Key`, and uses the shared validation and secure persistence pipeline. It is disabled by default.

## Public plugin integration contract

The normative version 1 plugin-to-plugin Lead capture contract is documented
in `docs/PUBLIC_INTEGRATION_CONTRACT.md`.

Compatible local Grav plugins detect the exact
`goosialize-leads.capture` capability at contract version `1` through the
`goosialize-leads.public-capture.v1` container service. The integration uses
published request, trusted-context and result types over the same validation,
idempotency and secure persistence pipeline as supported capture entry points.

The version 1 runtime capability is registered under
`goosialize-leads.public-capture.v1`. Consumers must detect the published
interface, capability identifier and exact contract version before capture.

The capability reuses the same canonical validation, idempotency, persistence
and optional post-persistence notification pipeline as the supported Forms and
public JSON entry points. It returns only the published outcomes and validation
error map; it does not expose Lead IDs, storage paths, repositories,
notification state or internal exceptions.

Consumers must treat a missing, unavailable or incompatible capability as a
non-fatal integration state and must not access internal repositories, storage
services or controllers.
