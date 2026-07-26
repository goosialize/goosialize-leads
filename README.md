# Goosialize Leads

Goosialize Leads is planned as a standalone commercial plugin for Grav CMS 2.0.12. Its objective is to capture, securely store, deliver, organize, and manage leads directly inside Grav and native Admin2.

## Current status

**Standalone Phase 2 skeleton.** The plugin exposes only inert native entry points for API route registration with zero registered routes, Twig template-path registration, and Admin2 component-page discovery. It remains non-functional and contains no Lead capture, storage, functional API, management UI, Forms, Email, or delivery behavior.

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
│   └── integration
│       ├── clean-grav-plugin-load.sh
│       ├── installable-plugin-package.sh
│       └── phase-2d-entry-points.sh
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
