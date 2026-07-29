# Project Scope

## Product definition

Goosialize Leads is a standalone commercial plugin for Grav CMS 2.0.12. It will capture, securely store, deliver, organize, and manage leads directly inside Grav and native Admin2.

## MVP

Version 1.0.0 is scoped to the source-proven capture, storage, read-only
Admin2, CSV and notification-operation capabilities in the normative scope
closure below, plus release documentation and acceptance. Status mutation and
delete/restore are not v1 requirements because their safe native lifecycle is
blocked by Admin2 2.0.15.

## Deferred features

The following are explicitly deferred beyond version 1.0.0:

- Advanced analytics.
- CRM synchronization.
- Webhooks and third-party automation.
- Multi-agent assignment.
- Tags and advanced activity history.
- Database storage adapters.
- Paid licensing infrastructure.

## Explicit non-goals

Version 1.0.0 will not:

- Modify Grav core.
- Patch the API plugin.
- Replace or modify compiled Admin2 files.
- Depend on the Goosialize theme or any specific frontend theme.
- Recreate Admin2 controls using look-alike custom HTML or CSS.
- Use Shadow DOM where it prevents or isolates native Admin2 behavior.
- Store plugin configuration, pages, routes, templates, or runtime logic in unrelated projects.
- Modify unrelated files during installation, upgrade, or removal.
- Treat convenience features as higher priority than security, authorization, consent, or data integrity.
- Include any deferred feature listed above.

## Version 1.0.0 acceptance criteria

Version 1.0.0 is acceptable when:

1. The plugin installs cleanly on a supported Grav CMS 2.0.12 instance without modifying core, the API plugin, compiled Admin2 files, or unrelated files.
2. A site can render and submit a lead form independently of its active theme, with documented template override behavior.
3. Submitted leads and consent evidence are validated and securely persisted using atomic filesystem operations with duplicate protection.
4. Configured email notifications work without exposing protected paths or bypassing authorization.
5. Authorized users can inspect the bounded Lead index and operational inventory through real native read-only Admin2 pages using official extension points.
6. Lead filters, CSV export and notification operations enforce separate permissions; blocked status/update/delete behavior is absent.
7. Unauthorized access and unsafe input fail securely, while credentials and lead data remain out of logs and distributable fixtures.
8. Lead data survives supported upgrades, and plugin removal does not damage unrelated files.
9. Automated tests and documented manual scenarios cover capture, validation, storage, permissions, Admin2 workflows, delivery, clean installation, upgrade, and removal.
10. The distributable package and documentation satisfy the applicable Grav marketplace requirements.
# Goosialize Leads v1.0.0 scope closure

This section is the normative release-scope decision for v1.0.0. The target is
Grav 2.0.12, Admin2 2.0.15 and PHP `^8.3`; the immutable verification runtime
observed PHP 8.5.8.

## Included in v1.0.0

All merged functionality through Phase 5C.2 is `INCLUDED_IN_V1_0_0`:

- Grav Forms and public JSON Lead capture through one validation, normalization
  and capture service;
- contained filesystem persistence, deterministic identifiers, idempotency and
  immutable notification-outbox publication;
- native read-only Admin2 Lead index and filters, ACL-separated CSV export, and
  formula-injection-safe CSV serialization;
- bounded manual delivery through the Grav Email adapter, sent archives,
  durable CAS delivery state, deterministic retry/backoff, immutable dead
  letters and explicit uncertain-delivery reconciliation;
- Grav scheduler registration, bounded scheduled delivery, read-only
  operational inventory, CLI status and a native read-only Admin2 operations
  page; and
- deterministic package generation and genuine offline GPM installation.

The source and synthetic suites prove these capabilities. They do not prove a
real transport send, real scheduler execution or browser rendering.

## Not required for v1.0.0

| Item | Classification |
|---|---|
| Phases 0–3C.2 and notification Phase 5A–5C.2 merged functionality | `INCLUDED_IN_V1_0_0` |
| Phase 4A.1 Lead index and Phase 4C.1 CSV export | `INCLUDED_IN_V1_0_0` |
| Phase 6 security closure, Phase 7 acceptance, Phase 8 documentation/package work and Phase 9 release candidate gates | `INCLUDED_IN_V1_0_0` |
| Phase 4A.2 native Lead detail | `BLOCKED_BY_ADMIN2_2_0_15` |
| Phase 4B.1 Lead status mutation | `BLOCKED_BY_ADMIN2_2_0_15` |
| Phase 4B.2 reversible delete and restore | `BLOCKED_BY_ADMIN2_2_0_15` |
| SendPulse addon | `DEFERRED_AFTER_V1_0_0` |
| Mailchimp and other provider addons | `DEFERRED_AFTER_V1_0_0` |
| Generic webhooks, Google Sheets and CRM synchronization | `DEFERRED_AFTER_V1_0_0` |
| Attachments, scoring, routing and AI qualification | `DEFERRED_AFTER_V1_0_0` |
| Provider-specific credentials | `DEFERRED_AFTER_V1_0_0` |
| Automatic reconciliation of uncertain delivery | `DEFERRED_AFTER_V1_0_0` |
| Theme coupling, Grav-core changes, API-plugin patches, compiled Admin2 changes and legacy Admin v1 UI | `OUT_OF_SCOPE` |

These omissions do not make v1.0.0 incomplete. The blocked mutation features
remain unavailable on the pinned Admin2 release, and every deferred integration
is an optional expansion rather than a prerequisite for secure capture,
storage, inspection, export or notification operations.
