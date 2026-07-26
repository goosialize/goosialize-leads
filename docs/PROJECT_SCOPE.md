# Project Scope

## Product definition

Goosialize Leads is a standalone commercial plugin for Grav CMS 2.0.12. It will capture, securely store, deliver, organize, and manage leads directly inside Grav and native Admin2.

## MVP

Version 1.0.0 is scoped to:

- Standalone lead capture forms.
- Theme-independent rendering and documented template overrides.
- Secure filesystem storage.
- Atomic writes and duplicate protection.
- Consent capture.
- Email notifications.
- Lead magnet or protected resource delivery.
- A native Admin2 Leads management page.
- Search and combined filters.
- CSV export.
- Lead statuses.
- Permission-gated update and delete operations.
- Clean installation on Grav CMS 2.0.12.
- Automated and manual test scenarios.
- Marketplace-ready packaging and documentation.

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
4. Configured email notifications and protected-resource delivery work without exposing protected paths or bypassing authorization.
5. Authorized users can manage leads through a real native Admin2 page using official extension points.
6. Search, combined filters, CSV export, statuses, updates, and deletion enforce their defined permissions.
7. Unauthorized access and unsafe input fail securely, while credentials and lead data remain out of logs and distributable fixtures.
8. Lead data survives supported upgrades, and plugin removal does not damage unrelated files.
9. Automated tests and documented manual scenarios cover capture, validation, storage, permissions, Admin2 workflows, delivery, clean installation, upgrade, and removal.
10. The distributable package and documentation satisfy the applicable Grav marketplace requirements.
