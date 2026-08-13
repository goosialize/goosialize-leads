# Goosialize Leads

Goosialize Leads is a standalone, theme-independent Lead capture,
storage, Admin2 reporting and notification-delivery plugin for Grav CMS 2.

The release supports:

- canonical filesystem Lead storage with atomic no-replace publication;
- durable idempotency and exact replay protection;
- opt-in native Grav Forms capture;
- opt-in bounded public JSON capture;
- a versioned plugin-to-plugin public capture capability;
- a permission-gated native Admin2 workspace over a bounded latest-100 Lead
  index, with native Search, Status, Source, State, Form / Resource and
  creation-date filters;
- dynamic Source and Form / Resource vocabularies, inline exact-ID Edit,
  optimistic revisions, status and Active/Inactive changes, reversible Delete
  and Restore;
- bounded native Admin2 CSV export;
- a durable notification outbox;
- bounded manual and scheduled notification delivery;
- durable retry, dead-letter and duplicate-risk state;
- revision-safe operator reconciliation;
- bounded human-readable and JSON operational status.

Optional capture, Admin2, CSV, notification and scheduling facilities are
disabled by default and must be configured explicitly.

## Release status

Version 1.0.0 is published as a stable GitHub Release. This branch prepares
version 1.0.1 with the distribution metadata and packaged license required for
official Grav GPM review; it does not alter the immutable 1.0.0 tag or assets.

The Admin2 workspace is capability-aware. Read access exposes the bounded Lead
index and filters; write access adds inline Edit, status changes,
Active/Inactive transitions and Restore; delete permission adds reversible
Delete; and export requires its separate permission. Every endpoint enforces
its server-side ACL independently of UI visibility.

Captured primary Lead records remain immutable. Admin operations update only
validated metadata sidecars containing workflow status, state and optimistic
revision data. Delete is a reversible metadata state and never removes the
captured primary record.

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

## Documentation

### Getting started

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Upgrade](docs/UPGRADE.md)
- [Uninstall and data retention](docs/UNINSTALL_DATA_RETENTION.md)

### Capture integrations

- [Grav Forms integration](docs/FORMS_INTEGRATION.md)
- [Public JSON API integration](docs/JSON_API_INTEGRATION.md)
- [Public plugin integration contract](docs/PUBLIC_INTEGRATION_CONTRACT.md)
- [Examples](docs/EXAMPLES.md)

### Admin2 and export

- [Admin2 Lead index](docs/ADMIN2_LEAD_INDEX.md)
- [CSV export](docs/CSV_EXPORT.md)
- [Permissions](docs/PERMISSIONS.md)

### Notification operations

- [Notification delivery](docs/NOTIFICATION_DELIVERY.md)
- [Scheduler](docs/SCHEDULER.md)
- [Retry and dead-letter state](docs/RETRY_DEAD_LETTER.md)
- [Reconciliation](docs/RECONCILIATION.md)
- [Operational status](docs/OPERATIONAL_STATUS.md)
- [CLI reference](docs/CLI_REFERENCE.md)

### Operations and release

- [Security](docs/SECURITY.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [1.0.0 release notes](docs/RELEASE_NOTES_1.0.0.md)
- [1.0.1 release notes](docs/RELEASE_NOTES_1.0.1.md)

The manual browser acceptance checklist is a development-only release gate and
is intentionally excluded from the distributable package.

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
authenticated API/Admin2 user. The workspace displays and filters the bounded
latest-100 index. `write` adds Edit and Active/Inactive controls; `delete` adds
reversible Delete. Deleted Leads can only be restored by a write-capable user.
Primary records remain immutable: workflow status, state and optimistic
revision are stored in separate metadata sidecars. Exact-ID Edit and mutation
remain available for valid Leads older than the latest-100 index.

The native filters cover Search, Status, Source, State, Form / Resource,
Created from and Created to. Source and Form / Resource vocabularies are
derived dynamically from the bounded loaded dataset. CSV export remains
bounded and uses the server-generated
`goosialize-leads-YYYY-MM-DD-HHmm.csv` filename.

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
