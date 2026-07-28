# Goosialize Leads

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
    ├── REFERENCE_IMPLEMENTATION_AUDIT.md
    ├── REUSE_MATRIX.md
    └── ROADMAP.md
```

## Local package build and clean-install test

```bash
scripts/build-plugin-package.sh /tmp/goosialize-leads-package
GRAV_TEST_IMAGE=lscr.io/linuxserver/grav:2.0.12 tests/integration/installable-plugin-package.sh
GRAV_TEST_IMAGE=lscr.io/linuxserver/grav:2.0.12 tests/integration/phase-2d-entry-points.sh
```

The manifest is `packaging/package-files.txt`; the complete isolation and safety contract is documented in `docs/INSTALLABLE_PLUGIN_PACKAGE_TEST.md`. Build output must remain outside this repository.

## Native Admin2 Lead Index

Set `admin2_index.enabled: true` and grant `api.goosialize_leads.read` to an
authenticated API/Admin2 user. The page title always states
`Leads — latest 100`; filtering is native and client-side over that bounded
collection. The index is disabled by default and never changes Lead data.

## Public JSON capture

Phase 3C.2 adds an opt-in `POST /api/v1/goosialize-leads/capture` endpoint when the local API plugin is installed and `public_api.enabled` is true. It requires JSON, an exact allowed Origin, a mandatory `Idempotency-Key`, and uses the shared validation and secure persistence pipeline. It is disabled by default.
