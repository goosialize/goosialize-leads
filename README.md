# Goosialize Leads

Goosialize Leads is planned as a standalone commercial plugin for Grav CMS 2.0.12. Its objective is to capture, securely store, deliver, organize, and manage leads directly inside Grav and native Admin2.

## Current status

**Foundation only.** This repository currently contains project scope, architecture principles, roadmap, and development instructions. It does not yet contain a working plugin.

> Goosialize Leads is not yet installable.

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

## Initial repository layout

```text
.
├── AGENTS.md
├── README.md
├── .gitignore
└── docs
    ├── ARCHITECTURE_PRINCIPLES.md
    ├── PROJECT_SCOPE.md
    └── ROADMAP.md
```
